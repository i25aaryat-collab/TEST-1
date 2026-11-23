<?php
/* ==========================================================================
 * profile/create_profile.php  —  Owner: M3 (Profiles and validation)
 * --------------------------------------------------------------------------
 * CRUD: CREATE. A newly registered user fills in their profile here.
 * Validated in JavaScript first, then again in PHP (validate_profile).
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

require_login();
if (is_admin()) {
    redirect('admin/dashboard.php');               // admins don't have profiles
}

// Only one profile per user: if it already exists, edit it instead
$stmt = $pdo->prepare('SELECT 1 FROM profiles WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetch()) {
    redirect('profile/edit_profile.php');
}

$values = profile_defaults();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $values = read_profile_input($_POST);
    $errors = validate_profile($values);

    // Only store a photo when everything else is valid (no orphan files)
    $photo = null;
    if (!$errors) {
        $photo = resolve_picture($values['avatar'], $_FILES['photo'] ?? null, $_SESSION['user_id'], null, $errors);
    }

    $flatPhoto = null;
    if (!$errors) $flatPhoto = resolve_flat_photo(null, $errors, $_SESSION['user_id']);
    if (!$errors) {
        save_profile($pdo, (int) $_SESSION['user_id'], $values, $photo, $flatPhoto, true);
        set_flash('success', 'Profile saved. Now the compatibility test: it decides your score with each person.');
        redirect('profile/test.php');
    }
}

$mode = 'create';
$currentPhoto = null;
$pageTitle = 'Create your profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="page-head">
        <div>
            <h1>Set up your profile</h1>
            <p class="muted">Step 1 of 2: the basics. Next comes the compatibility test.</p>
        </div>
    </div>
    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert">Please fix the <?= count($errors) ?> highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?> below.</div>
    <?php endif; ?>
    <?php require __DIR__ . '/_profile_form.php'; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
