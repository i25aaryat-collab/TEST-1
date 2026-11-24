<?php
/**
 * groups/index.php  —  Owner: M4
 * My households (people I plan to live with), and a form to start one with people I'm connected with.
 * ?with=ID pre-ticks a connection (from the chat page).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/group_helpers.php';

require_profile();
$myId = (int) $_SESSION['user_id'];
$connections = my_connections($pdo, $myId);
$groups = my_groups($pdo, $myId);
$with = (int) ($_GET['with'] ?? 0);

$pageTitle = 'Households';
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow">
    <div class="page-head"><div><h1>Households</h1><p class="muted">Group up with the people you've connected with, save flats together and vote on which to visit first. When your household is complete, mark it as settled and you stop showing up in Discover. Only members can see a household.</p></div></div>

    <?php foreach ($groups as $g): ?>
        <a class="card group-row" href="<?= e(url('groups/view.php?id=' . (int) $g['id'])) ?>">
            <strong><?= e($g['name']) ?><?= $g['settled_at'] ? ' <span class="settled-pill">Settled</span>' : '' ?></strong>
            <span class="muted small"><?= (int) $g['members'] ?> people · <?= (int) $g['flats'] ?> flat<?= $g['flats'] == 1 ? '' : 's' ?></span>
        </a>
    <?php endforeach; ?>

    <div class="card" style="margin-top:18px">
        <h2 class="section-title">Start a household</h2>
        <?php if (!$connections): ?>
            <p class="muted">You need at least one connection first. <a href="<?= e(url('swipe/swipe.php')) ?>">Find people on Discover</a>.</p>
        <?php else: ?>
            <form method="post" action="<?= e(url('groups/action.php')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-group"><label for="gname">Name</label><input id="gname" name="name" maxlength="60" required value="Our household"></div>
                <fieldset class="form-group"><legend>Who's in? <span class="muted small">(people you're connected with)</span></legend>
                    <div class="pill-group">
                        <?php foreach ($connections as $id => $name): ?>
                            <label class="pill"><input type="checkbox" name="members[]" value="<?= (int) $id ?>" <?= $id === $with ? 'checked' : '' ?>><span><?= e(explode(' ', $name)[0]) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <button class="btn btn-primary">Create household</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
