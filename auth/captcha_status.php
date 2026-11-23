<?php
/**
 * auth/captcha_status.php  —  Owner: M2 (Authentication)
 * Health check for the Turnstile setup. Never prints the secret.
 * Asks Cloudflare to check a dummy token: "invalid-input-response" means the secret key is
 * accepted (the dummy token is rightly refused); "invalid-input-secret" means the secret is wrong.
 */
require_once __DIR__ . '/captcha_verify.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$out = ['enabled' => CAPTCHA_ENABLED, 'mode' => TURNSTILE_TEST_KEYS ? 'test keys (env vars not set)' : 'live keys',
        'site_key_starts' => substr(TURNSTILE_SITE_KEY, 0, 6), 'host' => $_SERVER['HTTP_HOST'] ?? ''];
if (!TURNSTILE_TEST_KEYS) {
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => TURNSTILE_SECRET_KEY, 'response' => 'status-check'])]);
    $r = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    $codes = (array) ($r['error-codes'] ?? []);
    $out['cloudflare_says'] = $codes ?: ($r ? 'ok' : 'no answer');
    $out['secret_key'] = in_array('invalid-input-secret', $codes, true) ? 'WRONG' : (in_array('invalid-input-response', $codes, true) ? 'accepted' : 'unknown');
}
echo json_encode($out, JSON_PRETTY_PRINT);
