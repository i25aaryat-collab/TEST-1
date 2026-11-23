<?php
/**
 * chat/typing.php  —  Owner: M5 (Chat)
 * POST match_id: "I'm typing". The other person's poll shows "… is typing" for 6 seconds.
 * Sent at most every 3 seconds while someone types.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

header('Content-Type: application/json');
require_login();
check_csrf(true);
$myId = (int) $_SESSION['user_id'];
$matchId = (int) ($_POST['match_id'] ?? 0);
if (!find_my_match($pdo, $matchId, $myId)) exit(json_encode(['ok' => false]));
$pdo->prepare('DELETE FROM chat_typing WHERE match_id = ? AND user_id = ?')->execute([$matchId, $myId]);
$pdo->prepare('INSERT INTO chat_typing (match_id, user_id) VALUES (?, ?)')->execute([$matchId, $myId]);
echo json_encode(['ok' => true]);
