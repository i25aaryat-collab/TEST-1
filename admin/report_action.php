<?php
/* ==========================================================================
 * admin/report_action.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Admin only, POST only. action=dismiss closes the report; action=remove
 * deletes the reported message / listing / user, then closes every open
 * report about the same thing.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../profile/profile_helpers.php';

require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/reports.php');
check_csrf();

$stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ? AND status = 'open'");
$stmt->execute([(int) ($_POST['id'] ?? 0)]);
$report = $stmt->fetch();
if (!$report) {
    set_flash('error', 'That report was already handled.');
    redirect('admin/reports.php');
}

if (($_POST['action'] ?? '') === 'dismiss') {
    $pdo->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ?")->execute([$report['id']]);
    set_flash('success', 'Report dismissed.');
    redirect('admin/reports.php');
}

// Remove the content
$id = (int) $report['target_id'];
switch ($report['target_type']) {
    case 'message':
        $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
        break;
    case 'listing':
        $p = $pdo->prepare('SELECT photo FROM listings WHERE id = ?'); $p->execute([$id]);
        delete_photo_file($p->fetchColumn() ?: null);
        $pdo->prepare('DELETE FROM listings WHERE id = ?')->execute([$id]);
        break;
    case 'user':
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'")->execute([$id]);   // admins are protected
        break;
}
$pdo->prepare("UPDATE reports SET status = 'removed' WHERE target_type = ? AND target_id = ? AND status = 'open'")
    ->execute([$report['target_type'], $id]);
set_flash('success', ucfirst($report['target_type']) . ' removed.');
redirect('admin/reports.php');
