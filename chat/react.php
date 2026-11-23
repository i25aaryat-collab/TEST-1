<?php
/**
 * chat/react.php  —  Owner: M5 (Chat)
 * POST match_id, message_id, emoji: react to a message in MY chat.
 * One reaction per person per message; sending the same emoji again removes it.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

header('Content-Type: application/json');
require_login();
check_csrf(true);
$myId = (int) $_SESSION['user_id'];
$matchId = (int) ($_POST['match_id'] ?? 0);
$messageId = (int) ($_POST['message_id'] ?? 0);
$emoji = (string) ($_POST['emoji'] ?? '');
if (!find_my_match($pdo, $matchId, $myId) || !in_array($emoji, REACTIONS, true)) exit(json_encode(['ok' => false]));
$m = $pdo->prepare('SELECT 1 FROM messages WHERE id = ? AND match_id = ?');
$m->execute([$messageId, $matchId]);
if (!$m->fetchColumn()) exit(json_encode(['ok' => false]));

$cur = $pdo->prepare('SELECT emoji FROM message_reactions WHERE message_id = ? AND user_id = ?');
$cur->execute([$messageId, $myId]);
$old = $cur->fetchColumn();
$pdo->prepare('DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?')->execute([$messageId, $myId]);
if ($old !== $emoji) $pdo->prepare('INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)')->execute([$messageId, $myId, $emoji]);
echo json_encode(['ok' => true]);
