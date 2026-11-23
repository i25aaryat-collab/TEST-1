<?php
/**
 * groups/view.php  —  Owner: M4
 * One shortlist: members, saved flats and the "visit first" poll.
 * The tally is shown only here, and only to members (checked by my_group()).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/group_helpers.php';

require_profile();
$myId = (int) $_SESSION['user_id'];
$group = my_group($pdo, (int) ($_GET['id'] ?? 0), $myId);
if (!$group) { set_flash('error', 'That shortlist does not exist or you are not in it.'); redirect('groups/index.php'); }
$gid = (int) $group['id'];

$m = $pdo->prepare('SELECT u.id, u.name FROM group_members g JOIN users u ON u.id = g.user_id WHERE g.group_id = ? ORDER BY u.name');
$m->execute([$gid]); $members = array_column($m->fetchAll(), 'name', 'id');

$f = $pdo->prepare('SELECT l.*, (SELECT COUNT(*) FROM poll_votes v WHERE v.group_id = s.group_id AND v.listing_id = l.id) AS votes
                    FROM shortlist s JOIN listings l ON l.id = s.listing_id WHERE s.group_id = ? ORDER BY votes DESC, l.rent');
$f->execute([$gid]); $flats = $f->fetchAll();

$v = $pdo->prepare('SELECT listing_id FROM poll_votes WHERE group_id = ? AND user_id = ?');
$v->execute([$gid, $myId]); $myVote = (int) $v->fetchColumn();
$addable = array_diff_key(my_connections($pdo, $myId), $members);

$pageTitle = $group['name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow">
    <div class="page-head">
        <div><h1><?= e($group['name']) ?></h1><p class="muted">With <?= e(implode(', ', array_map(fn($n) => explode(' ', $n)[0], $members))) ?> · private to this group</p></div>
        <a class="btn" href="<?= e(url('groups/index.php')) ?>">All shortlists</a>
    </div>

    <?php if (!$flats): ?>
        <div class="card empty-state"><div class="empty-icon"><?= house_icon() ?></div><h2>No flats saved yet</h2>
            <p>Open any flat and use "Save to shortlist".</p>
            <a class="btn btn-primary" href="<?= e(url('listings/index.php')) ?>">Browse flats</a></div>
    <?php else: ?>
        <?php if ($group['poll_open']): ?><h2 class="section-title">Which flat should we visit first?</h2><p class="muted small">One vote each. Change it any time.</p><?php endif; ?>
        <?php foreach ($flats as $fl): ?>
            <div class="card shortlist-row">
                <div>
                    <a href="<?= e(url('listings/view.php?id=' . (int) $fl['id'])) ?>"><strong><?= e($fl['title']) ?></strong></a>
                    <div class="muted small"><?= e($fl['area']) ?>, <?= e($fl['city']) ?> · <?= $fl['rent'] ? e(rupees((int) $fl['rent'])) . '/month' : 'Rent on request' ?></div>
                    <?php if ($group['poll_open']): ?>
                        <div class="poll-bar"><span style="width:<?= count($members) ? round(100 * $fl['votes'] / count($members)) : 0 ?>%"></span></div>
                        <div class="tiny muted"><?= (int) $fl['votes'] ?> of <?= count($members) ?> vote<?= count($members) == 1 ? '' : 's' ?></div>
                    <?php endif; ?>
                </div>
                <div class="btn-row">
                    <?php if ($group['poll_open']): ?>
                        <form method="post" action="<?= e(url('groups/action.php')) ?>"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="vote"><input type="hidden" name="group_id" value="<?= $gid ?>"><input type="hidden" name="listing_id" value="<?= (int) $fl['id'] ?>">
                            <button class="btn btn-sm <?= $myVote === (int) $fl['id'] ? 'btn-primary' : '' ?>" <?= $myVote === (int) $fl['id'] ? 'disabled' : '' ?>><?= $myVote === (int) $fl['id'] ? 'Your vote ✓' : 'Vote' ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('groups/action.php')) ?>"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove"><input type="hidden" name="group_id" value="<?= $gid ?>"><input type="hidden" name="listing_id" value="<?= (int) $fl['id'] ?>">
                        <button class="link-btn small">Remove</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$group['poll_open'] && count($flats) >= 2): ?>
            <form method="post" action="<?= e(url('groups/action.php')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="action" value="poll"><input type="hidden" name="group_id" value="<?= $gid ?>">
                <button class="btn btn-primary">Start a poll: which flat should we visit first?</button>
            </form>
        <?php elseif (!$group['poll_open']): ?>
            <p class="muted small">Save one more flat to start a poll.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($addable): ?>
        <form method="post" action="<?= e(url('groups/action.php')) ?>" class="card inline-form" style="margin-top:18px"><?= csrf_field() ?>
            <input type="hidden" name="action" value="add_member"><input type="hidden" name="group_id" value="<?= $gid ?>">
            <label for="addm" class="small">Add a connection</label>
            <select id="addm" name="user_id"><?php foreach ($addable as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach; ?></select>
            <button class="btn btn-sm">Add</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
