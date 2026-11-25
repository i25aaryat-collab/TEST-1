<?php
/* ==========================================================================
 * admin/reports.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Admin moderation queue. Users report messages, listings and profiles;
 * here an admin sees each report with the reported content and either:
 *   - Dismiss  (nothing wrong), or
 *   - Remove   (delete the message / listing / user)
 * Filter by status (open, dismissed, removed) and type.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_admin();

$status = in_array($_GET['status'] ?? '', ['open', 'dismissed', 'removed', 'all'], true) ? $_GET['status'] : 'open';
$type   = in_array($_GET['type'] ?? '', ['message', 'listing', 'user'], true) ? $_GET['type'] : '';

// Each report, with a preview of what was reported (it may already be gone)
$sql = "SELECT r.*, u.name AS reporter,
               CASE r.target_type
                   WHEN 'message' THEN (SELECT body FROM messages WHERE id = r.target_id)
                   WHEN 'listing' THEN (SELECT title FROM listings WHERE id = r.target_id)
                   ELSE (SELECT name FROM users WHERE id = r.target_id)
               END AS preview,
               CASE r.target_type
                   WHEN 'message' THEN (SELECT us.name FROM messages ms JOIN users us ON us.id = ms.sender_id WHERE ms.id = r.target_id)
                   WHEN 'listing' THEN (SELECT us.name FROM listings ls JOIN users us ON us.id = ls.user_id WHERE ls.id = r.target_id)
                   ELSE NULL
               END AS author
        FROM reports r JOIN users u ON u.id = r.reporter_id WHERE 1 = 1";
$params = [];
if ($status !== 'all') { $sql .= ' AND r.status = ?'; $params[] = $status; }
if ($type !== '')      { $sql .= ' AND r.target_type = ?'; $params[] = $type; }
$sql .= ' ORDER BY r.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) AS n FROM reports GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <div><h1>Reports</h1><p class="muted">Things users flagged. Review each one: dismiss it, or remove the content.</p></div>
    </div>

    <div class="stat-grid">
        <div class="card stat"><div class="num"><?= (int) ($counts['open'] ?? 0) ?></div><div class="label">Open</div></div>
        <div class="card stat"><div class="num"><?= (int) ($counts['removed'] ?? 0) ?></div><div class="label">Removed</div></div>
        <div class="card stat"><div class="num"><?= (int) ($counts['dismissed'] ?? 0) ?></div><div class="label">Dismissed</div></div>
    </div>

    <div class="card">
        <form class="toolbar" method="get" action="">
            <label class="sr-only" for="status">Status</label>
            <select id="status" name="status"><?php foreach (['open' => 'Open', 'removed' => 'Removed', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $t): ?><option value="<?= $k ?>" <?= $k === $status ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
            <label class="sr-only" for="type">Type</label>
            <select id="type" name="type"><option value="">All types</option><?php foreach (['message' => 'Messages', 'listing' => 'Listings', 'user' => 'Profiles'] as $k => $t): ?><option value="<?= $k ?>" <?= $k === $type ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary" type="submit">Filter</button>
        </form>

        <?php if (!$reports): ?>
            <div class="empty-state"><div class="empty-icon">✓</div><h2>Nothing to review</h2><p>No <?= $status === 'all' ? '' : e($status) ?> reports right now.</p></div>
        <?php else: ?>
            <ul class="report-list">
                <?php foreach ($reports as $r): ?>
                    <li class="report-item">
                        <div class="grow">
                            <div><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
                                 <strong><?= e(ucfirst($r['target_type'])) ?></strong>
                                 <?= $r['author'] ? 'by ' . e($r['author']) : '' ?>
                                 <span class="muted small">· reported by <?= e($r['reporter']) ?> on <?= e(nice_date($r['created_at'])) ?></span></div>
                            <?php $encrypted = $r['target_type'] === 'message' && $r['preview'] !== null && str_starts_with($r['preview'], 'e2e:1:'); ?>
                            <?php if ($encrypted): ?>
                                <blockquote class="report-preview"><?= $r['evidence'] !== null ? e(mb_strimwidth($r['evidence'], 0, 300, '…')) : '<em class="muted">End-to-end encrypted, and no copy was sent with the report</em>' ?></blockquote>
                                <?php if ($r['evidence'] !== null): ?><p class="muted small">End-to-end encrypted message: this is the text as it appeared on the reporter's device.</p><?php endif; ?>
                            <?php else: ?>
                                <blockquote class="report-preview"><?= $r['preview'] !== null ? e(mb_strimwidth($r['preview'], 0, 160, '…')) : '<em class="muted">Already deleted</em>' ?></blockquote>
                            <?php endif; ?>
                            <div class="small"><strong>Reason:</strong> <?= e($r['reason']) ?></div>
                            <?php if ($r['target_type'] === 'listing' && $r['preview'] !== null): ?>
                                <a class="small" href="<?= e(url('listings/view.php?id=' . (int) $r['target_id'])) ?>">Open listing</a>
                            <?php elseif ($r['target_type'] === 'user' && $r['preview'] !== null): ?>
                                <a class="small" href="<?= e(url('profile/view_profile.php?id=' . (int) $r['target_id'])) ?>">Open profile</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($r['status'] === 'open'): ?>
                            <div class="btn-row">
                                <form method="post" action="<?= e(url('admin/report_action.php')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="dismiss">
                                    <button class="btn btn-sm" type="submit">Dismiss</button>
                                </form>
                                <form method="post" action="<?= e(url('admin/report_action.php')) ?>" data-confirm="Delete this <?= e($r['target_type']) ?> permanently?" data-confirm-button="Remove">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="remove">
                                    <button class="btn btn-danger btn-sm" type="submit">Remove <?= e($r['target_type']) ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
