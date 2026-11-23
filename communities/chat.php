<?php
/* ==========================================================================
 * communities/chat.php  —  Owner: M4 (Communities)
 * --------------------------------------------------------------------------
 * Group chat for one community. Only members can read or post.
 *   chat.php?id=5                    -> the chat page
 *   chat.php?id=5&poll=1&after=120   -> JSON: messages newer than #120
 *   POST action=send  (kind text | sticker | gif, body)  -> JSON
 *   POST action=delete (message_id, my own only)          -> JSON
 * Like the 1-to-1 chat, the page asks for new messages every 2 seconds.
 * The table is created by migration_v9; if it's missing we create it once.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/community_helpers.php';

/** Run a query; if community_messages doesn't exist yet, create it and try again */
function cm_run(PDO $pdo, callable $fn)
{
    try { return $fn(); }
    catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42P01', '42S02'], true)) throw $e;     // "no such table" (Postgres / MySQL)
        $file = __DIR__ . '/../database/migration_v9_community_chat_' . (DB_DRIVER === 'pgsql' ? 'pg' : 'mysql') . '.sql';
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents($file))))) as $sql) $pdo->exec($sql);
        return $fn();
    }
}

/** Messages after a given id, oldest first, with each sender's first name and photo */
function cm_messages(PDO $pdo, int $communityId, int $myId, int $after): array
{
    return cm_run($pdo, function () use ($pdo, $communityId, $myId, $after) {
        $s = $pdo->prepare('SELECT m.id, m.sender_id, m.body, m.kind, m.created_at, u.name, p.photo
                            FROM community_messages m JOIN users u ON u.id = m.sender_id LEFT JOIN profiles p ON p.user_id = u.id
                            WHERE m.community_id = ? AND m.id > ? ORDER BY m.id LIMIT 200');
        $s->execute([$communityId, $after]);
        return array_map(fn($r) => [
            'id' => (int) $r['id'], 'mine' => (int) $r['sender_id'] === $myId, 'uid' => (int) $r['sender_id'],
            'name' => explode(' ', $r['name'])[0], 'photo' => photo_url($r['photo']),
            'kind' => $r['kind'], 'body' => $r['body'], 'time' => date('j M, g:i A', strtotime($r['created_at'])),
        ], $s->fetchAll());
    });
}

$myId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$id   = (int) ($_GET['id'] ?? $_POST['community_id'] ?? 0);
$isJson = isset($_GET['poll']) || $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isJson) {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    if (!$myId) { http_response_code(401); exit(json_encode(['ok' => false, 'error' => 'Please log in again.'])); }
}
$c = $myId ? my_community($pdo, $id, $myId) : null;          // members only, checked on the server
if ($isJson && !$c) exit(json_encode(['ok' => false, 'error' => 'You are not in this community.']));

// ---------- JSON: new messages ----------
if (isset($_GET['poll'])) {
    exit(json_encode(['ok' => true, 'messages' => cm_messages($pdo, $id, $myId, max(0, (int) ($_GET['after'] ?? 0)))]));
}

// ---------- JSON: send or delete ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf(true);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        cm_run($pdo, fn() => $pdo->prepare('DELETE FROM community_messages WHERE id = ? AND community_id = ? AND sender_id = ?')
                                ->execute([(int) ($_POST['message_id'] ?? 0), $id, $myId]));
        exit(json_encode(['ok' => true]));
    }
    $kind = (string) ($_POST['kind'] ?? 'text');
    $body = trim((string) ($_POST['body'] ?? ''));
    $valid = match ($kind) {
        'text'    => $body !== '' && mb_strlen($body) <= 500,
        'sticker' => in_array($body, STICKER_IDS, true),
        'gif'     => in_array($body, GIF_IDS, true),
        default   => false,
    };
    if (!$valid) exit(json_encode(['ok' => false, 'error' => $kind === 'text' ? 'Messages can be 1 to 500 characters.' : 'Unknown sticker.']));
    cm_run($pdo, fn() => $pdo->prepare('INSERT INTO community_messages (community_id, sender_id, body, kind) VALUES (?, ?, ?, ?)')
                            ->execute([$id, $myId, $body, $kind]));
    exit(json_encode(['ok' => true]));
}

// ---------- The page ----------
require_profile();
if (!$c) { set_flash('error', 'Join the community first to see its chat.'); redirect('communities/index.php'); }
$count = $pdo->prepare('SELECT COUNT(*) FROM community_members WHERE community_id = ?');
$count->execute([$id]);
$members = (int) $count->fetchColumn();
$messages = cm_messages($pdo, $id, $myId, 0);
$emojis = ['😀','😂','🥲','😍','😎','🤔','🙏','👍','👋','🎉','🔥','❤️','🏠','🔑','☕','🍕'];

$pageTitle = $c['name'] . ' · Chat';
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow">
    <div class="card chat-card group-chat">
        <div class="chat-head">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('communities/index.php?id=' . $id)) ?>" aria-label="Back to <?= e($c['name']) ?>">←</a>
            <div class="who">
                <strong><?= e($c['name']) ?></strong>
                <span class="small muted"><?= $members ?> member<?= $members === 1 ? '' : 's' ?> · group chat</span>
            </div>
        </div>

        <div class="chat-messages" id="chat-messages" role="log" aria-live="polite" aria-label="Messages"></div>

        <div class="picker" id="picker" hidden>
            <div class="picker-tabs" role="tablist">
                <button type="button" role="tab" aria-selected="true" data-tab="emoji">Emoji</button>
                <button type="button" role="tab" aria-selected="false" data-tab="sticker">Stickers</button>
                <button type="button" role="tab" aria-selected="false" data-tab="gif">GIFs</button>
            </div>
            <div class="picker-panel" data-panel="emoji"><div class="emoji-grid"><?php foreach ($emojis as $em): ?><button type="button" class="emoji" data-emoji="<?= e($em) ?>" aria-label="Insert <?= e($em) ?>"><?= e($em) ?></button><?php endforeach; ?></div></div>
            <div class="picker-panel media" data-panel="sticker" hidden>
                <?php foreach (STICKER_IDS as $sid): ?><button type="button" data-kind="sticker" data-id="<?= e($sid) ?>" aria-label="Send sticker: <?= e(str_replace('_', ' ', $sid)) ?>"><img src="<?= e(sticker_src($sid)) ?>" alt="" loading="lazy"></button><?php endforeach; ?>
            </div>
            <div class="picker-panel media" data-panel="gif" hidden>
                <?php foreach (GIF_IDS as $g): ?><button type="button" data-kind="gif" data-id="<?= e($g) ?>" aria-label="Send GIF: <?= e($g) ?>"><img src="<?= e(url('css/gifs/' . $g . '.svg')) ?>" alt="" loading="lazy"></button><?php endforeach; ?>
            </div>
        </div>

        <form class="chat-form" id="chat-form" novalidate>
            <button type="button" class="btn btn-ghost picker-toggle" id="picker-toggle" aria-expanded="false" aria-controls="picker" aria-label="Emoji, stickers and GIFs">😊</button>
            <div class="grow">
                <label class="sr-only" for="body">Message</label>
                <textarea id="body" name="body" placeholder="Message <?= e($c['name']) ?>…" rows="1" maxlength="500"></textarea>
                <div class="field-error" data-error-for="body"></div>
            </div>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    </div>
</div>
<?php
$cfg = json_encode([
    'poll' => url('communities/chat.php?poll=1&id=' . $id), 'send' => url('communities/chat.php'), 'id' => $id,
    'profile' => url('profile/view_profile.php?id='), 'gifs' => url('css/gifs/'), 'messages' => $messages,
    'stickers' => array_combine(STICKER_IDS, array_map('sticker_src', STICKER_IDS)),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$extraScripts = <<<HTML
<script>
/* ---------- Community group chat ---------- */
(function () {
    const C = $cfg;
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const box = document.getElementById('chat-messages'), form = document.getElementById('chat-form'), input = document.getElementById('body');
    let lastId = 0, lastSender = 0, busy = false;

    /** Add messages to the bottom. textContent only, so nothing anyone types can run as HTML. */
    function add(list) {
        if (!list.length && !lastId) {
            box.innerHTML = '<div class="chat-empty">No messages yet. Say hi to the group 👋</div>'; return;
        }
        const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 150;
        if (list.length && !lastId) box.innerHTML = '';
        list.forEach(function (m) {
            if (m.id <= lastId) return;
            const row = document.createElement('div');
            row.className = 'bubble-row' + (m.mine ? ' mine' : '') + (m.uid === lastSender ? ' cont' : '');
            if (!m.mine && m.uid !== lastSender) {
                const who = document.createElement('a');
                who.className = 'group-who'; who.href = C.profile + m.uid;
                const av = document.createElement('img'); av.className = 'avatar avatar-sm'; av.src = m.photo; av.alt = '';
                who.appendChild(av); who.appendChild(document.createTextNode(m.name));
                row.appendChild(who);
            }
            let el;
            if (m.kind === 'text') {
                el = document.createElement('div'); el.className = 'bubble'; el.textContent = m.body;
                const t = document.createElement('span'); t.className = 'bubble-time'; t.textContent = m.time; el.appendChild(t);
            } else {
                el = document.createElement('img'); el.className = 'chat-media chat-' + m.kind; el.alt = m.kind;
                el.src = m.kind === 'gif' ? C.gifs + encodeURIComponent(m.body) + '.svg' : (C.stickers[m.body] || '');
            }
            row.appendChild(el);
            if (m.mine) {
                const del = document.createElement('button');
                del.type = 'button'; del.className = 'msg-tool'; del.textContent = 'Delete'; del.setAttribute('aria-label', 'Delete message');
                del.addEventListener('click', function () { post({ action: 'delete', message_id: m.id }).then(function () { row.remove(); }); });
                row.appendChild(del);
            }
            box.appendChild(row);
            lastId = m.id; lastSender = m.uid;
        });
        if (nearBottom || list.some(function (m) { return m.mine; })) box.scrollTop = box.scrollHeight;
    }
    function post(fields) {
        const body = new FormData();
        Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
        body.append('community_id', C.id); body.append('csrf_token', CSRF);
        return fetch(C.send, { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); });
    }
    function poll() {
        if (busy || document.hidden) return;
        busy = true;
        fetch(C.poll + '&after=' + lastId, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.ok) add(d.messages); })
            .catch(function () {})
            .finally(function () { busy = false; });
    }
    function send(fields) {
        return post(Object.assign({ action: 'send' }, fields)).then(function (d) {
            if (!d.ok) { RM.showError(form, 'body', d.error || 'Could not send.'); return; }
            poll();
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = input.value.trim(); if (!text) return;
        input.value = ''; send({ kind: 'text', body: text });
    });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });

    // Picker: emoji go into the text box; stickers and GIFs send straight away
    const picker = document.getElementById('picker'), toggle = document.getElementById('picker-toggle');
    toggle.addEventListener('click', function () { picker.hidden = !picker.hidden; toggle.setAttribute('aria-expanded', String(!picker.hidden)); });
    picker.querySelectorAll('[data-tab]').forEach(function (t) {
        t.addEventListener('click', function () {
            picker.querySelectorAll('[data-tab]').forEach(function (x) { x.setAttribute('aria-selected', String(x === t)); });
            picker.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== t.dataset.tab; });
        });
    });
    picker.querySelectorAll('[data-emoji]').forEach(function (b) { b.addEventListener('click', function () { input.value += b.dataset.emoji; input.focus(); }); });
    picker.querySelectorAll('[data-kind]').forEach(function (b) {
        b.addEventListener('click', function () { picker.hidden = true; toggle.setAttribute('aria-expanded', 'false'); send({ kind: b.dataset.kind, body: b.dataset.id }); });
    });

    add(C.messages);
    box.scrollTop = box.scrollHeight;
    setInterval(poll, 2000);
    document.addEventListener('visibilitychange', poll);
})();
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
