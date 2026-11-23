<?php
/* ==========================================================================
 * swipe/compatibility.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * The compatibility score, from the RoomieMatch test (profile/test.php).
 *
 *  - The twenty questions use 0..100 sliders stored in profiles.answers JSON.
 *  - Fit per question = 100 - the distance between two answers.
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

/** Stored answers are normalized to 0..100. Older 1..5 scores are expanded to that scale. */
function test_answers(array $profile): array
{
    $payload = json_decode((string) ($profile['answers'] ?? ''), true);
    if (!is_array($payload)) return [];
    $isCurrentScale = (int) ($payload['_scale'] ?? 0) === 100;
    unset($payload['_scale']);

    $answers = [];
    foreach (test_questions() as $key => $_question) {
        $value = $payload[$key] ?? null;
        if (!is_numeric($value)) continue;
        $value = (int) $value;
        if ($isCurrentScale && $value >= 0 && $value <= 100) {
            $answers[$key] = $value;
        } elseif (!$isCurrentScale && $value >= 1 && $value <= 5) {
            $answers[$key] = ($value - 1) * 25;
        }
    }
    return $answers;
}

/**
 * Each shared answer has equal weight: fit = 100 - the absolute distance
 * between two 0..100 slider values. Skips are excluded from both numerator
 * and denominator, and fewer than five shared answers yields no score.
 */
function compatibility_result(array $a, array $b): array
{
    $answersA = test_answers($a);
    $answersB = test_answers($b);
    $sum = 0;
    $shared = 0;
    $sections = [];
    $same = $largestDifference = null;

    foreach (test_questions() as $key => $question) {
        if (!isset($answersA[$key], $answersB[$key])) continue;
        $gap = abs($answersA[$key] - $answersB[$key]);
        $fit = 100 - $gap;
        $sum += $fit;
        $shared++;
        $sections[$question['section']][] = $fit;
        if ($gap <= 5 && !$same) $same = 'You both chose similar answers for: ' . lcfirst($question[0]);
        if ($gap >= 25 && (!$largestDifference || $gap > $largestDifference[1])) $largestDifference = [$question[0], $gap];
    }

    return [
        'score' => $shared >= MIN_SHARED ? (int) round($sum / $shared) : null,
        'shared' => $shared,
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

/** "82% · based on 12 shared answers ⓘ" (or "Not enough answers yet ⓘ"), linking to How matching works */
function score_line(array $r): string
{
    $info = '<a class="info-i" href="' . e(url('how-matching-works.php')) . '" title="How matching works" aria-label="How matching works">ⓘ</a>';
    if ($r['score'] === null) return '<p class="score-line muted small">Not enough answers yet (' . (int) $r['shared'] . ' shared) ' . $info . '</p>';
    return '<p class="score-line small"><strong>' . $r['score'] . '%</strong> · based on ' . $r['shared'] . ' shared answer' . ($r['shared'] === 1 ? '' : 's') . ' ' . $info . '</p>';
}
