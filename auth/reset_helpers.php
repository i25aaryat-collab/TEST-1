<?php
/**
 * auth/reset_helpers.php  —  Owner: M2 (Authentication)
 * Shared by forgot.php (sends the link) and reset.php (sets the new password).
 * A link holds a random 32-byte token; the database keeps only its SHA-256,
 * it works once, and expires after RESET_MINUTES.
 */
require_once __DIR__ . '/../includes/mailer.php';

const RESET_MINUTES = 60;
const RESET_MAX_PER_HOUR = 3;     // per account, so nobody can flood someone's inbox

/** Run $fn; if password_resets doesn't exist yet, create it from the migration and run again */
function pr_db(PDO $pdo, callable $fn)
{
    try { return $fn(); }
    catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42P01', '42S02'], true)) throw $e;
        $file = __DIR__ . '/../database/migration_v13_password_resets_' . (DB_DRIVER === 'pgsql' ? 'pg' : 'mysql') . '.sql';
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents($file))))) as $sql) $pdo->exec($sql);
        return $fn();
    }
}

/** The site's own address for links in emails. Never taken from an unknown Host header. */
function site_origin(): string
{
    if ($u = getenv('APP_URL')) return rtrim($u, '/');
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $local = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host);
    if ($local) return 'http://' . $host . BASE_URL;
    return 'https://roomiezmatch.vercel.app';
}

/** The valid (unused, unexpired) reset row for a token from a link, or null */
function find_reset(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $s = $pdo->prepare('SELECT r.id, r.user_id, u.email, u.name FROM password_resets r JOIN users u ON u.id = r.user_id
                        WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > ?');
    $s->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
    return $s->fetch() ?: null;
}
