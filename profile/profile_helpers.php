<?php
require_once __DIR__ . '/../swipe/lifestyle.php';
/* ==========================================================================
 * profile/profile_helpers.php  —  Owner: M3 (Profiles and validation)
 * --------------------------------------------------------------------------
 * Shared by create_profile.php and edit_profile.php so the rules are
 * written once:
 *   read_profile_input()   tidy up what the form sent
 *   validate_profile()     SERVER-SIDE validation (the brief's rules)
 *   save_uploaded_photo()  checks and stores the optional photo
 * ========================================================================== */

require_once __DIR__ . '/../includes/helpers.php';

const MAX_PHOTO_BYTES = 2 * 1024 * 1024;              // 2 MB
const ALLOWED_PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
const MAX_BUDGET = 200000;
const MIN_BUDGET = 1000;

/** Empty values for a brand-new form */
function profile_defaults(): array
{
    return [
        'age' => '', 'gender' => '', 'city' => '', 'budget_min' => '', 'budget_max' => '',
        'sleep_slot' => '', 'cleanliness' => '', 'smoking' => '', 'drinking' => '', 'pets' => '',
        'languages' => [], 'bio' => '',
        'ok_smoking' => '', 'ok_drinking' => '', 'ok_pets' => '', 'min_cleanliness' => '', 'sleep_flex' => '',
        'avatar' => '',
        'q_clean' => '', 'q_quiet' => '', 'q_meals' => '', 'q_guests' => '', 'q_chores' => '',
        'household_note' => '', 'phone' => '',
    ];
}

/**
 * Read the submitted form into a clean array. Text is trimmed, the city is
 * written as "Indore" whatever case was typed (so the city filter matches),
 * and languages become an array.
 */
function read_profile_input(array $post): array
{
    $city = trim(preg_replace('/\s+/', ' ', (string) ($post['city'] ?? '')));
    $langs = $post['languages'] ?? [];

    return [
        'age'         => trim((string) ($post['age'] ?? '')),
        'gender'      => (string) ($post['gender'] ?? ''),
        'city'        => ucwords(strtolower($city)),
        'budget_min'  => trim((string) ($post['budget_min'] ?? '')),
        'budget_max'  => trim((string) ($post['budget_max'] ?? '')),
        'sleep_slot'  => (string) ($post['sleep_slot'] ?? ''),
        'cleanliness' => (string) ($post['cleanliness'] ?? ''),
        'smoking'     => (string) ($post['smoking'] ?? ''),
        'drinking'    => (string) ($post['drinking'] ?? ''),
        'pets'        => (string) ($post['pets'] ?? ''),
        'languages'   => is_array($langs) ? array_map('strval', $langs) : [],
        'bio'         => trim(str_replace("\r\n", "\n", (string) ($post['bio'] ?? ''))),
        'ok_smoking'      => (string) ($post['ok_smoking'] ?? ''),
        'ok_drinking'     => (string) ($post['ok_drinking'] ?? ''),
        'ok_pets'         => (string) ($post['ok_pets'] ?? ''),
        'min_cleanliness' => (string) ($post['min_cleanliness'] ?? ''),
        'sleep_flex'      => (string) ($post['sleep_flex'] ?? ''),
        'avatar'          => (string) ($post['avatar'] ?? ''),   // c01..c12, "upload" or "none"
        // Optional lifestyle quiz: '' = skipped, '0'..'4' = answer
        'q_clean'  => (string) ($post['q_clean'] ?? ''),  'q_quiet'  => (string) ($post['q_quiet'] ?? ''),
        'q_meals'  => (string) ($post['q_meals'] ?? ''),  'q_guests' => (string) ($post['q_guests'] ?? ''),
        'q_chores' => (string) ($post['q_chores'] ?? ''),
        'household_note' => trim((string) ($post['household_note'] ?? '')),
        'phone'          => preg_replace('/[\s-]/', '', (string) ($post['phone'] ?? '')),
    ];
}

/** True if $value is a whole number written with digits only, within [min, max] */
function is_int_between(string $value, int $min, int $max): bool
{
    return ctype_digit($value) && (int) $value >= $min && (int) $value <= $max;
}

/**
 * Server-side validation. Returns [field => message]; empty means valid.
 * Every rule here is also checked in JavaScript (_profile_form.php), but
 * this is the one that cannot be skipped.
 */
function validate_profile(array $d): array
{
    $errors = [];

    // Age: whole number 18-80
    if (!is_int_between($d['age'], 18, 80)) {
        $errors['age'] = 'Age must be a whole number from 18 to 80.';
    }

    // Gender: one of the dropdown options
    if (!array_key_exists($d['gender'], GENDER_OPTIONS)) {
        $errors['gender'] = 'Choose an option from the list.';
    }

    // City: required, letters and spaces only
    if ($d['city'] === '') {
        $errors['city'] = 'City is required.';
    } elseif (!preg_match('/^[A-Za-z ]+$/', $d['city']) || strlen($d['city']) > 100) {
        $errors['city'] = 'Use letters and spaces only (e.g. Indore, New Delhi).';
    }

    // Budget: digits only, min < max, max not above 2,00,000
    $minOk = is_int_between($d['budget_min'], MIN_BUDGET, MAX_BUDGET);
    $maxOk = is_int_between($d['budget_max'], MIN_BUDGET, MAX_BUDGET);
    if (!$minOk) {
        $errors['budget_min'] = 'Enter a whole number of rupees from 1,000 to 2,00,000.';
    }
    if (!$maxOk) {
        $errors['budget_max'] = 'Enter a whole number of rupees from 1,000 to 2,00,000.';
    }
    if ($minOk && $maxOk && (int) $d['budget_min'] >= (int) $d['budget_max']) {
        $errors['budget_max'] = 'Maximum must be more than the minimum.';
    }

    // Bedtime slot 1-5 and cleanliness 1-5
    if (!array_key_exists((int) $d['sleep_slot'], SLEEP_SLOTS) || !ctype_digit($d['sleep_slot'])) {
        $errors['sleep_slot'] = 'Pick your usual bedtime.';
    }
    if (!array_key_exists((int) $d['cleanliness'], CLEANLINESS_LEVELS) || !ctype_digit($d['cleanliness'])) {
        $errors['cleanliness'] = 'Pick a cleanliness level from 1 to 5.';
    }

    // Smoking, drinking, pets: must be exactly "yes" or "no"
    foreach (['smoking' => 'smoke', 'drinking' => 'drink', 'pets' => 'have pets'] as $field => $verb) {
        if (!in_array($d[$field], ['yes', 'no'], true)) {
            $errors[$field] = "Tell us whether you $verb.";
        }
    }

    // Languages: optional (never scored), each from the fixed list, no repeats
    if (array_diff($d['languages'], LANGUAGE_OPTIONS) || count($d['languages']) !== count(array_unique($d['languages']))) {
        $errors['languages'] = 'Pick languages from the list only.';
    }

    // Optional lifestyle quiz answers: blank or 0-4
    foreach (['q_clean', 'q_quiet', 'q_meals', 'q_guests', 'q_chores'] as $q) {
        if ($d[$q] !== '' && !in_array($d[$q], ['0', '1', '2', '3', '4'], true)) $errors[$q] = 'Pick one of the five points, or leave it blank.';
    }
    if (mb_strlen($d['household_note']) > 300) $errors['household_note'] = 'Keep this under 300 characters.';
    if ($d['phone'] !== '' && !preg_match('/^(\+91)?[6-9][0-9]{9}$/', $d['phone'])) $errors['phone'] = 'Enter a 10-digit Indian mobile number (optionally starting +91).';

    // What they are okay with in a flatmate: each must be one of the offered answers
    foreach (['ok_smoking' => 'a smoker', 'ok_drinking' => 'someone who drinks', 'ok_pets' => 'pets'] as $field => $what) {
        if (!array_key_exists($d[$field], TOLERANCE_OPTIONS)) {
            $errors[$field] = "Tell us if you're okay living with $what.";
        }
    }
    if (!ctype_digit($d['min_cleanliness']) || !array_key_exists((int) $d['min_cleanliness'], CLEANLINESS_LEVELS)) {
        $errors['min_cleanliness'] = 'Pick the messiest flatmate you could live with.';
    }
    if (!ctype_digit($d['sleep_flex']) || !array_key_exists((int) $d['sleep_flex'], SLEEP_FLEX_OPTIONS)) {
        $errors['sleep_flex'] = 'Pick how different a bedtime you are okay with.';
    }

    // Picture: one of the icons, an upload, or none
    if (!in_array($d['avatar'], AVATAR_IDS, true) && !in_array($d['avatar'], ['upload', 'none'], true)) {
        $errors['avatar'] = 'Pick an icon, upload a photo, or choose "No picture".';
    }

    // Bio: optional, max 300 characters
    if (mb_strlen($d['bio']) > 300) {
        $errors['bio'] = 'Bio can be at most 300 characters (you have ' . mb_strlen($d['bio']) . ').';
    }

    return $errors;
}

/** Languages array -> "Hindi,English" in the same order as the fixed list */
function languages_to_string(array $languages): string
{
    return implode(',', array_values(array_intersect(LANGUAGE_OPTIONS, $languages)));
}

/**
 * Check and store an uploaded photo.
 *   - no file chosen: returns null (photo is optional)
 *   - problem: adds $errors['photo'] and returns null
 *   - success: returns the new unique file name inside uploads/
 * We look at the file's real contents (finfo + getimagesize), not just its
 * name, so a script renamed to "cat.jpg" is refused.
 */
function save_uploaded_photo(?array $file, int $userId, array &$errors): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > MAX_PHOTO_BYTES) {
        $errors['photo'] = 'Photo must be 2 MB or smaller.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $errors['photo'] = 'Upload failed. Please try again.';
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(ALLOWED_PHOTO_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        $errors['photo'] = 'Only JPG or PNG images are allowed.';
        return null;
    }

    // Unique name, e.g. u12_9f3c1a2b7d4e6f80.jpg, so files never overwrite each other
    $newName = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . ALLOWED_PHOTO_TYPES[$mime];

    // Serverless host: keep the photo in the database (see config.php, STORE_IN_DB)
    if (STORE_IN_DB) {
        global $pdo;
        $pdo->prepare('INSERT INTO photos (name, mime, data) VALUES (?, ?, ?)')
            ->execute([$newName, $mime, base64_encode(file_get_contents($file['tmp_name']))]);
        return $newName;
    }
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $newName)) {
        $errors['photo'] = 'Could not save the photo. Please try again.';
        return null;
    }
    return $newName;
}

/** Remove an old photo file from uploads/ (safe even if it is missing) */
function delete_photo_file(?string $fileName): void
{
    if ($fileName && STORE_IN_DB) {
        global $pdo;
        $pdo->prepare('DELETE FROM photos WHERE name = ?')->execute([$fileName]);
    }
    if ($fileName && basename($fileName) === $fileName) {          // no "../" tricks
        $path = __DIR__ . '/../uploads/' . $fileName;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/**
 * Work out the profile picture from the picker:
 *   an icon (c01..c12) -> "avatar:c01"
 *   "upload"           -> the new uploaded file (or keep the current upload)
 *   "none"             -> NULL
 * Old uploaded photos that are replaced are deleted. Returns the value for
 * profiles.photo; adds $errors['avatar'] / ['photo'] if something is wrong.
 */
function resolve_picture(string $choice, ?array $file, int $userId, ?string $current, array &$errors): ?string
{
    $currentIsUpload = $current && !str_starts_with($current, 'avatar:');

    if (in_array($choice, AVATAR_IDS, true) || $choice === 'none') {
        if ($currentIsUpload) {
            delete_photo_file($current);
        }
        return $choice === 'none' ? null : 'avatar:' . $choice;
    }

    // "upload": a new file replaces the old one; without a file, keep the old upload
    $new = save_uploaded_photo($file, $userId, $errors);
    if ($new) {
        if ($currentIsUpload) delete_photo_file($current);
        return $new;
    }
    if (!isset($errors['photo']) && !$currentIsUpload) {
        $errors['photo'] = 'Choose a JPG or PNG to upload, or pick one of the icons.';
    }
    return $current;
}

/** Turn the saved photo column into the picker's choice (for pre-filling the edit form) */
function picture_choice(?string $photo): string
{
    if ($photo === null || $photo === '') return 'none';
    return str_starts_with($photo, 'avatar:') ? substr($photo, 7) : 'upload';
}

/** Quiz value for SQL: null when skipped */
function quiz_value(string $v): ?int { return $v === '' ? null : (int) $v; }
