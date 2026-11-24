<?php
/* ==========================================================================
 * auth/reset.php  —  Owner: M2 (Authentication)
 * --------------------------------------------------------------------------
 * Opened from the emailed link (?token=...). Checks the token is unused and
 * unexpired, then sets a new password (same rules as sign-up), marks every
 * open link for that account as used and clears failed-login lockouts.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/reset_helpers.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$reset = pr_db($pdo, fn() => find_reset($pdo, $token));
$errors = [];

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Use at least 8 characters, with a letter and a number.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Use 72 characters or fewer.';
    }
    if (!$errors && $password !== $confirm) $errors['confirm'] = "The two passwords don't match.";

    if (!$errors) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL')->execute([date('Y-m-d H:i:s'), $reset['user_id']]);
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$reset['email']]);
        $pdo->commit();
        if (is_logged_in()) { $_SESSION = []; session_regenerate_id(true); }
        set_flash('success', 'Password changed. Log in with your new password.');
        redirect('auth/login.php');
    }
}

$pageTitle = 'Set a new password';
$bodyClass = 'page-auth';
require __DIR__ . '/../includes/header.php';
?>

<div class="container auth-wrap">
    <div class="card auth-card">
        <?php $stageTitle = 'A <em>fresh</em> start.'; $stageText = 'Pick a new password and you are back in.'; $stageMood = $reset ? '' : 'sad'; require __DIR__ . '/_stage.php'; ?>

        <div class="auth-form">
            <?php if (!$reset): ?>
                <h1>This link has expired</h1>
                <p class="muted">Reset links work once, for <?= RESET_MINUTES ?> minutes. Ask for a new one and use the latest email.</p>
                <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('auth/forgot.php')) ?>">Send a new link</a>
            <?php else: ?>
                <h1>Set a new password</h1>
                <p class="muted">For <?= e($reset['email']) ?></p>
                <form id="reset-form" method="post" action="<?= e(url('auth/reset.php')) ?>" class="form-grid" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="form-group">
                        <label for="password">New password</label>
                        <div class="password-wrap">
                            <input type="password" id="password" name="password" autocomplete="new-password" class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                            <button type="button" class="password-toggle" aria-label="Show password">Show</button>
                        </div>
                        <div class="hint">At least 8 characters, with a letter and a number.</div>
                        <div class="field-error" data-error-for="password"><?= e($errors['password'] ?? '') ?></div>
                    </div>
                    <div class="form-group">
                        <label for="confirm">Type it again</label>
                        <input type="password" id="confirm" name="confirm" autocomplete="new-password" class="<?= isset($errors['confirm']) ? 'is-invalid' : '' ?>" required>
                        <div class="field-error" data-error-for="confirm"><?= e($errors['confirm'] ?? '') ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Save new password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(url('js/characters.js')) . '"></script><script src="' . e(url('js/auth-stage.js')) . '?v=1" defer></script>' . <<<'HTML'
<script>
var f = document.getElementById('reset-form');
if (f) RM.validateForm(f, function (form) {
    var e = {}, p = form.password.value;
    if (p.length < 8 || !/[A-Za-z]/.test(p) || !/[0-9]/.test(p)) e.password = 'Use at least 8 characters, with a letter and a number.';
    else if (p !== form.confirm.value) e.confirm = "The two passwords don't match.";
    return e;
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
