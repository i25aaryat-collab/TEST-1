<?php
/* ==========================================================================
 * profile/edit_profile.php  —  Owner: M3 (Profiles and validation)
 * --------------------------------------------------------------------------
 * CRUD: UPDATE. The form opens pre-filled with the saved profile.
 * Users can only ever edit their OWN profile: the row is chosen by the
 * session's user_id, never by an id in the URL.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

$profile = require_profile();             // the logged-in user's saved row

// Pre-fill: turn the saved row into the same shape the form expects
$values = profile_to_values($profile);
$errors = [];
$currentPhoto = $profile['photo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $values = read_profile_input($_POST);
    $errors = validate_profile($values);

    $photo = $currentPhoto;
    if (!$errors) {
        $photo = resolve_picture($values['avatar'], $_FILES['photo'] ?? null, $_SESSION['user_id'], $currentPhoto, $errors);
    }

    if (!$errors) {
        $flatPhoto = resolve_flat_photo($profile['flat_photo'] ?? null, $errors, (int) $_SESSION['user_id']);
        if (!$errors) {
            save_profile($pdo, (int) $_SESSION['user_id'], $values, $photo, $flatPhoto, false);
            set_flash('success', 'Profile updated.');
            redirect('profile/view_profile.php');
        }
    }
}

$mode = 'edit';
$pageTitle = 'Edit profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="page-head">
        <div>
            <h1>Edit your profile</h1>
            <p><a class="btn btn-sm" href="<?= e(url('profile/test.php')) ?>">Retake or finish the compatibility test →</a></p>
            <p class="muted">Changes update your compatibility scores straight away.</p>
        </div>
    </div>
    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert">Please fix the <?= count($errors) ?> highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?> below. Nothing was saved yet.</div>
    <?php endif; ?>
    <?php require __DIR__ . '/_profile_form.php'; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
