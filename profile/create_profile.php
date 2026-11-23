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

    if (!$errors) {
        $stmt = $pdo->prepare('
            INSERT INTO profiles
                (user_id, age, gender, city, budget_min, budget_max, sleep_slot, cleanliness,
                 smoking, drinking, pets, languages, bio, photo,
                 ok_smoking, ok_drinking, ok_pets, min_cleanliness, sleep_flex,
                 q_clean, q_quiet, q_meals, q_guests, q_chores, household_note, phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $_SESSION['user_id'], (int) $values['age'], $values['gender'], $values['city'],
            (int) $values['budget_min'], (int) $values['budget_max'], (int) $values['sleep_slot'],
            (int) $values['cleanliness'], $values['smoking'], $values['drinking'], $values['pets'],
            languages_to_string($values['languages']), $values['bio'], $photo,
            $values['ok_smoking'], $values['ok_drinking'], $values['ok_pets'],
            (int) $values['min_cleanliness'], (int) $values['sleep_flex'],
            quiz_value($values['q_clean']), quiz_value($values['q_quiet']), quiz_value($values['q_meals']),
            quiz_value($values['q_guests']), quiz_value($values['q_chores']), $values['household_note'], $values['phone'] ?: null,
        ]);
        set_flash('success', 'Your profile is live! Here are your best matches in ' . $values['city'] . '.');
        redirect('swipe/swipe.php');
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
            <h1>Your compatibility test</h1>
            <p class="muted">A two-minute compatibility test. Your answers decide who you see and your score with each person.</p>
        </div>
    </div>
    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert">Please fix the <?= count($errors) ?> highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?> below.</div>
    <?php endif; ?>
    <?php require __DIR__ . '/_profile_form.php'; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
