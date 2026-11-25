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

/* ---------- Open communities: ask to join, every member must say yes ---------- */

/** My request to join a community (or null) */
function my_join_request(PDO $pdo, int $communityId, int $userId): ?array
{
    $s = $pdo->prepare('SELECT * FROM community_requests WHERE community_id = ? AND user_id = ?');
    $s->execute([$communityId, $userId]);
    return $s->fetch() ?: null;
}

/** Public communities I'm not in (optionally matching a search), newest first */
function open_communities(PDO $pdo, int $userId, string $q = ''): array
{
    $sql = "SELECT c.id, c.name, c.topic, c.about, c.created_at,
                   (SELECT COUNT(*) FROM community_members x WHERE x.community_id = c.id) AS members,
                   r.status AS my_status, r.decided_at AS my_decided
            FROM communities c LEFT JOIN community_requests r ON r.community_id = c.id AND r.user_id = ?
            WHERE c.is_public = 1 AND NOT EXISTS (SELECT 1 FROM community_members m WHERE m.community_id = c.id AND m.user_id = ?)";
    $params = [$userId, $userId];
    if ($q !== '') { $sql .= ' AND (LOWER(c.name) LIKE LOWER(?) OR LOWER(COALESCE(c.about, \'\')) LIKE LOWER(?))'; array_push($params, "%$q%", "%$q%"); }
    $s = $pdo->prepare($sql . ' ORDER BY members DESC, c.created_at DESC LIMIT 40');
    $s->execute($params);
    return $s->fetchAll();
}

/** Waiting requests for a community, each with the requester's profile and the votes so far */
function pending_join_requests(PDO $pdo, int $communityId, int $myId): array
{
    $s = $pdo->prepare("SELECT r.id AS request_id, r.message, r.created_at AS asked_at, u.id AS uid, u.name, p.*,
                               (SELECT COUNT(*) FROM community_votes v WHERE v.request_id = r.id AND v.vote = 1) AS yes_votes,
                               (SELECT v.vote FROM community_votes v WHERE v.request_id = r.id AND v.voter_id = ?) AS my_vote
                        FROM community_requests r JOIN users u ON u.id = r.user_id LEFT JOIN profiles p ON p.user_id = u.id
                        WHERE r.community_id = ? AND r.status = 'pending' ORDER BY r.created_at");
    $s->execute([$myId, $communityId]);
    return $s->fetchAll();
}

/** Decide a request once the votes allow it: any "no" declines; a "yes" from every current member lets them in.
    Returns the new status. */
function settle_join_request(PDO $pdo, int $requestId): string
{
    $r = $pdo->prepare('SELECT * FROM community_requests WHERE id = ?');
    $r->execute([$requestId]); $req = $r->fetch();
    if (!$req || $req['status'] !== 'pending') return $req['status'] ?? 'gone';

    $v = $pdo->prepare('SELECT m.user_id, v.vote FROM community_members m
                        LEFT JOIN community_votes v ON v.request_id = ? AND v.voter_id = m.user_id
                        WHERE m.community_id = ?');
    $v->execute([$requestId, $req['community_id']]);
    $votes = $v->fetchAll();
    $no = array_filter($votes, fn($x) => $x['vote'] !== null && (int) $x['vote'] === 0);
    $yes = array_filter($votes, fn($x) => $x['vote'] !== null && (int) $x['vote'] === 1);

    if ($no) $status = 'declined';
    elseif ($votes && count($yes) === count($votes)) $status = 'accepted';
    else return 'pending';

    $pdo->prepare('UPDATE community_requests SET status = ?, decided_at = CURRENT_TIMESTAMP WHERE id = ? AND status = ?')->execute([$status, $requestId, 'pending']);
    if ($status === 'accepted') {
        try { $pdo->prepare('INSERT INTO community_members (community_id, user_id) VALUES (?, ?)')->execute([$req['community_id'], $req['user_id']]); }
        catch (PDOException $e) { if (!is_duplicate_error($e)) throw $e; }
    }
    return $status;
}

/** How many requests are waiting for MY vote, across my communities (for the list and the badge) */
function join_requests_waiting(PDO $pdo, int $myId): array
{
    try {
        $s = $pdo->prepare("SELECT r.community_id, COUNT(*) AS n FROM community_requests r
                            JOIN community_members m ON m.community_id = r.community_id AND m.user_id = ?
                            WHERE r.status = 'pending' AND NOT EXISTS (SELECT 1 FROM community_votes v WHERE v.request_id = r.id AND v.voter_id = ?)
                            GROUP BY r.community_id");
        $s->execute([$myId, $myId]);
        return array_map('intval', array_column($s->fetchAll(), 'n', 'community_id'));
    } catch (PDOException $e) { return []; }
}
