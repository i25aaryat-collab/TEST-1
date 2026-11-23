<?php
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
        'languages' => [], 'bio' => '', 'avatar' => '',
        'college' => '', 'college_status' => '', 'visibility' => 'everyone',
        'religion' => 'Prefer not to say', 'religion_pref' => 'any', 'culture_note' => '',
        'flat_stage' => '', 'flat_desc' => '', 'flat_area' => '', 'flat_rent' => '', 'flat_photo' => '',
        'flat_lat' => '', 'flat_lng' => '', 'ref_lat' => '', 'ref_lng' => '', 'radius_km' => 8,
        'phone' => '', 'contact_email' => '', 'instagram' => '',
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

    $num = fn($k) => is_numeric($post[$k] ?? null) ? (string) (float) $post[$k] : '';
    return [
        'age'         => trim((string) ($post['age'] ?? '')),
        'gender'      => (string) ($post['gender'] ?? ''),
        'city'        => ucwords(strtolower($city)),
        'budget_min'  => trim((string) ($post['budget_min'] ?? '')),
        'budget_max'  => trim((string) ($post['budget_max'] ?? '')),
        'languages'   => is_array($langs) ? array_values(array_map('strval', $langs)) : [],
        'bio'         => trim((string) ($post['bio'] ?? '')),
        'avatar'      => (string) ($post['avatar'] ?? ''),   // c01..c12, "upload" or "none"
        'college'        => trim(mb_substr((string) ($post['college'] ?? ''), 0, 255)),
        'college_status' => (string) ($post['college_status'] ?? ''),
        'visibility'     => (string) ($post['visibility'] ?? 'everyone'),
        'religion'       => (string) ($post['religion'] ?? 'Prefer not to say'),
        'religion_pref'  => (string) ($post['religion_pref'] ?? 'any'),
        'culture_note'   => trim((string) ($post['culture_note'] ?? '')),
        'flat_stage'  => (string) ($post['flat_stage'] ?? ''),
        'flat_desc'   => trim((string) ($post['flat_desc'] ?? '')),
        'flat_area'   => trim((string) ($post['flat_area'] ?? '')),
        'flat_rent'   => trim((string) ($post['flat_rent'] ?? '')),
        'flat_lat'    => $num('flat_lat'), 'flat_lng' => $num('flat_lng'),
        'ref_lat'     => $num('ref_lat'),  'ref_lng'  => $num('ref_lng'),
        'radius_km'   => (string) ($post['radius_km'] ?? '8'),
        'phone'         => preg_replace('/[\s-]/', '', (string) ($post['phone'] ?? '')),
        'contact_email' => trim((string) ($post['contact_email'] ?? '')),
        'instagram'     => ltrim(trim((string) ($post['instagram'] ?? '')), '@'),
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

    // Languages: optional (never scored), each from the fixed list, no repeats
    if (array_diff($d['languages'], LANGUAGE_OPTIONS) || count($d['languages']) !== count(array_unique($d['languages']))) {
        $errors['languages'] = 'Pick languages from the list only.';
    }

    // College: optional free text (search list or "not listed"); status and visibility from fixed lists
    if ($d['college_status'] !== '' && !in_array($d['college_status'], ['incoming', 'current', 'other'], true)) $errors['college_status'] = 'Choose an option from the list.';
    if (!array_key_exists($d['visibility'], VISIBILITY_OPTIONS)) $errors['visibility'] = 'Choose who can see your profile.';

    // Religion and culture: optional, fixed lists, filter only
    if (!in_array($d['religion'], RELIGION_OPTIONS, true)) $errors['religion'] = 'Choose an option from the list.';
    if (!array_key_exists($d['religion_pref'], RELIGION_PREFS)) $errors['religion_pref'] = 'Choose an option from the list.';
    if (mb_strlen($d['culture_note']) > 300) $errors['culture_note'] = 'Keep this under 300 characters.';

    // Flat in mind: optional stage and details
    if ($d['flat_stage'] !== '' && !array_key_exists((int) $d['flat_stage'], FLAT_STAGES)) $errors['flat_stage'] = 'Pick one of the stages.';
    if (mb_strlen($d['flat_desc']) > 200) $errors['flat_desc'] = 'Keep the description under 200 characters.';
    if (mb_strlen($d['flat_area']) > 100) $errors['flat_area'] = 'Keep the area under 100 characters.';
    if ($d['flat_rent'] !== '' && !is_int_between($d['flat_rent'], 500, MAX_BUDGET)) $errors['flat_rent'] = 'Enter a whole number of rupees from 500 to 2,00,000.';
    foreach (['flat', 'ref'] as $p) {
        $lat = $d[$p . '_lat']; $lng = $d[$p . '_lng'];
        if (($lat === '') !== ($lng === '') || ($lat !== '' && (abs((float) $lat) > 90 || abs((float) $lng) > 180))) $errors[$p . '_lat'] = 'Pick the location on the map again.';
    }
    if (!is_int_between($d['radius_km'], 1, 50)) $errors['radius_km'] = 'Pick a distance from 1 to 50 km.';

    // Private contact details (shared only by choice)
    if ($d['phone'] !== '' && !preg_match('/^(\+91)?[6-9][0-9]{9}$/', $d['phone'])) $errors['phone'] = 'Enter a 10-digit Indian mobile number (optionally starting +91).';
    if ($d['contact_email'] !== '' && !filter_var($d['contact_email'], FILTER_VALIDATE_EMAIL)) $errors['contact_email'] = 'Enter a valid email.';
    if ($d['instagram'] !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $d['instagram'])) $errors['instagram'] = 'Letters, numbers, dots and underscores only.';

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

/** Empty string -> NULL for optional SQL columns */
function nullable($v) { return $v === '' || $v === null ? null : $v; }

/** The profile columns this form saves, in order, ready for SQL */
function profile_form_columns(array $v): array
{
    return [
        'age' => (int) $v['age'], 'gender' => $v['gender'], 'city' => $v['city'],
        'budget_min' => (int) $v['budget_min'], 'budget_max' => (int) $v['budget_max'],
        'languages' => languages_to_string($v['languages']), 'bio' => $v['bio'],
        'college' => nullable($v['college']), 'college_status' => nullable($v['college_status']), 'visibility' => $v['visibility'],
        'religion' => $v['religion'], 'religion_pref' => $v['religion_pref'], 'culture_note' => nullable($v['culture_note']),
        'flat_stage' => nullable($v['flat_stage']), 'flat_desc' => nullable($v['flat_desc']), 'flat_area' => nullable($v['flat_area']),
        'flat_rent' => nullable($v['flat_rent']), 'flat_lat' => nullable($v['flat_lat']), 'flat_lng' => nullable($v['flat_lng']),
        'ref_lat' => nullable($v['ref_lat']), 'ref_lng' => nullable($v['ref_lng']), 'radius_km' => (int) $v['radius_km'],
        'phone' => nullable($v['phone']), 'contact_email' => nullable($v['contact_email']), 'instagram' => nullable($v['instagram']),
    ];
}

/** Flat photo: keep, replace with a new upload, or remove. Returns the file name to store (or null). */
function resolve_flat_photo(?string $current, array &$errors, int $userId): ?string
{
    if (!empty($_POST['flat_clear']) || !empty($_POST['flat_photo_remove'])) { delete_photo_file($current); $current = null; }
    $file = $_FILES['flat_photo'] ?? null;
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $photoErrors = [];
        $new = save_uploaded_photo($file, $userId, $photoErrors);
        if ($photoErrors) { $errors['flat_photo'] = reset($photoErrors); return $current; }
        delete_photo_file($current);
        return $new;
    }
    return $current;
}

/** Insert or update a profile from the form (one place for both create and edit) */
function save_profile(PDO $pdo, int $userId, array $values, ?string $photo, ?string $flatPhoto, bool $isNew): void
{
    $cols = profile_form_columns($values);
    if (!empty($_POST['flat_clear'])) {                  // "Clear all flat details"
        foreach (['flat_stage', 'flat_desc', 'flat_area', 'flat_rent', 'flat_lat', 'flat_lng'] as $k) $cols[$k] = null;
    }
    $cols['photo'] = $photo;
    $cols['flat_photo'] = $flatPhoto;
    if ($isNew) {
        $cols = ['user_id' => $userId] + $cols;
        $sql = 'INSERT INTO profiles (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($cols));
    } else {
        $sql = 'UPDATE profiles SET ' . implode(' = ?, ', array_keys($cols)) . ' = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?';
        $pdo->prepare($sql)->execute([...array_values($cols), $userId]);
    }
}

/** Profile row -> form values (for the edit page) */
function profile_to_values(array $p): array
{
    $v = profile_defaults();
    foreach ($v as $k => $_) if (array_key_exists($k, $p) && $p[$k] !== null) $v[$k] = is_array($v[$k]) ? $v[$k] : (string) $p[$k];
    $v['languages'] = $p['languages'] !== '' ? explode(',', $p['languages']) : [];
    $v['avatar'] = picture_choice($p['photo']);
    return $v;
}
