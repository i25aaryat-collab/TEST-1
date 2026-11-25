<?php
/* ==========================================================================
 * api/index.php  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * Only used on Vercel. Vercel runs PHP through one entry file, so vercel.json
 * sends every "*.php" address here, and this file runs the real page, e.g.
 *   /auth/login.php  ->  runs  auth/login.php
 * On XAMPP this file is never used.
 * ========================================================================== */

// Live site: never print PHP warnings into the page; they still go to the Vercel logs.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Security headers on every page: no framing by other sites (clickjacking), no MIME sniffing,
// don't leak full URLs to other sites, and only this site may ask for your location.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');

$root = realpath(__DIR__ . '/..');
$path = trim((string) ($_GET['__path'] ?? ''), '/');
unset($_GET['__path']);
if ($path === '') {
    $path = 'index.php';
}

// Only real .php pages inside the project; never config/, includes/, database/ or api/
$file = realpath($root . '/' . $path);
$blocked = '#^(config|includes|database|api)/#';
if (!$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !str_ends_with($file, '.php')
    || preg_match($blocked, substr($file, strlen($root) + 1)) || str_starts_with(basename($file), '_')) {
    http_response_code(404);
    require $root . '/_404.php';      // a real page with the menu, not a bare line of text
    exit;
}

// Make the page believe it was opened directly (the nav bar reads SCRIPT_NAME)
$relative = '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($root) + 1));
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $relative;
$_SERVER['SCRIPT_FILENAME'] = $file;
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['QUERY_STRING'] = http_build_query($_GET);

chdir(dirname($file));
require $file;
