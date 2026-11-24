<?php
/**
 * includes/mailer.php  —  Owner: M2 (Authentication)
 * Sends the site's emails (today: password reset links). Uses whichever is set
 * in Vercel env, in this order:
 *   1. SMTP  — SMTP_HOST, SMTP_PORT (465 = SSL, 587 = STARTTLS), SMTP_USER, SMTP_PASS
 *              e.g. a Gmail account with an App Password; sends to anyone.
 *   2. Resend — RESEND_API_KEY (needs a verified domain to send to anyone).
 *   3. MAIL_LOG_DIR — local testing only: the email is written to a file.
 * MAIL_FROM sets the sender ("RoomieMatch <you@gmail.com>"); defaults to SMTP_USER.
 * Secrets stay in env vars and are never printed.
 */

function mail_configured(): bool
{
    return (bool) (getenv('SMTP_HOST') || getenv('RESEND_API_KEY') || getenv('MAIL_LOG_DIR'));
}

function mail_from(): string
{
    $from = getenv('MAIL_FROM') ?: (getenv('SMTP_USER') ? 'RoomieMatch <' . getenv('SMTP_USER') . '>' : 'RoomieMatch <onboarding@resend.dev>');
    return str_replace(["\r", "\n"], '', $from);
}

/** Send one email. Returns true when the mail server accepted it. */
function send_mail(string $to, string $subject, string $text, string $html): bool
{
    $to = str_replace(["\r", "\n"], '', $to);
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    try {
        if (getenv('SMTP_HOST')) return smtp_send($to, $subject, $text, $html);
        if (getenv('RESEND_API_KEY')) return resend_send($to, $subject, $text, $html);
        if ($dir = getenv('MAIL_LOG_DIR')) {
            return (bool) file_put_contents(rtrim($dir, '/') . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt',
                "To: $to\nSubject: $subject\n\n$text\n");
        }
    } catch (Throwable $e) {
        error_log('Mail failed: ' . $e->getMessage());
    }
    return false;
}

function resend_send(string $to, string $subject, string $text, string $html): bool
{
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . getenv('RESEND_API_KEY'), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['from' => mail_from(), 'to' => [$to], 'subject' => $subject, 'text' => $text, 'html' => $html]),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) error_log('Resend answered ' . $code . ': ' . substr((string) $body, 0, 200));
    return $code >= 200 && $code < 300;
}

/** A small SMTP client (AUTH LOGIN over SSL or STARTTLS), enough for Gmail / Outlook / Zoho. */
function smtp_send(string $to, string $subject, string $text, string $html): bool
{
    $host = getenv('SMTP_HOST'); $port = (int) (getenv('SMTP_PORT') ?: 465);
    $user = getenv('SMTP_USER'); $pass = getenv('SMTP_PASS');
    $fp = stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 10);
    if (!$fp) throw new RuntimeException("connect: $errstr");
    stream_set_timeout($fp, 10);
    $read = function () use ($fp) { $out = ''; while (($line = fgets($fp, 515)) !== false) { $out .= $line; if (isset($line[3]) && $line[3] === ' ') break; } return $out; };
    $cmd = function (string $c, array $ok) use ($fp, $read) {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int) substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP ' . trim(substr($r, 0, 80)));
        return $r;
    };
    $cmd('', [220]);
    $cmd('EHLO roomiematch', [250]);
    if ($port !== 465) {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS failed');
        $cmd('EHLO roomiematch', [250]);
    }
    $cmd('AUTH LOGIN', [334]); $cmd(base64_encode($user), [334]); $cmd(base64_encode($pass), [235]);
    preg_match('/<([^>]+)>/', mail_from(), $m);
    $cmd('MAIL FROM:<' . ($m[1] ?? $user) . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $b = 'b' . bin2hex(random_bytes(8));
    $msg = 'From: ' . mail_from() . "\r\nTo: <$to>\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nDate: " . date('r')
         . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . '@roomiematch>' . "\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n"
         . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
         . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--";
    $cmd(str_replace("\n.", "\n..", $msg) . "\r\n.", [250]);
    fwrite($fp, "QUIT\r\n"); fclose($fp);
    return true;
}
