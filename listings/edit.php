<?php
/* ==========================================================================
 * listings/edit.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * CREATE and UPDATE a room listing.
 *   edit.php        -> post a new room
 *   edit.php?id=3   -> edit your own listing (only the owner can)
 * Admins can add a room for any user (choosing the owner) and edit any listing.
 * Validated in JavaScript and again in PHP (validate_listing).
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/listing_helpers.php';
require_once __DIR__ . '/../profile/profile_helpers.php';   // photo upload helpers

$admin = is_admin();
if ($admin) require_admin(); else require_profile();    // owners need a profile so seekers can see who they'd live with

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$listing = null;
if ($id) {
    // Owner check: the row must belong to the logged-in user (an admin may edit any)
    $stmt = $pdo->prepare('SELECT * FROM listings WHERE id = ? AND (user_id = ? OR ?)');
    $stmt->execute([$id, $_SESSION['user_id'], $admin ? 1 : 0]);
    $listing = $stmt->fetch();
    if (!$listing) {
        set_flash('error', 'You can only edit your own listings.');
        redirect('listings/index.php');
    }
}

$values = $listing ? array_intersect_key($listing, listing_defaults()) : listing_defaults();
$values['rent'] = (string) $values['rent'];
$errors = [];

// Whose room it is: the logged-in user, or (admin) any user picked in the form
$ownerId = $listing ? (int) $listing['user_id'] : ($admin ? (int) ($_POST['owner_id'] ?? $_GET['owner'] ?? 0) : (int) $_SESSION['user_id']);
$owners = $admin ? $pdo->query("SELECT u.id, u.name, u.email FROM users u JOIN profiles p ON p.user_id = u.id WHERE u.role = 'user' ORDER BY u.name")->fetchAll() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $values = read_listing_input($_POST);
    $errors = validate_listing($values);
    if ($admin && !in_array($ownerId, array_map('intval', array_column($owners, 'id')), true)) $errors['owner_id'] = 'Pick whose room this is (they need a profile).';

    $photo = $listing['photo'] ?? null;
    if (!$errors) {
        $new = save_uploaded_photo($_FILES['photo'] ?? null, $ownerId, $errors);
        if ($new) { delete_photo_file($photo); $photo = $new; }
    }

    if (!$errors) {
        $fields = [$values['title'], $values['city'], $values['area'], (int) $values['rent'], $values['room_type'],
                   $values['furnished'], $values['available_from'], $values['description'], $photo];
        if ($listing) {
            $status = ($_POST['status'] ?? 'open') === 'closed' ? 'closed' : 'open';
            $pdo->prepare('UPDATE listings SET title = ?, city = ?, area = ?, rent = ?, room_type = ?, furnished = ?,
                           available_from = ?, description = ?, photo = ?, status = ? WHERE id = ? AND user_id = ?')
                ->execute(array_merge($fields, [$status, $id, $ownerId]));
            set_flash('success', 'Listing updated.');
        } else {
            $pdo->prepare('INSERT INTO listings (title, city, area, rent, room_type, furnished, available_from, description, photo, user_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge($fields, [$ownerId]));
            $id = (int) $pdo->lastInsertId();
            set_flash('success', $admin ? 'Property added.' : 'Your room is listed!');
        }
        // Map pin from the location picker (exact, chosen by the poster)
        $lat = $_POST['loc_lat'] ?? ''; $lng = $_POST['loc_lng'] ?? '';
        if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
            $pdo->prepare('UPDATE listings SET lat = ?, lng = ?, loc_approx = 0 WHERE id = ? AND user_id = ?')->execute([(float) $lat, (float) $lng, $id, $ownerId]);
        }
        redirect($admin ? 'admin/listings.php' : 'listings/view.php?id=' . $id);
    }
}

function lerr(array $errors, string $f): string
{
    return '<div class="field-error" data-error-for="' . $f . '">' . e($errors[$f] ?? '') . '</div>';
}
function linv(array $errors, string $f): string { return isset($errors[$f]) ? 'is-invalid' : ''; }

$pageTitle = $listing ? 'Edit listing' : 'Post a room';
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="page-head"><div><h1><?= $admin ? ($listing ? 'Edit property' : 'Add a property') : ($listing ? 'Edit your listing' : 'Post a room') ?></h1>
        <p class="muted">Tell flat-seekers what the room is like. Be specific: it gets you better messages.</p></div></div>

    <form id="listing-form" class="card form-card form-grid" method="post" action="" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_PHOTO_BYTES ?>">
        <?php if ($admin): ?>
            <div class="form-group">
                <label for="owner_id">Owner</label>
                <?php if ($listing): ?>
                    <?php foreach ($owners as $o) if ((int) $o['id'] === $ownerId) echo '<p><strong>' . e($o['name']) . '</strong> <span class="muted small">' . e($o['email']) . '</span></p>'; ?>
                <?php else: ?>
                    <select id="owner_id" name="owner_id" class="<?= linv($errors, 'owner_id') ?>" required>
                        <option value="">Choose whose room this is…</option>
                        <?php foreach ($owners as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $ownerId === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?> (<?= e($o['email']) ?>)</option><?php endforeach; ?>
                    </select>
                    <div class="hint">Every flat belongs to a person. Only users with a profile are listed.</div>
                <?php endif; ?>
                <?= lerr($errors, 'owner_id') ?>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" maxlength="100" value="<?= e($values['title']) ?>" class="<?= linv($errors, 'title') ?>" placeholder="e.g. Sunny room in a 2BHK near Vijay Nagar">
            <?= lerr($errors, 'title') ?>
        </div>
        <div class="row-2">
            <div class="form-group"><label for="city">City</label>
                <input type="text" id="city" name="city" maxlength="100" value="<?= e($values['city']) ?>" class="<?= linv($errors, 'city') ?>" autocomplete="address-level2"><?= lerr($errors, 'city') ?></div>
            <div class="form-group"><label for="area">Area / neighbourhood</label>
                <input type="text" id="area" name="area" maxlength="100" value="<?= e($values['area']) ?>" class="<?= linv($errors, 'area') ?>"><?= lerr($errors, 'area') ?></div>
        </div>
        <div class="row-2">
            <div class="form-group"><label for="rent">Rent for this room (₹ / month)</label>
                <input type="number" id="rent" name="rent" min="1000" max="200000" step="500" inputmode="numeric" value="<?= e($values['rent']) ?>" class="<?= linv($errors, 'rent') ?>"><?= lerr($errors, 'rent') ?></div>
            <div class="form-group"><label for="available_from">Available from</label>
                <input type="date" id="available_from" name="available_from" min="<?= date('Y-m-d') ?>" value="<?= e($values['available_from']) ?>" class="<?= linv($errors, 'available_from') ?>"><?= lerr($errors, 'available_from') ?></div>
        </div>
        <div class="row-2">
            <fieldset class="form-group"><legend>Room type</legend>
                <div class="pill-group <?= linv($errors, 'room_type') ?>" data-field="room_type" role="radiogroup">
                    <?php foreach (ROOM_TYPES as $k => $t): ?><label class="pill"><input type="radio" name="room_type" value="<?= $k ?>" <?= $values['room_type'] === $k ? 'checked' : '' ?>><span><?= $t ?></span></label><?php endforeach; ?>
                </div><?= lerr($errors, 'room_type') ?></fieldset>
            <fieldset class="form-group"><legend>Furnished?</legend>
                <div class="pill-group <?= linv($errors, 'furnished') ?>" data-field="furnished" role="radiogroup">
                    <?php foreach (['yes' => 'Yes', 'no' => 'No'] as $k => $t): ?><label class="pill"><input type="radio" name="furnished" value="<?= $k ?>" <?= $values['furnished'] === $k ? 'checked' : '' ?>><span><?= $t ?></span></label><?php endforeach; ?>
                </div><?= lerr($errors, 'furnished') ?></fieldset>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="<?= linv($errors, 'description') ?>" placeholder="The room, the flat, who lives there, house rules…"><?= e($values['description']) ?></textarea>
            <div class="char-count" id="desc-count"></div><?= lerr($errors, 'description') ?>
        </div>
        <div class="form-group">
            <label>Location on the map <span class="muted">(people who haven't connected with you only see the rough area)</span></label>
            <?= location_picker('loc', $listing && !$listing['loc_approx'] ? ($listing['lat'] ?? '') : '', $listing && !$listing['loc_approx'] ? ($listing['lng'] ?? '') : '', 'Set the flat\'s location') ?>
        </div>
        <div class="form-group">
            <label for="photo">Photo <span class="muted">(optional, JPG or PNG up to 2 MB)</span></label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" class="<?= linv($errors, 'photo') ?>"><?= lerr($errors, 'photo') ?>
        </div>
        <?php if ($listing): ?>
            <fieldset class="form-group"><legend>Status</legend>
                <div class="pill-group" role="radiogroup">
                    <label class="pill"><input type="radio" name="status" value="open" <?= $listing['status'] === 'open' ? 'checked' : '' ?>><span>Open</span></label>
                    <label class="pill"><input type="radio" name="status" value="closed" <?= $listing['status'] === 'closed' ? 'checked' : '' ?>><span>Closed (room taken)</span></label>
                </div></fieldset>
        <?php endif; ?>
        <div class="form-actions">
            <a class="btn" href="<?= e(url($listing ? 'listings/view.php?id=' . $id : 'listings/index.php')) ?>">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= $listing ? 'Save changes' : ($admin ? 'Add property' : 'Post room') ?></button>
        </div>
    </form>

    <?php if ($listing): ?>
        <form class="card danger-zone" method="post" action="<?= e(url('listings/delete.php')) ?>" data-confirm="Delete this listing for good?" data-confirm-button="Delete listing">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
            <h2>Delete listing</h2><p class="muted">Removes it from Flats. This cannot be undone.</p>
            <button type="submit" class="btn btn-danger">Delete listing</button>
        </form>
    <?php endif; ?>
</div>

<?php
$extraScripts = ($extraScripts ?? '') . <<<'HTML'
<script>
// Same rules as validate_listing() in listing_helpers.php
const lf = document.getElementById('listing-form');
RM.validateForm(lf, function (f) {
    const e = {}, title = f.title.value.trim(), desc = f.description.value.trim();
    if (title.length < 5 || title.length > 100) e.title = 'Title must be 5 to 100 characters.';
    if (!/^[A-Za-z ]{2,100}$/.test(f.city.value.trim())) e.city = 'City: letters and spaces only.';
    if (f.area.value.trim().length < 2) e.area = 'Area must be 2 to 100 characters.';
    if (!RM.isIntInRange(f.rent.value, 1000, 200000)) e.rent = 'Rent must be a whole number of rupees from 1,000 to 2,00,000.';
    if (!['private', 'shared'].includes(RM.radioValue(f, 'room_type'))) e.room_type = 'Choose private or shared.';
    if (!['yes', 'no'].includes(RM.radioValue(f, 'furnished'))) e.furnished = 'Tell us if it is furnished.';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(f.available_from.value)) e.available_from = 'Pick a valid date.';
    if (desc.length < 20 || desc.length > 1000) e.description = 'Description must be 20 to 1,000 characters.';
    const file = f.photo.files[0];
    if (file && !['image/jpeg', 'image/png'].includes(file.type)) e.photo = 'Only JPG or PNG images are allowed.';
    else if (file && file.size > 2 * 1024 * 1024) e.photo = 'Photo must be 2 MB or smaller.';
    return e;
});
RM.charCounter(lf.description, document.getElementById('desc-count'), 1000);
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
