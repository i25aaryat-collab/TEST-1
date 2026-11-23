<?php
/**
 * communities/index.php  —  Owner: M4
 * GET: my communities + a form to start one.  POST: create one (I join it).
 * ?id=N shows one community: its share link and members ranked by my score with them.
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
    $code = new_community_code();
    $pdo->beginTransaction();
    if (DB_DRIVER === 'pgsql') { $s = $pdo->prepare('INSERT INTO communities (name, code, created_by, topic, about) VALUES (?, ?, ?, ?, ?) RETURNING id'); $s->execute([$name, $code, $myId, $topic, $about]); $id = (int) $s->fetchColumn(); }
    else { $pdo->prepare('INSERT INTO communities (name, code, created_by, topic, about) VALUES (?, ?, ?, ?, ?)')->execute([$name, $code, $myId, $topic, $about]); $id = (int) $pdo->lastInsertId(); }
    $pdo->prepare('INSERT INTO community_members (community_id, user_id) VALUES (?, ?)')->execute([$id, $myId]);
    $pdo->commit();
    set_flash('success', 'Community created. Share the link so people can join.');
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

$pageTitle = $view ? $view['name'] : 'Communities';
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow">
<?php if ($view): ?>
    <div class="page-head"><div><h1><?= e($view['name']) ?></h1><?php if (!empty($view['topic'])): ?><span class="topic-tag"><?= e(community_topic_label($view['topic'])) ?></span><?php endif; ?>
            <?php if (!empty($view['about'])): ?><p><?= e($view['about']) ?></p><?php endif; ?>
            <p class="muted"><?= count($members) + 1 ?> members · ranked by your score · only members see this list</p></div>
        <a class="btn" href="<?= e(url('communities/index.php')) ?>">All communities</a></div>
    <div class="card share-link">
        <label for="clink" class="small"><strong>Invite link</strong>: anyone with it can join</label>
        <div class="inline-form"><input id="clink" readonly value="<?= e(community_link($view)) ?>" onclick="this.select()">
            <button type="button" class="btn btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('clink').value).then(function(){RM.toast('Link copied','success')})">Copy</button>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('Join ' . $view['name'] . ' on RoomieMatch and see who you\'d live well with: ' . community_link($view)) ?>">WhatsApp</a></div>
    </div>
    <?php if (!$members): ?><div class="card empty-state"><h2>Just you so far</h2><p>Share the link above with your friends or batch.</p></div><?php endif; ?>
    <?php foreach ($members as $m): $first = explode(' ', $m['name'])[0]; ?>
        <a class="card member-row" href="<?= e(url('profile/view_profile.php?id=' . (int) $m['uid'])) ?>">
            <img class="avatar avatar-md" src="<?= e(photo_url($m['photo'])) ?>" alt="">
            <div><strong><?= e($first) ?>, <?= (int) $m['age'] ?></strong><div class="muted small"><?= e($m['city']) ?><?= $m['college'] ? ' · ' . e($m['college']) : '' ?></div>
                <?= score_line($m['result']) ?></div>
            <?= score_ring($m['result']['score']) ?>
        </a>
    <?php endforeach; ?>
    <p class="small muted" style="margin-top:14px">To swipe through them: Discover → Filters → Community → <?= e($view['name']) ?>.</p>
<?php else: ?>
    <div class="page-head"><div><h1>Communities</h1><p class="muted">Your friends, batch or hostel. Share a link, then see who in it you'd live well with.</p></div></div>
    <?php foreach ($mine as $c): ?>
        <a class="card group-row" href="<?= e(url('communities/index.php?id=' . (int) $c['id'])) ?>"><strong><?= e($c['name']) ?><?php if (!empty($c['topic'])): ?> <span class="topic-tag"><?= e(community_topic_label($c['topic'])) ?></span><?php endif; ?></strong><span class="muted small"><?= (int) $c['members'] ?> members</span></a>
    <?php endforeach; ?>
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
