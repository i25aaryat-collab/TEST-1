<?php
/* ==========================================================================
 * listings/view.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * READ one listing. Shows the room, the owner, and (for a logged-in seeker)
 * the compatibility score with the owner plus a "Message the owner" button:
 * that sends a request, and chat opens once the owner accepts.
 * Anyone logged in can report a listing to the admins.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/listing_helpers.php';
require_once __DIR__ . '/../swipe/compatibility.php';

$stmt = $pdo->prepare("SELECT l.*, u.name AS owner, p.photo AS owner_photo, p.age AS owner_age
                       FROM listings l JOIN users u ON u.id = l.user_id LEFT JOIN profiles p ON p.user_id = u.id
                       WHERE l.id = ? AND u.email <> 'listings@roomiematch.test'");   // directory flats (no person) are not shown
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$l = $stmt->fetch();
$isOwner = $l && is_logged_in() && (int) $l['user_id'] === (int) $_SESSION['user_id'];
if (!$l || ($l['status'] !== 'open' && !$isOwner && !is_admin())) {
    set_flash('error', 'That listing is no longer available.');
    redirect('listings/index.php');
}

// For a logged-in seeker: score with the owner, and request / match state
$score = null; $state = 'none'; $matchId = null;
if (is_logged_in() && !is_admin() && !$isOwner) {
    $me = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $me->execute([$_SESSION['user_id']]); $me = $me->fetch();
    $owner = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $owner->execute([$l['user_id']]); $owner = $owner->fetch();
    if ($me && $owner) $score = compatibility_score($me, $owner);

    $a = min((int) $_SESSION['user_id'], (int) $l['user_id']); $b = max((int) $_SESSION['user_id'], (int) $l['user_id']);
    $m = $pdo->prepare('SELECT id FROM matches WHERE user1_id = ? AND user2_id = ?');
    $m->execute([$a, $b]);
    if ($matchId = $m->fetchColumn()) {
        $state = 'matched';
    } else {
        $s = $pdo->prepare("SELECT 1 FROM swipes WHERE swiper_id = ? AND swiped_id = ? AND direction = 'like'");
        $s->execute([$_SESSION['user_id'], $l['user_id']]);
        if ($s->fetch()) $state = 'requested';
    }
}

$pageTitle = $l['title'];
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <article class="card listing-view">
        <div class="listing-hero"><?php if ($l['photo']): ?><span class="hero-blur" aria-hidden="true" style="background-image:url('<?= e(photo_url($l['photo'])) ?>')"></span><img src="<?= e(photo_url($l['photo'])) ?>" alt="<?= is_representative_photo($l['photo']) ? 'Representative photo, not the actual room' : 'Photo of the room' ?>" referrerpolicy="no-referrer" loading="lazy"><?php if (is_representative_photo($l['photo'])): ?><span class="photo-note">Representative photo</span><?php endif; ?><?php else: ?><span class="no-photo"><img class="avatar" src="<?= e(photo_url($l['owner_photo'])) ?>" alt=""><span><?= e(explode(' ', $l['owner'])[0]) ?> hasn't added room photos yet</span></span><?php endif; ?></div>
        <div class="listing-price big"><?= $l['rent'] ? e(rupees((int) $l['rent'])) . '<small>/month</small>' : 'Rent on request' ?></div>
        <h1><?= e($l['title']) ?></h1>
        <p class="muted"><?= e($l['area']) ?>, <?= e($l['city']) ?> · available from <?= e(nice_date($l['available_from'])) ?><?= $l['status'] === 'closed' ? ' · <strong>Closed</strong>' : '' ?></p>
        <div class="tags"><span class="tag"><?= e(ROOM_TYPES[$l['room_type']]) ?></span><span class="tag"><?= $l['furnished'] === 'yes' ? 'Furnished' : 'Unfurnished' ?></span></div>
        <p style="margin-top:16px"><?= nl2br(e($l['description'])) ?></p>
        <?php if ($l['lat'] !== null):
            $exact = !$l['loc_approx'] && ($isOwner || $state === 'matched');
            [$mlat, $mlng] = $exact ? [(float) $l['lat'], (float) $l['lng']] : approx_point((float) $l['lat'], (float) $l['lng']);
            $extraScripts = ($extraScripts ?? '') . map_assets(); ?>
            <div class="listing-map" id="listing-map" data-lat="<?= $mlat ?>" data-lng="<?= $mlng ?>" data-exact="<?= $exact ? 1 : 0 ?>"></div>
            <p class="muted small"><?= $exact ? 'Exact location' : 'Approximate area' . ($l['loc_approx'] ? '' : '. The exact spot shows once you connect with the poster.') ?>
                <?php if (!empty($me) && ($dn = distance_note($me, $l['lat'], $l['lng']))): ?> · <?= $dn ?><?php endif; ?></p>
        <?php endif; ?>

        <div class="owner-row">
            <img class="avatar" src="<?= e(photo_url($l['owner_photo'])) ?>" alt="">
            <div class="grow"><strong>Posted by <?= e(explode(' ', $l['owner'])[0]) ?></strong><div class="muted small">on <?= e(nice_date($l['created_at'])) ?></div></div>
            <?php if ($score !== null): ?><?= score_ring($score) ?><?php endif; ?>
        </div>

        <div class="btn-row" style="margin-top:16px">
            <?php if ($isOwner): ?>
                <a class="btn btn-primary" href="<?= e(url('listings/edit.php?id=' . (int) $l['id'])) ?>">Edit listing</a>
            <?php elseif (!is_logged_in()): ?>
                <a class="btn btn-primary" href="<?= e(url('auth/login.php')) ?>">Log in to message the owner</a>
            <?php elseif ($state === 'matched'): ?>
                <a class="btn btn-primary" href="<?= e(url('chat/conversation.php?match_id=' . (int) $matchId)) ?>">Open chat</a>
            <?php elseif ($state === 'requested'): ?>
                <button class="btn" disabled>Request sent ✓</button>
            <?php elseif (!is_admin()): ?>
                <button type="button" class="btn btn-primary" id="contact" data-user-id="<?= (int) $l['user_id'] ?>">Message the owner</button>
            <?php endif; ?>
            <?php if (is_logged_in() && !is_admin()):
                require_once __DIR__ . '/../groups/group_helpers.php';
                $myGroups = my_groups($pdo, (int) $_SESSION['user_id']); ?>
                <?php if ($myGroups): ?>
                    <form method="post" action="<?= e(url('groups/action.php')) ?>" class="inline-form"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="add"><input type="hidden" name="from_listing" value="1"><input type="hidden" name="listing_id" value="<?= (int) $l['id'] ?>">
                        <select name="group_id" aria-label="Shortlist"><?php foreach ($myGroups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select>
                        <button class="btn">Save to shortlist</button>
                    </form>
                <?php else: ?>
                    <a class="btn" href="<?= e(url('groups/index.php')) ?>">Start a flat shortlist</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (is_logged_in() && !$isOwner && !is_admin()): ?>
                <button type="button" class="btn btn-ghost" id="report">Report listing</button>
            <?php endif; ?>
            <?php if (is_admin()): ?>
                <form method="post" action="<?= e(url('listings/delete.php')) ?>" data-confirm="Remove this listing?" data-confirm-button="Remove">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn btn-danger">Remove listing</button>
                </form>
            <?php endif; ?>
        </div>
    </article>
</div>

<?php
$actionUrl = json_encode(url('swipe/swipe_action.php'));
$reportUrl = json_encode(url('report.php'));
$chatUrl   = json_encode(url('chat/conversation.php?match_id='));
$listingId = (int) $l['id'];
$extraScripts = ($extraScripts ?? '') . <<<HTML
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const contact = document.getElementById('contact'), report = document.getElementById('report');
// "Message the owner" sends a request; chat opens when the owner accepts (or at once if they already asked you)
if (contact) contact.addEventListener('click', function () {
    contact.disabled = true;
    fetch($actionUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ swiped_id: parseInt(contact.dataset.userId, 10), direction: 'like' }) })
    .then(function (r) { return r.json(); }).then(function (d) {
        if (d.matched) { window.location.href = $chatUrl + d.match_id; return; }
        if (!d.ok) { contact.disabled = false; RM.toast(d.error || 'Something went wrong.', 'error'); return; }
        contact.textContent = 'Request sent ✓';
        RM.toast('Request sent. Chat opens when the owner accepts.', 'success');
    });
});
if (report) report.addEventListener('click', function () {
    RM.promptBox('Report this listing', 'What is wrong with it? Our admins will review it.', 'Report').then(function (reason) {
        if (reason === null) return;
        const body = new FormData();
        body.append('csrf_token', CSRF); body.append('target_type', 'listing'); body.append('target_id', '$listingId'); body.append('reason', reason);
        fetch($reportUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); }).then(function (d) { RM.toast(d.message, d.ok ? 'success' : 'error'); });
    });
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
