<?php
/* ==========================================================================
 * listings/delete.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * DELETE a listing (POST only). The owner can delete their own; an admin
 * can delete any listing (moderation).
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../profile/profile_helpers.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('listings/index.php');
check_csrf();

$stmt = $pdo->prepare('SELECT id, user_id, photo FROM listings WHERE id = ?');
$stmt->execute([(int) ($_POST['id'] ?? 0)]);
$listing = $stmt->fetch();

if (!$listing || ((int) $listing['user_id'] !== (int) $_SESSION['user_id'] && !is_admin())) {
    set_flash('error', 'You can only delete your own listings.');
} else {
    $pdo->prepare('DELETE FROM listings WHERE id = ?')->execute([$listing['id']]);
    delete_photo_file($listing['photo']);
    set_flash('success', 'Listing deleted.');
}
redirect(is_admin() ? 'admin/reports.php' : 'listings/index.php');
