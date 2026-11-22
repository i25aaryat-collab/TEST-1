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
$values = [
    'age' => (string) $profile['age'], 'gender' => $profile['gender'], 'city' => $profile['city'],
    'budget_min' => (string) $profile['budget_min'], 'budget_max' => (string) $profile['budget_max'],
    'sleep_slot' => (string) $profile['sleep_slot'], 'cleanliness' => (string) $profile['cleanliness'],
    'smoking' => $profile['smoking'], 'drinking' => $profile['drinking'], 'pets' => $profile['pets'],
    'languages' => array_filter(explode(',', $profile['languages'])), 'bio' => (string) $profile['bio'],
    'ok_smoking' => $profile['ok_smoking'], 'ok_drinking' => $profile['ok_drinking'], 'ok_pets' => $profile['ok_pets'],
    'min_cleanliness' => (string) $profile['min_cleanliness'], 'sleep_flex' => (string) $profile['sleep_flex'],
    'avatar' => picture_choice($profile['photo']),
];
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
        $stmt = $pdo->prepare('
            UPDATE profiles SET
                age = ?, gender = ?, city = ?, budget_min = ?, budget_max = ?, sleep_slot = ?,
                cleanliness = ?, smoking = ?, drinking = ?, pets = ?, languages = ?, bio = ?, photo = ?,
                ok_smoking = ?, ok_drinking = ?, ok_pets = ?, min_cleanliness = ?, sleep_flex = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ');
        $stmt->execute([
            (int) $values['age'], $values['gender'], $values['city'], (int) $values['budget_min'],
            (int) $values['budget_max'], (int) $values['sleep_slot'], (int) $values['cleanliness'],
            $values['smoking'], $values['drinking'], $values['pets'],
            languages_to_string($values['languages']), $values['bio'], $photo,
            $values['ok_smoking'], $values['ok_drinking'], $values['ok_pets'],
            (int) $values['min_cleanliness'], (int) $values['sleep_flex'],
            $_SESSION['user_id'],
        ]);
        set_flash('success', 'Profile updated.');
        redirect('profile/view_profile.php');
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
            <p class="muted">Changes update your compatibility scores straight away.</p>
        </div>
    </div>
    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert">Please fix the <?= count($errors) ?> highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?> below. Nothing was saved yet.</div>
    <?php endif; ?>
    <?php require __DIR__ . '/_profile_form.php'; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
