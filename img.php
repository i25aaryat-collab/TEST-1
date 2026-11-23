<?php
/**
 * img.php  —  Owner: M1 (Lead)
 * GET ?u=https://... — passes an imported listing photo through our own server.
 * The sites those photos come from refuse to show them on other websites
 * ("hotlink protection"), so the browser only saw a broken-image icon.
 * Only the hosts used by the imported listings are allowed (no open proxy),
 * only real images are passed on, and browsers/CDN cache them for a week.
 */
const IMG_HOSTS = ['images.jdmagicbox.com', 'magazine.homepublication.in', 'jumanji.livspace-cdn.com',
                   'media.designcafe.com', 'metaguise.com'];

$u = (string) ($_GET['u'] ?? '');
$host = strtolower((string) parse_url($u, PHP_URL_HOST));
if (!str_starts_with($u, 'https://') || !in_array($host, IMG_HOSTS, true)) { http_response_code(404); exit; }

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
if ($status !== 200 || !$info || strlen($body) > 8 * 1024 * 1024) { http_response_code(404); exit; }

header('Content-Type: ' . $info['mime']);
header('Cache-Control: public, max-age=604800, s-maxage=604800, immutable');
header('X-Content-Type-Options: nosniff');
echo $body;
