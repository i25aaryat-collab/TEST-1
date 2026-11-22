<?php
/* ==========================================================================
 * auth/login.php  —  Owner: M2 (Authentication)
 * --------------------------------------------------------------------------
 * Log in with email + password + reCAPTCHA.
 * Server-side steps, in order:
 *   1. CSRF check (form came from our page)
 *   2. Fields filled in and email looks valid
 *   3. CAPTCHA verified with Google
 *   4. Not locked out (5 wrong passwords in 10 minutes locks the email)
 *   5. password_verify() against the stored hash
 * On success the session id is replaced (session_regenerate_id) so an old,
 * stolen session id becomes useless. The error never says WHICH of email
 * or password was wrong, so nobody can find out which emails are registered.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/captcha_verify.php';

/** Where a user goes after logging in */
function home_for_logged_in_user(PDO $pdo): string
{
    if (is_admin()) return 'admin/dashboard.php';
    $stmt = $pdo->prepare('SELECT 1 FROM profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ? 'swipe/swipe.php' : 'profile/create_profile.php';
}

// Already logged in? No need to see this page.
if (is_logged_in()) {
    redirect(home_for_logged_in_user($pdo));
}

$errors = [];          // field name => message (shown next to the field)
$formError = '';       // one message at the top of the form
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    // Batch accounts log in with a username; it maps to a hidden email
    if (preg_match('/^[a-z]{2,30}$/', $email)) $email .= '@' . USERNAME_DOMAIN;

    // ---- Step 2: basic field checks ----
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email or username.';
    }
    if ($password === '') {
        $errors['password'] = 'Enter your password.';
    }

    // ---- Step 3: CAPTCHA ----
    if (!$errors && !verify_captcha($_POST['g-recaptcha-response'] ?? null)) {
        $errors['captcha'] = 'Please tick "I\'m not a robot" and try again.';
    }

    if (!$errors) {
        // ---- Step 4: lockout check ----
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts
                               WHERE email = ? AND attempted_at > ?');
        $stmt->execute([$email, date('Y-m-d H:i:s', time() - LOCKOUT_MINUTES * 60)]);
        $recentFails = (int) $stmt->fetchColumn();

        if ($recentFails >= MAX_LOGIN_ATTEMPTS) {
            $formError = 'Too many failed attempts. For your safety this account is locked for '
                       . LOCKOUT_MINUTES . ' minutes. Please try again later.';
        } else {
            // ---- Step 5: check email + password ----
            $stmt = $pdo->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Success: forget old failures, protect the session, remember who this is
                $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['role']    = $user['role'];
                set_flash('success', 'Welcome back, ' . $user['name'] . '!');
                redirect(home_for_logged_in_user($pdo));
            }

            // Failure: record it, then show the same message for any mistake
            $pdo->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)')
                ->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '']);
            $left = MAX_LOGIN_ATTEMPTS - ($recentFails + 1);
            $formError = 'Invalid email or password.';
            if ($left <= 2) {
                $formError .= $left > 0
                    ? " $left " . ($left === 1 ? 'try' : 'tries') . ' left before a ' . LOCKOUT_MINUTES . '-minute lock.'
                    : ' The account is now locked for ' . LOCKOUT_MINUTES . ' minutes.';
            }
        }
    }
}

$pageTitle = 'Log in';
$bodyClass = 'page-auth';
$extraHead = captcha_script();
require __DIR__ . '/../includes/header.php';
?>

<div class="container auth-wrap">
    <div class="card auth-card">
        <?php $stageTitle = "They've been <em>waiting</em> for you."; $stageText = 'Log back in and see who in your city fits your bedtime, your mess and your music.'; $stageMood = $formError ? 'sad' : ''; require __DIR__ . '/_stage.php'; ?>

        <div class="auth-form">
            <h1>Log in</h1>
            <p class="muted">New here? <a href="<?= e(url('auth/register.php')) ?>">Create an account</a></p>

            <?php if ($formError): ?>
                <div class="flash flash-error" role="alert"><?= e($formError) ?></div>
            <?php endif; ?>

            <form id="login-form" method="post" action="" class="form-grid" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="email">Email or username</label>
                    <input type="text" id="email" name="email" value="<?= e(str_replace('@' . USERNAME_DOMAIN, '', $email)) ?>" autocomplete="username"
                           class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>" required>
                    <div class="field-error" data-error-for="email"><?= e($errors['email'] ?? '') ?></div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" autocomplete="current-password"
                               class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                        <button type="button" class="password-toggle" aria-label="Show password">Show</button>
                    </div>
                    <div class="field-error" data-error-for="password"><?= e($errors['password'] ?? '') ?></div>
                </div>

                <div class="form-group">
                    <div class="captcha-box" data-field="captcha"><?= captcha_widget() ?></div>
                    <div class="field-error" data-error-for="captcha"><?= e($errors['captcha'] ?? '') ?></div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Log in</button>
            </form>

            <div class="demo-hint">
                <strong>IPM batch:</strong> log in with your first name as the username (password shared by your CR).<br>
                <strong>Demo:</strong> usera@roomiematch.test · userb@roomiematch.test · admin@roomiematch.test (password <code>Roomie@123</code>)
            </div>
        </div>
    </div>
</div>

<?php
// Client-side rules for this form (the same rules run again in PHP above)
$extraScripts = '<script src="' . e(url('js/characters.js')) . '"></script><script src="' . e(url('js/auth-stage.js')) . '?v=1" defer></script>' . <<<'HTML'
<script>
RM.validateForm(document.getElementById('login-form'), function (form) {
    const errors = {};
    const id = form.email.value.trim();
    if (!RM.isEmail(id) && !/^[a-zA-Z]{2,30}$/.test(id)) errors.email = 'Enter a valid email or username.';
    if (!RM.isFilled(form.password.value)) errors.password = 'Enter your password.';
    // grecaptcha only exists when Google's script loaded (i.e. we are online)
    if (window.grecaptcha && document.querySelector('.g-recaptcha') && grecaptcha.getResponse() === '') {
        errors.captcha = 'Please tick "I\'m not a robot".';
    }
    return errors;
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
