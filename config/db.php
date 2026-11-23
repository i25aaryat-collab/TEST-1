<?php
/* ==========================================================================
 * config/db.php  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * Opens ONE connection to MySQL using PDO and stores it in $pdo.
 * Every page that needs the database starts with:
 *     require_once __DIR__ . '/../config/db.php';   // gives you $pdo
 *
 * Why PDO + prepared statements? The SQL and the user's data are sent to
 * MySQL separately, so typed input can never change the query.
 * That is how we stop SQL injection.
 * ========================================================================== */

require_once __DIR__ . '/config.php';

$pdo = null;
foreach (explode(',', DB_HOST) as $host) {          // try each host until one answers
    try {
        $dsn = DB_DRIVER === 'pgsql'
            ? 'pgsql:host=' . trim($host) . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=' . (getenv('DB_SSLMODE') ?: 'prefer') . ';connect_timeout=4'   // pgsql ignores ATTR_TIMEOUT: without this a dead host stalls the page for minutes
            : 'mysql:host=' . trim($host) . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errors throw exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as ['column' => value]
            // MySQL: real prepared statements. PostgreSQL via Supabase's connection pooler:
            // PDO prepares the statement itself (still safely escaped), which the pooler needs.
            PDO::ATTR_EMULATE_PREPARES   => DB_DRIVER === 'pgsql',
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        break;
    } catch (PDOException $e) {
        // Keep diagnostics in Vercel's private runtime logs; never show the
        // underlying exception to a visitor because it may reveal infrastructure details.
        error_log('Database connection attempt failed (' . DB_DRIVER . '): ' . $e->getMessage());
        $pdo = null;
    }
}

if (!$pdo) {
    // Never show the raw error (it can leak the password or server name).
    http_response_code(500);
    exit('<h2 style="font-family:sans-serif">The site is temporarily unavailable.</h2>'
       . '<p style="font-family:sans-serif">Please try again shortly.</p>');
}

// Store and show times in Indian Standard Time, whatever the server's zone is.
$pdo->exec(DB_DRIVER === 'pgsql' ? "SET TIME ZONE 'Asia/Kolkata'" : "SET time_zone = '+05:30'");

/** True when an INSERT failed because the row already exists (UNIQUE key). */
function is_duplicate_error(PDOException $e): bool
{
    return in_array((string) $e->getCode(), ['23000', '23505'], true);   // MySQL, PostgreSQL
}
