<?php
/* ==========================================================================
 * auth/register.php  —  Owner: M2 (Authentication)
 * --------------------------------------------------------------------------
 * Create an account: name, email, password, confirm password, reCAPTCHA.
 * Rules (checked in JavaScript AND again here in PHP):
 *   - name: 2 to 100 characters, letters, spaces, dots, hyphens, apostrophes
 *   - email: valid format, max 150 characters, not already registered
 *   - password: at least 8 characters with a letter and a number
 *   - confirm password must match
 * The password is stored only as password_hash() (bcrypt with a random
 * salt), never as plain text. After registering the user is logged in and
 * sent to create their profile.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/captcha_verify.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'swipe/swipe.php');
}

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name     = trim(preg_replace('/\s+/', ' ', $_POST['name'] ?? ''));
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    // ---- Name ----
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Name must be 2 to 100 characters.';
    } elseif (!preg_match("/^[A-Za-z .'-]+$/", $name)) {
        $errors['name'] = 'Use letters, spaces, dots, hyphens or apostrophes only.';
    }

    // ---- Email ----
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with this email already exists. Try logging in.';
        }
    }

    // ---- Password ----
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Use at least 8 characters, with at least one letter and one number.';
    }
    if ($confirm !== $password) {
        $errors['confirm_password'] = 'The two passwords do not match.';
    }

    // ---- CAPTCHA (only asked of Google once everything else is fine) ----
    if (!$errors && !verify_captcha($_POST['g-recaptcha-response'] ?? null)) {
        $errors['captcha'] = 'Please tick "I\'m not a robot" and try again.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

            // Log the new user straight in
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            $_SESSION['name']    = $name;
            $_SESSION['role']    = 'user';
            set_flash('success', 'Account created! Now tell us a bit about how you live.');
            redirect('profile/create_profile.php');
        } catch (PDOException $ex) {
            // 23000 = duplicate email (two sign-ups at the same moment)
            if (is_duplicate_error($ex)) {
                $errors['email'] = 'An account with this email already exists. Try logging in.';
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle = 'Create account';
$bodyClass = 'page-auth';
$extraHead = captcha_script();
require __DIR__ . '/../includes/header.php';

/** Small helper: class + message for one field (keeps the HTML below short) */
function field_class(array $errors, string $name): string { return isset($errors[$name]) ? 'is-invalid' : ''; }
?>

<div class="container auth-wrap">
    <div class="card auth-card">
        <?php $stageTitle = "Find your people. <em>Then</em> your flat."; $stageText = 'Two minutes to sign up. A lot less time arguing about dishes later.'; $stageMood = $errors ? 'sad' : ''; require __DIR__ . '/_stage.php'; ?>

        <div class="auth-form">
            <h1>Create your account</h1>
            <p class="muted">Already a member? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>

            <form id="register-form" method="post" action="" class="form-grid" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" value="<?= e($name) ?>" maxlength="100" autocomplete="name" class="<?= field_class($errors, 'name') ?>" required>
                    <div class="field-error" data-error-for="name"><?= e($errors['name'] ?? '') ?></div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="150" autocomplete="email" class="<?= field_class($errors, 'email') ?>" required>
                    <div class="field-error" data-error-for="email"><?= e($errors['email'] ?? '') ?></div>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input type="password" id="password" name="password" autocomplete="new-password" class="<?= field_class($errors, 'password') ?>" required>
                            <button type="button" class="password-toggle" aria-label="Show password">Show</button>
                        </div>
                        <span class="hint">8+ characters, a letter and a number</span>
                        <div class="field-error" data-error-for="password"><?= e($errors['password'] ?? '') ?></div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm password</label>
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" class="<?= field_class($errors, 'confirm_password') ?>" required>
                        <div class="field-error" data-error-for="confirm_password"><?= e($errors['confirm_password'] ?? '') ?></div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="captcha-box" data-field="captcha"><?= captcha_widget() ?></div>
                    <div class="field-error" data-error-for="captcha"><?= e($errors['captcha'] ?? '') ?></div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Create account</button>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(url('js/characters.js')) . '"></script><script src="' . e(url('js/auth-stage.js')) . '?v=1" defer></script>' . <<<'HTML'
<script>
// Same rules as the PHP at the top of this file
RM.validateForm(document.getElementById('register-form'), function (form) {
    const errors = {};
    const name = form.name.value.trim();
    if (name.length < 2 || name.length > 100) errors.name = 'Name must be 2 to 100 characters.';
    else if (!/^[A-Za-z .'-]+$/.test(name)) errors.name = 'Use letters, spaces, dots, hyphens or apostrophes only.';

    if (!RM.isEmail(form.email.value) || form.email.value.length > 150) errors.email = 'Enter a valid email address.';

    if (!RM.isStrongPassword(form.password.value)) errors.password = 'Use at least 8 characters, with at least one letter and one number.';
    if (form.confirm_password.value !== form.password.value || form.confirm_password.value === '') errors.confirm_password = 'The two passwords do not match.';

    if (window.grecaptcha && document.querySelector('.g-recaptcha') && grecaptcha.getResponse() === '') {
        errors.captcha = 'Please tick "I\'m not a robot".';
    }
    return errors;
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
