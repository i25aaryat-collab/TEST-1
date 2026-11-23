<?php
/* auth/captcha_verify.php — Owner: M2 (Authentication) */
require_once __DIR__ . '/../config/config.php';

function captcha_widget(string $action): string
{
    if (!CAPTCHA_ENABLED) return '';
    return '<input type="hidden" name="g-recaptcha-response" data-action="' . htmlspecialchars($action, ENT_QUOTES) . '" data-sitekey="' . htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES) . '"><noscript>Enable JavaScript to verify this form.</noscript>';
}

function captcha_script(): string
{
    if (!CAPTCHA_ENABLED) return '';
    return '<script async src="https://www.google.com/recaptcha/api.js?render=' . rawurlencode(RECAPTCHA_SITE_KEY) . '"></script><script defer src="' . htmlspecialchars(url('js/recaptcha.js'), ENT_QUOTES) . '"></script>';
}

function captcha_result_valid(array $result, string $action): bool
{
    $score = $result['score'] ?? null;
    return ($result['success'] ?? false) === true
        && ($result['action'] ?? null) === $action
        && captcha_host_ok((string) ($result['hostname'] ?? ''))
        && (is_int($score) || is_float($score)) && $score >= 0.3 && $score <= 1;
}

/** Google's token must be for the site the person is actually on (any of our deployments, or localhost when testing) */
function captcha_host_ok(string $host): bool
{
    $here = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    return $host !== '' && (strtolower($host) === $here || in_array(strtolower($host), ['roomiezmatch.vercel.app', 'localhost', '127.0.0.1'], true));
}

function verify_captcha(mixed $token, string $action): bool
{
    if (!CAPTCHA_ENABLED) return true;
    if (RECAPTCHA_SECRET_KEY === '') {
        // Secret not set in Vercel yet: don't lock everyone out, just log it loudly.
        error_log('reCAPTCHA: RECAPTCHA_SECRET_KEY is not set, skipping verification');
        return true;
    }
    if (!is_string($token) || $token === '') return false;
    $body = http_build_query(['secret' => RECAPTCHA_SECRET_KEY, 'response' => $token]);
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FAILONERROR => true]);
        $reply = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $body, 'timeout' => 10, 'follow_location' => 0]]);
        $reply = @file_get_contents($url, false, $ctx);
    }
    $result = is_string($reply) ? json_decode($reply, true) : null;
    return is_array($result) && captcha_result_valid($result, $action);
}
