<?php
/* ==========================================================================
 * config/config.php  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * The ONE place for settings that change between your laptop (XAMPP) and
 * the online host: database login, reCAPTCHA keys and the site's base URL.
 *
 * SECURITY: this file holds secrets. Never paste your real keys or the
 * online database password into an AI chat, WhatsApp group or GitHub.
 * ========================================================================== */

// ---------- 1. Database login ----------------------------------------------
// XAMPP defaults: host 127.0.0.1, user "root", empty password.
// On the online host, replace these with the values from the host's
// "MySQL Databases" page (see README, section "Put it online").
// On Vercel the same settings come from environment variables instead, so no
// password is ever written in this file there.
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');          // 'mysql' (XAMPP) or 'pgsql' (Supabase)
define('DB_HOST',   getenv('DB_HOST')   ?: '127.0.0.1');      // may list several hosts, comma-separated
define('DB_PORT',   getenv('DB_PORT')   ?: (DB_DRIVER === 'pgsql' ? '5432' : '3306'));
define('DB_NAME',   getenv('DB_NAME')   ?: 'roommate_match');
define('DB_USER',   getenv('DB_USER')   ?: 'root');
define('DB_PASS',   getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Serverless hosts (Vercel) forget files between requests, so there we keep
// login sessions and uploaded photos in the database instead of on disk.
define('STORE_IN_DB', getenv('STORE_IN_DB') === '1');

// ---------- 2. Google reCAPTCHA v3 ----------------------------------------
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '6Les-y4uAAAAAFLZqlirFo4r5xR88C92bHzypbVw');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// Emergency switch: set to false ONLY if the laptop has no internet during
// the demo (Google's CAPTCHA cannot load offline). Keep it true otherwise.
define('CAPTCHA_ENABLED', true);

// ---------- 3. Login lockout ------------------------------------------------
// After this many wrong passwords for one email, that email is locked
// for LOCKOUT_MINUTES. Stops people guessing passwords forever.
define('USERNAME_DOMAIN', 'ipm.roomiematch.test');   // username "aarya" = aarya@ipm.roomiematch.test
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 10);

// ---------- 4. Time zone ----------------------------------------------------
date_default_timezone_set('Asia/Kolkata');

// ---------- 5. Base URL (worked out automatically) -------------------------
// On XAMPP the site lives at  http://localhost/roommate-match/  so every link
// must start with "/roommate-match". Online it usually lives at the domain
// root, so links start with "" instead. We work this out by comparing the
// project folder with the web server's document root, so the same code runs
// in both places without edits.
function detect_base_url(): string
{
    if (getenv('BASE_URL') !== false) {
        return rtrim(getenv('BASE_URL'), '/');   // set explicitly (e.g. "" on Vercel)
    }
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $docRoot     = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');

    // Windows paths are not case-sensitive (C:/xampp vs c:/xampp)
    if ($docRoot !== '' && stripos($projectRoot, $docRoot) === 0) {
        return rtrim(substr($projectRoot, strlen($docRoot)), '/');
    }
    return '/roommate-match';   // safe fallback for XAMPP
}
define('BASE_URL', detect_base_url());

/**
 * url('auth/login.php')  ->  "/roommate-match/auth/login.php"
 * Use this for EVERY link, form action and redirect so nothing breaks when
 * the site moves from localhost to the online host.
 */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}
