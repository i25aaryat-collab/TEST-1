<?php
/**
 * communities/join.php  —  Owner: M4
 * The invite link: ?c=CODE. Logged out -> log in first, then come back here.
 * GET shows "Join X?"; POST joins (so a link preview can never join anyone by itself).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$code = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($_GET['c'] ?? $_POST['c'] ?? '')));
$s = $pdo->prepare('SELECT * FROM communities WHERE code = ?');
$s->execute([$code]);
$c = $s->fetch();
if (!$c) { set_flash('error', 'That invite link is not valid.'); redirect('index.php'); }
if (!is_logged_in()) { $_SESSION['after_login'] = 'communities/join.php?c=' . $code; set_flash('success', 'Log in or sign up to join ' . $c['name'] . '.'); redirect('auth/login.php'); }
require_profile();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try { $pdo->prepare('INSERT INTO community_members (community_id, user_id) VALUES (?, ?)')->execute([(int) $c['id'], (int) $_SESSION['user_id']]); }
    catch (PDOException $e) { if (!is_duplicate_error($e)) throw $e; }
    set_flash('success', 'You joined ' . $c['name'] . '.');
    redirect('communities/index.php?id=' . (int) $c['id']);
}
$pageTitle = 'Join ' . $c['name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow"><div class="card empty-state">
    <div class="empty-icon">🏘️</div><h1>Join <?= e($c['name']) ?>?</h1>
    <p class="muted">Members can see each other ranked by compatibility. Your email is never shown.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="c" value="<?= e($code) ?>"><button class="btn btn-primary">Join community</button></form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
