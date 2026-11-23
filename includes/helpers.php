<?php
/* ==========================================================================
 * includes/helpers.php  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * Small shared tools every member can use:
 *   e()              safe output (stops XSS)
 *   set_flash() / get_flash()   one-time success or error messages
 *   photo_url()      the address of a user's photo (or a default)
 *   redirect()       send the browser to another page and stop
 * plus the fixed option lists from the brief (bedtime slots, languages...).
 * ========================================================================== */

require_once __DIR__ . '/../config/config.php';

// ---------- Fixed option lists (Section 7 of the brief) ---------------------

/** Bedtime slots: the number is stored in profiles.sleep_slot */
const SLEEP_SLOTS = [
    1 => '8 PM to 10 PM',
    2 => '10 PM to 12 AM',
    3 => '12 AM to 2 AM',
    4 => '2 AM to 4 AM',
    5 => '4 AM to 6 AM',
];

/** Cleanliness levels 1-5 with a friendly label for each */
const CLEANLINESS_LEVELS = [
    1 => 'Untidy',
    2 => 'A bit messy',
    3 => 'Average',
    4 => 'Tidy',
    5 => 'Very tidy',
];

/** "Are you okay with a flatmate who...?" answers (ok_smoking, ok_drinking, ok_pets) */
const TOLERANCE_OPTIONS = ['yes' => 'Fine with it', 'maybe' => 'Rather not', 'no' => 'Not okay'];

/** "How different can your flatmate's bedtime be?" (sleep_flex, in 2-hour slots) */
const SLEEP_FLEX_OPTIONS = [
    0 => 'Same 2-hour window',
    1 => 'Up to 2 hours apart',
    2 => 'Up to 4 hours apart',
    4 => "Doesn't matter",
];

/** The 12 character icons users can pick instead of a photo (css/avatars/c01.svg ...) */
const AVATAR_IDS = ['c01', 'c02', 'c03', 'c04', 'c05', 'c06', 'c07', 'c08', 'c09', 'c10', 'c11', 'c12'];

/** The only languages a user may pick */
const LANGUAGE_OPTIONS = [
    'Hindi', 'English', 'Marathi', 'Gujarati', 'Punjabi', 'Bengali',
    'Tamil', 'Telugu', 'Kannada', 'Malayalam', 'Urdu',
];

/** Chat extras: stickers are the characters with a speech bubble, GIFs are animated reactions */
// Sticker packs: our 12 characters (css/stickers/c01.svg) + Microsoft Fluent 3D
// emoji (MIT licence, css/stickers/fluent/*.webp)
const STICKER_PACKS = [
    'Characters' => AVATAR_IDS,
    'Flat life'  => ["house", "house_with_garden", "key", "door", "bed", "couch_and_lamp", "broom", "sponge", "bucket", "basket", "shopping_cart", "potted_plant", "pizza", "hot_beverage", "teacup_without_handle", "bubble_tea", "cooking", "money_bag", "alarm_clock", "package", "party_popper", "wrapped_gift", "light_bulb", "soap"],
    'Feelings'   => ["smiling_face_with_hearts", "face_with_tears_of_joy", "grinning_squinting_face", "hugging_face", "thinking_face", "sleeping_face", "partying_face", "pleading_face", "face_with_rolling_eyes", "saluting_face", "melting_face", "star_struck", "nerd_face", "smiling_face_with_sunglasses", "zany_face", "yawning_face", "thumbs_up", "waving_hand", "handshake", "red_heart", "fire", "sparkles", "dog_face", "ghost"],
];
const STICKER_IDS = [...STICKER_PACKS['Characters'], ...STICKER_PACKS['Flat life'], ...STICKER_PACKS['Feelings']];

/** Image path for a sticker id */
function sticker_src(string $id): string
{
    return url(in_array($id, AVATAR_IDS, true) ? 'css/stickers/' . $id . '.svg' : 'css/stickers/fluent/' . $id . '.webp');
}

/** Quick reactions on chat messages */
const REACTIONS = ['❤️', '😂', '👍', '😮', '😢', '🙏'];
const GIF_IDS = ['yay', 'home', 'keys', 'lol', 'night', 'deal'];  // css/gifs/yay.svg ...

const GENDER_OPTIONS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

// ---------- Output safety ---------------------------------------------------

/**
 * e($text): escape text before printing it in HTML.
 * Turns < > " ' & into harmless codes, so a user who types <script> in
 * their bio just sees the text, it never runs. (This is how we stop XSS.)
 */
function e($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// ---------- Navigation ------------------------------------------------------

/** Send the browser to another page of the site and stop this script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// ---------- Flash messages --------------------------------------------------
// A "flash" is a message saved in the session that shows once on the next
// page, e.g. "Profile updated" after saving a form.

/** Save a one-time message. $type is 'success' or 'error'. */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Read the message and remove it, so it only appears once. */
function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ---------- Photos ----------------------------------------------------------

/**
 * Address of a profile picture: an uploaded photo, a picked character icon
 * ("avatar:c01"), or a default silhouette when there is neither (NULL).
 */
function photo_url(?string $fileName): string
{
    // A picked character icon is stored as "avatar:c01"
    if ($fileName && str_starts_with($fileName, 'avatar:')) {
        $id = substr($fileName, 7);
        return url('css/avatars/' . (in_array($id, AVATAR_IDS, true) ? $id : 'c01') . '.svg');
    }
    // An image link (imported listings) is used as it is
    if ($fileName && preg_match('#^https://#', $fileName)) {
        return url('img.php?u=' . rawurlencode($fileName));   // passed through our server (img.php)
    }
    if ($fileName && is_file(__DIR__ . '/../uploads/' . $fileName)) {
        return url('uploads/' . rawurlencode($fileName));
    }
    if ($fileName && STORE_IN_DB) {
        return url('photo.php?f=' . rawurlencode($fileName));   // photo kept in the database
    }
    return url('css/default-avatar.svg');
}

/** "8000" -> "8,000" (Indian digit grouping for rupee amounts up to lakhs) */
function rupees(int $amount): string
{
    $s = (string) $amount;
    if (strlen($s) <= 3) return '₹' . $s;
    $last3 = substr($s, -3);
    $rest  = substr($s, 0, -3);
    $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return '₹' . $rest . ',' . $last3;
}

/** "2026-11-21 14:05:00" -> "21 Nov 2026" */
function nice_date(string $timestamp): string
{
    return date('j M Y', strtotime($timestamp));
}

/** True for imported photos that are links, which are not pictures of the actual place */
function is_representative_photo(?string $photo): bool
{
    return (bool) ($photo && str_starts_with($photo, 'https://'));
}

/** Who can see a profile on Discover */
const VISIBILITY_OPTIONS = ['everyone' => 'Everyone', 'college' => 'My college only', 'search' => 'Only in search'];

/** Religion (optional, profile and filter only — never scored) */
const RELIGION_OPTIONS = ['Prefer not to say', 'Hinduism', 'Islam', 'Christianity', 'Sikhism', 'Buddhism', 'Jainism',
    'Zoroastrianism (Parsi)', 'Judaism', "Bahá'í Faith", 'Tribal / indigenous faith', 'Spiritual, not religious', 'Atheist / agnostic', 'Other'];
const RELIGION_PREFS = ['any' => 'No preference', 'same' => 'Prefer the same religion as me', 'open' => 'Comfortable with different routines in shared spaces'];

/** Flat-search stage: [icon, label] */
const FLAT_STAGES = [1 => ['🌱', "Haven't started"], 2 => ['🗺️', 'Exploring areas'], 3 => ['⚖️', 'Comparing options'],
    4 => ['📌', 'Shortlisted a flat'], 5 => ['🔑', 'Chosen a flat']];

require_once __DIR__ . '/map.php';
