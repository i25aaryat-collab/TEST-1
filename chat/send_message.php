<?php
/* ==========================================================================
 * chat/send_message.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Handles two actions inside a chat (POST only):
 *   action=send    add a message (1 to 500 characters), or kind=sticker / gif
 *   action=delete  delete one of YOUR OWN messages
 * Answers in JSON when called by fetch(), otherwise redirects back to the
 * conversation (so it still works with JavaScript switched off).
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

$json = wants_json();

/** Finish: JSON for fetch(), or a flash message + redirect for a normal form */
function finish(bool $ok, string $message, int $matchId, bool $json): void
{
    if ($json) {
        header('Content-Type: application/json');
        if (!$ok) http_response_code(422);
        exit(json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $message]));
    }
    if (!$ok) set_flash('error', $message);
    redirect($matchId ? 'chat/conversation.php?match_id=' . $matchId : 'chat/inbox.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('chat/inbox.php');
}
if (!is_logged_in()) {
    $json ? finish(false, 'Please log in again.', 0, true) : require_login();
}
check_csrf($json);

$myId    = (int) $_SESSION['user_id'];
$matchId = (int) ($_POST['match_id'] ?? 0);
$action  = $_POST['action'] ?? 'send';

// You must belong to this match to do anything in it
if (!find_my_match($pdo, $matchId, $myId)) {
    finish(false, 'That chat does not exist or is not yours.', 0, $json);
}

if ($action === 'send') {
    // Server-side validation: 1 to 500 characters after trimming spaces
    $body = trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')));
    $kind = $_POST['kind'] ?? 'text';

    // A sticker or GIF: the body must be one of the bundled ids
    if ($kind === 'sticker' || $kind === 'gif') {
        if (!in_array($body, $kind === 'sticker' ? STICKER_IDS : GIF_IDS, true)) {
            finish(false, 'Unknown sticker.', $matchId, $json);
        }
        $pdo->prepare('INSERT INTO messages (match_id, sender_id, body, kind) VALUES (?, ?, ?, ?)')
            ->execute([$matchId, $myId, $body, $kind]);
        finish(true, '', $matchId, $json);
    }

    $length = mb_strlen($body);
    if ($length === 0) {
        finish(false, 'Type a message first.', $matchId, $json);
    }
    if ($length > 500) {
        finish(false, 'Messages can be at most 500 characters (yours has ' . $length . ').', $matchId, $json);
    }
    $pdo->prepare('INSERT INTO messages (match_id, sender_id, body) VALUES (?, ?, ?)')
        ->execute([$matchId, $myId, $body]);
    finish(true, '', $matchId, $json);
}

if ($action === 'delete') {
    // "AND sender_id = ?" means you can only ever delete your own messages
    $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ? AND match_id = ? AND sender_id = ?');
    $stmt->execute([(int) ($_POST['message_id'] ?? 0), $matchId, $myId]);
    if ($stmt->rowCount() === 0) {
        finish(false, 'You can only delete your own messages.', $matchId, $json);
    }
    finish(true, '', $matchId, $json);
}

finish(false, 'Unknown action.', $matchId, $json);
