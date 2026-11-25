<?php
/* ==========================================================================
 * profile/view_profile.php  —  Owner: M3 (Profiles and validation)
 * --------------------------------------------------------------------------
 * CRUD: READ.
 *   view_profile.php          -> your own profile, with Edit and Delete
 *   view_profile.php?id=7     -> someone else's profile (e.g. from Matches),
 *                                with the compatibility score explained
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../swipe/compatibility.php';

require_login();

$viewId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $_SESSION['user_id'];
$isOwn  = $viewId === (int) $_SESSION['user_id'];

if ($isOwn) {
    $mine = require_profile();      // sends admins to the dashboard, others to create_profile if needed
}

// Load the profile we want to show (admins never have one)
$stmt = $pdo->prepare("
    SELECT u.id AS uid, u.name, u.created_at AS joined, p.*
    FROM users u JOIN profiles p ON p.user_id = u.id
    WHERE u.id = ? AND u.role = 'user'
");
$stmt->execute([$viewId]);
$person = $stmt->fetch();

if (!$person) {
    set_flash('error', 'That profile does not exist (it may have been deleted).');
    redirect(is_admin() ? 'admin/dashboard.php' : 'swipe/matches.php');
}

// Someone else's profile: work out the score against mine, and whether we matched
$score = null;
$breakdown = [];
$matchId = null;
if (!$isOwn && !is_admin()) {
    $stmt = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $mine = $stmt->fetch();
    if ($mine) {
        $result = compatibility_result($mine, $person);
        $breakdown = $result['sections'];
        $score = $result['score'];
    }
    $stmt = $pdo->prepare('SELECT id FROM matches WHERE user1_id = ? AND user2_id = ?');
    $stmt->execute([min($viewId, $_SESSION['user_id']), max($viewId, $_SESSION['user_id'])]);
    $matchId = $stmt->fetchColumn() ?: null;
}

$languages = array_filter(explode(',', $person['languages']));
$pageTitle = $isOwn ? 'My profile' : $person['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="card">
        <div class="profile-hero">
            <img src="<?= e(photo_url($person['photo'])) ?>" alt="Photo of <?= e($person['name']) ?>">
            <div style="flex:1">
                <h1><?= e($person['name']) ?>, <?= (int) $person['age'] ?></h1>
                <p class="muted" style="margin:4px 0 10px">
                    <?= gender_label($person) !== '' ? e(gender_label($person)) . ' · ' : '' ?><?= e($person['city']) ?> · member since <?= e(nice_date($person['joined'])) ?>
                </p>
                <?php if ($score !== null): ?>
                    <span class="score <?= score_class($score) ?>"><?= $score ?>% compatible</span>
                <?php endif; ?>
            </div>
            <div class="btn-row">
                <?php if ($isOwn): ?>
                    <a class="btn btn-primary" href="<?= e(url('profile/edit_profile.php')) ?>">Edit profile</a>
                    <a class="btn" href="<?= e(url('profile/my_type.php')) ?>">My flatmate type</a>
                <?php elseif ($matchId): ?>
                    <a class="btn btn-primary" href="<?= e(url('chat/conversation.php?match_id=' . $matchId)) ?>">Chat</a>
                <?php endif; ?>
                <?php if (!$isOwn && !is_admin()): ?>
                    <button type="button" class="btn btn-ghost" id="report-user" data-id="<?= (int) $person['uid'] ?>">Report</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($person['bio']): ?>
            <p style="margin-top:20px"><?= nl2br(e($person['bio'])) ?></p>
        <?php endif; ?>

        <div class="detail-grid" style="margin-top:20px">
            <div class="detail"><span>Budget</span><?= e(rupees((int) $person['budget_min'])) ?> – <?= e(rupees((int) $person['budget_max'])) ?> / month</div>
            <?php if (flatmates_label($person) !== ''): ?><div class="detail"><span>Wants to live with</span><?= e(flatmates_label($person)) ?></div><?php endif; ?>
            <?php if (stay_label($person) !== ''): ?><div class="detail"><span>Plans to stay</span><?= e(stay_label($person)) ?></div><?php endif; ?>
            <?php if (!empty($person['college'])): ?><div class="detail"><span>College</span><?= e($person['college']) ?><?= $person['college_status'] === 'incoming' ? ' · incoming' : ($person['college_status'] === 'current' ? ' · current student' : '') ?></div><?php endif; ?>
            <?php $ans = test_answers($person); ?>
            <div class="detail"><span>Test</span><?= count($ans) ?> of <?= count(test_questions()) ?> answered</div>
        </div>

        <?php if (!empty($person['flat_stage'])): [$fIcon, $fLabel] = FLAT_STAGES[(int) $person['flat_stage']] ?? ['', '']; ?>
            <h2 class="section-title" style="margin-top:22px">Flat in mind</h2>
            <div class="flat-mind">
                <?php if (!empty($person['flat_photo'])): ?><img src="<?= e(photo_url($person['flat_photo'])) ?>" alt="Photo of <?= e(explode(' ', $person['name'])[0]) ?>'s flat"><?php endif; ?>
                <div>
                    <span class="stage-pill"><?= $fIcon ?> <?= e($fLabel) ?></span>
                    <?php if ($person['flat_desc']): ?><p><?= e($person['flat_desc']) ?></p><?php endif; ?>
                    <p class="small"><?= $person['flat_rent'] ? '<strong>' . e(rupees((int) $person['flat_rent'])) . '</strong>/month' : '' ?><?= $person['flat_rent'] && $person['flat_area'] ? ' · ' : '' ?><?= e($person['flat_area'] ?? '') ?></p>
                    <?php if (!empty($mine) && !$isOwn && $person['flat_lat'] !== null): ?><p class="small muted"><?= distance_note($mine, $person['flat_lat'], $person['flat_lng']) ?></p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($person['culture_note'])): ?>
            <h2 class="section-title" style="margin-top:22px">Things flatmates should know</h2>
            <p><?= nl2br(e($person['culture_note'])) ?></p>
        <?php endif; ?>

        <div style="margin-top:16px">
            <span class="form-label">Comfortable chatting in</span>
            <div class="tags" style="margin-top:6px">
                <?php foreach ($languages as $lang): ?><span class="tag"><?= e($lang) ?></span><?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($breakdown): ?>
        <div class="card">
            <h2><?= $score === null ? 'Not enough answers yet' : 'Why ' . $score . '%?' ?></h2>
            <?= score_line($result) ?>
            <?php if ($result['same']): ?><p class="small"><?= e($result['same']) ?></p><?php endif; ?>
            <?php if ($result['discuss']): ?><p class="small">💬 <?= e($result['discuss']) ?></p><?php endif; ?>
            <div class="factor-list">
                <?php foreach ($breakdown as $factor => $pts): $max = COMPATIBILITY_MAX[$factor] ?? 100; ?>
                    <div class="factor">
                        <span class="name"><?= e($factor) ?></span>
                        <span class="bar"><i style="width: <?= round($pts / $max * 100) ?>%"></i></span>
                        <span class="pts"><?= round($pts) ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($isOwn): ?>
        <div class="card danger-zone">
            <h2>Delete account</h2>
            <p class="muted">This permanently removes your profile, swipes, matches and messages. It cannot be undone.</p>
            <a class="btn btn-danger" href="<?= e(url('profile/delete_account.php')) ?>">Delete my account</a>
        </div>
    <?php endif; ?>
</div>

<?php
$reportUrl = json_encode(url('report.php'));
$extraScripts = <<<HTML
<script>
const rb = document.getElementById('report-user');
if (rb) rb.addEventListener('click', function () {
    RM.promptBox('Report this profile', 'What is wrong? Our admins will review it.', 'Report').then(function (reason) {
        if (reason === null) return;
        const body = new FormData();
        body.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        body.append('target_type', 'user'); body.append('target_id', rb.dataset.id); body.append('reason', reason);
        fetch($reportUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); }).then(function (d) { RM.toast(d.message, d.ok ? 'success' : 'error'); });
    });
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
