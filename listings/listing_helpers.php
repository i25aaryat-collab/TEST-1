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

// ---------- "Request exact location" (table location_requests, created on first use) ----------

/** Run a query; create location_requests the first time it is needed */
function lr_db(PDO $pdo, callable $fn)
{
    try { return $fn(); }
    catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42P01', '42S02'], true)) throw $e;
        $file = __DIR__ . '/../database/migration_v11_location_requests_' . (DB_DRIVER === 'pgsql' ? 'pg' : 'mysql') . '.sql';
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents($file))))) as $sql) $pdo->exec($sql);
        return $fn();
    }
}

/** My request for a flat's exact location: null, or the row (status pending / approved / declined) */
function my_location_request(PDO $pdo, int $listingId, int $userId): ?array
{
    return lr_db($pdo, function () use ($pdo, $listingId, $userId) {
        $s = $pdo->prepare('SELECT * FROM location_requests WHERE listing_id = ? AND requester_id = ?');
        $s->execute([$listingId, $userId]);
        return $s->fetch() ?: null;
    });
}

/** Pending requests on a flat, newest first, with the person's name and photo (for the owner) */
function pending_location_requests(PDO $pdo, int $listingId): array
{
    return lr_db($pdo, function () use ($pdo, $listingId) {
        $s = $pdo->prepare("SELECT r.id, r.created_at, u.id AS uid, u.name, p.photo, p.answers, p.age
                            FROM location_requests r JOIN users u ON u.id = r.requester_id LEFT JOIN profiles p ON p.user_id = u.id
                            WHERE r.listing_id = ? AND r.status = 'pending' ORDER BY r.created_at DESC");
        $s->execute([$listingId]);
        return $s->fetchAll();
    });
}

/** Google Street View: an embed when GOOGLE_MAPS_EMBED_KEY is set, and always a link that opens it */
function street_view(float $lat, float $lng): string
{
    // Street-level photos come from Mapillary (free, no billing; env MAPILLARY_TOKEN = its client token).
    // Google's embed still works too if GOOGLE_MAPS_EMBED_KEY is ever set.
    $gKey = getenv('GOOGLE_MAPS_EMBED_KEY') ?: '';
    $mKey = getenv('MAPILLARY_TOKEN') ?: '';
    $view = '';
    if ($mKey) {
        $view = '<div class="street-view" id="mly-view" data-lat="' . $lat . '" data-lng="' . $lng . '" data-token="' . e($mKey) . '">'
              . '<p class="muted small sv-msg">Loading street view…</p></div>';
    } elseif ($gKey) {
        $view = '<iframe class="street-view" title="Street View of the flat" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" src="'
              . e('https://www.google.com/maps/embed/v1/streetview?key=' . rawurlencode($gKey) . '&location=' . $lat . ',' . $lng . '&fov=80') . '"></iframe>';
    }
    $google = 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=' . $lat . ',' . $lng;
    $mly = 'https://www.mapillary.com/app/?lat=' . $lat . '&lng=' . $lng . '&z=17';
    return '<div class="street-view-box"><h3 class="h-small">Street view</h3>' . $view
        . '<div class="btn-row"><a class="btn btn-sm" href="' . e($google) . '" target="_blank" rel="noopener">Open in Google Street View</a>'
        . '<a class="btn btn-ghost btn-sm" href="' . e($mly) . '" target="_blank" rel="noopener">Open in Mapillary</a></div>'
        . '<span class="muted small">Street-level photos may not be available on every lane.</span></div>';
}
