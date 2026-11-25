<?php
/**
 * groups/group_helpers.php  —  Owner: M4 (Matching + Flats)
 * Households: private groups of connected flatmates with a shared shortlist of
 * existing listings and one poll, "Which flat should we visit first?".
 * Every page checks membership on the server.
 */

/** The group if I'm a member, else null */
function my_group(PDO $pdo, int $groupId, int $userId): ?array
{
    $s = $pdo->prepare('SELECT g.* FROM flat_groups g JOIN group_members m ON m.group_id = g.id WHERE g.id = ? AND m.user_id = ?');
    $s->execute([$groupId, $userId]);
    return $s->fetch() ?: null;
}

/** People I'm connected with (mutual matches): id => name */
function my_connections(PDO $pdo, int $userId): array
{
    $s = $pdo->prepare('SELECT u.id, u.name FROM matches m
        JOIN users u ON u.id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
        WHERE m.user1_id = ? OR m.user2_id = ? ORDER BY u.name');
    $s->execute([$userId, $userId, $userId]);
    return array_column($s->fetchAll(), 'name', 'id');
}

/** Groups I belong to */
function my_groups(PDO $pdo, int $userId): array
{
    $s = $pdo->prepare('SELECT g.id, g.name, g.settled_at, (SELECT COUNT(*) FROM shortlist s WHERE s.group_id = g.id) AS flats,
                               (SELECT COUNT(*) FROM group_members x WHERE x.group_id = g.id) AS members
                        FROM flat_groups g JOIN group_members m ON m.group_id = g.id WHERE m.user_id = ? ORDER BY g.id DESC');
    $s->execute([$userId]);
    return $s->fetchAll();
}

/** Settle a household: everyone in it has found their people and stops showing up in Discover.
    Their open rooms are closed too (the room is taken). Returns how many rooms were closed.
    Starting to look again does not reopen rooms: owners reopen them from the flat's edit page. */
function settle_household(PDO $pdo, int $groupId, bool $settled): int
{
    $pdo->prepare('UPDATE flat_groups SET settled_at = ? WHERE id = ?')->execute([$settled ? date('Y-m-d H:i:s') : null, $groupId]);
    $pdo->prepare('UPDATE profiles SET looking = ? WHERE user_id IN (SELECT user_id FROM group_members WHERE group_id = ?)')->execute([$settled ? 0 : 1, $groupId]);
    if (!$settled) return 0;
    $s = $pdo->prepare("UPDATE listings SET status = 'closed' WHERE status = 'open' AND user_id IN (SELECT user_id FROM group_members WHERE group_id = ?)");
    $s->execute([$groupId]);
    return $s->rowCount();
}

/** The settled household I'm in (for the "you've found your people" note), or null */
function my_settled_household(PDO $pdo, int $userId): ?array
{
    $s = $pdo->prepare('SELECT g.id, g.name FROM flat_groups g JOIN group_members m ON m.group_id = g.id WHERE m.user_id = ? AND g.settled_at IS NOT NULL ORDER BY g.settled_at DESC LIMIT 1');
    $s->execute([$userId]);
    return $s->fetch() ?: null;
}
