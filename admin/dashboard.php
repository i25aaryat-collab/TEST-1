<?php
/* ==========================================================================
 * admin/dashboard.php  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Admin only. Shows:
 *   - counts of users, profiles, swipes, matches and messages
 *   - a table of every user, with search (name or email), a role filter,
 *     sorting (name or join date, either direction) and a Delete button
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_admin();

// ---------- Counts for the stat cards ----------
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'user') AS users,
        (SELECT COUNT(*) FROM profiles)                  AS profiles,
        (SELECT COUNT(*) FROM swipes)                    AS swipes,
        (SELECT COUNT(*) FROM matches)                   AS matches,
        (SELECT COUNT(*) FROM messages)                  AS messages,
        (SELECT COUNT(*) FROM listings)                  AS listings,
        (SELECT COUNT(*) FROM reports WHERE status = 'open') AS open_reports
")->fetch();

// ---------- Search, filter and sort (from the address bar) ----------
$search = trim($_GET['q'] ?? '');
$role   = in_array($_GET['role'] ?? '', ['user', 'admin'], true) ? $_GET['role'] : '';

// Only these exact ORDER BY strings can ever be used. The user picks a key,
// never raw SQL, so sorting cannot be used for SQL injection.
$sorts = [
    'date_desc' => ['u.created_at DESC', 'Newest first'],
    'date_asc'  => ['u.created_at ASC',  'Oldest first'],
    'name_asc'  => ['u.name ASC',        'Name A to Z'],
    'name_desc' => ['u.name DESC',       'Name Z to A'],
];
$sort = array_key_exists($_GET['sort'] ?? '', $sorts) ? $_GET['sort'] : 'date_desc';

$sql = "SELECT u.id, u.name, u.email, u.role, u.created_at, p.city, p.photo,
               (SELECT COUNT(*) FROM matches m WHERE m.user1_id = u.id OR m.user2_id = u.id) AS match_count
        FROM users u
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE 1 = 1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (LOWER(u.name) LIKE LOWER(?) OR LOWER(u.email) LIKE LOWER(?))';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($role !== '') {
    $sql .= ' AND u.role = ?';
    $params[] = $role;
}
$sql .= ' ORDER BY ' . $sorts[$sort][0];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

/** Link for a clickable column heading: toggles between the two directions */
function sort_link(string $field, string $label, string $current, string $search, string $role): string
{
    $next  = $current === $field . '_asc' ? $field . '_desc' : $field . '_asc';
    $arrow = str_starts_with($current, $field) ? (str_ends_with($current, 'asc') ? ' ↑' : ' ↓') : '';
    $query = http_build_query(['q' => $search, 'role' => $role, 'sort' => $next]);
    return '<a href="?' . e($query) . '">' . e($label) . $arrow . '</a>';
}

$pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <div>
            <h1>Admin dashboard</h1>
            <p class="muted">Everyone on RoomieMatch, at a glance.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="card stat"><div class="num"><?= (int) $stats['users'] ?></div><div class="label">Users</div></div>
        <div class="card stat"><div class="num"><?= (int) $stats['profiles'] ?></div><div class="label">Profiles</div></div>
        <div class="card stat"><div class="num"><?= (int) $stats['swipes'] ?></div><div class="label">Swipes</div></div>
        <div class="card stat"><div class="num"><?= (int) $stats['matches'] ?></div><div class="label">Matches</div></div>
        <div class="card stat"><div class="num"><?= (int) $stats['messages'] ?></div><div class="label">Messages</div></div>
        <div class="card stat"><div class="num"><?= (int) $stats['listings'] ?></div><div class="label">Listings</div></div>
        <a class="card stat stat-link" href="<?= e(url('admin/reports.php')) ?>"><div class="num"><?= (int) $stats['open_reports'] ?></div><div class="label">Open reports →</div></a>
    </div>

    <div class="card">
        <form class="toolbar" method="get" action="" role="search">
            <label class="sr-only" for="q">Search by name or email</label>
            <input type="search" id="q" name="q" placeholder="Search name or email…" value="<?= e($search) ?>">
            <label class="sr-only" for="role">Role</label>
            <select id="role" name="role">
                <option value="">All roles</option>
                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Users</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admins</option>
            </select>
            <label class="sr-only" for="sort">Sort</label>
            <select id="sort" name="sort">
                <?php foreach ($sorts as $key => [, $label]): ?>
                    <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Apply</button>
            <?php if ($search !== '' || $role !== '' || $sort !== 'date_desc'): ?>
                <a class="btn" href="<?= e(url('admin/dashboard.php')) ?>">Clear</a>
            <?php endif; ?>
        </form>

        <p class="muted small">Showing <?= count($users) ?> <?= count($users) === 1 ? 'account' : 'accounts' ?><?= $search !== '' ? ' matching "' . e($search) . '"' : '' ?>.</p>

        <?php if (!$users): ?>
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h2>No accounts found</h2>
                <p>Try a different name or email.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th><?= sort_link('name', 'Name', $sort, $search, $role) ?></th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>City</th>
                            <th>Matches</th>
                            <th><?= sort_link('date', 'Joined', $sort, $search, $role) ?></th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <img class="avatar" src="<?= e(photo_url($u['photo'])) ?>" alt="">
                                        <?php if ($u['city']): ?>
                                            <a href="<?= e(url('profile/view_profile.php?id=' . (int) $u['id'])) ?>"><?= e($u['name']) ?></a>
                                        <?php else: ?>
                                            <?= e($u['name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= e($u['email']) ?></td>
                                <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                                <td><?= $u['city'] ? e($u['city']) : '<span class="muted">No profile</span>' ?></td>
                                <td><?= (int) $u['match_count'] ?></td>
                                <td><?= e(nice_date($u['created_at'])) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'user'): ?>
                                        <form method="post" action="<?= e(url('admin/delete_user.php')) ?>"
                                              data-confirm="Delete <?= e($u['name']) ?>? Their profile, swipes, matches and messages will be removed permanently."
                                              data-confirm-button="Delete user">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted small">Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
