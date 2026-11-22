<?php
/* ==========================================================================
 * photo.php  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * Shows a profile photo stored in the database (only used on serverless
 * hosts like Vercel, where uploads can't be kept as files).
 *   photo.php?f=u12_9f3c1a2b7d4e6f80.jpg
 * ========================================================================== */

require_once __DIR__ . '/config/db.php';

$stmt = $pdo->prepare('SELECT mime, data FROM photos WHERE name = ?');
$stmt->execute([(string) ($_GET['f'] ?? '')]);
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $photo['mime']);
header('Cache-Control: public, max-age=86400');
echo base64_decode($photo['data']);
