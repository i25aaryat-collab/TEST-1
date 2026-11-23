<?php
/**
 * auth/captcha_verify.php  —  Owner: M2 (Authentication)
 * Cloudflare Turnstile (the "I'm human" check) on login and sign-up.
 *   1. captcha_script() loads Cloudflare's script; captcha_widget() places the widget.
 *   2. Cloudflare puts a one-time token in a hidden field called cf-turnstile-response.
 *   3. verify_captcha() sends that token + our SECRET key to Cloudflare and checks the answer
 *      (success, same action, and a hostname that is really our site).
 * The secret key never reaches the browser. Keys come from Vercel env vars
 * TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY (see config/config.php).
 */
require_once __DIR__ . '/../config/config.php';

function captcha_widget(string $action): string
{
    if (!CAPTCHA_ENABLED) return '';
    return '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars(TURNSTILE_SITE_KEY, ENT_QUOTES) . '" data-action="' . htmlspecialchars($action, ENT_QUOTES)
         . '" data-theme="auto" data-size="flexible"></div><noscript>Turn on JavaScript to finish this check.</noscript>';
}

function captcha_script(): string
{
    return CAPTCHA_ENABLED ? '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>' : '';
}

/** Cloudflare's token must be for the site the person is actually on (or localhost when testing) */
function captcha_host_ok(string $host): bool
{
    $host = strtolower($host);
    $here = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    return $host !== '' && ($host === $here || in_array($host, ['roomiezmatch.vercel.app', 'localhost', '127.0.0.1', 'example.com'], true));
}

function captcha_result_valid(array $result, string $action): bool
{
    if (($result['success'] ?? false) !== true) return false;
    if (TURNSTILE_TEST_KEYS) return true;   // Cloudflare's test keys answer with dummy action/hostname
    return ($result['action'] ?? $action) === $action && captcha_host_ok((string) ($result['hostname'] ?? ''));
}

function verify_captcha(mixed $token, string $action): bool
{
    if (!CAPTCHA_ENABLED) return true;
    if (!is_string($token) || $token === '' || strlen($token) > 2048) return false;
    $body = http_build_query(['secret' => TURNSTILE_SECRET_KEY, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $reply = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $body, 'timeout' => 10]]);
        $reply = @file_get_contents($url, false, $ctx);
    }
    $result = is_string($reply) ? json_decode($reply, true) : null;
    if (!is_array($result)) { error_log('Turnstile: no answer from Cloudflare'); return false; }
    if (empty($result['success'])) error_log('Turnstile failed: ' . implode(',', (array) ($result['error-codes'] ?? [])));
    return captcha_result_valid($result, $action);
}
