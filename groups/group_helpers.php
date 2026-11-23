<?php
/**
 * groups/group_helpers.php  —  Owner: M4 (Matching + Flats)
 * Private flat-hunting groups of connected flatmates: a shared shortlist of
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
    $s = $pdo->prepare('SELECT g.id, g.name, (SELECT COUNT(*) FROM shortlist s WHERE s.group_id = g.id) AS flats,
                               (SELECT COUNT(*) FROM group_members x WHERE x.group_id = g.id) AS members
                        FROM flat_groups g JOIN group_members m ON m.group_id = g.id WHERE m.user_id = ? ORDER BY g.id DESC');
    $s->execute([$userId]);
    return $s->fetchAll();
}
