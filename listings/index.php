<?php
/* ==========================================================================
 * listings/index.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * "Flats": rooms offered by people who already have a flat.
 * Anyone can browse; logged-in users can post a room.
 * Search (title or area), filters (city, max rent, room type, furnished)
 * and sort (newest, cheapest, soonest available).
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/listing_helpers.php';
require_once __DIR__ . '/../swipe/compatibility.php';

$cities = $pdo->query("SELECT DISTINCT city FROM listings WHERE status = 'open' AND user_id NOT IN (SELECT id FROM users WHERE email = 'listings@roomiematch.test') ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$q        = trim($_GET['q'] ?? '');
$city     = in_array($_GET['city'] ?? '', $cities, true) ? $_GET['city'] : '';
$maxRent  = isset($_GET['max_rent']) && ctype_digit($_GET['max_rent']) ? (int) $_GET['max_rent'] : null;
$roomType = array_key_exists($_GET['room_type'] ?? '', ROOM_TYPES) ? $_GET['room_type'] : '';
$furnished = in_array($_GET['furnished'] ?? '', ['yes', 'no'], true) ? $_GET['furnished'] : '';
$sorts = ['new' => ['l.created_at DESC', 'Newest first'], 'cheap' => ['l.rent ASC', 'Cheapest first'], 'soon' => ['l.available_from ASC', 'Available soonest']];
$sort  = array_key_exists($_GET['sort'] ?? '', $sorts) ? $_GET['sort'] : 'new';
$onlyMatches = !empty($_GET['matches']);

// People I've matched with: their flats are pinned on top (and can be the only ones shown)
$matchIds = [];
if (is_logged_in() && !is_admin()) {
    $st = $pdo->prepare('SELECT CASE WHEN user1_id = ? THEN user2_id ELSE user1_id END FROM matches WHERE user1_id = ? OR user2_id = ?');
    $st->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $matchIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// Each flat is shown through the PERSON who posted it: their name, picture and your score with them.
$sql = "SELECT l.*, u.name AS owner, u.email AS owner_email, p.photo AS owner_photo, p.answers, p.age, p.age_min, p.age_max
        FROM listings l JOIN users u ON u.id = l.user_id LEFT JOIN profiles p ON p.user_id = u.id
        WHERE l.status = 'open' AND u.email <> 'listings@roomiematch.test'";   // every flat belongs to a person; imported directory flats are hidden
$params = [];
if ($q !== '')          { $sql .= ' AND (LOWER(l.title) LIKE LOWER(?) OR LOWER(l.area) LIKE LOWER(?))'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($city !== '')       { $sql .= ' AND l.city = ?'; $params[] = $city; }
if ($maxRent !== null)  { $sql .= ' AND l.rent <= ?'; $params[] = $maxRent; }
if ($roomType !== '')   { $sql .= ' AND l.room_type = ?'; $params[] = $roomType; }
if ($furnished !== '')  { $sql .= ' AND l.furnished = ?'; $params[] = $furnished; }
if ($onlyMatches)       { $sql .= $matchIds ? ' AND l.user_id IN (' . implode(',', $matchIds) . ')' : ' AND 1 = 0'; }   // ints only
$sql .= ' ORDER BY ' . $sorts[$sort][0];             // only fixed, known ORDER BY strings
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// My profile (for scores and distances), if I have one
$me = null;
if (is_logged_in() && !is_admin()) {
    $st = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?'); $st->execute([$_SESSION['user_id']]); $me = $st->fetch() ?: null;
}
const DIRECTORY_EMAIL = 'listings@roomiematch.test';    // the account holding imported directory listings
foreach ($listings as &$l) {
    $l['is_directory'] = $l['owner_email'] === DIRECTORY_EMAIL;
    $l['result'] = ($me && !$l['is_directory'] && $l['age']) ? compatibility_result($me, $l) : null;
    $l['is_match'] = in_array((int) $l['user_id'], $matchIds, true);
}
unset($l);
$groups = array_filter([
    'From your matches' => array_values(array_filter($listings, fn($l) => $l['is_match'])),
    'Rooms'             => array_values(array_filter($listings, fn($l) => !$l['is_match'])),
]);

// People (not listings) who have a flat in mind: comparing, shortlisted or chosen
$people = [];
$st = $pdo->prepare("SELECT u.id AS uid, u.name, p.* FROM profiles p JOIN users u ON u.id = p.user_id
                     WHERE u.role = 'user' AND p.flat_stage >= 3 AND (p.flat_area IS NOT NULL OR p.flat_rent IS NOT NULL)" . ($city !== '' ? ' AND p.city = ?' : '') . ($me ? ' AND u.id <> ?' : '') . ' ORDER BY p.flat_stage DESC LIMIT 12');
$st->execute(array_values(array_filter([$city !== '' ? $city : null, $me ? (int) $_SESSION['user_id'] : null])));
foreach ($st->fetchAll() as $p) { $p['result'] = $me ? compatibility_result($me, $p) : null; $people[] = $p; }

// Points for the map view: exact pins only for a flat's own page; here everything is approximate
$points = [];
foreach ($listings as $l) if ($l['lat'] !== null) {
    [$la, $ln] = approx_point((float) $l['lat'], (float) $l['lng']);
    $points[] = ['lat' => $la, 'lng' => $ln, 'price' => $l['rent'] ? rupees((int) $l['rent']) : 'Ask', 'title' => $l['title'], 'area' => $l['area'],
                 'who' => $l['is_directory'] ? $l['title'] : explode(' ', $l['owner'])[0], 'url' => url('listings/view.php?id=' . (int) $l['id'])];
}
foreach ($people as $p) if ($p['flat_lat'] !== null) {
    [$la, $ln] = approx_point((float) $p['flat_lat'], (float) $p['flat_lng']);
    $points[] = ['lat' => $la, 'lng' => $ln, 'price' => $p['flat_rent'] ? rupees((int) $p['flat_rent']) : '🏠', 'title' => $p['flat_desc'] ?: 'Flat in mind', 'area' => $p['flat_area'] ?? '',
                 'who' => explode(' ', $p['name'])[0], 'url' => url('profile/view_profile.php?id=' . (int) $p['uid'])];
}
$extraScripts = map_assets();

// My own listings (open or closed), so I can manage them
$mine = [];
if (is_logged_in() && !is_admin()) {
    $stmt = $pdo->prepare('SELECT * FROM listings WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $mine = $stmt->fetchAll();
}

$pageTitle = 'Flats';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <div>
            <h1>Rooms looking for a flatmate</h1>
            <p class="muted">Posted by people who already have a flat. Found a good one? Message the owner.</p>
        </div>
        <?php if (is_logged_in() && !is_admin()): ?>
            <a class="btn btn-primary" href="<?= e(url('listings/edit.php')) ?>">+ Post a room</a>
        <?php elseif (!is_logged_in()): ?>
            <a class="btn btn-primary" href="<?= e(url('auth/register.php')) ?>">Sign up to post a room</a>
        <?php endif; ?>
    </div>

    <?php if ($mine): ?>
        <section class="card my-listings" aria-labelledby="mine-title">
            <h2 id="mine-title">Your listings</h2>
            <ul class="request-list">
                <?php foreach ($mine as $l): ?>
                    <li class="request-item">
                        <div class="grow">
                            <a href="<?= e(url('listings/view.php?id=' . (int) $l['id'])) ?>"><strong><?= e($l['title']) ?></strong></a>
                            <div class="muted small"><?= e($l['area']) ?>, <?= e($l['city']) ?> · <?= $l['rent'] ? e(rupees((int) $l['rent'])) . '/month' : 'Rent on request' ?></div>
                        </div>
                        <span class="badge <?= $l['status'] === 'open' ? 'badge-open' : 'badge-user' ?>"><?= e($l['status']) ?></span>
                        <a class="btn btn-sm" href="<?= e(url('listings/edit.php?id=' . (int) $l['id'])) ?>">Edit</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <form class="toolbar card" method="get" action="" role="search">
        <label class="sr-only" for="q">Search by title or area</label>
        <input type="search" id="q" name="q" placeholder="Search title or area…" value="<?= e($q) ?>">
        <label class="sr-only" for="city">City</label>
        <select id="city" name="city"><option value="">All cities</option><?php foreach ($cities as $c): ?><option <?= $c === $city ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
        <label class="sr-only" for="max_rent">Max rent</label>
        <input type="number" id="max_rent" name="max_rent" min="1000" step="500" placeholder="Max rent ₹" value="<?= $maxRent ?? '' ?>">
        <label class="sr-only" for="room_type">Room type</label>
        <select id="room_type" name="room_type"><option value="">Any room</option><?php foreach (ROOM_TYPES as $k => $t): ?><option value="<?= $k ?>" <?= $k === $roomType ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
        <label class="sr-only" for="furnished">Furnished</label>
        <select id="furnished" name="furnished"><option value="">Furnished or not</option><option value="yes" <?= $furnished === 'yes' ? 'selected' : '' ?>>Furnished</option><option value="no" <?= $furnished === 'no' ? 'selected' : '' ?>>Unfurnished</option></select>
        <label class="sr-only" for="sort">Sort</label>
        <select id="sort" name="sort"><?php foreach ($sorts as $k => [, $t]): ?><option value="<?= $k ?>" <?= $k === $sort ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
        <?php if (is_logged_in() && !is_admin()): ?>
            <label class="check-inline"><input type="checkbox" name="matches" value="1" <?= $onlyMatches ? 'checked' : '' ?> onchange="this.form.submit()"> Only my matches<?= $matchIds ? ' (' . count($matchIds) . ')' : '' ?></label>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <?php if ($people): ?>
        <h2 class="section-title" style="margin-top:22px">People with a flat in mind</h2>
        <div class="people-strip">
            <?php foreach ($people as $p): [$fi, $fl] = FLAT_STAGES[(int) $p['flat_stage']]; ?>
                <a class="card person-flat" href="<?= e(url('profile/view_profile.php?id=' . (int) $p['uid'])) ?>">
                    <div class="poster"><img class="avatar avatar-sm" src="<?= e(photo_url($p['photo'])) ?>" alt=""><div><strong><?= e(explode(' ', $p['name'])[0]) ?>, <?= (int) $p['age'] ?></strong><div class="muted small"><?= e($p['college'] ?: $p['city']) ?></div></div>
                        <?php if ($p['result']): ?><span class="poster-score <?= score_class($p['result']['score']) ?>"><?= $p['result']['score'] === null ? '–' : $p['result']['score'] . '%' ?></span><?php endif; ?></div>
                    <span class="stage-pill"><?= $fi ?> <?= e($fl) ?></span>
                    <div class="small" style="margin-top:6px"><?= $p['flat_rent'] ? '<strong>' . e(rupees((int) $p['flat_rent'])) . '</strong>/month · ' : '' ?><?= e($p['flat_area'] ?? '') ?></div>
                    <?php if ($me && $p['flat_lat'] !== null && ($d = distance_note($me, $p['flat_lat'], $p['flat_lng']))): ?><div class="small muted"><?= $d ?></div><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="flats-head">
        <h2 class="section-title">Rooms</h2>
        <?php if (is_logged_in()): ?><div class="view-toggle" role="group" aria-label="View"><button type="button" data-fview="list" aria-pressed="true">List</button><button type="button" data-fview="map" aria-pressed="false">Map</button></div><?php endif; ?>
    </div>
    <?php if (is_logged_in()): ?><div id="flats-map" class="flats-map" hidden data-points="<?= e(json_encode($points)) ?>"></div><?php endif; ?>
    <p class="muted small flats-map-note" hidden>Pins show the approximate area only. Exact addresses are shared by the person once you connect.</p>

    <?php if (!$listings): ?>
        <div class="card empty-state">
            <div class="empty-icon"><?= house_icon() ?></div>
            <h2><?= $onlyMatches ? 'None of your matches has listed a room' : 'No rooms match that' ?></h2>
            <p><?= $onlyMatches ? 'Matches who are still looking for a place won\'t have one here. Untick "Only my matches" to see every room.' : 'Try fewer filters, or post a room yourself.' ?></p>
            <a class="btn" href="<?= e(url('listings/index.php')) ?>">Clear filters</a>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $groupTitle => $groupListings): ?>
        <?php if (count($groups) > 1 || $groupTitle !== 'Rooms'): ?><h3 class="group-title"><?= e($groupTitle) ?> <span class="count-pill"><?= count($groupListings) ?></span></h3><?php endif; ?>
        <div class="match-grid">
            <?php foreach ($groupListings as $l): ?>
                <article class="card listing-card">
                    <?php if (!$l['is_directory']): ?><div class="poster">
                            <img class="avatar avatar-sm" src="<?= e(photo_url($l['owner_photo'])) ?>" alt="">
                            <div><strong><a href="<?= e(url('profile/view_profile.php?id=' . (int) $l['user_id'])) ?>"><?= e(explode(' ', $l['owner'])[0]) ?></a></strong><?= $l['age'] ? ', ' . (int) $l['age'] : '' ?>
                                <div class="muted small"><?= $l['is_match'] ? '<span class="match-tag">Your match</span> · ' : '' ?>has a room to share</div></div>
                            <?php if ($l['result']): ?><span class="poster-score <?= score_class($l['result']['score']) ?>" title="Your score with <?= e(explode(' ', $l['owner'])[0]) ?>"><?= $l['result']['score'] === null ? '–' : $l['result']['score'] . '%' ?></span><?php endif; ?>
                    </div><?php endif; ?>
                    <?php $nophoto = '<span class="no-photo"><img class="avatar" src="' . e(photo_url($l['owner_photo'])) . '" alt=""><span>' . e(explode(' ', $l['owner'])[0]) . ' hasn\'t added room photos yet</span></span>'; ?>
                    <a class="listing-photo" data-fallback="<?= e($nophoto) ?>" href="<?= e(url('listings/view.php?id=' . (int) $l['id'])) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($l['photo']): ?><img src="<?= e(photo_url($l['photo'])) ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.parentNode.innerHTML=this.parentNode.dataset.fallback"><?php if (is_representative_photo($l['photo'])): ?><span class="photo-note">Representative photo</span><?php endif; ?><?php else: ?><?= $nophoto ?><?php endif; ?>
                    </a>
                    <div class="body">
                        <div class="listing-price"><?= $l['rent'] ? e(rupees((int) $l['rent'])) . '<small>/month</small>' : 'Rent on request' ?></div>
                        <h3><a href="<?= e(url('listings/view.php?id=' . (int) $l['id'])) ?>"><?= e($l['title']) ?></a></h3>
                        <div class="muted small"><?= e($l['area']) ?>, <?= e($l['city']) ?> · from <?= e(nice_date($l['available_from'])) ?></div>
                        <?php if ($me && $l['lat'] !== null && ($d = distance_note($me, $l['lat'], $l['lng']))): ?><div class="small"><?= $d ?></div><?php endif; ?>
                        <div class="tags">
                            <span class="tag"><?= e(ROOM_TYPES[$l['room_type']]) ?></span>
                            <span class="tag"><?= $l['furnished'] === 'yes' ? 'Furnished' : 'Unfurnished' ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$extraScripts .= <<<'HTML'
<script>
// List / Map toggle for Flats. The map is drawn the first time it's opened.
(function () {
    const box = document.getElementById('flats-map'), grid = document.querySelector('.match-grid, .flats-list'); let drawn = false;
    document.querySelectorAll('[data-fview]').forEach(function (b) {
        b.addEventListener('click', function () {
            const map = b.dataset.fview === 'map';
            document.querySelectorAll('[data-fview]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
            box.hidden = !map; document.querySelector('.flats-map-note').hidden = !map; if (grid) grid.hidden = map;
            if (map && !drawn && window.RMMap) {
                drawn = true;
                const pts = JSON.parse(box.dataset.points || '[]');
                const first = pts[0] || { lat: RMMap.INDORE[0], lng: RMMap.INDORE[1] };
                const m = RMMap.create(box, first.lat, first.lng, 11);
                const esc = function (t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
                pts.forEach(function (p) {
                    m.circle(p.lat, p.lng, 500);
                    m.marker(p.lat, p.lng, '<button type="button" class="price-pin">' + esc(p.price) + '</button>',
                        '<strong>' + esc(p.who) + '</strong><br>' + esc(p.title) + '<br><span style="color:#888">' + esc(p.area) + '</span><br><a href="' + p.url + '">Open →</a>');
                });
            }
        });
    });
})();
</script>
HTML;
require __DIR__ . '/../includes/footer.php'; ?>
