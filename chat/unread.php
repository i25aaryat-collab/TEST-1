<?php
/* ==========================================================================
 * chat/unread.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * JSON for the red badges in the menu, asked for every few seconds by the
 * footer script:  {"unread": 3, "requests": 1}
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!is_logged_in() || is_admin()) {
    exit(json_encode(['unread' => 0, 'requests' => 0]));
}
$myId = (int) $_SESSION['user_id'];
echo json_encode(notification_counts($pdo, $myId));
