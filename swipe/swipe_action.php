<?php
/* ==========================================================================
 * swipe/swipe_action.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * Called by JavaScript fetch() from swipe.php (AJAX). Never shows a page;
 * it only answers in JSON:
 *     {"ok": true, "matched": false}
 *     {"ok": true, "matched": true, "match_id": 7}
 *     {"ok": false, "error": "..."}
 *
 * Steps:
 *   1. Logged in? Has a profile? CSRF token correct?
 *   2. Input valid? (a real, non-admin user who isn't me; like or pass)
 *   3. Save the swipe (the UNIQUE key stops double swipes)
 *   4. If it's a like and they already liked me -> create the match,
 *      with the smaller id in user1_id as the brief requires.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

/** Send a JSON answer and stop */
function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// ---------- 1. Who is asking? ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Use POST.'], 405);
}
if (!is_logged_in() || is_admin()) {
    respond(['ok' => false, 'error' => 'Please log in again.'], 401);
}
check_csrf(true);                               // token comes in the X-CSRF-Token header

$myId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT 1 FROM profiles WHERE user_id = ?');
$stmt->execute([$myId]);
if (!$stmt->fetch()) {
    respond(['ok' => false, 'error' => 'Create your profile first.'], 403);
}

// ---------- 2. Read and check the input ----------
// fetch() sends JSON; a normal form would send $_POST. Accept both.
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$swipedId  = filter_var($input['swiped_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$direction = $input['direction'] ?? '';

if (!$swipedId || !in_array($direction, ['like', 'pass'], true)) {
    respond(['ok' => false, 'error' => 'Invalid swipe.'], 422);
}
if ($swipedId === $myId) {
    respond(['ok' => false, 'error' => 'You cannot swipe on yourself.'], 422);
}

// The other person must exist, be a normal user and have a profile
$stmt = $pdo->prepare("SELECT 1 FROM users u JOIN profiles p ON p.user_id = u.id WHERE u.id = ? AND u.role = 'user'");
$stmt->execute([$swipedId]);
if (!$stmt->fetch()) {
    respond(['ok' => false, 'error' => 'That person is no longer on RoomieMatch.'], 404);
}

// ---------- 3. Save the swipe ----------
try {
    $pdo->prepare('INSERT INTO swipes (swiper_id, swiped_id, direction) VALUES (?, ?, ?)')
        ->execute([$myId, $swipedId, $direction]);
} catch (PDOException $e) {
    if (is_duplicate_error($e)) {               // UNIQUE (swiper_id, swiped_id) already exists
        respond(['ok' => false, 'error' => 'You already swiped on this person.'], 409);
    }
    throw $e;
}

if ($direction === 'pass') {
    respond(['ok' => true, 'matched' => false]);
}

// ---------- 4. Did they already like me? ----------
$stmt = $pdo->prepare("SELECT 1 FROM swipes WHERE swiper_id = ? AND swiped_id = ? AND direction = 'like'");
$stmt->execute([$swipedId, $myId]);
if (!$stmt->fetch()) {
    respond(['ok' => true, 'matched' => false]);
}

// Mutual like -> create the match. Smaller id always goes in user1_id.
// If two requests arrive at once, the UNIQUE key keeps one row and the other is ignored.
$user1 = min($myId, $swipedId);
$user2 = max($myId, $swipedId);
try {
    $pdo->prepare('INSERT INTO matches (user1_id, user2_id) VALUES (?, ?)')->execute([$user1, $user2]);
} catch (PDOException $e) {
    if (!is_duplicate_error($e)) throw $e;
}

$stmt = $pdo->prepare('SELECT id FROM matches WHERE user1_id = ? AND user2_id = ?');
$stmt->execute([$user1, $user2]);
respond(['ok' => true, 'matched' => true, 'match_id' => (int) $stmt->fetchColumn()]);
