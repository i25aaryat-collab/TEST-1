<?php
/* ==========================================================================
 * _404.php  —  Owner: M1 (Layout)
 * Shown by api/index.php for any address that isn't a page. The leading
 * underscore means it can't be opened directly, only included.
 * ========================================================================== */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle = 'Page not found';
require __DIR__ . '/includes/header.php';
?>
<div class="container container-narrow not-found">
    <div class="card">
        <p class="nf-code">404</p>
        <h1>This room is empty.</h1>
        <p class="muted">The page you were looking for has moved out, or the link has a typo.</p>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(url(is_logged_in() ? (is_admin() ? 'admin/dashboard.php' : 'swipe/swipe.php') : 'index.php')) ?>"><?= is_logged_in() ? 'Back to RoomieMatch' : 'Go to the homepage' ?></a>
            <a class="btn" href="<?= e(url('listings/index.php')) ?>">Browse flats</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
