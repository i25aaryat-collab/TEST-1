<?php
/**
 * listings/location_request.php  —  Owner: M5 (Listings)
 * POST only.
 *   action=request  (a seeker)  -> asks the owner for a flat's exact location
 *   action=approve / decline (the flat's owner, with request_id) -> answers it
 * After approval the seeker sees the exact pin and street photos (Mapillary) on the flat page.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/listing_helpers.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('listings/index.php');
check_csrf();

$me = (int) $_SESSION['user_id'];
$listingId = (int) ($_POST['listing_id'] ?? 0);
$back = ($_POST['back'] ?? '') === 'matches' ? 'swipe/matches.php#loc-title' : 'listings/view.php?id=' . $listingId;

$s = $pdo->prepare('SELECT id, user_id, title, status, loc_approx, lat FROM listings WHERE id = ?');
$s->execute([$listingId]);
$l = $s->fetch();
if (!$l) { set_flash('error', 'That flat no longer exists.'); redirect('listings/index.php'); }

$action = (string) ($_POST['action'] ?? '');

if ($action === 'request') {
    if ((int) $l['user_id'] === $me || is_admin()) redirect($back);
    if ($l['status'] !== 'open' || $l['lat'] === null) { set_flash('error', 'This flat has no exact location to share.'); redirect($back); }
    lr_db($pdo, function () use ($pdo, $listingId, $me) {
        try {
            $pdo->prepare('INSERT INTO location_requests (listing_id, requester_id) VALUES (?, ?)')->execute([$listingId, $me]);
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) throw $e;          // already asked: keep the existing answer
        }
    });
    set_flash('success', 'Request sent. You will see the exact spot here once the owner approves.');
    redirect($back);
}

if ($action === 'approve' || $action === 'decline') {
    if ((int) $l['user_id'] !== $me) { set_flash('error', 'Only the owner can answer location requests.'); redirect($back); }
    $requestId = (int) ($_POST['request_id'] ?? 0);
    lr_db($pdo, function () use ($pdo, $requestId, $listingId, $action) {
        $pdo->prepare("UPDATE location_requests SET status = ?, decided_at = CURRENT_TIMESTAMP WHERE id = ? AND listing_id = ? AND status = 'pending'")
            ->execute([$action === 'approve' ? 'approved' : 'declined', $requestId, $listingId]);
    });
    set_flash('success', $action === 'approve' ? 'Shared. They can now see the exact location.' : 'Request declined.');
    redirect($back);
}

redirect($back);
