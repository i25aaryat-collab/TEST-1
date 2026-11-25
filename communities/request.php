<?php
/**
 * communities/request.php  —  Owner: M4
 * POST only, for open communities (is_public = 1):
 *   action=ask     (community_id, message?)   ask to join
 *   action=cancel  (community_id)             take my request back
 *   action=vote    (request_id, vote=1|0)     a member says yes or no
 * Someone gets in only when EVERY member has said yes; one "no" declines.
 * A declined request can be sent again after 7 days.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/community_helpers.php';

require_profile();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('communities/index.php');
check_csrf();
$myId = (int) $_SESSION['user_id'];
$action = (string) ($_POST['action'] ?? '');

if ($action === 'ask' || $action === 'cancel') {
    $cid = (int) ($_POST['community_id'] ?? 0);
    $s = $pdo->prepare('SELECT * FROM communities WHERE id = ? AND is_public = 1');
    $s->execute([$cid]); $c = $s->fetch();
    if (!$c) { set_flash('error', 'That community is not open to requests.'); redirect('communities/index.php'); }
    if (my_community($pdo, $cid, $myId)) redirect('communities/index.php?id=' . $cid);
    $mine = my_join_request($pdo, $cid, $myId);

    if ($action === 'cancel') {
        if ($mine && $mine['status'] === 'pending') $pdo->prepare('DELETE FROM community_requests WHERE id = ?')->execute([$mine['id']]);
        set_flash('success', 'Request withdrawn.');
        redirect('communities/index.php#find');
    }

    $message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 200) ?: null;
    if ($mine && $mine['status'] === 'pending') { set_flash('success', 'You have already asked. Members are still deciding.'); redirect('communities/index.php#find'); }
    if ($mine && $mine['status'] === 'declined' && strtotime($mine['decided_at']) > time() - 7 * 86400) {
        set_flash('error', 'You can ask ' . $c['name'] . ' again from ' . date('j M', strtotime($mine['decided_at']) + 7 * 86400) . '.');
        redirect('communities/index.php#find');
    }
    if ($mine) $pdo->prepare('DELETE FROM community_requests WHERE id = ?')->execute([$mine['id']]);   // old declined one: start over
    $pdo->prepare('INSERT INTO community_requests (community_id, user_id, message) VALUES (?, ?, ?)')->execute([$cid, $myId, $message]);
    set_flash('success', 'Request sent. You will be in once every member of ' . $c['name'] . ' says yes.');
    redirect('communities/index.php#find');
}

if ($action === 'vote') {
    $rid = (int) ($_POST['request_id'] ?? 0);
    $vote = ($_POST['vote'] ?? '') === '1' ? 1 : 0;
    $s = $pdo->prepare("SELECT r.*, c.name FROM community_requests r JOIN communities c ON c.id = r.community_id WHERE r.id = ? AND r.status = 'pending'");
    $s->execute([$rid]); $req = $s->fetch();
    if (!$req || !my_community($pdo, (int) $req['community_id'], $myId)) { set_flash('error', 'That request is no longer open.'); redirect('communities/index.php'); }
    try { $pdo->prepare('INSERT INTO community_votes (request_id, voter_id, vote) VALUES (?, ?, ?)')->execute([$rid, $myId, $vote]); }
    catch (PDOException $e) { if (!is_duplicate_error($e)) throw $e; }
    $status = settle_join_request($pdo, $rid);
    set_flash('success', match ($status) {
        'accepted' => 'Everyone said yes. They have joined ' . $req['name'] . '.',
        'declined' => 'Request declined.',
        default    => 'Thanks. They join once every member has said yes.',
    });
    redirect('communities/index.php?id=' . (int) $req['community_id'] . '#requests');
}

redirect('communities/index.php');
