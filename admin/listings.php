<?php
/* ==========================================================================
 * admin/listings.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Admin only. Every property on the site, with search (title, area, owner),
 * city and status filters, and sorting.
 *   CREATE  "Add a property" -> listings/edit.php (admin picks the owner)
 *   READ    this table, and each flat's own page
 *   UPDATE  Edit -> listings/edit.php?id=…, or Open / Close right here
 *   DELETE  Delete -> listings/delete.php (after a confirmation box)
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_admin();

// Open / close straight from the table (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $status = ($_POST['status'] ?? '') === 'closed' ? 'closed' : 'open';
    $pdo->prepare('UPDATE listings SET status = ? WHERE id = ?')->execute([$status, (int) ($_POST['id'] ?? 0)]);
    set_flash('success', $status === 'open' ? 'Property reopened.' : 'Property closed. It no longer shows in Flats.');
    redirect('admin/listings.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'city', 'status', 'sort']))));
}

$search = trim($_GET['q'] ?? '');
$city   = trim($_GET['city'] ?? '');
$status = in_array($_GET['status'] ?? '', ['open', 'closed'], true) ? $_GET['status'] : '';
$sorts = [
    'new'       => ['l.created_at DESC', 'Newest first'],
    'old'       => ['l.created_at ASC',  'Oldest first'],
    'rent_asc'  => ['l.rent ASC',        'Rent: low to high'],
    'rent_desc' => ['l.rent DESC',       'Rent: high to low'],
];
$sort = array_key_exists($_GET['sort'] ?? '', $sorts) ? $_GET['sort'] : 'new';

$sql = "SELECT l.id, l.title, l.city, l.area, l.rent, l.status, l.created_at, l.photo, l.loc_approx,
               u.id AS owner_id, u.name AS owner, u.email
        FROM listings l JOIN users u ON u.id = l.user_id WHERE 1 = 1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (LOWER(l.title) LIKE LOWER(?) OR LOWER(l.area) LIKE LOWER(?) OR LOWER(u.name) LIKE LOWER(?))';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
if ($city !== '')   { $sql .= ' AND l.city = ?';   $params[] = $city; }
if ($status !== '') { $sql .= ' AND l.status = ?'; $params[] = $status; }
$sql .= ' ORDER BY ' . $sorts[$sort][0];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();
$cities = $pdo->query('SELECT DISTINCT city FROM listings ORDER BY city')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Properties';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <div><h1>Properties</h1><p class="muted">Every flat on RoomieMatch. Each one belongs to a person.</p></div>
        <a class="btn btn-primary" href="<?= e(url('listings/edit.php')) ?>">Add a property</a>
    </div>

    <div class="card">
        <form class="toolbar" method="get" action="" role="search">
            <label class="sr-only" for="q">Search</label>
            <input type="search" id="q" name="q" placeholder="Search title, area or owner…" value="<?= e($search) ?>">
            <label class="sr-only" for="city">City</label>
            <select id="city" name="city"><option value="">All cities</option>
                <?php foreach ($cities as $c): ?><option <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
            <label class="sr-only" for="status">Status</label>
            <select id="status" name="status"><option value="">Open and closed</option>
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option></select>
            <label class="sr-only" for="sort">Sort</label>
            <select id="sort" name="sort"><?php foreach ($sorts as $k => [, $label]): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <button type="submit" class="btn btn-primary">Apply</button>
            <?php if ($search !== '' || $city !== '' || $status !== '' || $sort !== 'new'): ?><a class="btn" href="<?= e(url('admin/listings.php')) ?>">Clear</a><?php endif; ?>
        </form>

        <p class="muted small">Showing <?= count($listings) ?> <?= count($listings) === 1 ? 'property' : 'properties' ?>.</p>

        <?php if (!$listings): ?>
            <div class="empty-state"><h2>No properties found</h2><p>Try a different search, or add one.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Property</th><th>Owner</th><th>City</th><th>Rent</th><th>Status</th><th>Added</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($listings as $l): ?>
                        <tr>
                            <td><div class="user-cell"><img class="avatar thumb-sq" src="<?= e(photo_url($l['photo'])) ?>" alt="">
                                <div><a href="<?= e(url('listings/view.php?id=' . (int) $l['id'])) ?>"><?= e($l['title']) ?></a>
                                <div class="muted small"><?= e($l['area']) ?><?= $l['loc_approx'] ? '' : ' · exact location' ?></div></div></div></td>
                            <td><a href="<?= e(url('admin/user_form.php?id=' . (int) $l['owner_id'])) ?>"><?= e($l['owner']) ?></a></td>
                            <td><?= e($l['city']) ?></td>
                            <td><?= e(rupees((int) $l['rent'])) ?></td>
                            <td><span class="badge badge-<?= $l['status'] === 'open' ? 'user' : 'admin' ?>"><?= e($l['status']) ?></span></td>
                            <td><?= e(nice_date($l['created_at'])) ?></td>
                            <td><div class="row-actions">
                                <a class="btn btn-sm" href="<?= e(url('listings/edit.php?id=' . (int) $l['id'])) ?>">Edit</a>
                                <form method="post" action=""><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" name="status" value="<?= $l['status'] === 'open' ? 'closed' : 'open' ?>"><?= $l['status'] === 'open' ? 'Close' : 'Reopen' ?></button></form>
                                <form method="post" action="<?= e(url('listings/delete.php')) ?>" data-confirm="Delete &quot;<?= e($l['title']) ?>&quot;? This can't be undone." data-confirm-button="Delete property">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                    <button class="btn btn-danger btn-sm">Delete</button></form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
