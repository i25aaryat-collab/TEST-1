<?php
/* ==========================================================================
 * admin/delete_user.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Admin deletes a user (POST only, after a confirmation box).
 * Admin accounts are protected and cannot be deleted here.
 * CASCADE removes the user's profile, swipes, matches and messages.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../profile/profile_helpers.php';   // delete_photo_file()

require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/dashboard.php');
}
check_csrf();

$userId = (int) ($_POST['user_id'] ?? 0);

$stmt = $pdo->prepare('SELECT u.name, u.role, p.photo FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'That user does not exist (maybe already deleted).');
} elseif ($user['role'] === 'admin') {
    set_flash('error', 'Admin accounts cannot be deleted.');
} else {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    delete_photo_file($user['photo']);
    set_flash('success', $user['name'] . ' was deleted, along with all their data.');
}
redirect('admin/dashboard.php');
