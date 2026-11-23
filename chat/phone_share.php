<?php
/**
 * chat/phone_share.php  —  Owner: M5 (Chat)
 * POST: share or stop sharing MY phone number with the other person in a match.
 * One direction only: a row (me -> them) lets them see my number; it never
 * reveals theirs to me. Off by default; nothing happens without this POST.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('chat/inbox.php');
check_csrf();

$myId = (int) $_SESSION['user_id'];
$matchId = (int) ($_POST['match_id'] ?? 0);
$match = find_my_match($pdo, $matchId, $myId);           // must be MY match
if (!$match) { set_flash('error', 'That chat does not exist or is not yours.'); redirect('chat/inbox.php'); }
$back = 'chat/conversation.php?match_id=' . $matchId;

if (($_POST['action'] ?? '') === 'unshare') {
    $pdo->prepare('DELETE FROM phone_shares WHERE sharer_id = ? AND viewer_id = ?')->execute([$myId, $match['other_id']]);
    set_flash('success', 'Your number is no longer shared.');
    redirect($back);
}

// Saving a number first if the profile has none
$phone = preg_replace('/[\s-]/', '', (string) ($_POST['phone'] ?? ''));
if ($phone !== '') {
    if (!preg_match('/^(\+91)?[6-9][0-9]{9}$/', $phone)) { set_flash('error', 'Enter a 10-digit Indian mobile number.'); redirect($back); }
    $pdo->prepare('UPDATE profiles SET phone = ? WHERE user_id = ?')->execute([$phone, $myId]);
}
$has = $pdo->prepare("SELECT phone FROM profiles WHERE user_id = ? AND phone IS NOT NULL AND phone <> ''");
$has->execute([$myId]);
if (!$has->fetchColumn()) { set_flash('error', 'Add your phone number first.'); redirect($back); }

try {
    $pdo->prepare('INSERT INTO phone_shares (sharer_id, viewer_id) VALUES (?, ?)')->execute([$myId, $match['other_id']]);
} catch (PDOException $e) {
    if (!is_duplicate_error($e)) throw $e;                // already shared: fine
}
set_flash('success', 'Shared your number with ' . explode(' ', $match['other_name'])[0] . '.');
redirect($back);
