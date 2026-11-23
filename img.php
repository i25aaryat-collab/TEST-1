<?php
/**
 * img.php  —  Owner: M1 (Lead)
 * GET ?u=https://... — passes an imported listing photo through our own server.
 * The sites those photos come from refuse to show them on other websites
 * ("hotlink protection"), so the browser only saw a broken-image icon.
 * Only the hosts used by the imported listings are allowed (no open proxy),
 * only real images are passed on, and browsers/CDN cache them for a week.
 * The first fetch saves a copy in the photos table (Supabase), so later
 * visits never depend on the source site. If a source refuses, a drawn
 * placeholder is shown instead of a broken-image icon.
 */
const IMG_HOSTS = ['images.jdmagicbox.com', 'magazine.homepublication.in', 'jumanji.livspace-cdn.com',
                   'media.designcafe.com', 'metaguise.com'];

$u = (string) ($_GET['u'] ?? '');
$host = strtolower((string) parse_url($u, PHP_URL_HOST));
if (!str_starts_with($u, 'https://') || !in_array($host, IMG_HOSTS, true)) { http_response_code(404); exit; }

// 1. Already saved in our database (Supabase)? Serve it from there.
require_once __DIR__ . '/config/db.php';
$key = 'ext_' . substr(hash('sha256', $u), 0, 32);
$row = $pdo->prepare('SELECT mime, data FROM photos WHERE name = ?');
$row->execute([$key]);
if ($hit = $row->fetch()) { serve($hit['mime'], base64_decode($hit['data'])); }

// 2. Otherwise fetch it once from the source and keep a copy in the database
$ch = curl_init($u);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; RoomieMatch image fetch)',
    CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/jpeg,image/png,*/*;q=0.5'],
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

$info = $body ? @getimagesizefromstring($body) : false;      // must really be an image
if ($status !== 200 || !$info || strlen($body) > 8 * 1024 * 1024) { placeholder(); }

// Shrink big photos before storing (the sources are up to 2048px wide)
if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($body)) && imagesx($im) > 900) {
    $small = imagescale($im, 900);
    ob_start(); imagejpeg($small, null, 82); $body = ob_get_clean();
    $info['mime'] = 'image/jpeg';
}
try { $pdo->prepare('INSERT INTO photos (name, mime, data) VALUES (?, ?, ?)')->execute([$key, $info['mime'], base64_encode($body)]); }
catch (PDOException $e) { /* another request saved it first: fine */ }
serve($info['mime'], $body);

function serve(string $mime, string $bytes): void
{
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=604800, s-maxage=604800, immutable');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
    exit;
}

/** A clean drawn "room" instead of a broken-image icon, when the source refuses */
function placeholder(): void
{
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=3600');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 260"><defs><linearGradient id="g" x2="1" y2="1"><stop offset="0" stop-color="#FFE3E4"/><stop offset="1" stop-color="#E6E3FA"/></linearGradient></defs>'
       . '<rect width="400" height="260" fill="url(#g)"/><rect x="40" y="150" width="320" height="10" rx="5" fill="#2B2D42" opacity=".12"/>'
       . '<rect x="70" y="105" width="120" height="45" rx="10" fill="#FF5A5F" opacity=".75"/><rect x="62" y="95" width="30" height="55" rx="8" fill="#FF5A5F"/>'
       . '<rect x="250" y="60" width="80" height="60" rx="6" fill="#fff" stroke="#2B2D42" stroke-opacity=".25" stroke-width="3"/><path d="M290 60v60M250 90h80" stroke="#2B2D42" stroke-opacity=".2" stroke-width="3"/>'
       . '<circle cx="225" cy="130" r="14" fill="#22A06B" opacity=".6"/><rect x="221" y="140" width="8" height="12" fill="#2B2D42" opacity=".3"/>'
       . '<text x="200" y="215" text-anchor="middle" font-family="Inter,Arial,sans-serif" font-size="15" fill="#6B6F86">Photo not available</text></svg>';
    exit;
}
