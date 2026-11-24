<?php
/* ==========================================================================
 * includes/header.php  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * Top of every page: <head>
    <script>try{var t=localStorage.getItem('rm-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>, the navigation bar and any flash message.
 *
 * Before including it, a page may set:
 *   $pageTitle  text for the browser tab
 *   $extraHead  extra tags for <head> (e.g. the Turnstile script)
 *   $bodyClass  a CSS class for <body> (e.g. "page-auth")
 * ========================================================================== */

require_once __DIR__ . '/auth_check.php';   // starts the session + helpers
require_once __DIR__ . '/../chat/chat_helpers.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' · RoomieMatch' : 'RoomieMatch · Find a flatmate you actually get along with';
$flash     = get_flash();

// Which menu item to highlight: compare the current script with each link
$current = $_SERVER['SCRIPT_NAME'] ?? '';
function nav_link(string $path, string $label, string $current, string $badge = '', int $count = 0): string
{
    $active = str_ends_with($current, '/' . $path) ? ' class="active" aria-current="page"' : '';
    $pill = $badge === '' ? '' : '<span class="nav-badge" data-badge="' . $badge . '"' . ($count ? '' : ' hidden') . '>'
          . $count . '<span class="sr-only"> new</span></span>';
    return '<a href="' . e(url($path)) . '"' . $active . '>' . e($label) . $pill . '</a>';
}

// Red badges: unread messages and requests waiting (kept fresh by footer.php)
$unread = $requestsWaiting = 0;
if (is_logged_in() && !is_admin() && isset($pdo)) {
    $notificationCounts = notification_counts($pdo, (int) $_SESSION['user_id']);
    $unread = $notificationCounts['unread'];
    $requestsWaiting = $notificationCounts['requests'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="RoomieMatch helps you find a compatible flatmate: take the compatibility test, match, then chat.">
    <!-- Used by JavaScript fetch() calls so the server can check the request came from our page -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e(url('css/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('css/style.css')) ?>?v=<?= substr(md5_file(__DIR__ . '/../css/style.css'), 0, 8) ?>"><!-- version changes with the file, so browsers never keep an old copy -->
    <?= $extraHead ?? '' ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <nav class="navbar container" aria-label="Main">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(url('css/logo.svg')) ?>" alt="" width="34" height="34">
            <span>Roomie<strong>Match</strong></span>
        </a>

        <!-- Hamburger button: only visible on small screens (see style.css) -->
        <button class="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="nav-links">
            <span></span><span></span><span></span>
        </button>

        <div class="nav-links" id="nav-links">
            <?php if (!is_logged_in()): ?>
                <?= nav_link('listings/index.php', 'Flats', $current) ?>
                <?= nav_link('auth/login.php', 'Log in', $current) ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('auth/register.php')) ?>">Sign up free</a>
            <?php elseif (is_admin()): ?>
                <?= nav_link('admin/dashboard.php', 'Users', $current) ?>
                <?= nav_link('admin/reports.php', 'Reports', $current) ?>
                <span class="nav-user">Hi, <?= e($_SESSION['name']) ?></span>
                <a class="btn btn-outline btn-sm" href="<?= e(url('auth/logout.php')) ?>">Log out</a>
            <?php else: ?>
                <?= nav_link('swipe/swipe.php', 'Discover', $current) ?>
                <?= nav_link('swipe/matches.php', 'Matches', $current, 'requests', $requestsWaiting) ?>
                <?= nav_link('chat/inbox.php', 'Chats', $current, 'chats', $unread) ?>
                <?= nav_link('listings/index.php', 'Flats', $current) ?>
                <?= nav_link('map.php', 'Map', $current) ?>
                <?= nav_link('communities/index.php', 'Communities', $current) ?>
                <?= nav_link('profile/view_profile.php', 'My profile', $current) ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('auth/logout.php')) ?>">Log out</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<!-- Light/dark switch: pull the lamp's cord (js/pullcord.js) -->
<div class="lamp" id="lamp">
    <div class="glow"></div>
    <button type="button" role="switch" aria-checked="true" aria-label="Light mode">Switch light or dark mode</button>
    <svg viewBox="0 0 96 170" aria-hidden="true">
        <rect class="mount" x="40" y="-1" width="16" height="5" rx="2" fill="var(--navy)" opacity=".85"/>
        <line class="wire" x1="48" y1="3" x2="48" y2="30" stroke="var(--muted)" stroke-width="1.5"/>
        <path d="M40 30h16l20 26q1 3-2 3H22q-3 0-2-3Z" fill="var(--navy)"/>
        <path class="bulb" d="M40 59a8 7 0 0 0 16 0Z" fill="#FCD34D"/>
        <path class="cord" d="" fill="none" stroke="var(--muted)" stroke-width="1.5" stroke-linecap="round"/>
        <path class="hit grab" d="" fill="none" stroke="transparent" stroke-width="20" style="pointer-events:stroke"/>
        <g class="bead grab"><circle r="14" fill="transparent"/><rect x="-4" y="1" width="8" height="15" rx="4" fill="var(--navy)"/></g>
    </svg>
    <span class="hint" aria-hidden="true"></span>
</div>
<main class="site-main" id="main" tabindex="-1">
<?php if ($flash): ?>
    <div class="container">
        <div class="flash flash-<?= e($flash['type']) ?>" role="status">
            <?= e($flash['message']) ?>
            <button type="button" class="flash-close" aria-label="Dismiss">&times;</button>
        </div>
    </div>
<?php endif; ?>
