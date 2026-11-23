<?php
/**
 * includes/map.php  —  Owner: M1 (Lead)
 * Maps: MapTiler when MAPTILER_KEY is set on the server (Vercel env var),
 * otherwise free OpenStreetMap tiles. js/map.js does the drawing.
 *
 *  location_picker()  a "move the map under a fixed pin" picker that writes
 *                     hidden <input>s {prefix}_lat / {prefix}_lng
 *  map_assets()       the <script>/<link> tags, printed once per page
 *  distance_km()      straight-line distance between two points
 *  approx_point()     a point rounded to ~1 km, for people who haven't connected
 */

function maptiler_key(): string
{
    return (string) getenv('MAPTILER_KEY');
}

/** Script and style tags for map pages (call once, before the closing body) */
function map_assets(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;
    $key = maptiler_key();
    $html = $key
        ? '<link rel="stylesheet" href="https://cdn.maptiler.com/maptiler-sdk-js/v2.5.0/maptiler-sdk.css"><script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.5.0/maptiler-sdk.umd.min.js"></script>'
        : '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
    return $html . '<script>window.RM_MAP = ' . json_encode(['key' => $key]) . ';</script><script src="' . e(url('js/map.js')) . '?v=1"></script>';
}

/** Fixed-pin location picker. Values are empty strings when nothing is chosen yet. */
function location_picker(string $prefix, $lat, $lng, string $button): string
{
    $has = $lat !== '' && $lat !== null;
    $GLOBALS['extraScripts'] = ($GLOBALS['extraScripts'] ?? '') . map_assets();
    return '<div class="loc-picker" data-prefix="' . e($prefix) . '">'
        . '<input type="hidden" name="' . e($prefix) . '_lat" value="' . e((string) $lat) . '">'
        . '<input type="hidden" name="' . e($prefix) . '_lng" value="' . e((string) $lng) . '">'
        . '<div class="loc-summary"><span class="loc-address">' . ($has ? 'Location set ✓' : 'No location yet') . '</span>'
        . '<button type="button" class="btn btn-sm js-loc-open">' . e($button) . '</button>'
        . ($has ? ' <button type="button" class="link-btn small js-loc-clear">Remove</button>' : '') . '</div>'
        . '<div class="loc-panel" hidden>'
        . '<input type="search" class="loc-search" placeholder="Search an area or address" aria-label="Search an area or address">'
        . '<ul class="loc-results" hidden></ul>'
        . '<div class="loc-map"><div class="loc-canvas"></div><div class="loc-pin" aria-hidden="true"><span></span></div></div>'
        . '<p class="loc-live muted small" aria-live="polite">Drag the map to put the pin on the spot.</p>'
        . '<div class="btn-row"><button type="button" class="btn btn-primary btn-sm js-loc-confirm">Confirm this spot</button>'
        . '<button type="button" class="btn btn-ghost btn-sm js-loc-cancel">Cancel</button></div></div></div>';
}

/** Straight-line distance in km (haversine) */
function distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371; $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

/** Rounded to 2 decimals (~1 km) so an exact address isn't revealed */
function approx_point(float $lat, float $lng): array
{
    return [round($lat, 2), round($lng, 2)];
}

/** "≈3.2 km from your point · inside your 8 km radius" (or '' if either point is missing) */
function distance_note(array $me, $lat, $lng): string
{
    if ($lat === null || $lat === '' || empty($me['ref_lat'])) return '';
    $km = distance_km((float) $me['ref_lat'], (float) $me['ref_lng'], (float) $lat, (float) $lng);
    $r = (int) ($me['radius_km'] ?: 8);
    return '≈' . number_format($km, 1) . ' km from your point · ' . ($km <= $r ? '<span class="ok-text">inside</span>' : '<span class="far-text">outside</span>') . ' your ' . $r . ' km radius';
}
