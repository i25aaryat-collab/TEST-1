<?php
/**
 * profile/test.php  —  Owner: M3 (Profiles)
 * Twenty 1-5 scales in five sections. Each question and each section
 * can be skipped; skipped answers stay out of the score.
 * Answers are stored as JSON in profiles.answers. Scoring: swipe/compatibility.php.
 * Buttons: "Save and finish later" (profile), "See matches with what I've
 * answered" and "Finish" (both go to the Discover swipe page).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../swipe/compatibility.php';

$me = require_profile();
$userId = (int) $_SESSION['user_id'];
$answers = test_answers($me);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $go = $_POST['go'] ?? 'finish';                     // finish | later | matches
    $answers = [];
    $submitted = isset($_POST['q']) && is_array($_POST['q']) ? $_POST['q'] : [];
    foreach (test_questions() as $key => $question) {
        $value = (string) ($submitted[$key] ?? '');
        if ($value === '') continue;                    // skipped
        if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 5) {
            $errors['q_' . $key] = 'Pick a point from 1 to 5, or skip the question.';
            continue;
        }
        $answers[$key] = (int) $value;
    }

    if (!$errors) {
        $storedAnswers = $answers;                      // 1..5 per question
        $pdo->prepare('UPDATE profiles SET answers = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')
            ->execute([json_encode($storedAnswers), $userId]);
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
            <p class="muted"><?= $total ?> questions in five short sections, each on a 1–5 scale. Skip any question or section; only answers you both give are compared.
                <a href="<?= e(url('how-matching-works.php')) ?>" class="info-i" title="How matching works">ⓘ How it's scored</a></p>
        </div>
    </div>

    <div class="test-progress" aria-hidden="true"><?php for ($i = 0; $i < count(TEST_SECTIONS); $i++): ?><span><i></i></span><?php endfor; ?></div>
    <p class="small muted" id="test-count" aria-live="polite"></p>

    <?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>

    <form method="post" id="test-form" class="test-form" novalidate>
        <?= csrf_field() ?>

        <?php $n = 0; $step = 0; foreach (TEST_SECTIONS as $section => $qs): $step++; ?>
            <section class="card test-section" data-step="<?= $step ?>">
                <div class="sec-head">
                    <div><h2><?= e($section) ?></h2><p class="muted small">Pick 1 to 5. Every question is optional.</p></div>
                    <button type="button" class="link-btn small tq-skip-section">Skip this section</button>
                </div>
                <?php foreach ($qs as $k => [$ask, $low, $high, $kind]): $n++; $val = $answers[$k] ?? null; $optional = in_array($k, ['culture', 'religion_diff'], true); ?>
                    <div class="tq<?= $val === null ? ' is-unset' : '' ?>" data-kind="<?= e($kind) ?>">
                        <label for="q-<?= e($k) ?>"><?= $n ?>. <?= e($ask) ?><?php if ($optional): ?> <span class="badge-opt">Optional</span><?php endif; ?></label>
                        <input type="hidden" name="q[<?= e($k) ?>]" value="<?= $val === null ? '' : (int) $val ?>">
                        <div class="tq-scale">
                            <span class="tq-end"><?= e($low) ?></span>
                            <div class="tq-track"><input type="range" id="q-<?= e($k) ?>" min="1" max="5" step="1" value="<?= $val === null ? 3 : (int) $val ?>" aria-describedby="v-<?= e($k) ?>"><span class="tq-ticks" aria-hidden="true"><i>1</i><i>2</i><i>3</i><i>4</i><i>5</i></span></div>
                            <span class="tq-end"><?= e($high) ?></span>
                        </div>
                        <div class="tq-foot">
                            <span class="tq-val" id="v-<?= e($k) ?>"><?= $val === null ? 'Not answered' : 'Your answer: ' . (int) $val . ' of 5' ?></span>
                            <button type="button" class="link-btn small tq-skip">Skip question</button>
                        </div>
                    </div>
                <?php endforeach; ?>
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
// Keep the test short and forgiving: skip a question or clear an entire section.
(function () {
    const form = document.getElementById('test-form');
    const secs = Array.from(form.querySelectorAll('.test-section'));
    const bars = document.querySelectorAll('.test-progress i');
    let at = 0;
    form.classList.add('stepped');

    function answered(sec) {
        return Array.from(sec.querySelectorAll('.tq input[type=hidden]')).filter(function (input) { return input.value !== ''; }).length;
    }
    function timeText(startHour, spanHours, value) {
        const minutes = Math.round((startHour * 60 + spanHours * 60 * (Number(value) - 1) / 4) / 30) * 30 % 1440;   // snap to :00 / :30
        const hour24 = Math.floor(minutes / 60), minute = minutes % 60;
        const period = hour24 >= 12 ? 'pm' : 'am';
        const hour12 = hour24 % 12 || 12;
        return hour12 + ':' + String(minute).padStart(2, '0') + ' ' + period;
    }
    function labelFor(q, value) {
        const kind = q.dataset.kind, number = Number(value);
        if (kind === 'bedtime') return number + ' of 5 · around ' + timeText(20, 7, number) + (number === 5 ? ' or later' : '');
        if (kind === 'waketime') return number + ' of 5 · around ' + timeText(5, 6, number) + (number === 5 ? ' or later' : '');
        if (kind === 'temperature') return (18 + 3 * (number - 1)) + '°C';
        return number + ' of 5';
    }
    function update() {
        let done = 0, total = 0;
        secs.forEach(function (sec, i) {
            const count = sec.querySelectorAll('.tq').length, countAnswered = answered(sec);
            total += count; done += countAnswered;
            if (bars[i]) bars[i].style.transform = 'scaleX(' + (count ? countAnswered / count : 0) + ')';
        });
        const count = document.getElementById('test-count');
        if (count) count.textContent = done + ' of ' + total + ' answered · section ' + (at + 1) + ' of ' + secs.length;
    }
    function show() {
        secs.forEach(function (sec, i) { sec.hidden = i !== at; });
        document.getElementById('t-back').disabled = at === 0;
        document.getElementById('t-next').hidden = at === secs.length - 1;
        document.getElementById('t-finish').hidden = at !== secs.length - 1;
        update();
        secs[at].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    function skipQuestion(q) {
        const hidden = q.querySelector('input[type=hidden]'), range = q.querySelector('input[type=range]');
        hidden.value = ''; range.value = 3; q.classList.add('is-unset');
        const out = q.querySelector('.tq-val'); if (out) out.textContent = 'Skipped';
        q.closest('.test-section').classList.remove('section-skipped');
        const skipButton = q.closest('.test-section').querySelector('.tq-skip-section');
        if (skipButton) skipButton.textContent = 'Skip this section';
        update();
    }
    function skipSection(sec) {
        sec.querySelectorAll('.tq').forEach(function (q) {
            const hidden = q.querySelector('input[type=hidden]'), range = q.querySelector('input[type=range]');
            hidden.value = ''; range.value = 3; q.classList.add('is-unset');
            const out = q.querySelector('.tq-val'); if (out) out.textContent = 'Skipped';
        });
        sec.classList.add('section-skipped');
        const button = sec.querySelector('.tq-skip-section'); if (button) button.textContent = 'Section skipped ✓';
        update();
        if (at < secs.length - 1) { at++; show(); }
    }

    document.getElementById('t-next').addEventListener('click', function () { if (at < secs.length - 1) { at++; show(); } });
    document.getElementById('t-back').addEventListener('click', function () { if (at > 0) { at--; show(); } });
    form.querySelectorAll('.tq').forEach(function (q) {
        const range = q.querySelector('input[type=range]'), hidden = q.querySelector('input[type=hidden]');
        const out = q.querySelector('.tq-val');
        if (hidden.value !== '') { q.classList.remove('is-unset'); out.textContent = 'Your answer: ' + labelFor(q, hidden.value); }
        range.addEventListener('input', function () {
            hidden.value = range.value; q.classList.remove('is-unset');
            q.closest('.test-section').classList.remove('section-skipped');
            const skipButton = q.closest('.test-section').querySelector('.tq-skip-section');
            if (skipButton) skipButton.textContent = 'Skip this section';
            out.textContent = 'Your answer: ' + labelFor(q, range.value);
            update();
        });
        q.querySelector('.tq-skip').addEventListener('click', function () { skipQuestion(q); });
    });
    form.querySelectorAll('.tq-skip-section').forEach(function (button) {
        button.addEventListener('click', function () { skipSection(button.closest('.test-section')); });
    });
    show();
})();
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
