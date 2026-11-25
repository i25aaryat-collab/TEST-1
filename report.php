<?php
/* ==========================================================================
 * report.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * A logged-in user flags something for the admins (POST only):
 *   target_type = message | listing | user,  target_id,  reason (5-300 chars)
 * The report goes to the admin's Reports page (admin/reports.php), where it
 * is dismissed or the content is removed.
 * You can only report a message from a chat you are in, and only once.
 * Answers in JSON for fetch(), otherwise redirects back with a message.
 * ========================================================================== */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$json = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

/** Finish with JSON (fetch) or a flash message + redirect (normal form) */
function done(bool $ok, string $message, bool $json): void
{
    if ($json) {
        header('Content-Type: application/json');
        if (!$ok) http_response_code(422);
        exit(json_encode(['ok' => $ok, 'message' => $message]));
    }
    set_flash($ok ? 'success' : 'error', $message);
    $back = $_SERVER['HTTP_REFERER'] ?? '';
    header('Location: ' . (str_starts_with($back, (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '')) ? $back : url('index.php')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
if (!is_logged_in()) done(false, 'Please log in again.', $json);
check_csrf($json);

$myId   = (int) $_SESSION['user_id'];
$type   = $_POST['target_type'] ?? '';
$id     = (int) ($_POST['target_id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));

// ---- Server-side validation ----
if (!in_array($type, ['message', 'listing', 'user'], true) || $id <= 0) {
    done(false, 'Nothing to report.', $json);
}
if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
    done(false, 'Please describe the problem in 5 to 300 characters.', $json);
}

// ---- The thing must exist (and a message must be in one of MY chats) ----
$exists = match ($type) {
    'message' => 'SELECT 1 FROM messages msg JOIN matches m ON m.id = msg.match_id
                  WHERE msg.id = ? AND msg.sender_id <> ? AND (m.user1_id = ? OR m.user2_id = ?)',
    'listing' => 'SELECT 1 FROM listings WHERE id = ? AND user_id <> ?',
    'user'    => "SELECT 1 FROM users WHERE id = ? AND id <> ? AND role = 'user'",
};
$params = $type === 'message' ? [$id, $myId, $myId, $myId] : [$id, $myId];
$stmt = $pdo->prepare($exists);
$stmt->execute($params);
if (!$stmt->fetch()) {
    done(false, 'You can only report messages, listings or people you can see.', $json);
}

// An end-to-end encrypted message can't be read by admins, so the reporter's own
// device sends the text it shows (only for the one message being reported).
$evidence = $type === 'message' ? mb_substr(trim((string) ($_POST['evidence'] ?? '')), 0, 600) : '';

try {
    $pdo->prepare('INSERT INTO reports (reporter_id, target_type, target_id, reason, evidence) VALUES (?, ?, ?, ?, ?)')
        ->execute([$myId, $type, $id, $reason, $evidence !== '' ? $evidence : null]);
} catch (PDOException $e) {
    if (!is_duplicate_error($e)) throw $e;
    done(false, 'You have already reported this. Our admins will look at it.', $json);
}
done(true, 'Thanks. Our admins will review this report.', $json);
