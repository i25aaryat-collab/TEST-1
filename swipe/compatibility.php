<?php
/* ==========================================================================
 * swipe/compatibility.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * The compatibility score, from the RoomieMatch test (profile/test.php).
 *
 *  - Every question is a 1..5 scale, stored in profiles.answers JSON.
 *  - Fit per question = 100 - 25 x (steps apart): same 100, 1 apart 75, 2 apart 50, 3 apart 25, opposite ends 0.
 *  - Every question has equal weight. A skipped answer is excluded.
 *  - Fewer than MIN_SHARED shared answers -> no score ("Not enough answers yet").
 *  - Budget and city are Discover filters, not part of the score.
 *  - Religion and culture questions count only when both people answer them.
 * Questions live here so labels and scoring stay in sync.
 * ========================================================================== */

const MIN_SHARED = 5;

const TEST_SECTIONS = [
    'Sleep and daily rhythm' => [
        'bedtime' => ['What time do you usually go to bed?', 'Around 8 pm', '3 am or later', 'bedtime'],
        'waketime' => ['What time do you usually wake up?', 'Around 5 am', '11 am or later', 'waketime'],
        'light' => ['How much light do you like in the room while sleeping?', 'Pitch dark', 'Bright lights on', 'scale'],
        'noise' => ['How much noise are you comfortable with when sleeping or studying?', 'As quiet as possible', 'Normal household noise is fine', 'scale'],
    ],
    'Cleanliness and chores' => [
        'tidy' => ['How tidy do you like shared spaces to be?', 'Relaxed and lived-in', 'Very neat and orderly', 'scale'],
        'dishes' => ['How soon do you prefer dishes to be washed after eating?', 'Later is fine', 'Right away', 'scale'],
        'chores' => ['How structured should shared chores be?', 'Handle them as they come up', 'Follow a regular rota', 'scale'],
        'help' => ['How often would you prefer professional cleaning help at home?', 'Not at all', 'Several times a week', 'scale'],
    ],
    'Guests and shared space' => [
        'day_guests' => ['How often are you comfortable having daytime guests at home?', 'Rarely', 'Very often', 'scale'],
        'overnight' => ['How comfortable are you with overnight guests?', 'Prefer to avoid them', 'Comfortable with them often', 'scale'],
        'notice' => ['How much advance notice do you prefer before a roommate invites guests?', 'Little or none', 'Always discuss it beforehand', 'scale'],
        'shared' => ['How do you like to use shared spaces?', 'Mostly quiet and private', 'Social and shared often', 'scale'],
    ],
    'Food and household practices' => [
        'nonveg' => ['How comfortable are you with non-vegetarian food being cooked in the shared kitchen?', 'Not comfortable', 'Completely comfortable', 'scale'],
        'groceries' => ['How do you prefer to handle groceries and food at home?', 'Keep everything separate', 'Share most groceries and meals', 'scale'],
        'culture' => ['How often are religious or cultural practices part of your home routine?', 'Rarely or never', 'A regular part of daily life', 'scale'],
        'religion_diff' => ['How comfortable are you sharing a home with someone whose religious or cultural routines differ from yours?', 'Prefer routines to stay private', 'Comfortable with different routines in shared spaces', 'scale'],
    ],
    'Comfort and boundaries' => [
        'pets' => ['How comfortable are you living with pets?', 'Not comfortable', 'Very comfortable', 'scale'],
        'smoking' => ['How comfortable are you with smoking or vaping around the home?', 'Not comfortable', 'Very comfortable', 'scale'],
        'ac_temp' => ['What AC temperature do you usually prefer?', '18°C', '30°C', 'temperature'],
        'ac_use' => ['How much AC use and shared electricity cost are you comfortable with?', 'Prefer minimal AC use', 'Comfortable using it often and sharing the cost', 'scale'],
    ],
];

function test_questions(): array
{
    $all = [];
    foreach (TEST_SECTIONS as $section => $questions) {
        foreach ($questions as $key => $question) $all[$key] = $question + ['section' => $section];
    }
    return $all;
}

/** Answers as 1..5 steps. (Answers saved during the short-lived 0..100 slider version are rounded to the nearest step.) */
function test_answers(array $profile): array
{
    $payload = json_decode((string) ($profile['answers'] ?? ''), true);
    if (!is_array($payload)) return [];
    $fromHundred = (int) ($payload['_scale'] ?? 0) === 100;
    unset($payload['_scale']);

    $answers = [];
    foreach (test_questions() as $key => $_question) {
        $value = $payload[$key] ?? null;
        if (!is_numeric($value)) continue;
        $value = $fromHundred ? (int) round(max(0, min(100, (float) $value)) / 25) + 1 : (int) $value;
        if ($value >= 1 && $value <= 5) $answers[$key] = $value;
    }
    return $answers;
}

/**
 * Each shared answer has equal weight: fit = 100 - 25 x the number of steps
 * between two 1..5 answers. Skips are excluded from both numerator
 * and denominator, and fewer than five shared answers yields no score.
 */
function compatibility_result(array $a, array $b): array
{
    $answersA = test_answers($a);
    $answersB = test_answers($b);
    $sum = 0;
    $shared = 0;
    $sections = [];
    $items = [];                                   // every shared question, for the "how is this worked out" panel
    $same = $largestDifference = null;

    foreach (test_questions() as $key => $question) {
        if (!isset($answersA[$key], $answersB[$key])) continue;
        $steps = abs($answersA[$key] - $answersB[$key]);
        $gap = $steps * 25;                                      // 0, 25, 50, 75 or 100
        $fit = 100 - $gap;
        $sum += $fit;
        $shared++;
        $sections[$question['section']][] = $fit;
        $items[] = ['q' => $question[0], 'you' => $answersA[$key], 'them' => $answersB[$key], 'fit' => $fit];
        if ($steps === 0 && !$same) $same = 'You both chose similar answers for: ' . lcfirst($question[0]);
        if ($steps >= 2 && (!$largestDifference || $gap > $largestDifference[1])) $largestDifference = [$question[0], $gap];
    }

    // How long each wants to stay counts like one more question (same 1..5 scale and formula)
    if (!empty($a['stay']) && !empty($b['stay'])) {
        $steps = abs((int) $a['stay'] - (int) $b['stay']);
        $fit = 100 - 25 * $steps;
        $sum += $fit; $shared++;
        $sections['How long you stay'][] = $fit;
        $items[] = ['q' => 'How long you want to live together', 'you' => (int) $a['stay'], 'them' => (int) $b['stay'], 'fit' => $fit,
                    'you_label' => stay_label($a), 'them_label' => stay_label($b)];
        if ($steps >= 2 && (!$largestDifference || 25 * $steps > $largestDifference[1])) $largestDifference = ['How long you each plan to stay', 25 * $steps];
    }

    return [
        'score' => $shared >= MIN_SHARED ? (int) round($sum / $shared) : null,
        'shared' => $shared,
        'sum' => $sum,
        'items' => $items,
        'sections' => array_map(fn($fits) => array_sum($fits) / count($fits), $sections),
        'same' => $same,
        'discuss' => $largestDifference ? 'Worth a chat: ' . lcfirst($largestDifference[0]) : null,
    ];
}

/** Score only (null = not enough shared answers) */
function compatibility_score(array $a, array $b): ?int
{
    return compatibility_result($a, $b)['score'];
}

/** Section => fit % (0..100), only sections with shared answers */
function compatibility_breakdown(array $a, array $b): array
{
    return compatibility_result($a, $b)['sections'];
}

/** Every section scores out of 100 in the breakdown table */
const COMPATIBILITY_MAX = [
    'Sleep and daily rhythm' => 100,
    'Cleanliness and chores' => 100,
    'Guests and shared space' => 100,
    'Food and household practices' => 100,
    'Comfort and boundaries' => 100,
    'How long you stay' => 100,
];

/** CSS class for colouring a score */
function score_class(?int $score): string
{
    if ($score === null) return 'score-none';
    return $score >= 75 ? 'score-high' : ($score >= 50 ? 'score-mid' : 'score-low');
}

/** Round progress ring showing a score; "–" when there isn't one yet */
function score_ring(?int $score): string
{
    $label = $score === null ? 'Not enough answers yet' : $score . '% compatible';
    return '<div class="score-ring ' . score_class($score) . '" style="--p:' . (int) $score . '" role="img" aria-label="' . $label . '">'
         . '<svg viewBox="0 0 64 64" aria-hidden="true"><circle class="track" cx="32" cy="32" r="27"/>'
         . '<circle class="bar" cx="32" cy="32" r="27" pathLength="100"/></svg>'
         . '<span>' . ($score === null ? '–' : $score . '<small>%</small>') . '</span></div>';
}

/** "82% · based on 12 shared answers" + an (i) that opens how this exact score was worked out */
function score_line(array $r): string
{
    if ($r['score'] === null) return '<div class="score-line muted small">Not enough answers yet (' . (int) $r['shared'] . ' shared) ' . score_explain($r) . '</div>';
    return '<div class="score-line small"><strong>' . $r['score'] . '%</strong> · based on ' . $r['shared'] . ' shared answer' . ($r['shared'] === 1 ? '' : 's') . ' ' . score_explain($r) . '</div>';
}

/**
 * The (i) button: opens a small panel showing the formula and this pair's own numbers.
 *   score = average over shared questions of (100 - 25 x |your answer - their answer|), answers on 1..5
 */
function score_explain(array $r): string
{
    $rows = '';
    foreach ($r['items'] ?? [] as $i) {
        $rows .= '<tr><td>' . e($i['q']) . '</td><td>' . (isset($i['you_label']) ? e($i['you_label']) . ' (' . (int) $i['you'] . ')' : (int) $i['you']) . '</td><td>' . (isset($i['them_label']) ? e($i['them_label']) . ' (' . (int) $i['them'] . ')' : (int) $i['them']) . '</td><td>100 − 25 × ' . abs((int) $i['you'] - (int) $i['them']) . ' = <strong>' . (int) $i['fit'] . '</strong></td></tr>';
    }
    $n = (int) $r['shared'];
    $result = $r['score'] === null
        ? '<p class="why-result">Only ' . $n . ' shared answer' . ($n === 1 ? '' : 's') . '. A score needs at least ' . MIN_SHARED . ', so none is shown yet.</p>'
        : '<p class="why-result">' . (count($r['items'] ?? []) <= 12 ? implode(' + ', array_map(fn($i) => (int) $i['fit'], $r['items'] ?? [])) . ' = ' : 'Fits add up to ')
          . (int) ($r['sum'] ?? 0) . ', ÷ ' . $n . ' answers = <strong>' . $r['score'] . '%</strong></p>';
    return '<details class="score-why"><summary aria-label="How this score is worked out" title="How this score is worked out">i</summary><div class="why-panel">'
        . '<p class="why-formula"><strong>Score</strong> = average of <code>100 − 25 × |your answer − their answer|</code> over the questions you both answered</p>'
        . '<p class="muted small">Every answer is on a 1–5 scale. Same answer = 100, one step apart = 75, two = 50, three = 25, opposite ends = 0. Skipped questions don\'t count. At least ' . MIN_SHARED . ' shared answers are needed.</p>'
        . ($rows ? '<div class="why-table"><table><thead><tr><th>Question</th><th>You</th><th>Them</th><th>Fit</th></tr></thead><tbody>' . $rows . '</tbody></table></div>' : '')
        . $result
        . '<div class="why-foot"><a class="small" href="' . e(url('how-matching-works.php')) . '">More about matching</a>'
        . '<button type="button" class="btn btn-sm" onclick="this.closest(\'details\').open=false">Close</button></div></div></details>';
}
