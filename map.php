<?php
/**
 * map.php  —  Owner: M4 (Matching + Flats)
 * The Map tab: where people have listed their flats.
 *  - Rooms posted on Flats, and flats people have "in mind" on their profile
 *  - Always approximate (about 1 km): exact spots are only on a flat's own
 *    page, for the poster and the people they've connected with
 *  - Click a pin for a card that leads with the person; the side list mirrors the map
 * Map engine: includes/map.php + js/map.js (MapTiler with MAPTILER_KEY, else OpenStreetMap).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/swipe/compatibility.php';
require_login();   // the map shows where people live: members only

$me = null;
if (is_logged_in() && !is_admin()) {
    $st = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?'); $st->execute([$_SESSION['user_id']]); $me = $st->fetch() ?: null;
}
const MAP_DIRECTORY_EMAIL = 'listings@roomiematch.test';

$points = [];
$rows = $pdo->query("SELECT l.id, l.title, l.area, l.city, l.rent, l.lat, l.lng, l.user_id, u.name, u.email, p.photo AS avatar, p.answers, p.age, p.age_min, p.age_max
                     FROM listings l JOIN users u ON u.id = l.user_id LEFT JOIN profiles p ON p.user_id = u.id
                     WHERE l.status = 'open' AND l.lat IS NOT NULL AND u.email <> 'listings@roomiematch.test'")->fetchAll();   // only flats with a person behind them
foreach ($rows as $r) {
    [$la, $ln] = approx_point((float) $r['lat'], (float) $r['lng']);
    $dir = $r['email'] === MAP_DIRECTORY_EMAIL;
    $score = ($me && !$dir && $r['age'] && (int) $r['user_id'] !== (int) $_SESSION['user_id']) ? compatibility_score($me, $r) : null;
    $points[] = ['lat' => $la, 'lng' => $ln, 'city' => $r['city'], 'type' => 'Room',
        'who' => $dir ? $r['title'] : explode(' ', $r['name'])[0], 'avatar' => $dir ? null : photo_url($r['avatar']),
        'title' => $dir ? '' : $r['title'], 'area' => $r['area'], 'price' => $r['rent'] ? rupees((int) $r['rent']) . '/month' : 'Rent on request',
        'pin' => $r['rent'] ? rupees((int) $r['rent']) : 'Room', 'score' => $score, 'url' => url('listings/view.php?id=' . (int) $r['id'])];
}
$rows = $pdo->query("SELECT u.id, u.name, p.* FROM profiles p JOIN users u ON u.id = p.user_id
                     WHERE u.role = 'user' AND p.flat_lat IS NOT NULL AND p.flat_stage >= 3")->fetchAll();
foreach ($rows as $r) {
    if ($me && (int) $r['user_id'] === (int) $_SESSION['user_id']) continue;
    [$la, $ln] = approx_point((float) $r['flat_lat'], (float) $r['flat_lng']);
    $points[] = ['lat' => $la, 'lng' => $ln, 'city' => $r['city'], 'type' => 'Flat in mind',
        'who' => explode(' ', $r['name'])[0], 'avatar' => photo_url($r['photo']),
        'title' => $r['flat_desc'] ?: (FLAT_STAGES[(int) $r['flat_stage']][1] ?? 'Flat in mind'), 'area' => $r['flat_area'] ?? '',
        'price' => $r['flat_rent'] ? rupees((int) $r['flat_rent']) . '/month' : '', 'pin' => $r['flat_rent'] ? rupees((int) $r['flat_rent']) : 'Flat',
        'score' => $me ? compatibility_score($me, $r) : null, 'url' => url('profile/view_profile.php?id=' . (int) $r['id'])];
}
$cities = array_values(array_unique(array_column($points, 'city')));
sort($cities);
$start = $me['city'] ?? 'Indore';
if (!in_array($start, $cities, true)) $start = $cities[0] ?? 'Indore';
$myPoint = ($me && $me['ref_lat'] !== null) ? ['lat' => (float) $me['ref_lat'], 'lng' => (float) $me['ref_lng'], 'r' => (int) ($me['radius_km'] ?: 8)] : null;

$pageTitle = 'Map';
$extraScripts = map_assets();
require __DIR__ . '/includes/header.php';
?>
<div class="container map-page">
    <div class="page-head">
        <div><h1>Where flats are</h1>
            <p class="muted">Rooms people have listed, and flats people already have in mind. Pins show the rough area only.</p></div>
        <div class="map-controls">
            <label for="map-city" class="sr-only">City</label>
            <select id="map-city"><?php foreach ($cities as $c): ?><option <?= $c === $start ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
            <div class="view-toggle" role="group" aria-label="Show">
                <button type="button" data-show="all" aria-pressed="true">All</button>
                <button type="button" data-show="Room" aria-pressed="false">Rooms</button>
                <button type="button" data-show="Flat in mind" aria-pressed="false">Flats in mind</button>
            </div>
        </div>
    </div>
    <div class="map-layout">
        <div id="big-map" class="big-map" role="region" aria-label="Map of flats"></div>
        <aside class="map-list" aria-label="Flats in this city"><p class="muted small" id="map-count"></p><div id="map-items"></div></aside>
    </div>
    <?php if ($me && !$myPoint): ?><p class="small muted">Tip: set your campus or workplace on <a href="<?= e(url('profile/edit_profile.php')) ?>">your profile</a> to see your radius on the map.</p><?php endif; ?>
</div>
<?php
$data = json_encode(['points' => $points, 'me' => $myPoint], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$houseJs = json_encode(house_icon());
$extraScripts .= <<<HTML
<script>
(function () {
    const D = $data, el = document.getElementById('big-map'), list = document.getElementById('map-items');
    if (!window.RMMap) { el.innerHTML = '<p class="muted" style="padding:20px">The map could not load. Check your connection.</p>'; return; }
    const esc = function (t) { const d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
    const first = D.points[0] || { lat: RMMap.INDORE[0], lng: RMMap.INDORE[1] };
    const map = RMMap.create(el, first.lat, first.lng, 12);
    let markers = [], show = 'all';
    if (D.me) { map.circle(D.me.lat, D.me.lng, D.me.r * 1000); map.marker(D.me.lat, D.me.lng, '<span class="me-pin" title="Your point (only you see this)"></span>'); }
    const HOUSE = {$houseJs};
    function card(p) {
        return '<div class="map-card">' + (p.avatar ? '<img src="' + esc(p.avatar) + '" alt="">' : '<span class="map-card-dir">' + HOUSE + '</span>') +
            '<div><strong>' + esc(p.who) + '</strong>' + (p.score !== null ? ' <span class="map-score">' + p.score + '%</span>' : '') +
            (p.title ? '<div class="small">' + esc(p.title) + '</div>' : '') + '<div class="small muted">' + esc(p.area) + (p.price ? ' · ' + esc(p.price) : '') + '</div>' +
            '<div class="tiny muted">' + esc(p.type) + '</div><a href="' + esc(p.url) + '">Open</a></div></div>';
    }
    function draw() {
        const city = document.getElementById('map-city').value;
        markers.forEach(function (m) { m.remove(); }); markers = [];
        const pts = D.points.filter(function (p) { return p.city === city && (show === 'all' || p.type === show); });
        list.innerHTML = '';
        pts.forEach(function (p) {
            markers.push(map.marker(p.lat, p.lng, '<button type="button" class="price-pin' + (p.type === 'Room' ? '' : ' pin-mind') + '">' + esc(p.pin) + '</button>', card(p)));
            const item = document.createElement('button'); item.type = 'button'; item.className = 'map-item'; item.innerHTML = card(p);
            item.addEventListener('click', function () { map.setCenter(p.lat, p.lng, 15); });
            list.appendChild(item);
        });
        document.getElementById('map-count').textContent = pts.length + ' in ' + city;
        if (pts[0]) map.setCenter(pts[0].lat, pts[0].lng, 12);
    }
    document.getElementById('map-city').addEventListener('change', draw);
    document.querySelectorAll('[data-show]').forEach(function (b) {
        b.addEventListener('click', function () { show = b.dataset.show; document.querySelectorAll('[data-show]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); }); draw(); });
    });
    draw();
})();
</script>
HTML;
require __DIR__ . '/includes/footer.php';
