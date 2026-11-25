<?php
/**
 * communities/index.php  —  Owner: M4
 * GET: my communities + a form to start one.  POST: create one (I join it).
 * ?id=N shows one community: its share link, members ranked by my score with them,
 *   and (for listed communities) people asking to join, which every member votes on.
 * The list view also has "Find communities": listed ones anyone can ask to join (communities/request.php).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../swipe/compatibility.php';
require_once __DIR__ . '/community_helpers.php';

$me = require_profile();
$myId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 60) { set_flash('error', 'Give the community a name (up to 60 characters).'); redirect('communities/index.php'); }
    $topic = (string) ($_POST['topic'] ?? '');
    if (!isset(COMMUNITY_TOPICS[$topic])) { $topic = 'other'; }
    $about = mb_substr(trim((string) ($_POST['about'] ?? '')), 0, 200) ?: null;
    $public = ($_POST['access'] ?? 'listed') === 'listed' ? 1 : 0;
    $code = new_community_code();
    $pdo->beginTransaction();
    if (DB_DRIVER === 'pgsql') { $s = $pdo->prepare('INSERT INTO communities (name, code, created_by, topic, about, is_public) VALUES (?, ?, ?, ?, ?, ?) RETURNING id'); $s->execute([$name, $code, $myId, $topic, $about, $public]); $id = (int) $s->fetchColumn(); }
    else { $pdo->prepare('INSERT INTO communities (name, code, created_by, topic, about, is_public) VALUES (?, ?, ?, ?, ?, ?)')->execute([$name, $code, $myId, $topic, $about, $public]); $id = (int) $pdo->lastInsertId(); }
    $pdo->prepare('INSERT INTO community_members (community_id, user_id) VALUES (?, ?)')->execute([$id, $myId]);
    $pdo->commit();
    set_flash('success', $public ? 'Community created and listed. People can ask to join, or you can share the link.' : 'Community created. Share the link so people can join.');
    redirect('communities/index.php?id=' . $id);
}

$view = isset($_GET['id']) ? my_community($pdo, (int) $_GET['id'], $myId) : null;
$members = [];
if ($view) {
    $s = $pdo->prepare('SELECT u.id AS uid, u.name, p.* FROM community_members m JOIN users u ON u.id = m.user_id JOIN profiles p ON p.user_id = u.id
                        WHERE m.community_id = ? AND u.id <> ?');
    $s->execute([(int) $view['id'], $myId]);
    foreach ($s->fetchAll() as $p) { $p['result'] = compatibility_result($me, $p); $members[] = $p; }
    usort($members, fn($a, $b) => [$b['result']['score'] ?? -1, $b['result']['shared']] <=> [$a['result']['score'] ?? -1, $a['result']['shared']]);
}
$mine = my_communities($pdo, $myId);
$requests = $view && !empty($view['is_public']) ? pending_join_requests($pdo, (int) $view['id'], $myId) : [];
foreach ($requests as &$rq) $rq['result'] = !empty($rq['answers']) ? compatibility_result($me, $rq) : null;
unset($rq);
$waiting = $view ? [] : join_requests_waiting($pdo, $myId);
$search = trim((string) ($_GET['q'] ?? ''));
$open = $view ? [] : open_communities($pdo, $myId, $search);

$pageTitle = $view ? $view['name'] : 'Communities';
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow">
<?php if ($view): ?>
    <div class="page-head"><div><h1><?= e($view['name']) ?></h1><?php if (!empty($view['topic'])): ?><span class="topic-tag"><?= e(community_topic_label($view['topic'])) ?></span><?php endif; ?>
            <?php if (!empty($view['about'])): ?><p><?= e($view['about']) ?></p><?php endif; ?>
            <p class="muted"><?= count($members) + 1 ?> members · <?= !empty($view['is_public']) ? 'listed: anyone can ask to join' : 'link only' ?> · ranked by your score</p></div>
        <div class="btn-row"><a class="btn btn-primary" href="<?= e(url('communities/chat.php?id=' . (int) $view['id'])) ?>">Open group chat</a>
            <a class="btn" href="<?= e(url('communities/index.php')) ?>">All communities</a></div></div>
    <div class="card share-link">
        <label for="clink" class="small"><strong>Invite link</strong>: anyone with it can join</label>
        <div class="inline-form"><input id="clink" readonly value="<?= e(community_link($view)) ?>" onclick="this.select()">
            <button type="button" class="btn btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('clink').value).then(function(){RM.toast('Link copied','success')})">Copy</button>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('Join ' . $view['name'] . ' on RoomieMatch and see who you\'d live well with: ' . community_link($view)) ?>">WhatsApp</a></div>
    </div>
    <?php if ($requests): $total = count($members) + 1; ?>
        <section class="card join-requests" id="requests">
            <h2 class="section-title">Asking to join <span class="count-pill"><?= count($requests) ?></span></h2>
            <p class="muted small">They join once all <?= $total ?> members say yes. One "no" declines.</p>
            <?php foreach ($requests as $rq): $yes = (int) $rq['yes_votes']; $first = explode(' ', $rq['name'])[0]; ?>
                <div class="jr">
                    <a class="jr-who" href="<?= e(url('profile/view_profile.php?id=' . (int) $rq['uid'])) ?>">
                        <img class="avatar avatar-md" src="<?= e(photo_url($rq['photo'] ?? null)) ?>" alt="">
                        <div><strong><?= e($first) ?><?= !empty($rq['age']) ? ', ' . (int) $rq['age'] : '' ?></strong>
                            <div class="muted small"><?= e($rq['city'] ?? '') ?><?= $rq['result'] && $rq['result']['score'] !== null ? ' · ' . (int) $rq['result']['score'] . '% compatible with you' : '' ?></div></div>
                    </a>
                    <?php if ($rq['message']): ?><p class="jr-note">"<?= e($rq['message']) ?>"</p><?php endif; ?>
                    <div class="jr-votes" aria-label="<?= $yes ?> of <?= $total ?> said yes"><?php for ($i = 0; $i < $total; $i++): ?><span class="<?= $i < $yes ? 'y' : '' ?>"></span><?php endfor; ?>
                        <small><?= $yes ?> of <?= $total ?> said yes</small></div>
                    <?php if ($rq['my_vote'] === null): ?>
                        <form method="post" action="<?= e(url('communities/request.php')) ?>" class="btn-row"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="vote"><input type="hidden" name="request_id" value="<?= (int) $rq['request_id'] ?>">
                            <button class="btn btn-primary btn-sm" name="vote" value="1">Accept</button>
                            <button class="btn btn-ghost btn-sm" name="vote" value="0">Decline</button>
                        </form>
                    <?php else: ?><p class="muted small">You said yes. Waiting for the others.</p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php if (!$members): ?><div class="card empty-state"><h2>Just you so far</h2><p>Share the link above with your friends or batch.</p></div><?php endif; ?>
    <?php foreach ($members as $m): $first = explode(' ', $m['name'])[0]; ?>
        <div class="card member-row">
            <a href="<?= e(url('profile/view_profile.php?id=' . (int) $m['uid'])) ?>"><img class="avatar avatar-md" src="<?= e(photo_url($m['photo'])) ?>" alt=""></a>
            <div><a class="member-name" href="<?= e(url('profile/view_profile.php?id=' . (int) $m['uid'])) ?>"><strong><?= e($first) ?>, <?= (int) $m['age'] ?></strong></a><div class="muted small"><?= e($m['city']) ?><?= $m['college'] ? ' · ' . e($m['college']) : '' ?></div>
                <?= score_line($m['result']) ?></div>
            <?= score_ring($m['result']['score']) ?>
        </div>
    <?php endforeach; ?>
    <p class="small muted" style="margin-top:14px">To swipe through them: Discover → Filters → Community → <?= e($view['name']) ?>.</p>
<?php else: ?>
    <div class="page-head"><div><h1>Communities</h1><p class="muted">Your friends, batch or hostel. Share a link, then see who in it you'd live well with.</p></div></div>
    <?php foreach ($mine as $c): ?>
        <a class="card group-row" href="<?= e(url('communities/index.php?id=' . (int) $c['id'])) ?>"><strong><?= e($c['name']) ?><?php if (!empty($c['topic'])): ?> <span class="topic-tag"><?= e(community_topic_label($c['topic'])) ?></span><?php endif; ?></strong><span class="muted small"><?= (int) $c['members'] ?> members<?= !empty($waiting[(int) $c['id']]) ? ' · <span class="count-pill">' . $waiting[(int) $c['id']] . ' asking to join</span>' : '' ?></span></a>
    <?php endforeach; ?>
    <section class="find-communities" id="find">
        <div class="find-head"><h2 class="section-title">Find communities</h2>
            <form method="get" action="#find" role="search"><label class="sr-only" for="cq">Search communities</label><input type="search" id="cq" name="q" value="<?= e($search) ?>" placeholder="Search Bengaluru interns, IIM Indore, gym"></form></div>
        <?php if (!$open): ?><p class="muted"><?= $search !== '' ? 'No listed communities match that.' : 'No listed communities to join yet. Start one below.' ?></p><?php endif; ?>
        <?php foreach ($open as $o): $decided = $o['my_decided'] ? strtotime($o['my_decided']) : 0; ?>
            <div class="card open-community">
                <div class="oc-top"><strong><?= e($o['name']) ?></strong><?php if (!empty($o['topic'])): ?><span class="topic-tag"><?= e(community_topic_label($o['topic'])) ?></span><?php endif; ?></div>
                <?php if ($o['about']): ?><p class="muted small"><?= e($o['about']) ?></p><?php endif; ?>
                <div class="oc-foot">
                    <span class="muted small"><?= (int) $o['members'] ?> member<?= (int) $o['members'] === 1 ? '' : 's' ?> · everyone must say yes</span>
                    <?php if ($o['my_status'] === 'pending'): ?>
                        <form method="post" action="<?= e(url('communities/request.php')) ?>" class="inline-form"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel"><input type="hidden" name="community_id" value="<?= (int) $o['id'] ?>">
                            <span class="pill-note">Requested</span><button class="btn btn-ghost btn-sm">Withdraw</button></form>
                    <?php elseif ($o['my_status'] === 'declined' && $decided > time() - 7 * 86400): ?>
                        <span class="pill-note">Not this time · ask again from <?= e(date('j M', $decided + 7 * 86400)) ?></span>
                    <?php else: ?>
                        <details class="oc-ask"><summary class="btn btn-sm">Ask to join</summary>
                            <form method="post" action="<?= e(url('communities/request.php')) ?>"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="ask"><input type="hidden" name="community_id" value="<?= (int) $o['id'] ?>">
                                <label class="sr-only" for="m<?= (int) $o['id'] ?>">A note to the members</label>
                                <input id="m<?= (int) $o['id'] ?>" name="message" maxlength="200" placeholder="Say hi, and why you'd like to join (optional)">
                                <button class="btn btn-primary btn-sm">Send request</button></form></details>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <form method="post" class="card community-create" id="ccForm"><?= csrf_field() ?>
        <h2 class="section-title">Start a community</h2>
        <div class="cc-grid">
            <div>
                <label class="cc-step" for="cname"><span>1</span> Give it a name</label>
                <div class="cc-name-wrap"><input id="cname" class="cc-name" name="name" maxlength="60" required autocomplete="off" placeholder="e.g. IPM 2025 batch"><span class="cc-count" id="ccCount">0/60</span></div>

                <p class="cc-step"><span>2</span> What brings you together?</p>
                <div class="cc-topics">
                    <?php foreach (COMMUNITY_TOPICS as $k => [$label, $hint]): ?>
                    <label class="cc-topic"><input type="radio" name="topic" value="<?= e($k) ?>" <?= $k === 'batch' ? 'checked' : '' ?>>
                        <span><strong><?= e($label) ?></strong><small><?= e($hint) ?></small></span></label>
                    <?php endforeach; ?>
                </div>

                <label class="cc-step" for="cabout"><span>3</span> A line about it <em>(optional)</em></label>
                <textarea id="cabout" name="about" maxlength="200" rows="3" placeholder="e.g. For everyone in the batch looking for flatmates near campus next term"></textarea>

                <p class="cc-step"><span>4</span> Who can join?</p>
                <div class="cc-topics cc-access">
                    <label class="cc-topic"><input type="radio" name="access" value="listed" checked><span><strong>Listed</strong><small>Anyone can find it and ask. Every member must say yes.</small></span></label>
                    <label class="cc-topic"><input type="radio" name="access" value="link"><span><strong>Link only</strong><small>Hidden. Only people with your link can join.</small></span></label>
                </div>
            </div>
            <aside class="cc-preview" aria-live="polite">
                <p class="small muted">How the invite will look</p>
                <div class="cc-card">
                    <span class="topic-tag" id="pvTopic">College batch</span>
                    <h3 id="pvName">Your community</h3>
                    <p id="pvAbout" class="muted small">Share the link and see who in it you'd live well with.</p>
                    <div class="cc-avatars"><i></i><i></i><i></i><b>+ you</b></div>
                </div>
            </aside>
        </div>
        <button class="btn btn-primary cc-go" id="ccGo" disabled>Create and get link</button>
    </form>
    <script>
    (function () {
        var f = document.getElementById('ccForm'), n = f.cname, a = f.cabout, go = document.getElementById('ccGo');
        function upd() {
            var v = n.value.trim();
            document.getElementById('ccCount').textContent = n.value.length + '/60';
            document.getElementById('pvName').textContent = v || 'Your community';
            document.getElementById('pvAbout').textContent = a.value.trim() || "Share the link and see who in it you'd live well with.";
            var t = f.querySelector('input[name=topic]:checked');
            document.getElementById('pvTopic').textContent = t ? t.parentNode.querySelector('strong').textContent : '';
            go.disabled = !v;
            go.classList.toggle('is-ready', !!v);
        }
        f.addEventListener('input', upd); f.addEventListener('change', upd); upd();
    })();
    </script>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
