<?php
/* ==========================================================================
 * chat/inbox.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * All of my matches with a preview of the last message, most recent
 * conversation first.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_profile();
$myId = (int) $_SESSION['user_id'];

// For each match: the other person, plus the newest message (if any)
$stmt = $pdo->prepare("
    SELECT m.id AS match_id, m.created_at AS matched_at,
           u.name, p.photo,
           lm.body AS last_body, lm.kind AS last_kind, lm.sender_id AS last_sender, lm.created_at AS last_time,
           (SELECT COUNT(*) FROM messages un WHERE un.match_id = m.id AND un.sender_id <> ? AND un.read_at IS NULL) AS unread
    FROM matches m
    JOIN users u ON u.id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
    LEFT JOIN profiles p ON p.user_id = u.id
    LEFT JOIN messages lm ON lm.id = (SELECT MAX(id) FROM messages WHERE match_id = m.id)
    WHERE m.user1_id = ? OR m.user2_id = ?
    ORDER BY COALESCE(lm.created_at, m.created_at) DESC
");
// order: unread subquery, CASE, WHERE, WHERE
$stmt->execute([$myId, $myId, $myId, $myId]);
$chats = $stmt->fetchAll();

$pageTitle = 'Chats';
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="page-head">
        <div>
            <h1>Chats</h1>
            <p class="muted">Only people you've matched with can message you.</p>
        </div>
    </div>

    <div class="card">
        <?php if (!$chats): ?>
            <div class="empty-state">
                <div class="empty-icon">💬</div>
                <h2>No conversations yet</h2>
                <p>Match with someone first: when you both swipe right, a chat opens here.</p>
                <a class="btn btn-primary" href="<?= e(url('swipe/swipe.php')) ?>">Find people</a>
            </div>
        <?php else: ?>
            <ul class="inbox-list">
                <?php foreach ($chats as $c): ?>
                    <li class="inbox-item<?= $c['unread'] ? ' unread' : '' ?>">
                        <a href="<?= e(url('chat/conversation.php?match_id=' . (int) $c['match_id'])) ?>">
                            <img class="avatar" src="<?= e(photo_url($c['photo'])) ?>" alt="">
                            <div class="inbox-main">
                                <strong><?= e($c['name']) ?></strong>
                                <div class="inbox-preview">
                                    <?php if ($c['last_body'] === null): ?>
                                        <em>New match! Say hello 👋</em>
                                    <?php else: ?>
                                        <?= (int) $c['last_sender'] === $myId ? 'You: ' : '' ?><?= e(message_preview($c['last_kind'], $c['last_body'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="inbox-time"><?= e(date('j M', strtotime($c['last_time'] ?? $c['matched_at']))) ?>
                                <?php if ($c['unread']): ?><span class="badge-count" aria-label="<?= (int) $c['unread'] ?> unread"><?= (int) $c['unread'] ?></span><?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
