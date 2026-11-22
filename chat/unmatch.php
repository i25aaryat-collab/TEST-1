<?php
/* ==========================================================================
 * chat/unmatch.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Deletes a match (POST only). The messages table's foreign key has
 * ON DELETE CASCADE, so every message in the chat is deleted with it.
 * The swipes stay, so the two people don't reappear on each other's
 * swipe page.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('chat/inbox.php');
}
check_csrf();

$myId  = (int) $_SESSION['user_id'];
$match = find_my_match($pdo, (int) ($_POST['match_id'] ?? 0), $myId);

if (!$match) {
    set_flash('error', 'That match does not exist or is not yours.');
    redirect('chat/inbox.php');
}

$pdo->prepare('DELETE FROM matches WHERE id = ?')->execute([$match['id']]);
set_flash('success', 'You unmatched ' . $match['other_name'] . '. The chat has been deleted.');
redirect('chat/inbox.php');
