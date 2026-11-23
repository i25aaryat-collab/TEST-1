<?php
/* ==========================================================================
 * auth/captcha_verify.php  —  Owner: M2 (Authentication)
 * --------------------------------------------------------------------------
 * Google reCAPTCHA v2 ("I'm not a robot" checkbox).
 *
 * How it works:
 *   1. The checkbox runs in the browser. When ticked, Google puts a one-time
 *      token in a hidden field called g-recaptcha-response.
 *   2. The form sends that token to our PHP page.
 *   3. verify_captcha() sends the token + our SECRET key to Google's
 *      siteverify address. Google replies {"success": true} or false.
 * The secret key never reaches the browser, so a bot cannot fake step 3.
 * Keys live in config/config.php.
 * ========================================================================== */

require_once __DIR__ . '/../config/config.php';

/** HTML for the checkbox. Put it inside the <form>. */
function captcha_widget(): string
{
    if (!CAPTCHA_ENABLED) {
        return '<p class="muted small">CAPTCHA is switched off in config/config.php (offline mode).</p>';
    }
    return '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES) . '"></div>';
}

/** The <script> tag that loads Google's checkbox. Put it in $extraHead. */
function captcha_script(): string
{
    return CAPTCHA_ENABLED ? '<script src="https://www.google.com/recaptcha/api.js" async defer></script>' : '';
}

/**
 * Ask Google whether the token from the form is genuine.
 * Returns true only when Google says "success".
 */
function verify_captcha(?string $token): bool
{
    if (!CAPTCHA_ENABLED) {
        return true;                       // offline demo mode
    }
    if (!$token || RECAPTCHA_SECRET_KEY === '') {
        return false;
    }

    $postData = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $endpoint = 'https://www.google.com/recaptcha/api/siteverify';
    $reply = false;

    // Most hosts have cURL; if not, fall back to file_get_contents
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $reply = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 10,
        ]]);
        $reply = @file_get_contents($endpoint, false, $context);
    }

    if ($reply === false) {
        return false;                      // could not reach Google: fail safe
    }
    $result = json_decode($reply, true);
    return !empty($result['success']);
}
