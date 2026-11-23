<?php
/* ==========================================================================
 * chat/chat_helpers.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * The one security check every chat file needs: "is this match mine?"
 * A user may only read, send to, or unmatch a match they are part of.
 * ========================================================================== */

/**
 * Returns the match plus the OTHER person's name and photo, but only if
 * $userId is one of the two people in it. Otherwise returns null.
 */
function find_my_match(PDO $pdo, int $matchId, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT m.id, m.user1_id, m.user2_id, m.created_at,
               u.id AS other_id, u.name AS other_name, p.photo AS other_photo
        FROM matches m
        JOIN users u ON u.id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE m.id = ? AND (m.user1_id = ? OR m.user2_id = ?)
    ");
    $stmt->execute([$userId, $matchId, $userId, $userId]);
    return $stmt->fetch() ?: null;
}

/** All messages in a match, oldest first, shaped for JSON */
function load_messages(PDO $pdo, int $matchId, int $myId): array
{
    $stmt = $pdo->prepare('SELECT id, sender_id, body, kind, read_at, created_at FROM messages WHERE match_id = ? ORDER BY id');
    $stmt->execute([$matchId]);
    $messages = [];
    foreach ($stmt->fetchAll() as $row) {
        $messages[] = [
            'id'   => (int) $row['id'],
            'mine' => (int) $row['sender_id'] === $myId,
            'body' => $row['body'],                              // escaped later, when shown
            'kind' => $row['kind'],                              // text, sticker or gif
            'seen' => $row['read_at'] !== null,                  // for "Seen" under my last message
            'time' => date('j M, g:i A', strtotime($row['created_at'])),
            'reactions' => [], 'my_reaction' => null,
        ];
    }
    // Reactions: one per person per message, counted by emoji
    if ($messages) {
        $r = $pdo->prepare('SELECT r.message_id, r.user_id, r.emoji FROM message_reactions r JOIN messages m ON m.id = r.message_id WHERE m.match_id = ?');
        $r->execute([$matchId]);
        $byId = array_flip(array_column($messages, 'id'));
        foreach ($r->fetchAll() as $x) {
            if (!isset($byId[(int) $x['message_id']])) continue;
            $i = $byId[(int) $x['message_id']];
            $messages[$i]['reactions'][$x['emoji']] = ($messages[$i]['reactions'][$x['emoji']] ?? 0) + 1;
            if ((int) $x['user_id'] === $myId) $messages[$i]['my_reaction'] = $x['emoji'];
        }
    }
    return $messages;
}

/** Is the other person typing right now? (they pinged chat/typing.php in the last 6 seconds) */
function other_is_typing(PDO $pdo, int $matchId, int $myId): bool
{
    $s = $pdo->prepare('SELECT at FROM chat_typing WHERE match_id = ? AND user_id <> ? ORDER BY at DESC LIMIT 1');
    $s->execute([$matchId, $myId]);
    $at = $s->fetchColumn();
    return $at && (time() - strtotime($at)) <= 6;
}

/** True when the request came from fetch() and wants JSON back */
function wants_json(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/** Mark the other person's messages in this match as read by me */
function mark_read(PDO $pdo, int $matchId, int $myId): void
{
    $pdo->prepare('UPDATE messages SET read_at = CURRENT_TIMESTAMP
                   WHERE match_id = ? AND sender_id <> ? AND read_at IS NULL')
        ->execute([$matchId, $myId]);
}

/** Both navigation badge counts in one database round trip. */
function notification_counts(PDO $pdo, int $myId): array
{
    $stmt = $pdo->prepare("SELECT
        (SELECT COUNT(*) FROM messages msg JOIN matches m ON m.id = msg.match_id
         WHERE (m.user1_id = ? OR m.user2_id = ?) AND msg.sender_id <> ? AND msg.read_at IS NULL) AS unread,
        (SELECT COUNT(*) FROM swipes WHERE swiped_id = ? AND direction = 'like'
         AND swiper_id NOT IN (SELECT swiped_id FROM swipes WHERE swiper_id = ?)) AS requests");
    $stmt->execute([$myId, $myId, $myId, $myId, $myId]);
    $counts = $stmt->fetch() ?: ['unread' => 0, 'requests' => 0];
    return ['unread' => (int) $counts['unread'], 'requests' => (int) $counts['requests']];
}

/** Unread messages for me across all my matches (the red badge on "Chats") */
function unread_count(PDO $pdo, int $myId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages msg JOIN matches m ON m.id = msg.match_id
                           WHERE (m.user1_id = ? OR m.user2_id = ?) AND msg.sender_id <> ? AND msg.read_at IS NULL');
    $stmt->execute([$myId, $myId, $myId]);
    return (int) $stmt->fetchColumn();
}

/** Requests waiting for my answer (the badge on "Matches") */
function pending_requests(PDO $pdo, int $myId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM swipes WHERE swiped_id = ? AND direction = 'like'
                           AND swiper_id NOT IN (SELECT swiped_id FROM swipes WHERE swiper_id = ?)");
    $stmt->execute([$myId, $myId]);
    return (int) $stmt->fetchColumn();
}

/** How a message reads in the inbox preview */
function message_preview(string $kind, string $body): string
{
    return match ($kind) { 'sticker' => 'Sent a sticker', 'gif' => 'Sent a GIF', default => $body };
}
