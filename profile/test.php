<?php
/**
 * profile/test.php  —  Owner: M3 (Profiles)
 * The compatibility test: an age range (required) and 19 questions in five
 * sections. Every slider starts empty; touching it activates it. Single
 * questions can be skipped; each section needs at least one answer.
 * Answers are stored as JSON in profiles.answers (skipped = left out),
 * the age range in profiles.age_min / age_max. Scoring: swipe/compatibility.php.
 * Buttons: "Save and finish later" (profile), "See matches with what I've
 * answered" and "Finish" (both go to the Discover swipe page).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../swipe/compatibility.php';

$me = require_profile();
$userId = (int) $_SESSION['user_id'];
$answers = test_answers($me);
$ageMin = (string) ($me['age_min'] ?? ''); $ageMax = (string) ($me['age_max'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $go = $_POST['go'] ?? 'finish';                     // finish | later | matches
    $answers = [];
    foreach (test_questions() as $k => $q) {
        $v = (string) ($_POST['q'][$k] ?? '');
        if ($v === '') continue;                        // skipped
        if (!ctype_digit($v) || (int) $v < 1 || (int) $v > 5) { $errors['q_' . $k] = 'Pick a point from 1 to 5, or skip.'; continue; }
        $answers[$k] = (int) $v;
    }
    $ageMin = trim((string) ($_POST['age_min'] ?? '')); $ageMax = trim((string) ($_POST['age_max'] ?? ''));
    if (!ctype_digit($ageMin) || !ctype_digit($ageMax) || (int) $ageMin < 17 || (int) $ageMax > 60 || (int) $ageMin > (int) $ageMax) {
        $errors['age'] = 'Set the youngest and oldest age you are comfortable living with.';
    }
    if ($go === 'finish') {                              // "Save for later" and "See matches" work with partial answers
        foreach (TEST_SECTIONS as $section => $qs) {
            if (!array_intersect_key($answers, $qs)) $errors['section_' . md5($section)] = 'Answer at least one question in "' . $section . '".';
        }
    }
    if (!$errors) {
        $pdo->prepare('UPDATE profiles SET answers = ?, age_min = ?, age_max = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')
            ->execute([json_encode($answers), (int) $ageMin ?: null, (int) $ageMax ?: null, $userId]);
        if ($go === 'later') { set_flash('success', 'Saved. You can finish the test any time from your profile.'); redirect('profile/view_profile.php'); }
        set_flash('success', 'Test saved: ' . count($answers) . ' answers. Here are your best matches.');
        redirect('swipe/swipe.php');
    }
}

$total = count(test_questions());
$pageTitle = 'Compatibility test';
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow">
    <div class="page-head">
        <div>
            <h1>Your compatibility test</h1>
            <p class="muted">Age + <?= $total ?> questions in 5 short sections · about 4 minutes · answer at least one per section, skip the rest.
                <a href="<?= e(url('how-matching-works.php')) ?>" class="info-i" title="How matching works">ⓘ How it's scored</a></p>
        </div>
    </div>

    <div class="test-progress" aria-hidden="true"><?php for ($i = 0; $i <= count(TEST_SECTIONS); $i++): ?><span><i></i></span><?php endfor; ?></div>
    <p class="small muted" id="test-count" aria-live="polite"></p>

    <?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>

    <form method="post" id="test-form" class="test-form" novalidate>
        <?= csrf_field() ?>

        <section class="card test-section" data-step="0">
            <div class="sec-head"><div><h2>Age of flatmates</h2><p class="muted small">One of the biggest factors: it counts as much as <?= AGE_WEIGHT ?> ordinary questions.</p></div><span class="badge-req">Required</span></div>
            <p><strong>Which ages are you comfortable living with?</strong> <span class="muted small">(you are <?= (int) $me['age'] ?>)</span></p>
            <div class="age-range">
                <label>From <input type="number" name="age_min" min="17" max="60" value="<?= e($ageMin ?: (string) max(17, (int) $me['age'] - 3)) ?>" required></label>
                <label>to <input type="number" name="age_max" min="17" max="60" value="<?= e($ageMax ?: (string) ((int) $me['age'] + 4)) ?>" required></label>
            </div>
            <p class="muted small">Scoring: if you're both inside each other's range, full marks. Each year outside takes away a quarter, checked both ways.</p>
        </section>

        <?php $n = 0; $step = 0; foreach (TEST_SECTIONS as $section => $qs): $step++; ?>
            <section class="card test-section" data-step="<?= $step ?>">
                <div class="sec-head"><div><h2><?= e($section) ?></h2><p class="muted small">Answer at least 1. Tap or drag a slider to answer.</p></div></div>
                <?php foreach ($qs as $k => [$ask, $low, $high, $labels, $weight]): $n++; $val = $answers[$k] ?? null; ?>
                    <div class="tq<?= $val === null ? ' is-unset' : '' ?>" data-labels="<?= e(json_encode($labels)) ?>">
                        <label for="q-<?= $k ?>"><?= $n ?>. <?= e($ask) ?><?php if (!$weight): ?> <span class="badge-opt">Optional · not scored</span><?php endif; ?></label>
                        <input type="hidden" name="q[<?= $k ?>]" value="<?= $val ?? '' ?>">
                        <div class="tq-scale">
                            <span class="tq-end"><?= e($low) ?></span>
                            <input type="range" id="q-<?= $k ?>" min="1" max="5" step="1" value="<?= $val ?? 3 ?>" aria-describedby="v-<?= $k ?>">
                            <span class="tq-end"><?= e($high) ?></span>
                        </div>
                        <div class="tq-foot">
                            <span class="tq-val" id="v-<?= $k ?>"><?= $val === null ? 'Not answered' : 'Your answer: ' . $val . ' / 5' . ($labels ? ' · ' . e($labels[$val - 1]) : '') ?></span>
                            <button type="button" class="link-btn small tq-skip">Skip question</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="error-text sec-error" hidden>Answer at least one question in this section to continue.</p>
            </section>
        <?php endforeach; ?>

        <div class="test-actions">
            <button type="button" class="btn" id="t-back">Back</button>
            <div class="btn-row">
                <button class="btn btn-ghost" name="go" value="later">Save and finish later</button>
                <button class="btn" name="go" value="matches" formnovalidate>See matches with what I've answered</button>
            </div>
            <button type="button" class="btn btn-primary" id="t-next">Next section</button>
            <button class="btn btn-primary" name="go" value="finish" id="t-finish" hidden>Finish and see matches</button>
        </div>
    </form>
</div>
<?php
$extraScripts = <<<'HTML'
<script>
// One section at a time (without JavaScript every section simply shows)
(function () {
    const form = document.getElementById('test-form');
    const secs = Array.from(form.querySelectorAll('.test-section'));
    const bars = document.querySelectorAll('.test-progress i');
    let at = 0;
    form.classList.add('stepped');
    function answered(sec) { return Array.from(sec.querySelectorAll('.tq input[type=hidden]')).filter(function (i) { return i.value !== ''; }).length; }
    function show() {
        secs.forEach(function (s, i) { s.hidden = i !== at; });
        document.getElementById('t-back').disabled = at === 0;
        document.getElementById('t-next').hidden = at === secs.length - 1;
        document.getElementById('t-finish').hidden = at !== secs.length - 1;
        update();
        secs[at].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    function update() {
        let done = 0, total = 0;
        secs.forEach(function (s, i) {
            const qs = s.querySelectorAll('.tq').length, a = answered(s);
            total += qs; done += a;
            if (bars[i]) bars[i].style.transform = 'scaleX(' + (i === 0 ? 1 : (qs ? a / qs : 0)) + ')';
        });
        document.getElementById('test-count').textContent = done + ' of ' + total + ' questions answered · section ' + (at + 1) + ' of ' + secs.length;
    }
    function sectionOk() {
        const s = secs[at], err = s.querySelector('.sec-error');
        if (at === 0) return true;
        const ok = answered(s) > 0; if (err) err.hidden = ok; return ok;
    }
    document.getElementById('t-next').addEventListener('click', function () { if (sectionOk()) { at++; show(); } });
    document.getElementById('t-back').addEventListener('click', function () { if (at > 0) { at--; show(); } });
    document.getElementById('t-finish').addEventListener('click', function (e) { if (!sectionOk()) e.preventDefault(); });

    // Sliders start "unset": the first touch or key press activates them
    form.querySelectorAll('.tq').forEach(function (q) {
        const range = q.querySelector('input[type=range]'), hidden = q.querySelector('input[type=hidden]');
        const out = q.querySelector('.tq-val'), labels = JSON.parse(q.dataset.labels || 'null');
        function set() {
            hidden.value = range.value; q.classList.remove('is-unset');
            out.textContent = 'Your answer: ' + range.value + ' / 5' + (labels ? ' · ' + labels[range.value - 1] : '');
            update();
        }
        range.addEventListener('input', set);
        range.addEventListener('pointerdown', function () { setTimeout(set, 0); });
        range.addEventListener('keydown', function (e) { if (q.classList.contains('is-unset') && /Arrow|Home|End|PageUp|PageDown/.test(e.key)) setTimeout(set, 0); });
        q.querySelector('.tq-skip').addEventListener('click', function () {
            hidden.value = ''; range.value = 3; q.classList.add('is-unset'); out.textContent = 'Skipped'; update();
        });
    });
    show();
})();
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
