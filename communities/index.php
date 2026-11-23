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
    $code = new_community_code();
    $pdo->beginTransaction();
    if (DB_DRIVER === 'pgsql') { $s = $pdo->prepare('INSERT INTO communities (name, code, created_by) VALUES (?, ?, ?) RETURNING id'); $s->execute([$name, $code, $myId]); $id = (int) $s->fetchColumn(); }
    else { $pdo->prepare('INSERT INTO communities (name, code, created_by) VALUES (?, ?, ?)')->execute([$name, $code, $myId]); $id = (int) $pdo->lastInsertId(); }
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
    <div class="page-head"><div><h1><?= e($view['name']) ?></h1><p class="muted"><?= count($members) + 1 ?> members · ranked by your score · only members see this list</p></div>
        <a class="btn" href="<?= e(url('communities/index.php')) ?>">All communities</a></div>
    <div class="card share-link">
        <label for="clink" class="small"><strong>Invite link</strong>: anyone with it can join</label>
        <div class="inline-form"><input id="clink" readonly value="<?= e(community_link($view)) ?>" onclick="this.select()">
            <button type="button" class="btn btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('clink').value).then(function(){RM.toast('Link copied','success')})">Copy</button>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('Join ' . $view['name'] . ' on RoomieMatch and see who you\'d live well with: ' . community_link($view)) ?>">WhatsApp</a></div>
    </div>
    <?php if (!$members): ?><div class="card empty-state"><div class="empty-icon">👋</div><h2>Just you so far</h2><p>Share the link above with your friends or batch.</p></div><?php endif; ?>
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
        <a class="card group-row" href="<?= e(url('communities/index.php?id=' . (int) $c['id'])) ?>"><strong><?= e($c['name']) ?></strong><span class="muted small"><?= (int) $c['members'] ?> members</span></a>
    <?php endforeach; ?>
    <form method="post" class="card" style="margin-top:16px"><?= csrf_field() ?>
        <h2 class="section-title">Start a community</h2>
        <div class="form-group"><label for="cname">Name</label><input id="cname" name="name" maxlength="60" required placeholder="e.g. IPM 2025 batch"></div>
        <button class="btn btn-primary">Create and get link</button>
    </form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
