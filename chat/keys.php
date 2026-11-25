<?php
/* ==========================================================================
 * chat/keys.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Key directory for end-to-end encrypted chats (see js/e2e.js). JSON only.
 *   GET  ?mine=1           my public key + my LOCKED private key + salt
 *   GET  ?user_id=7        someone's public key (only if we're matched)
 *   POST action=setup      store my keys (first time, or replace=1 when the
 *                          old ones were locked with a password I no longer have)
 *   POST action=check      is this my password? (before unlocking a device)
 * The server never sees a private key it can open: it is locked in the
 * browser with a key that comes from the user's password.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
$out = function (array $a, int $code = 200) { http_response_code($code); exit(json_encode($a)); };

if (!is_logged_in() || is_admin()) $out(['ok' => false, 'error' => 'Please log in again.'], 401);
$myId = (int) $_SESSION['user_id'];

$b64 = fn(string $s, int $min, int $max) => strlen($s) >= $min && strlen($s) <= $max && preg_match('#^[A-Za-z0-9+/]+=*$#', $s);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['mine'])) {
        $s = $pdo->prepare('SELECT e2e_pub, e2e_priv, e2e_salt FROM users WHERE id = ?');
        $s->execute([$myId]); $r = $s->fetch();
        $out(['ok' => true, 'pub' => $r['e2e_pub'] ?? null, 'priv' => $r['e2e_priv'] ?? null, 'salt' => $r['e2e_salt'] ?? null]);
    }
    $other = (int) ($_GET['user_id'] ?? 0);
    $s = $pdo->prepare('SELECT u.e2e_pub FROM users u JOIN matches m ON (m.user1_id = ? AND m.user2_id = u.id) OR (m.user2_id = ? AND m.user1_id = u.id) WHERE u.id = ?');
    $s->execute([$myId, $myId, $other]);
    $pub = $s->fetchColumn();
    if ($pub === false) $out(['ok' => false, 'error' => 'Not matched.'], 404);
    $out(['ok' => true, 'pub' => $pub]);
}

check_csrf(true);
$action = $_POST['action'] ?? '';

if ($action === 'check') {
    $s = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $s->execute([$myId]);
    $ok = password_verify((string) ($_POST['password'] ?? ''), (string) $s->fetchColumn());
    if (!$ok) usleep(400000);                      // slow down guessing
    $out($ok ? ['ok' => true] : ['ok' => false, 'error' => "That password doesn't match."], $ok ? 200 : 422);
}

if ($action === 'setup') {
    $pub = (string) ($_POST['pub'] ?? ''); $priv = (string) ($_POST['priv'] ?? ''); $salt = (string) ($_POST['salt'] ?? '');
    if (!$b64($pub, 80, 200) || !$b64($priv, 100, 400) || !$b64($salt, 16, 64)) $out(['ok' => false, 'error' => 'Bad key.'], 422);
    $sql = 'UPDATE users SET e2e_pub = ?, e2e_priv = ?, e2e_salt = ? WHERE id = ?' . (empty($_POST['replace']) ? ' AND e2e_pub IS NULL' : '');
    $s = $pdo->prepare($sql);
    $s->execute([$pub, $priv, $salt, $myId]);
    $out($s->rowCount() ? ['ok' => true] : ['ok' => false, 'error' => 'Keys already set up.'], $s->rowCount() ? 200 : 409);
}

$out(['ok' => false, 'error' => 'Unknown action.'], 400);
