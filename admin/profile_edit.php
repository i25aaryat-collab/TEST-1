<?php
/* ==========================================================================
 * admin/profile_edit.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Admin only: CREATE or UPDATE any user's profile (?user_id=…).
 * Uses exactly the same form, validation and save code as a user editing
 * their own profile (profile/_profile_form.php, validate_profile, save_profile),
 * so an admin can't save anything a user couldn't.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../profile/profile_helpers.php';

require_admin();

$userId = (int) ($_GET['user_id'] ?? 0);
$s = $pdo->prepare("SELECT u.id, u.name, u.role, p.* FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?");
$s->execute([$userId]);
$row = $s->fetch();
if (!$row || $row['role'] !== 'user') { set_flash('error', 'Only user accounts have profiles.'); redirect('admin/dashboard.php'); }

$isNew = $row['user_id'] === null;
$values = $isNew ? profile_defaults() : profile_to_values($row);
$currentPhoto = $isNew ? null : $row['photo'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $values = read_profile_input($_POST);
    $errors = validate_profile($values);

    $photo = $currentPhoto;
    if (!$errors) $photo = resolve_picture($values['avatar'], $_FILES['photo'] ?? null, $userId, $currentPhoto, $errors);
    if (!$errors) {
        $flatPhoto = resolve_flat_photo($isNew ? null : ($row['flat_photo'] ?? null), $errors, $userId);
        if (!$errors) {
            save_profile($pdo, $userId, $values, $photo, $flatPhoto, $isNew);
            set_flash('success', $row['name'] . "'s profile was " . ($isNew ? 'created.' : 'updated.'));
            redirect('admin/user_form.php?id=' . $userId);
        }
    }
}

$mode = $isNew ? 'create' : 'edit';
$cancelUrl = 'admin/user_form.php?id=' . $userId;
$pageTitle = ($isNew ? 'Create' : 'Edit') . ' profile: ' . $row['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="page-head">
        <div><h1><?= $isNew ? 'Create a profile for ' : 'Edit profile: ' ?><?= e($row['name']) ?></h1>
            <p class="muted">You're editing as an admin. <?= e(explode(' ', $row['name'])[0]) ?> answers the compatibility test from their own account.</p></div>
        <a class="btn" href="<?= e(url('admin/user_form.php?id=' . $userId)) ?>">Back to account</a>
    </div>
    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert">Please fix the <?= count($errors) ?> highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?> below. Nothing was saved yet.</div>
    <?php endif; ?>
    <?php require __DIR__ . '/../profile/_profile_form.php'; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
