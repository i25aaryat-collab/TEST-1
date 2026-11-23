<?php
/**
 * communities/community_helpers.php  —  Owner: M4 (Matching)
 * Communities: a friend group, batch or hostel that people join with a link
 * (/communities/join.php?c=CODE). Members see each other ranked by score,
 * and can filter Discover to "only people in this community".
 */

/** Communities I'm in: [id, name, code, members] */
function my_communities(PDO $pdo, int $userId): array
{
    $s = $pdo->prepare('SELECT c.id, c.name, c.code, c.topic, (SELECT COUNT(*) FROM community_members x WHERE x.community_id = c.id) AS members
                        FROM communities c JOIN community_members m ON m.community_id = c.id WHERE m.user_id = ? ORDER BY c.name');
    $s->execute([$userId]);
    return $s->fetchAll();
}

/** The community if I'm a member, else null */
function my_community(PDO $pdo, int $id, int $userId): ?array
{
    $s = $pdo->prepare('SELECT c.* FROM communities c JOIN community_members m ON m.community_id = c.id WHERE c.id = ? AND m.user_id = ?');
    $s->execute([$id, $userId]);
    return $s->fetch() ?: null;
}

/** A short random join code, e.g. "k7f3q9ad" */
function new_community_code(): string
{
    return substr(strtolower(bin2hex(random_bytes(6))), 0, 10);
}

/** Full join link to share */
function community_link(array $c): string
{
    $scheme = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !empty($_SERVER['HTTPS'])) ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('communities/join.php?c=' . rawurlencode($c['code']));
}

const COMMUNITY_TOPICS = [
    'batch'    => ['College batch', 'Your class or year group'],
    'hostel'   => ['Hostel or PG', 'People in the same building'],
    'work'     => ['Workplace', 'Colleagues or interns'],
    'city'     => ['Moving to a city', 'Everyone heading to the same place'],
    'interest' => ['Shared interest', 'Gym, music, gaming, anything'],
    'other'    => ['Something else', 'Describe it below'],
];

function community_topic_label(?string $t): string { return COMMUNITY_TOPICS[$t][0] ?? ''; }
