<?php
/**
 * geo.php  —  Owner: M5 (Maps)
 * Place search for the location pickers, done on our server so we follow
 * OpenStreetMap's Nominatim rules (https://operations.osmfoundation.org/policies/nominatim/):
 *   - a real User-Agent and Referer that identify RoomieMatch
 *   - at most 1 request per second to Nominatim (shared across all visitors)
 *   - answers cached (table geo_cache, 30 days), so the same search never hits OSM twice
 * Suggestions as you type come from Photon (Komoot, OSM data, made for type-ahead);
 * Nominatim is the fallback and turns a dropped pin into an address.
 *
 *   GET ?q=text[&lat=..&lng=..]  -> [{name, short, area, lat, lng}, ...]  (up to 6, India, biased near lat/lng)
 *   GET ?lat=..&lng=..           -> {name}                          (address of a point)
 * Only for logged-in people (the pickers live on the profile and listing forms).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';
session_write_close();                       // never hold the session while we wait on OSM

header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) { http_response_code(401); exit('[]'); }

const GEO_UA       = 'RoomieMatch/1.0 (student project; https://roomiezmatch.vercel.app)';
const GEO_REFERER  = 'https://roomiezmatch.vercel.app/';
const GEO_TTL      = 30 * 86400;

$q   = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($_GET['q'] ?? ''))), 0, 120);
$lat = is_numeric($_GET['lat'] ?? null) ? round((float) $_GET['lat'], 4) : null;
$lng = is_numeric($_GET['lng'] ?? null) ? round((float) $_GET['lng'], 4) : null;
if ($lat !== null && (abs($lat) > 90 || abs($lng) > 180)) { $lat = $lng = null; }

if ($q !== '') {
    if (mb_strlen($q) < 3) exit('[]');
    // Bias towards the person's area (~60 km box), rounded so nearby searches share a cache entry
    $near = $lat !== null ? [round($lat, 1), round($lng, 1)] : null;
    $key = 'q:' . mb_strtolower($q) . ($near ? '@' . implode(',', $near) : '');
    echo geo_cached($pdo, $key, fn() => geo_search($q, $near));
} elseif ($lat !== null) {
    echo geo_cached($pdo, 'r:' . round($lat, 4) . ',' . round($lng, 4), fn() => geo_reverse($lat, $lng));
} else {
    echo '[]';
}

/** Cached answer, or ask OSM and keep the answer. Failed lookups are not cached. */
function geo_cached(PDO $pdo, string $key, callable $fetch): string
{
    $id = hash('sha256', $key);
    $row = geo_db($pdo, function () use ($pdo, $id) {
        $s = $pdo->prepare('SELECT body, created_at FROM geo_cache WHERE k = ?');
        $s->execute([$id]);
        return $s->fetch();
    });
    if ($row && (int) $row['created_at'] > time() - GEO_TTL) {
        header('X-Geo-Cache: hit');
        return $row['body'];
    }
    $body = $fetch();
    if ($body === null) { http_response_code(502); return '[]'; }
    geo_db($pdo, function () use ($pdo, $id, $body) {
        $sql = DB_DRIVER === 'pgsql'
            ? 'INSERT INTO geo_cache (k, body, created_at) VALUES (?, ?, ?) ON CONFLICT (k) DO UPDATE SET body = EXCLUDED.body, created_at = EXCLUDED.created_at'
            : 'REPLACE INTO geo_cache (k, body, created_at) VALUES (?, ?, ?)';
        $pdo->prepare($sql)->execute([$id, $body, time()]);
    });
    header('X-Geo-Cache: miss');
    return $body;
}

/** Run a query; create geo_cache on first use (same pattern as communities/chat.php) */
function geo_db(PDO $pdo, callable $fn)
{
    try { return $fn(); }
    catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42P01', '42S02'], true)) throw $e;
        $file = __DIR__ . '/database/migration_v10_geo_cache_' . (DB_DRIVER === 'pgsql' ? 'pg' : 'mysql') . '.sql';
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents($file))))) as $sql) $pdo->exec($sql);
        return $fn();
    }
}

/**
 * Suggestions as you type come from Photon (built for type-ahead, forgives spelling like "vijaynagar").
 * Nominatim's policy does not allow type-ahead, so it is only the fallback when Photon is down
 * (paced to 1 request/second for the whole site).
 */
function geo_search(string $q, ?array $near): ?string
{
    $pp = ['q' => $q, 'limit' => 7, 'lang' => 'en', 'bbox' => '68,6,98,37'];          // India
    if ($near) { $pp['lat'] = $near[0]; $pp['lon'] = $near[1]; $pp['location_bias_scale'] = 0.4; }
    $j = geo_get(getenv('GEO_PHOTON_URL') ?: 'https://photon.komoot.io/api/', $pp);
    if (is_array($j) && isset($j['features'])) {
        $seen = []; $out = [];
        foreach ($j['features'] as $f) {
            $p = $f['properties'] ?? [];
            if (($p['countrycode'] ?? 'IN') !== 'IN') continue;
            $main = $p['name'] ?? trim(($p['housenumber'] ?? '') . ' ' . ($p['street'] ?? ''));
            $area = array_values(array_unique(array_filter([$p['street'] ?? null, $p['locality'] ?? null, $p['district'] ?? null, $p['city'] ?? $p['county'] ?? null, $p['state'] ?? null])));
            $area = array_values(array_filter($area, fn($x) => $x !== $main));
            $name = implode(', ', array_filter(array_merge([$main], $area)));
            if ($name === '' || isset($seen[$name])) continue;
            $seen[$name] = true;
            $out[] = ['name' => $name, 'short' => $main ?: ($area[0] ?? $name), 'area' => implode(', ', array_slice($area, 0, 3)),
                      'lat' => (float) $f['geometry']['coordinates'][1], 'lng' => (float) $f['geometry']['coordinates'][0]];
        }
        if ($out) return json_encode(array_slice($out, 0, 6));
    }
    // Fallback: Nominatim (full search, not type-ahead)
    $p = ['format' => 'jsonv2', 'q' => $q, 'countrycodes' => 'in', 'limit' => 6, 'addressdetails' => 1, 'accept-language' => 'en'];
    if ($near) $p['viewbox'] = ($near[1] - .6) . ',' . ($near[0] + .6) . ',' . ($near[1] + .6) . ',' . ($near[0] - .6);
    $j = geo_nominatim('search', $p);
    if (!is_array($j)) return null;
    return json_encode(array_map(fn($x) => [
        'name' => $x['display_name'], 'short' => geo_short($x, 0), 'area' => implode(', ', array_slice(array_map('trim', explode(',', $x['display_name'])), 1, 3)),
        'lat' => (float) $x['lat'], 'lng' => (float) $x['lon'],
    ], $j));
}

function geo_reverse(float $lat, float $lng): ?string
{
    $j = geo_nominatim('reverse', ['format' => 'jsonv2', 'lat' => $lat, 'lon' => $lng, 'zoom' => 17, 'addressdetails' => 1, 'accept-language' => 'en']);
    if (!is_array($j)) return null;
    return json_encode(['name' => isset($j['display_name']) ? geo_short($j, 3) : '']);
}

/** "Name, Area, City" from a Nominatim result */
function geo_short(array $x, int $n = 2): string
{
    $a = $x['address'] ?? [];
    $parts = array_values(array_unique(array_filter([
        $x['name'] ?? null, $a['road'] ?? null, $a['neighbourhood'] ?? $a['suburb'] ?? null, $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['county'] ?? null,
    ])));
    return $parts ? implode(', ', array_slice($parts, 0, $n + 1)) : implode(',', array_slice(explode(',', $x['display_name'] ?? ''), 0, $n));
}

/** One Nominatim call, never faster than 1 per second across the whole site (slot kept in geo_cache) */
function geo_nominatim(string $path, array $params)
{
    global $pdo;
    $slot = hash('sha256', '__nominatim_last');
    $now = microtime(true);
    $last = (float) (geo_db($pdo, function () use ($pdo, $slot) {
        $s = $pdo->prepare('SELECT body FROM geo_cache WHERE k = ?'); $s->execute([$slot]); return $s->fetchColumn();
    }) ?: 0);
    $wait = $last + 1.05 - $now;
    if ($wait > 0) { if ($wait > 2) return null; usleep((int) ($wait * 1e6)); }     // queue briefly, else let Photon answer
    geo_db($pdo, function () use ($pdo, $slot) {
        $sql = DB_DRIVER === 'pgsql'
            ? 'INSERT INTO geo_cache (k, body, created_at) VALUES (?, ?, 0) ON CONFLICT (k) DO UPDATE SET body = EXCLUDED.body'
            : 'REPLACE INTO geo_cache (k, body, created_at) VALUES (?, ?, 0)';
        $pdo->prepare($sql)->execute([$slot, (string) microtime(true)]);
    });
    return geo_get((getenv('GEO_NOMINATIM_URL') ?: 'https://nominatim.openstreetmap.org/') . $path, $params);
}

/** GET a JSON URL with our identifying headers; null on any failure (4 s max) */
function geo_get(string $url, array $params)
{
    $ch = curl_init($url . '?' . http_build_query($params));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['User-Agent: ' . GEO_UA, 'Referer: ' . GEO_REFERER, 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) { error_log("geo: $url answered $code"); return null; }
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}
