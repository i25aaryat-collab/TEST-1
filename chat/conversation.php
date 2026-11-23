<?php
/* ==========================================================================
 * chat/conversation.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * One chat between two matched users.
 *   conversation.php?match_id=3          -> the chat page
 *   conversation.php?match_id=3&poll=1   -> JSON list of messages
 * The page asks for that JSON every 1.5 seconds with fetch() ("polling"),
 * so new messages, stickers and "Seen" ticks appear without reloading.
 * Opening or polling the chat marks the other person's messages as read,
 * which clears the red unread badge on "Chats".
 *
 * Security: find_my_match() checks on the SERVER that you are one of the
 * two people in this match. Changing the number in the URL won't let you
 * read anyone else's chat.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

$myId    = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$matchId = (int) ($_GET['match_id'] ?? 0);

// ---------- JSON mode (used by the polling script) ----------
if (isset($_GET['poll'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    if (!$myId) {
        http_response_code(401);
        exit(json_encode(['ok' => false, 'error' => 'Please log in again.']));
    }
    if (!find_my_match($pdo, $matchId, $myId)) {
        exit(json_encode(['ok' => false, 'gone' => true]));    // e.g. the other person unmatched
    }
    mark_read($pdo, $matchId, $myId);
    exit(json_encode(['ok' => true, 'messages' => load_messages($pdo, $matchId, $myId),
                      'unread' => unread_count($pdo, $myId)]));
}

// ---------- Normal page ----------
require_profile();
$match = find_my_match($pdo, $matchId, $myId);
if (!$match) {
    set_flash('error', 'That chat does not exist or is not yours.');
    redirect('chat/inbox.php');
}
mark_read($pdo, $matchId, $myId);
$messages  = load_messages($pdo, $matchId, $myId);
$firstName = explode(' ', $match['other_name'])[0];

// A few quick emojis for the picker (any emoji can also be typed)
$emojis = ['😀','😂','🥲','😍','😎','🤔','😅','🙏','👍','👋','🎉','🔥','❤️','🏠','🔑','🧹','☕','🍕','🐶','🐱','🌙','📦','💸','✅'];

$pageTitle = 'Chat with ' . $firstName;
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="card chat-card">
        <!-- Header: who you're talking to, report, unmatch -->
        <div class="chat-head">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('chat/inbox.php')) ?>" aria-label="Back to chats">←</a>
            <img class="avatar" src="<?= e(photo_url($match['other_photo'])) ?>" alt="">
            <div class="who">
                <strong><?= e($match['other_name']) ?></strong>
                <span class="small live-dot">Live chat</span>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('profile/view_profile.php?id=' . (int) $match['other_id'])) ?>">Profile</a>
            <form method="post" action="<?= e(url('chat/unmatch.php')) ?>"
                  data-confirm="Unmatch <?= e($firstName) ?>? The chat and all its messages will be deleted for both of you."
                  data-confirm-button="Unmatch">
                <?= csrf_field() ?>
                <input type="hidden" name="match_id" value="<?= (int) $matchId ?>">
                <button type="submit" class="btn btn-ghost btn-sm">Unmatch</button>
            </form>
        </div>

        <?php require __DIR__ . '/_connection_panel.php'; ?>

        <!-- Messages: drawn by PHP first, then kept up to date by JavaScript -->
        <div class="chat-messages" id="chat-messages" role="log" aria-live="polite" aria-label="Messages">
            <?php if (!$messages): ?>
                <div class="chat-empty">You're connected with <?= e($firstName) ?>. Break the ice with a hello or a sticker.</div>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <div class="bubble-row<?= $m['mine'] ? ' mine' : '' ?>">
                    <?php if ($m['kind'] === 'text'): ?>
                        <div class="bubble"><?= e($m['body']) ?><span class="bubble-time"><?= e($m['time']) ?></span></div>
                    <?php else: ?>
                        <img class="chat-media chat-<?= e($m['kind']) ?>" src="<?= e(url('css/' . ($m['kind'] === 'gif' ? 'gifs' : 'stickers') . '/' . $m['body'] . '.svg')) ?>" alt="<?= e($m['kind']) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Emoji / sticker / GIF picker -->
        <div class="picker" id="picker" hidden>
            <div class="picker-tabs" role="tablist">
                <button type="button" role="tab" aria-selected="true" data-tab="emoji">Emoji</button>
                <button type="button" role="tab" aria-selected="false" data-tab="sticker">Stickers</button>
                <button type="button" role="tab" aria-selected="false" data-tab="gif">GIFs</button>
            </div>
            <div class="picker-panel" data-panel="emoji">
                <?php foreach ($emojis as $em): ?><button type="button" class="emoji" data-emoji="<?= e($em) ?>" aria-label="Insert <?= e($em) ?>"><?= e($em) ?></button><?php endforeach; ?>
            </div>
            <div class="picker-panel media" data-panel="sticker" hidden>
                <?php foreach (STICKER_IDS as $s): ?><button type="button" data-kind="sticker" data-id="<?= e($s) ?>" aria-label="Send sticker <?= (int) substr($s, 1) ?>"><img src="<?= e(url('css/stickers/' . $s . '.svg')) ?>" alt="" loading="lazy"></button><?php endforeach; ?>
            </div>
            <div class="picker-panel media" data-panel="gif" hidden>
                <?php foreach (GIF_IDS as $g): ?><button type="button" data-kind="gif" data-id="<?= e($g) ?>" aria-label="Send GIF: <?= e($g) ?>"><img src="<?= e(url('css/gifs/' . $g . '.svg')) ?>" alt="" loading="lazy"></button><?php endforeach; ?>
            </div>
        </div>

        <!-- New message -->
        <form class="chat-form" id="chat-form" method="post" action="<?= e(url('chat/send_message.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="match_id" value="<?= (int) $matchId ?>">
            <button type="button" class="btn btn-ghost picker-toggle" id="picker-toggle" aria-expanded="false" aria-controls="picker" aria-label="Emoji, stickers and GIFs">😊</button>
            <div class="grow">
                <label class="sr-only" for="body">Message</label>
                <textarea id="body" name="body" placeholder="Type a message…" rows="1"></textarea>
                <div class="field-error" data-error-for="body"></div>
                <div class="char-count" id="body-count"></div>
            </div>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    </div>
</div>

<?php
$pollUrl   = json_encode(url('chat/conversation.php?poll=1&match_id=' . $matchId));
$sendUrl   = json_encode(url('chat/send_message.php'));
$reportUrl = json_encode(url('report.php'));
$inboxUrl  = json_encode(url('chat/inbox.php'));
$mediaUrl  = json_encode(url('css/'));
$name      = json_encode($firstName);
$extraScripts = <<<HTML
<script>
/* ---------- Live chat (M5) ---------- */
const POLL_URL = $pollUrl, SEND_URL = $sendUrl, REPORT_URL = $reportUrl, INBOX_URL = $inboxUrl, MEDIA_URL = $mediaUrl;
const OTHER = $name, MATCH_ID = $matchId;
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const box = document.getElementById('chat-messages');
const form = document.getElementById('chat-form');
let lastSignature = '';

/** Draw all messages. textContent is used for text, so no message can run as HTML. */
function render(messages) {
    const lastMine = messages.filter(function (m) { return m.mine; }).pop();
    const signature = messages.map(function (m) { return m.id; }).join(',') + '|' + (lastMine && lastMine.seen ? 's' : '');
    if (signature === lastSignature) return;                    // nothing changed: don't redraw
    const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 150;
    lastSignature = signature;
    box.innerHTML = '';

    if (messages.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'chat-empty';
        empty.textContent = 'You matched with ' + OTHER + '! Break the ice with a hello or a sticker.';
        box.appendChild(empty);
    }
    messages.forEach(function (m) {
        const row = document.createElement('div');
        row.className = 'bubble-row' + (m.mine ? ' mine' : '');
        if (m.kind === 'text') {
            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            bubble.textContent = m.body;
            const time = document.createElement('span');
            time.className = 'bubble-time';
            time.textContent = m.time;
            bubble.appendChild(time);
            row.appendChild(bubble);
        } else {
            const img = document.createElement('img');
            img.className = 'chat-media chat-' + m.kind;
            img.src = MEDIA_URL + (m.kind === 'gif' ? 'gifs/' : 'stickers/') + encodeURIComponent(m.body) + '.svg';
            img.alt = m.kind;
            row.appendChild(img);
        }
        // Delete my own message, or report the other person's
        const tool = document.createElement('button');
        tool.type = 'button';
        tool.className = 'msg-tool';
        tool.textContent = m.mine ? 'Delete' : 'Report';
        tool.setAttribute('aria-label', m.mine ? 'Delete message' : 'Report message');
        tool.addEventListener('click', function () { m.mine ? deleteMessage(m.id) : reportMessage(m.id); });
        row.appendChild(tool);
        box.appendChild(row);
    });
    if (lastMine && lastMine.seen && messages[messages.length - 1] === lastMine) {
        const seen = document.createElement('div');
        seen.className = 'seen';
        seen.textContent = 'Seen';
        box.appendChild(seen);
    }
    if (nearBottom) box.scrollTop = box.scrollHeight;
}

/** Ask the server for the latest messages (every 1.5 s) */
function poll() {
    fetch(POLL_URL, { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.gone) {
                form.hidden = true;
                box.innerHTML = '';
                const note = document.createElement('div');
                note.className = 'chat-empty';
                note.textContent = OTHER + ' is no longer matched with you. Taking you back to your chats…';
                box.appendChild(note);
                setTimeout(function () { window.location.href = INBOX_URL; }, 2000);
                return;
            }
            if (data.ok) { render(data.messages); RM.setBadge('chats', data.unread); }
        })
        .catch(function () { /* offline for a moment: try again next time */ });
}

/** POST to a chat endpoint, answer as JSON */
function post(url, fields) {
    const body = new FormData();
    Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
    body.append('csrf_token', CSRF);
    return fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); });
}

function send(fields) {
    return post(SEND_URL, Object.assign({ action: 'send', match_id: MATCH_ID }, fields)).then(function (data) {
        if (!data.ok) { RM.showError(form, 'body', data.error || 'Could not send.'); return data; }
        lastSignature = '';
        poll();
        setTimeout(function () { box.scrollTop = box.scrollHeight; }, 200);
        return data;
    });
}

function deleteMessage(id) {
    RM.confirmBox('Delete this message? It disappears for both of you.', 'Delete').then(function (ok) {
        if (ok) post(SEND_URL, { action: 'delete', match_id: MATCH_ID, message_id: id }).then(poll);
    });
}

function reportMessage(id) {
    RM.promptBox('Report this message', 'What is wrong with it? Our admins will review it.', 'Report').then(function (reason) {
        if (reason === null) return;
        post(REPORT_URL, { target_type: 'message', target_id: id, reason: reason }).then(function (d) { RM.toast(d.message, d.ok ? 'success' : 'error'); });
    });
}

// ---- Typing and sending ----
RM.validateForm(form, function (f) {
    const text = f.body.value.trim();
    if (text.length === 0) return { body: 'Type a message first.' };
    if (text.length > 500) return { body: 'Messages can be at most 500 characters.' };
    return {};
});
RM.charCounter(form.body, document.getElementById('body-count'), 500);

form.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;                      // validation failed
    e.preventDefault();
    const button = form.querySelector('button[type=submit]');
    button.disabled = true;
    send({ body: form.body.value.trim() }).then(function (data) {
        if (data.ok) { form.body.value = ''; form.body.dispatchEvent(new Event('input')); }
    }).finally(function () { button.disabled = false; form.body.focus(); });
});
form.body.addEventListener('keydown', function (e) {           // Enter sends, Shift+Enter = new line
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
});

// ---- Emoji / sticker / GIF picker ----
const picker = document.getElementById('picker'), toggle = document.getElementById('picker-toggle');
toggle.addEventListener('click', function () {
    picker.hidden = !picker.hidden;
    toggle.setAttribute('aria-expanded', picker.hidden ? 'false' : 'true');
});
picker.querySelectorAll('[role=tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
        picker.querySelectorAll('[role=tab]').forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
        picker.querySelectorAll('.picker-panel').forEach(function (p) { p.hidden = p.dataset.panel !== tab.dataset.tab; });
    });
});
picker.querySelectorAll('.emoji').forEach(function (b) {
    b.addEventListener('click', function () {
        const t = form.body, pos = t.selectionStart || t.value.length;
        t.value = t.value.slice(0, pos) + b.dataset.emoji + t.value.slice(pos);
        t.dispatchEvent(new Event('input'));
        t.focus();
    });
});
picker.querySelectorAll('[data-kind]').forEach(function (b) {
    b.addEventListener('click', function () {
        send({ kind: b.dataset.kind, body: b.dataset.id });
        picker.hidden = true; toggle.setAttribute('aria-expanded', 'false');
    });
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !picker.hidden) { picker.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }
});

box.scrollTop = box.scrollHeight;
poll();
setInterval(poll, 1500);
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
