<?php
/* ==========================================================================
 * auth/forgot.php  —  Owner: M2 (Authentication)
 * --------------------------------------------------------------------------
 * "Forgot password": enter your email, get a one-time link (valid 60 minutes).
 *   1. CSRF check, valid email, Cloudflare human check
 *   2. If the email has an account (and fewer than 3 links this hour):
 *      store SHA-256(token) and email the link
 *   3. Always show the same message, so nobody can find out which emails
 *      are registered.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/captcha_verify.php';
require_once __DIR__ . '/reset_helpers.php';

if (is_logged_in()) redirect('profile/edit_profile.php');

$errors = [];
$email = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors['email'] = 'Enter the email you signed up with.';
    if (!$errors && !verify_captcha($_POST['cf-turnstile-response'] ?? null, 'forgot')) $errors['captcha'] = 'Verification failed. Please try again.';

    if (!$errors) {
        $s = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ?');
        $s->execute([$email]);
        if ($user = $s->fetch()) {
            pr_db($pdo, function () use ($pdo, $user) {
                $n = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > ?');
                $n->execute([$user['id'], date('Y-m-d H:i:s', time() - 3600)]);
                if ((int) $n->fetchColumn() >= RESET_MAX_PER_HOUR) return;

                $token = bin2hex(random_bytes(32));
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
                    ->execute([$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + RESET_MINUTES * 60)]);

                $link = site_origin() . '/auth/reset.php?token=' . $token;
                $first = explode(' ', $user['name'])[0];
                $text = "Hi $first,\n\nSomeone (hopefully you) asked to reset your RoomieMatch password.\n\nSet a new one here (the link works once, for "
                      . RESET_MINUTES . " minutes):\n$link\n\nIf you didn't ask, ignore this email. Your password stays the same.\n\nRoomieMatch";
                $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#2B2D42;max-width:520px">'
                      . '<p>Hi ' . htmlspecialchars($first) . ',</p><p>Someone (hopefully you) asked to reset your RoomieMatch password.</p>'
                      . '<p><a href="' . htmlspecialchars($link) . '" style="display:inline-block;background:#FF5A5F;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:bold">Set a new password</a></p>'
                      . '<p style="color:#6C6F86;font-size:13px">The link works once, for ' . RESET_MINUTES . ' minutes. If you didn\'t ask, ignore this email. Your password stays the same.</p></div>';
                send_mail($user['email'], 'Reset your RoomieMatch password', $text, $html);
            });
        }
        $sent = true;      // same answer whether or not the email exists
    }
}

$pageTitle = 'Forgot password';
$bodyClass = 'page-auth';
$extraHead = captcha_script();
require __DIR__ . '/../includes/header.php';
?>

<div class="container auth-wrap">
    <div class="card auth-card">
        <?php $stageTitle = 'Happens to <em>everyone</em>.'; $stageText = "We'll send a link to your email so you can set a new password."; $stageMood = ''; require __DIR__ . '/_stage.php'; ?>

        <div class="auth-form">
            <h1>Forgot your password?</h1>
            <?php if ($sent): ?>
                <div class="flash flash-success" role="status">If <strong><?= e($email) ?></strong> has an account, a reset link is on its way. It works for <?= RESET_MINUTES ?> minutes. Check your spam folder too.</div>
                <p class="muted"><a href="<?= e(url('auth/login.php')) ?>">Back to log in</a></p>
            <?php else: ?>
                <p class="muted">Enter the email you signed up with. Remembered it? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>
                <?php if (!mail_configured()): ?><div class="flash flash-error" role="alert">Password reset emails aren't switched on yet. Please contact the RoomieMatch team.</div><?php endif; ?>
                <form id="forgot-form" method="post" action="" class="form-grid" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>" required>
                        <div class="field-error" data-error-for="email"><?= e($errors['email'] ?? '') ?></div>
                    </div>
                    <div class="form-group">
                        <div class="captcha-box" data-field="captcha"><?= captcha_widget('forgot') ?></div>
                        <div class="field-error" data-error-for="captcha"><?= e($errors['captcha'] ?? '') ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Send reset link</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(url('js/characters.js')) . '"></script><script src="' . e(url('js/auth-stage.js')) . '?v=1" defer></script>' . <<<'HTML'
<script>
var f = document.getElementById('forgot-form');
if (f) RM.validateForm(f, function (form) {
    return RM.isEmail(form.email.value) ? {} : { email: 'Enter the email you signed up with.' };
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
