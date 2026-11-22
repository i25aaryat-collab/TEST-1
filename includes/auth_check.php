<?php
/* ==========================================================================
 * includes/auth_check.php  —  Owner: M2 (Authentication)
 * --------------------------------------------------------------------------
 * Include this at the top of every page (after config/db.php). It:
 *   1. starts the session safely
 *   2. gives you the "gatekeeper" functions:
 *        require_login()    must be logged in
 *        require_admin()    must be logged in AND an admin
 *        require_profile()  must have filled in a profile (for swipe/chat)
 *   3. gives CSRF protection for forms: csrf_field() and check_csrf()
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Keeps login sessions in the `sessions` table instead of files. Only used
 * when STORE_IN_DB is on (Vercel), because serverless servers forget files.
 */
class DbSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo) {}
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string
    {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
        return (string) ($stmt->fetchColumn() ?: '');
    }
    public function write(string $id, string $data): bool
    {
        $sql = DB_DRIVER === 'pgsql'
            ? 'INSERT INTO sessions (id, data, updated_at) VALUES (?, ?, ?) ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, updated_at = EXCLUDED.updated_at'
            : 'REPLACE INTO sessions (id, data, updated_at) VALUES (?, ?, ?)';
        return $this->pdo->prepare($sql)->execute([$id, $data, time()]);
    }
    public function destroy(string $id): bool
    {
        return $this->pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
    }
    public function gc(int $maxLifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE updated_at < ?');
        $stmt->execute([time() - $maxLifetime]);
        return $stmt->rowCount();
    }
}

// ---------- 1. Start the session -------------------------------------------
// httponly: JavaScript cannot read the session cookie (protects against XSS)
// samesite=Lax: other websites cannot quietly send our cookie in a form post
if (session_status() === PHP_SESSION_NONE) {
    if (STORE_IN_DB) {
        session_set_save_handler(new DbSessionHandler($pdo), true);
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    ]);
    session_start();
}

// ---------- 2. Who is logged in? -------------------------------------------

/** True when someone is logged in. */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** True when the logged-in person is an admin. */
function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Stop here and go to the login page if nobody is logged in.
 * Also checks the account still exists (an admin may have deleted it while
 * the person was logged in), and refreshes the name and role.
 */
function require_login(): void
{
    global $pdo;

    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }

    if (isset($pdo)) {
        $stmt = $pdo->prepare('SELECT name, role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {                       // account was deleted
            $_SESSION = [];
            session_destroy();
            session_start();
            set_flash('error', 'Your account no longer exists.');
            redirect('auth/login.php');
        }
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
    }
}

/** Only admins may continue. Everyone else is sent back to the home page. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        set_flash('error', 'That page is for admins only.');
        redirect('index.php');
    }
}

/**
 * Swipe, matches and chat need a profile to work (the compatibility score
 * uses it). Users without one are sent to fill it in first.
 * Admins don't swipe, so they go to their dashboard.
 * Returns the logged-in user's profile row.
 */
function require_profile(): array
{
    global $pdo;
    require_login();

    if (is_admin()) {
        redirect('admin/dashboard.php');
    }

    $stmt = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();

    if (!$profile) {
        set_flash('error', 'Create your profile first, it takes a minute.');
        redirect('profile/create_profile.php');
    }
    return $profile;
}

// ---------- 3. CSRF protection ----------------------------------------------
// Every form carries a secret random token that only our pages know.
// If a form arrives without the right token it came from somewhere else
// (a "cross-site request forgery"), so we refuse it.

/** The token for this session (made once, then reused). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to put inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Call at the start of every POST handler. Accepts the token from a form
 * field or from the X-CSRF-Token header (used by fetch() requests).
 * Pass true for AJAX endpoints so the refusal comes back as JSON.
 */
function check_csrf(bool $json = false): void
{
    $sent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json');
            exit(json_encode(['ok' => false, 'error' => 'Your session expired. Please refresh the page.']));
        }
        exit('Security check failed. Please go back, refresh the page and try again.');
    }
}
