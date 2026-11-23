<?php
/* ==========================================================================
 * swipe/compatibility.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * The compatibility score, from the RoomieMatch test (profile/test.php).
 *
 *  - Every question is a 1–5 slider. Answers are stored in profiles.answers
 *    as JSON {"bedtime":3,...}. A skipped question is simply missing:
 *    it is never treated as a low value or as a disagreement.
 *  - Fit per question = 100 - 25 x |A - B|   (same 100, 1 step 75 … 4 steps 0)
 *  - Age: each person says which ages they're fine living with
 *    (profiles.age_min / age_max). Each year outside the other's range costs
 *    25, checked both ways; the worse direction counts. Weight: AGE_WEIGHT.
 *  - Score = weighted average over questions BOTH people answered.
 *  - Fewer than MIN_SHARED shared answers -> no score ("Not enough answers yet").
 *  - Budget and city are Discover filters, not part of the score.
 *  - Religion and culture are never scored (see CULTURE_* on the profile).
 * Weights and questions live here only, so they're easy to tune.
 * ========================================================================== */

const AGE_WEIGHT = 3;        // age counts as much as three ordinary questions
const MIN_SHARED = 5;        // shared answers needed before a score is shown

/** Sections in order: label => [ key => [question, low end, high end, value labels|null, weight] ] */
const TEST_SECTIONS = [
    'Sleep and daily rhythm' => [
        'bedtime'  => ['What time do you usually go to bed?', '8–9 pm', '3 am or later', ['8–9 pm', '10–11 pm', 'Around midnight', '1–2 am', '3 am or later'], 1],
        'waketime' => ['What time do you usually wake up?', '5–6 am', '11 am or later', ['5–6 am', '7 am', '8 am', '9–10 am', '11 am or later'], 1],
        'light'    => ['How much light do you like in the room while sleeping?', 'Pitch dark', 'Bright lights on', null, 1],
        'noise'    => ['How much noise are you comfortable with when sleeping or studying?', 'As quiet as possible', 'Normal household noise', null, 1],
    ],
    'Cleanliness' => [
        'tidy'     => ['How tidy do you like shared spaces to be?', 'Relaxed, lived-in', 'Very neat', null, 1],
        'dishes'   => ['How soon do you prefer dishes to be washed after eating?', 'Later is fine', 'Right away', null, 1],
        'help'     => ['How often would you prefer professional cleaning help?', 'Not at all', 'Several times a week', null, 1],
    ],
    'Guests and shared space' => [
        'day_guests' => ['How often are you comfortable having daytime guests at home?', 'Rarely', 'Very often', null, 1],
        'overnight'  => ['How comfortable are you with overnight guests?', 'Prefer to avoid', 'Comfortable often', null, 1],
        'notice'     => ['How much notice do you prefer before a flatmate invites guests?', 'Little or none', 'Always discuss first', null, 1],
        'shared'     => ['How do you like to use shared spaces?', 'Quiet and private', 'Social and shared', null, 1],
    ],
    'Food at home' => [
        'nonveg'     => ['How comfortable are you with non-vegetarian food cooked in the shared kitchen?', 'Not comfortable', 'Completely comfortable', null, 1],
        'groceries'  => ['How do you prefer to handle groceries and food?', 'Keep separate', 'Share most', null, 1],
        'culture'    => ['How often are religious or cultural practices part of your home routine?', 'Rarely or never', 'Daily', null, 0],   // optional, not scored
    ],
    'Comfort and boundaries' => [
        'pets'       => ['How comfortable are you living with pets?', 'Not comfortable', 'Very comfortable', null, 1],
        'smoking'    => ['How comfortable are you with smoking or vaping around the home?', 'Not comfortable', 'Very comfortable', null, 1],
        'drinking'   => ['How comfortable are you with alcohol or drinking around the house?', 'Not comfortable', 'Very comfortable', null, 1],
        'ac_temp'    => ['What AC temperature do you usually prefer?', '18°C', '30°C', ['18°C', '21°C', '24°C', '27°C', '30°C'], 1],
        'ac_use'     => ['How much AC use and shared electricity cost are you okay with?', 'Minimal AC', 'Often, share the cost', null, 1],
    ],
];

/** Every question key => its definition (flat list) */
function test_questions(): array
{
    $all = [];
    foreach (TEST_SECTIONS as $section => $qs) foreach ($qs as $k => $q) $all[$k] = $q + ['section' => $section];
    return $all;
}

/** A profile's answers as [key => 1..5] (skipped keys are absent) */
function test_answers(array $p): array
{
    $a = json_decode((string) ($p['answers'] ?? ''), true);
    return is_array($a) ? array_filter($a, fn($v) => is_int($v) && $v >= 1 && $v <= 5) : [];
}

/** Age fit 0..100, or null if either side gave no range */
function age_fit(array $a, array $b): ?float
{
    if (empty($a['age_min']) || empty($b['age_min'])) return null;
    $out = fn($age, $p) => max(0, (int) $p['age_min'] - $age, $age - (int) $p['age_max']);
    $worst = max($out((int) $a['age'], $b), $out((int) $b['age'], $a));
    return max(0, 100 - 25 * $worst);
}

/**
 * Full comparison. Returns:
 *  score (int|null), shared (answers compared), sections [label => fit %],
 *  same (a question you answered alike), discuss (biggest difference)
 */
function compatibility_result(array $a, array $b): array
{
    $A = test_answers($a); $B = test_answers($b);
    $sum = $weights = 0; $shared = 0; $sections = []; $same = $diff = null;
    if (($age = age_fit($a, $b)) !== null) {
        $sum += $age * AGE_WEIGHT; $weights += AGE_WEIGHT; $shared++;
        $sections['Age'] = [$age];
    }
    foreach (test_questions() as $k => $q) {
        if (!$q[4] || !isset($A[$k], $B[$k])) continue;           // not scored, or skipped by either
        $gap = abs($A[$k] - $B[$k]);
        $fit = 100 - 25 * $gap;
        $sum += $fit * $q[4]; $weights += $q[4]; $shared++;
        $sections[$q['section']][] = $fit;
        if ($gap === 0 && !$same) $same = $q[3] ? 'You both said "' . $q[3][$A[$k] - 1] . '" for: ' . lcfirst($q[0]) : 'You answered alike on: ' . lcfirst($q[0]);
        if ($gap >= 2 && (!$diff || $gap > $diff[1])) $diff = [$q[0], $gap];
    }
    return [
        'score'    => ($shared >= MIN_SHARED && $weights) ? (int) round($sum / $weights) : null,
        'shared'   => $shared,
        'sections' => array_map(fn($f) => array_sum($f) / count($f), $sections),
        'same'     => $same,
        'discuss'  => $diff ? 'Worth a chat: ' . lcfirst($diff[0]) : null,
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
const COMPATIBILITY_MAX = ['Age' => 100, 'Sleep and daily rhythm' => 100, 'Cleanliness' => 100,
    'Guests and shared space' => 100, 'Food at home' => 100, 'Comfort and boundaries' => 100];

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

/** "82% · based on 12 shared answers ⓘ" (or "Not enough answers yet ⓘ"), linking to How matching works */
function score_line(array $r): string
{
    $info = '<a class="info-i" href="' . e(url('how-matching-works.php')) . '" title="How matching works" aria-label="How matching works">ⓘ</a>';
    if ($r['score'] === null) return '<p class="score-line muted small">Not enough answers yet (' . (int) $r['shared'] . ' shared) ' . $info . '</p>';
    return '<p class="score-line small"><strong>' . $r['score'] . '%</strong> · based on ' . $r['shared'] . ' shared answer' . ($r['shared'] === 1 ? '' : 's') . ' ' . $info . '</p>';
}
