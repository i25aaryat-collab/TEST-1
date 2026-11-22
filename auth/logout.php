<?php
/* ==========================================================================
 * auth/logout.php  —  Owner: M2 (Authentication)
 * --------------------------------------------------------------------------
 * Ends the session completely (data, cookie and id) and goes to login.
 * ========================================================================== */

require_once __DIR__ . '/../includes/auth_check.php';

// 1. Empty the session data
$_SESSION = [];

// 2. Delete the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// 3. Destroy the session on the server
session_destroy();

// 4. Start a brand-new empty session just to carry the "logged out" message
session_start();
set_flash('success', 'You have been logged out.');
redirect('auth/login.php');
