<?php
/**
 * chat/contact_share.php  —  Owner: M5 (Chat)
 * POST: set which of MY contact details (phone, email, Instagram) the other
 * person in a match can see. One row per field per direction; anything not
 * ticked is removed (that's how you revoke). Never reveals their details to me.
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

// Only fields I actually filled in can be shared
$p = $pdo->prepare('SELECT phone, contact_email, instagram FROM profiles WHERE user_id = ?');
$p->execute([$myId]);
$mine = $p->fetch() ?: [];
$have = array_keys(array_filter(['phone' => $mine['phone'] ?? '', 'email' => $mine['contact_email'] ?? '', 'instagram' => $mine['instagram'] ?? '']));
$want = array_values(array_intersect(array_map('strval', (array) ($_POST['fields'] ?? [])), $have));

$pdo->beginTransaction();
$pdo->prepare('DELETE FROM contact_shares WHERE sharer_id = ? AND viewer_id = ?')->execute([$myId, $match['other_id']]);
$add = $pdo->prepare('INSERT INTO contact_shares (sharer_id, viewer_id, field) VALUES (?, ?, ?)');
foreach ($want as $f) $add->execute([$myId, $match['other_id'], $f]);
$pdo->commit();

$first = explode(' ', $match['other_name'])[0];
set_flash('success', $want ? 'Now sharing your ' . implode(', ', $want) . ' with ' . $first . '.' : 'You are not sharing any contact details with ' . $first . '.');
redirect('chat/conversation.php?match_id=' . $matchId);
