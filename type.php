<?php
/* ==========================================================================
 * type.php  —  Owner: M3 (Profiles and test)
 * --------------------------------------------------------------------------
 * Public page behind a shared flatmate-type link: type.php?t=host&by=CODE.
 * Shows the type with a proper link preview (WhatsApp / Instagram), and
 * remembers the invite code so a sign-up is credited to the friend who shared.
 * ========================================================================== */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/flatmate_type.php';

$key = $_GET['t'] ?? '';
if (!isset(FLATMATE_TYPES[$key])) redirect('index.php');
[$name, $line, $about, $colors] = FLATMATE_TYPES[$key];

$by = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['by'] ?? ''));
$inviter = null;
if ($by !== '') {
    $s = $pdo->prepare('SELECT id, name FROM users WHERE ref_code = ?');
    $s->execute([$by]);
    if ($inviter = $s->fetch()) $_SESSION['ref_by'] = (int) $inviter['id'];     // credited if they sign up in this visit
}
$first = $inviter ? explode(' ', $inviter['name'])[0] : null;

$origin = getenv('APP_URL') ?: 'https://roomiezmatch.vercel.app';
$pageTitle = ($first ? $first . " is " : "") . $name;
$extraHead = '<meta property="og:title" content="' . e(($first ? $first . ' is ' : "I'm ") . $name . ' on RoomieMatch') . '">'
           . '<meta property="og:description" content="' . e($line . ' What kind of flatmate are you?') . '">'
           . '<meta property="og:image" content="' . e($origin . '/css/types/' . $key . '.png') . '">'
           . '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">'
           . '<meta name="twitter:card" content="summary_large_image">';
require __DIR__ . '/includes/header.php';
?>
<div class="container type-page" style="--t1: <?= e($colors[0]) ?>; --t2: <?= e($colors[1]) ?>">
    <section class="type-reveal">
        <p class="type-eyebrow"><?= $first ? e($first) . "'s flatmate type" : 'A flatmate type' ?></p>
        <h1><?= e($name) ?></h1>
        <p class="type-line"><?= e($line) ?></p>
        <p class="type-about"><?= e($about) ?></p>
    </section>
    <section class="type-cta card">
        <h2>What kind of flatmate are you?</h2>
        <p class="muted">20 quick questions about sleep, guests, chores and food. You'll get your type and find people who live the way you do.</p>
        <?php if (is_logged_in()): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('profile/test.php')) ?>">Take the test</a>
        <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('auth/register.php')) ?>">Find my type</a>
            <p class="small muted">Already on RoomieMatch? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
