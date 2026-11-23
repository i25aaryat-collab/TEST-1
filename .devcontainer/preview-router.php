<?php
// The PHP development server ignores Apache's .htaccess restrictions.
$path = rawurldecode(explode('?', $_SERVER['REQUEST_URI'], 2)[0]);
if (preg_match('~^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]*$~D', $path) && !str_contains($path, '..')) {
    $root = realpath(__DIR__ . '/..');
    $file = realpath($root . $path);
    if ($file && str_starts_with($file, $root . '/') && is_file($file)
        && preg_match('~^/(css|js|media|uploads)/~', $path)
        && preg_match('~\.(css|js|png|jpe?g|webp|gif|svg|mp4|webm|woff2?)$~iD', $file)) {
        return false;
    }
    if (preg_match('~^/(?:(?:admin|auth|chat|communities|groups|listings|profile|swipe)/)?(?:[a-zA-Z0-9-][\w-]*\.php)?$~D', $path)) {
        $_GET['__path'] = str_ends_with($path, '/') ? $path . 'index.php' : $path;
        require $root . '/api/index.php';
        return;
    }
}
http_response_code(404);
echo 'Page not found.';
