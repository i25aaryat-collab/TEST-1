<?php
/**
 * how-matching-works.php  —  Owner: M4 (Matching)
 * Plain-language explanation of the score, linked from every ⓘ next to a score.
 * Numbers on this page come straight from swipe/compatibility.php, so it stays correct.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/swipe/compatibility.php';

$pageTitle = 'How matching works';
require __DIR__ . '/includes/header.php';
?>
<div class="container container-narrow">
    <div class="card how-page">
        <h1>How matching works</h1>
        <p class="muted">Your score with someone is a conversation starter, not a promise that you'll live well together.</p>

        <h2>1. You both answer the same short test</h2>
        <p>Age, plus <?= count(test_questions()) ?> questions in five areas. Each is a 1–5 slider with a clear meaning at each end, and neither end is the "right" answer.</p>
        <ul class="how-list">
            <li><strong>Age</strong>: the ages you're comfortable living with.</li>
            <?php foreach (TEST_SECTIONS as $s => $qs): ?><li><strong><?= e($s) ?></strong>: <?= e(implode(', ', array_map(fn($q) => rtrim(lcfirst($q[0]), '?'), array_values($qs)))) ?>.</li><?php endforeach; ?>
        </ul>

        <h2>2. Each shared question becomes a fit</h2>
        <div class="how-table">
            <div><b>Same answer</b><span>100</span></div><div><b>1 step apart</b><span>75</span></div><div><b>2 steps</b><span>50</span></div><div><b>3 steps</b><span>25</span></div><div><b>Opposite ends</b><span>0</span></div>
        </div>
        <p><strong>Age</strong> works differently: if you're each inside the other's range, it's 100. Each year outside takes away 25, checked both ways, and the worse direction counts. Age counts as much as <?= AGE_WEIGHT ?> ordinary questions.</p>

        <h2>3. Skipped answers never count against you</h2>
        <p>We only average the questions you <em>both</em> answered. That's why every score says how much it's based on, like "82% · based on 12 shared answers".</p>
        <p>With fewer than <?= MIN_SHARED ?> shared answers we show <strong>"Not enough answers yet"</strong> rather than a number that looks more certain than it is.</p>

        <h2>4. What's not in the score</h2>
        <ul class="how-list">
            <li><strong>Budget and city</strong> are filters on Discover.</li>
            <li><strong>Religion and culture</strong> are optional. They're only used if you choose them as a filter, never in the score.</li>
            <li><strong>Languages</strong> are shown on profiles, never scored.</li>
        </ul>

        <h2>5. Then it's up to you</h2>
        <p>Swipe right to send a request. If they accept, you can chat, share contact details if you both want to, and talk through the things that matter, like quiet hours, guests and dishes.</p>
        <div class="btn-row" style="margin-top:20px">
            <?php if (is_logged_in()): ?><a class="btn btn-primary" href="<?= e(url('profile/test.php')) ?>">Take or update the test</a><a class="btn" href="<?= e(url('swipe/swipe.php')) ?>">Discover</a>
            <?php else: ?><a class="btn btn-primary" href="<?= e(url('auth/register.php')) ?>">Find my roomie</a><?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
