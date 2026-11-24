<?php
/* ==========================================================================
 * admin/user_form.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Admin only.
 *   user_form.php        CREATE an account (name, email, role, password)
 *   user_form.php?id=7   UPDATE it (password only if a new one is typed),
 *                        plus links to edit their profile and their flats
 * Validated in PHP (and the browser). Emails stay unique; an admin can't
 * remove their own admin role.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$user = null;
if ($id) {
    $s = $pdo->prepare('SELECT u.id, u.name, u.email, u.role, u.created_at, p.user_id AS has_profile, p.city, p.photo
                        FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?');
    $s->execute([$id]);
    $user = $s->fetch();
    if (!$user) { set_flash('error', 'That user does not exist.'); redirect('admin/dashboard.php'); }
}

$values = ['name' => $user['name'] ?? '', 'email' => $user['email'] ?? '', 'role' => $user['role'] ?? 'user'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $values = [
        'name'  => trim(preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? ''))),
        'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
        'role'  => ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user',
    ];
    $password = (string) ($_POST['password'] ?? '');

    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 60 || !preg_match("/^[\\p{L} .'-]+$/u", $values['name'])) {
        $errors['name'] = 'Use 2 to 60 letters (spaces, dots, hyphens and apostrophes are fine).';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 150) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $dup = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
        $dup->execute([$values['email'], $id]);
        if ($dup->fetchColumn()) $errors['email'] = 'Another account already uses this email.';
    }
    if (!$user || $password !== '') {
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password) || strlen($password) > 72) {
            $errors['password'] = 'Use 8 to 72 characters, with a letter and a number.';
        }
    }
    if ($user && $id === (int) $_SESSION['user_id'] && $values['role'] !== 'admin') {
        $errors['role'] = "You can't remove your own admin role.";
    }

    if (!$errors) {
        if ($user) {
            $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?')->execute([$values['name'], $values['email'], $values['role'], $id]);
            if ($password !== '') $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            if ($id === (int) $_SESSION['user_id']) $_SESSION['name'] = $values['name'];
            set_flash('success', $values['name'] . "'s account was updated" . ($password !== '' ? ', with a new password.' : '.'));
            redirect('admin/user_form.php?id=' . $id);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (DB_DRIVER === 'pgsql') {
            $s = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?) RETURNING id');
            $s->execute([$values['name'], $values['email'], $hash, $values['role']]); $newId = (int) $s->fetchColumn();
        } else {
            $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute([$values['name'], $values['email'], $hash, $values['role']]);
            $newId = (int) $pdo->lastInsertId();
        }
        set_flash('success', 'Account created for ' . $values['name'] . ($values['role'] === 'user' ? '. Next, fill in their profile.' : '.'));
        redirect($values['role'] === 'user' ? 'admin/profile_edit.php?user_id=' . $newId : 'admin/dashboard.php');
    }
}

$flats = [];
if ($user) {
    $f = $pdo->prepare('SELECT id, title, city, rent, status FROM listings WHERE user_id = ? ORDER BY created_at DESC');
    $f->execute([$id]); $flats = $f->fetchAll();
}

$err = fn(string $k) => '<div class="field-error" data-error-for="' . $k . '">' . e($errors[$k] ?? '') . '</div>';
$inv = fn(string $k) => isset($errors[$k]) ? 'is-invalid' : '';

$pageTitle = $user ? 'Edit user' : 'Add a user';
require __DIR__ . '/../includes/header.php';
?>

<div class="container container-narrow">
    <div class="page-head">
        <div><h1><?= $user ? 'Edit ' . e($user['name']) : 'Add a user' ?></h1>
            <p class="muted"><?= $user ? 'Joined ' . e(nice_date($user['created_at'])) . '.' : 'They can log in with this email and password straight away.' ?></p></div>
        <a class="btn" href="<?= e(url('admin/dashboard.php')) ?>">All users</a>
    </div>

    <?php if ($errors): ?><div class="flash flash-error" role="alert">Please fix the highlighted <?= count($errors) === 1 ? 'field' : 'fields' ?>. Nothing was saved.</div><?php endif; ?>

    <form id="user-form" class="card form-card form-grid" method="post" action="" novalidate>
        <h2 class="section-title">Account</h2>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" maxlength="60" value="<?= e($values['name']) ?>" class="<?= $inv('name') ?>" required>
            <?= $err('name') ?>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="150" value="<?= e($values['email']) ?>" class="<?= $inv('email') ?>" required>
            <?= $err('email') ?>
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" class="<?= $inv('role') ?>">
                <option value="user" <?= $values['role'] === 'user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= $values['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <?= $err('role') ?>
        </div>
        <div class="form-group">
            <label for="password"><?= $user ? 'New password' : 'Password' ?></label>
            <input type="password" id="password" name="password" autocomplete="new-password" class="<?= $inv('password') ?>" <?= $user ? '' : 'required' ?>>
            <div class="hint"><?= $user ? 'Leave empty to keep their current password. ' : '' ?>At least 8 characters, with a letter and a number.</div>
            <?= $err('password') ?>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $user ? 'Save changes' : 'Create account' ?></button></div>
    </form>

    <?php if ($user && $user['role'] === 'user'): ?>
        <div class="card form-card">
            <h2 class="section-title">Profile</h2>
            <?php if ($user['has_profile']): ?>
                <p class="muted">Lives in <?= e($user['city']) ?>.</p>
                <div class="btn-row">
                    <a class="btn btn-primary" href="<?= e(url('admin/profile_edit.php?user_id=' . $id)) ?>">Edit profile</a>
                    <a class="btn" href="<?= e(url('profile/view_profile.php?id=' . $id)) ?>">View profile</a>
                </div>
            <?php else: ?>
                <p class="muted">No profile yet, so they don't show up in Discover and can't own a flat.</p>
                <a class="btn btn-primary" href="<?= e(url('admin/profile_edit.php?user_id=' . $id)) ?>">Create their profile</a>
            <?php endif; ?>
        </div>

        <div class="card form-card">
            <h2 class="section-title">Properties (<?= count($flats) ?>)</h2>
            <?php foreach ($flats as $fl): ?>
                <div class="admin-flat-row"><a href="<?= e(url('listings/view.php?id=' . (int) $fl['id'])) ?>"><?= e($fl['title']) ?></a>
                    <span class="muted small"><?= e($fl['city']) ?> · <?= e(rupees((int) $fl['rent'])) ?> · <?= e($fl['status']) ?></span>
                    <a class="btn btn-sm" href="<?= e(url('listings/edit.php?id=' . (int) $fl['id'])) ?>">Edit</a></div>
            <?php endforeach; ?>
            <?php if ($user['has_profile']): ?><a class="btn" href="<?= e(url('listings/edit.php?owner=' . $id)) ?>">Add a property for <?= e(explode(' ', $user['name'])[0]) ?></a><?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('admin/delete_user.php')) ?>" class="danger-zone"
              data-confirm="Delete <?= e($user['name']) ?>? Their profile, flats, matches and messages will be removed permanently." data-confirm-button="Delete user">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $id ?>">
            <button class="btn btn-danger">Delete this user</button>
        </form>
    <?php endif; ?>
</div>

<?php
$extraScripts = <<<'HTML'
<script>
RM.validateForm(document.getElementById('user-form'), function (f) {
    var e = {}, p = f.password.value, isNew = f.password.required;
    if (f.name.value.trim().length < 2) e.name = 'Use 2 to 60 letters.';
    if (!RM.isEmail(f.email.value)) e.email = 'Enter a valid email address.';
    if ((isNew || p) && (p.length < 8 || !/[A-Za-z]/.test(p) || !/[0-9]/.test(p))) e.password = 'Use 8 to 72 characters, with a letter and a number.';
    return e;
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
