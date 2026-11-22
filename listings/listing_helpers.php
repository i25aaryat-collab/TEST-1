<?php
/* ==========================================================================
 * listings/listing_helpers.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * Shared by the flat-listing pages: tidy input and SERVER-SIDE validation
 * for the "Post a room" form. The same rules run in JavaScript in edit.php.
 * ========================================================================== */

require_once __DIR__ . '/../includes/helpers.php';

const ROOM_TYPES = ['private' => 'Private room', 'shared' => 'Shared room'];

/** Empty form values */
function listing_defaults(): array
{
    return ['title' => '', 'city' => '', 'area' => '', 'rent' => '', 'room_type' => '', 'furnished' => '',
            'available_from' => '', 'description' => ''];
}

/** Read the submitted form */
function read_listing_input(array $post): array
{
    $clean = fn($k) => trim(preg_replace('/\s+/', ' ', (string) ($post[$k] ?? '')));
    return [
        'title'          => $clean('title'),
        'city'           => ucwords(strtolower($clean('city'))),
        'area'           => $clean('area'),
        'rent'           => $clean('rent'),
        'room_type'      => (string) ($post['room_type'] ?? ''),
        'furnished'      => (string) ($post['furnished'] ?? ''),
        'available_from' => (string) ($post['available_from'] ?? ''),
        'description'    => trim(str_replace("\r\n", "\n", (string) ($post['description'] ?? ''))),
    ];
}

/** Server-side validation. Returns [field => message]. */
function validate_listing(array $d): array
{
    $errors = [];
    if (mb_strlen($d['title']) < 5 || mb_strlen($d['title']) > 100) {
        $errors['title'] = 'Title must be 5 to 100 characters.';
    }
    if (!preg_match('/^[A-Za-z ]{2,100}$/', $d['city'])) {
        $errors['city'] = 'City: letters and spaces only.';
    }
    if (mb_strlen($d['area']) < 2 || mb_strlen($d['area']) > 100) {
        $errors['area'] = 'Area must be 2 to 100 characters.';
    }
    if (!ctype_digit($d['rent']) || (int) $d['rent'] < 1000 || (int) $d['rent'] > 200000) {
        $errors['rent'] = 'Rent must be a whole number of rupees from 1,000 to 2,00,000.';
    }
    if (!array_key_exists($d['room_type'], ROOM_TYPES)) {
        $errors['room_type'] = 'Choose private or shared.';
    }
    if (!in_array($d['furnished'], ['yes', 'no'], true)) {
        $errors['furnished'] = 'Tell us if it is furnished.';
    }
    $date = DateTime::createFromFormat('Y-m-d', $d['available_from']);
    if (!$date || $date->format('Y-m-d') !== $d['available_from']) {
        $errors['available_from'] = 'Pick a valid date.';
    } elseif ($d['available_from'] < date('Y-m-d', strtotime('-1 day')) || $d['available_from'] > date('Y-m-d', strtotime('+1 year'))) {
        $errors['available_from'] = 'Pick a date between today and one year from now.';
    }
    if (mb_strlen($d['description']) < 20 || mb_strlen($d['description']) > 1000) {
        $errors['description'] = 'Description must be 20 to 1,000 characters.';
    }
    return $errors;
}
