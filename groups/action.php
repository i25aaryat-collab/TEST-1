<?php
/**
 * groups/action.php  —  Owner: M4
 * All household changes (POST only): create, add_member, add (listing), remove, poll, vote, settle, unsettle.
 * A vote is one row per (group, person): changing it replaces the old one, so it is never counted twice.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/group_helpers.php';

require_profile();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('groups/index.php');
check_csrf();
$myId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $connections = my_connections($pdo, $myId);
    $members = array_values(array_intersect(array_map('intval', (array) ($_POST['members'] ?? [])), array_keys($connections)));
    if ($name === '' || mb_strlen($name) > 60) { set_flash('error', 'Give the household a name (up to 60 characters).'); redirect('groups/index.php'); }
    if (!$members) { set_flash('error', 'Pick at least one person you are connected with.'); redirect('groups/index.php'); }
    $pdo->beginTransaction();
    if (DB_DRIVER === 'pgsql') {
        $s = $pdo->prepare('INSERT INTO flat_groups (name, created_by) VALUES (?, ?) RETURNING id'); $s->execute([$name, $myId]); $gid = (int) $s->fetchColumn();
    } else {
        $pdo->prepare('INSERT INTO flat_groups (name, created_by) VALUES (?, ?)')->execute([$name, $myId]); $gid = (int) $pdo->lastInsertId();
    }
    $add = $pdo->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)');
    foreach (array_unique(array_merge([$myId], $members)) as $uid) $add->execute([$gid, $uid]);
    $pdo->commit();
    set_flash('success', 'Household created. Save flats from any flat page.');
    redirect('groups/view.php?id=' . $gid);
}

$gid = (int) ($_POST['group_id'] ?? 0);
$group = my_group($pdo, $gid, $myId);                 // every other action needs membership
if (!$group) { set_flash('error', 'That household does not exist or you are not in it.'); redirect('groups/index.php'); }
$back = 'groups/view.php?id=' . $gid;
$listingId = (int) ($_POST['listing_id'] ?? 0);

switch ($action) {
    case 'add_member':
        $uid = (int) ($_POST['user_id'] ?? 0);
        if (isset(my_connections($pdo, $myId)[$uid])) {
            try { $pdo->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)')->execute([$gid, $uid]); }
            catch (PDOException $e) { if (!is_duplicate_error($e)) throw $e; }
            set_flash('success', 'Added.');
        }
        break;
    case 'add':
        $l = $pdo->prepare("SELECT id FROM listings WHERE id = ? AND status = 'open'"); $l->execute([$listingId]);
        if (!$l->fetchColumn()) { set_flash('error', 'That flat is not available.'); break; }
        try { $pdo->prepare('INSERT INTO shortlist (group_id, listing_id, added_by) VALUES (?, ?, ?)')->execute([$gid, $listingId, $myId]); set_flash('success', 'Saved to ' . $group['name'] . '.'); }
        catch (PDOException $e) { if (!is_duplicate_error($e)) throw $e; set_flash('success', 'Already saved in this household.'); }
        break;
    case 'remove':
        $pdo->prepare('DELETE FROM poll_votes WHERE group_id = ? AND listing_id = ?')->execute([$gid, $listingId]);
        $pdo->prepare('DELETE FROM shortlist WHERE group_id = ? AND listing_id = ?')->execute([$gid, $listingId]);
        break;
    case 'poll':
        $c = $pdo->prepare('SELECT COUNT(*) FROM shortlist WHERE group_id = ?'); $c->execute([$gid]);
        if ($c->fetchColumn() >= 2) $pdo->prepare('UPDATE flat_groups SET poll_open = 1 WHERE id = ?')->execute([$gid]);
        break;
    case 'vote':
        $ok = $pdo->prepare('SELECT 1 FROM shortlist WHERE group_id = ? AND listing_id = ?'); $ok->execute([$gid, $listingId]);
        if ($group['poll_open'] && $ok->fetchColumn()) {
            // One current vote per person: replace, never add
            $pdo->prepare('DELETE FROM poll_votes WHERE group_id = ? AND user_id = ?')->execute([$gid, $myId]);
            $pdo->prepare('INSERT INTO poll_votes (group_id, user_id, listing_id) VALUES (?, ?, ?)')->execute([$gid, $myId, $listingId]);
        }
        break;
    case 'settle':                                    // we've found our people: stop looking
        settle_household($pdo, $gid, true);
        set_flash('success', 'Congratulations! ' . $group['name'] . ' is settled. Everyone in it is now hidden from Discover.');
        break;
    case 'unsettle':
        settle_household($pdo, $gid, false);
        set_flash('success', 'Back to looking. Everyone in ' . $group['name'] . ' shows up in Discover again.');
        break;
}
redirect($action === 'add' && !empty($_POST['from_listing']) ? 'listings/view.php?id=' . $listingId : $back);
