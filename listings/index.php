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

$cities = $pdo->query("SELECT DISTINCT city FROM listings WHERE status = 'open' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$q        = trim($_GET['q'] ?? '');
$city     = in_array($_GET['city'] ?? '', $cities, true) ? $_GET['city'] : '';
$maxRent  = isset($_GET['max_rent']) && ctype_digit($_GET['max_rent']) ? (int) $_GET['max_rent'] : null;
$roomType = array_key_exists($_GET['room_type'] ?? '', ROOM_TYPES) ? $_GET['room_type'] : '';
$furnished = in_array($_GET['furnished'] ?? '', ['yes', 'no'], true) ? $_GET['furnished'] : '';
$sorts = ['new' => ['l.created_at DESC', 'Newest first'], 'cheap' => ['l.rent ASC', 'Cheapest first'], 'soon' => ['l.available_from ASC', 'Available soonest']];
$sort  = array_key_exists($_GET['sort'] ?? '', $sorts) ? $_GET['sort'] : 'new';

$sql = "SELECT l.*, u.name AS owner FROM listings l JOIN users u ON u.id = l.user_id WHERE l.status = 'open'";
$params = [];
if ($q !== '')          { $sql .= ' AND (LOWER(l.title) LIKE LOWER(?) OR LOWER(l.area) LIKE LOWER(?))'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($city !== '')       { $sql .= ' AND l.city = ?'; $params[] = $city; }
if ($maxRent !== null)  { $sql .= ' AND l.rent <= ?'; $params[] = $maxRent; }
if ($roomType !== '')   { $sql .= ' AND l.room_type = ?'; $params[] = $roomType; }
if ($furnished !== '')  { $sql .= ' AND l.furnished = ?'; $params[] = $furnished; }
$sql .= ' ORDER BY ' . $sorts[$sort][0];             // only fixed, known ORDER BY strings
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

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
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <?php if (!$listings): ?>
        <div class="card empty-state">
            <div class="empty-icon">🏠</div>
            <h2>No rooms match that</h2>
            <p>Try fewer filters, or post a room yourself.</p>
            <a class="btn" href="<?= e(url('listings/index.php')) ?>">Clear filters</a>
        </div>
    <?php else: ?>
        <div class="match-grid">
            <?php foreach ($listings as $l): ?>
                <article class="card listing-card">
                    <a class="listing-photo" href="<?= e(url('listings/view.php?id=' . (int) $l['id'])) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($l['photo']): ?><img src="<?= e(photo_url($l['photo'])) ?>" alt="" loading="lazy"><?php else: ?><span>🏠</span><?php endif; ?>
                    </a>
                    <div class="body">
                        <div class="listing-price"><?= $l['rent'] ? e(rupees((int) $l['rent'])) . '<small>/month</small>' : 'Rent on request' ?></div>
                        <h3><a href="<?= e(url('listings/view.php?id=' . (int) $l['id'])) ?>"><?= e($l['title']) ?></a></h3>
                        <div class="muted small"><?= e($l['area']) ?>, <?= e($l['city']) ?> · from <?= e(nice_date($l['available_from'])) ?></div>
                        <div class="tags">
                            <span class="tag"><?= e(ROOM_TYPES[$l['room_type']]) ?></span>
                            <span class="tag"><?= $l['furnished'] === 'yes' ? 'Furnished' : 'Unfurnished' ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
