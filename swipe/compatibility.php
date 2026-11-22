<?php
/* ==========================================================================
 * swipe/compatibility.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * The compatibility score: 100 points across seven factors. City is NOT
 * scored; it is a filter on the Discover page.
 *
 * Habits are matched against what the OTHER person says they are okay with,
 * in both directions. A non-smoker who is "fine with" a smoker now scores
 * full points with one; the old rule only paired same with same.
 *
 *   Factor        Max   Rule
 *   Budget         25   25 x average share of each person's range covered by the overlap
 *   Languages      20   1 shared = 10, 2 = 15, 3 or more = 20, none = 0
 *   Bedtime        15   gap between bedtime slots vs the gap each person accepts:
 *                       15 minus 5 per slot beyond the stricter person's limit
 *   Cleanliness    15   each person's tidiness vs the messiest flatmate the
 *                       other accepts: 15 minus 5 per level short (both ways added)
 *   Pets           10   } each way: no habit = fine; habit + "Fine with it" = full,
 *   Smoking       7.5   } "Rather not" = half, "Not okay" = none.
 *   Drinking      7.5   } The worse of the two directions counts.
 *
 * $a and $b are rows from the profiles table.
 * ========================================================================== */

/** How acceptable one person's habit is to the other: 1, 0.5 or 0 */
function tolerance(string $otherHabit, string $myOk): float
{
    if ($otherHabit !== 'yes') return 1.0;            // they don't do it: nothing to tolerate
    return ['yes' => 1.0, 'maybe' => 0.5, 'no' => 0.0][$myOk] ?? 0.0;
}

/**
 * Points for each factor, before rounding. Used to explain a score
 * ("why 82%?") on Discover and on profile pages.
 */
function compatibility_breakdown(array $a, array $b): array
{
    $points = [];

    // Budget (25): how much of EACH person's range the overlap covers, averaged.
    $overlap = min($a['budget_max'], $b['budget_max']) - max($a['budget_min'], $b['budget_min']);
    $points['Budget'] = 0;
    if ($overlap > 0) {
        $widthA = max(1, $a['budget_max'] - $a['budget_min']);   // max(1,...) guards against dividing by 0
        $widthB = max(1, $b['budget_max'] - $b['budget_min']);
        $points['Budget'] = 25 * (($overlap / $widthA) + ($overlap / $widthB)) / 2;
    }

    // Languages (20): 1 shared = 10, 2 shared = 15, 3 or more = 20
    $langsA = array_map('trim', explode(',', strtolower($a['languages'])));
    $langsB = array_map('trim', explode(',', strtolower($b['languages'])));
    $shared = count(array_intersect($langsA, $langsB));
    $points['Languages'] = $shared > 0 ? min(20, 5 + 5 * $shared) : 0;

    // Bedtime (15): how far apart, beyond what the stricter person accepts
    $gap    = abs($a['sleep_slot'] - $b['sleep_slot']);
    $excess = max(0, $gap - min((int) $a['sleep_flex'], (int) $b['sleep_flex']));
    $points['Bedtime'] = max(0, 15 - 5 * $excess);

    // Cleanliness (15): is each person at least as tidy as the other needs?
    $shortfall = max(0, $a['min_cleanliness'] - $b['cleanliness'])
               + max(0, $b['min_cleanliness'] - $a['cleanliness']);
    $points['Cleanliness'] = max(0, 15 - 5 * $shortfall);

    // Pets, smoking, drinking: checked in both directions, the worse one counts
    foreach (['Pets' => ['pets', 'ok_pets', 10], 'Smoking' => ['smoking', 'ok_smoking', 7.5],
              'Drinking' => ['drinking', 'ok_drinking', 7.5]] as $factor => [$habit, $ok, $max]) {
        $points[$factor] = $max * min(tolerance($b[$habit], $a[$ok]), tolerance($a[$habit], $b[$ok]));
    }

    return $points;
}

/**
 * The brief's function: name and signature must not change because other
 * members call it. Adds up the factors and rounds to a whole number 0-100.
 */
function compatibility_score(array $a, array $b): int
{
    return (int) round(array_sum(compatibility_breakdown($a, $b)));
}

/** CSS class for colouring a score: green 75+, amber 50-74, grey below */
function score_class(int $score): string
{
    if ($score >= 75) return 'score-high';
    if ($score >= 50) return 'score-mid';
    return 'score-low';
}

/** Round progress ring showing a score (animated by CSS when it appears) */
function score_ring(int $score): string
{
    return '<div class="score-ring ' . score_class($score) . '" style="--p:' . $score . '" role="img" aria-label="' . $score . '% compatible">'
         . '<svg viewBox="0 0 64 64" aria-hidden="true"><circle class="track" cx="32" cy="32" r="27"/>'
         . '<circle class="bar" cx="32" cy="32" r="27" pathLength="100"/></svg>'
         . '<span>' . $score . '<small>%</small></span></div>';
}

/** Maximum points for each factor, for showing "10.4 / 25" */
const COMPATIBILITY_MAX = [
    'Budget' => 25, 'Bedtime' => 15, 'Cleanliness' => 15, 'Languages' => 20,
    'Pets' => 10, 'Smoking' => 7.5, 'Drinking' => 7.5,
];
