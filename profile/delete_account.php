<?php
/* ==========================================================================
 * profile/delete_account.php  —  Owner: M3 (Profiles and validation)
 * --------------------------------------------------------------------------
 * CRUD: DELETE. Asks for the password to confirm, then deletes the user.
 * Because every table's foreign key says ON DELETE CASCADE, MySQL removes
 * the profile, swipes, matches and messages automatically. We also remove
 * the photo file, end the session and go to the home page.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

require_login();
if (is_admin()) {
    set_flash('error', 'Admin accounts cannot be deleted from here.');
    redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // Re-check the password so nobody can delete an account left open on a shared laptop
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        $error = 'That password is not correct.';
    } else {
        // Remember the photo file name before the row disappears
        $stmt = $pdo->prepare('SELECT photo FROM profiles WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $photo = $stmt->fetchColumn() ?: null;

        // One DELETE; CASCADE removes everything linked to this user
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$_SESSION['user_id']]);
        delete_photo_file($photo);

        // Log out completely
        $_SESSION = [];
        session_destroy();
        header('Location: ' . url('index.php?deleted=1'));
        exit;
    }
}

$pageTitle = 'Delete account';
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="card danger-zone">
        <h1>Delete your account?</h1>
        <p>This will permanently delete:</p>
        <ul>
            <li>your account and profile</li>
            <li>all your likes and passes</li>
            <li>all your matches and every message in them</li>
        </ul>
        <p class="muted">This cannot be undone.</p>

        <form id="delete-form" method="post" action="" class="form-grid" novalidate
              data-confirm="Last check: your account and all your chats will be gone for good." data-confirm-button="Delete forever">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="password">Type your password to confirm</label>
                <input type="password" id="password" name="password" autocomplete="current-password" class="<?= $error ? 'is-invalid' : '' ?>" required>
                <div class="field-error" data-error-for="password"><?= e($error) ?></div>
            </div>
            <div class="btn-row">
                <a class="btn" href="<?= e(url('profile/view_profile.php')) ?>">Cancel, keep my account</a>
                <button type="submit" class="btn btn-danger">Delete my account</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = <<<'HTML'
<script>
RM.validateForm(document.getElementById('delete-form'), function (form) {
    return RM.isFilled(form.password.value) ? {} : { password: 'Type your password to confirm.' };
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
