<?php
/**
 * swipe/lifestyle.php  —  Owner: M4 (Matching)
 * The optional five-question "Lifestyle alignment".
 *  - Each answer is 0..4 on a five-point scale; NULL means skipped.
 *  - Answers 0,1,2,3,4 steps apart earn 100%, 75%, 50%, 25%, 0%.
 *  - Weighted average over questions BOTH people answered; skipped never counts as disagreement.
 *  - A percentage is shown only when at least 3 answers overlap.
 * Weights live here only — change them in one place.
 */

const LIFESTYLE_QUESTIONS = [
    'q_clean'  => ['weight' => 30, 'topic' => 'Cleanliness',          'ask' => 'After cooking, when do you usually wash up?',
                   'low' => 'Right away', 'high' => 'The next day is fine',
                   'same' => ['wash up right away', 'wash up soon after', 'wash up when it suits you', 'leave dishes for later', 'are fine leaving dishes till the next day'],
                   'discuss' => 'You wash up on different timelines—what works after dinner?'],
    'q_quiet'  => ['weight' => 25, 'topic' => 'Sleep and quiet hours', 'ask' => 'When do you like the flat to get quiet?',
                   'low' => 'Early evening', 'high' => 'Late at night',
                   'same' => ['like the flat quiet early', 'like it quiet fairly early', 'like it quiet around midnight', 'like it quiet late', 'are fine with late nights'],
                   'discuss' => 'You prefer different quiet hours—what would work on weeknights?'],
    'q_meals'  => ['weight' => 20, 'topic' => 'Eating and kitchen habits', 'ask' => 'How do you prefer to handle meals?',
                   'low' => 'Cook separately', 'high' => 'Usually cook together',
                   'same' => ['cook separately', 'mostly cook separately', 'mix it up with meals', 'often cook together', 'like cooking together'],
                   'discuss' => 'You see meals differently—shared groceries or separate shelves?'],
    'q_guests' => ['weight' => 15, 'topic' => 'Guests', 'ask' => 'How often do you like having friends over?',
                   'low' => 'Rarely', 'high' => 'Most days',
                   'same' => ['rarely have friends over', 'have friends over now and then', 'have friends over sometimes', 'often have friends over', 'like friends over most days'],
                   'discuss' => 'You feel differently about guests—how much notice feels fair before friends come over?'],
    'q_chores' => ['weight' => 10, 'topic' => 'Chores', 'ask' => 'How do you like to divide housework?',
                   'low' => 'Fixed rota', 'high' => 'Work it out as we go',
                   'same' => ['like a fixed chore rota', 'like a loose rota', 'are flexible about chores', 'mostly work chores out as you go', 'work chores out as you go'],
                   'discuss' => 'You split chores differently—rota or go with the flow?'],
];

/** Read one answer from a profile row: int 0..4 or null when skipped */
function lifestyle_answer(array $p, string $key): ?int
{
    return isset($p[$key]) && $p[$key] !== '' && $p[$key] !== null ? (int) $p[$key] : null;
}

/**
 * Compare two profiles.
 * Returns ['score' => int|null, 'compared' => n, 'shared' => sentence|null, 'discuss' => question|null]
 */
function lifestyle_alignment(array $a, array $b): array
{
    $sum = $weights = 0; $compared = 0; $best = null; $worst = null;
    foreach (LIFESTYLE_QUESTIONS as $key => $q) {
        $x = lifestyle_answer($a, $key); $y = lifestyle_answer($b, $key);
        if ($x === null || $y === null) continue;               // skipped: not compared
        $gap = abs($x - $y);
        $sum += $q['weight'] * (1 - $gap / 4);
        $weights += $q['weight'];
        $compared++;
        if ($gap === 0 && (!$best || $q['weight'] > $best[1])) $best = [$key, $q['weight'], $x];
        if ($gap >= 2 && (!$worst || $gap * $q['weight'] > $worst[1])) $worst = [$key, $gap * $q['weight']];
    }
    return [
        'score'    => $compared >= 3 ? (int) round(100 * $sum / $weights) : null,
        'compared' => $compared,
        'shared'   => $best ? 'You both ' . LIFESTYLE_QUESTIONS[$best[0]]['same'][$best[2]] . '.' : null,
        'discuss'  => $worst ? LIFESTYLE_QUESTIONS[$worst[0]]['discuss'] : null,
    ];
}

/** Small HTML block: "Lifestyle alignment 82% · 4 of 5 compared" + explanation */
function lifestyle_box(array $me, array $them): string
{
    $l = lifestyle_alignment($me, $them);
    if ($l['score'] === null) {
        return '<div class="lifestyle-box muted small">Lifestyle alignment appears once you both answer at least 3 of the 5 quiz questions'
             . ($l['compared'] ? ' (' . $l['compared'] . ' compared so far)' : '') . '.</div>';
    }
    $html = '<div class="lifestyle-box"><strong>Lifestyle alignment ' . $l['score'] . '%</strong>'
          . ' <span class="muted small">· ' . $l['compared'] . ' of 5 answers compared</span>';
    if ($l['shared'])  $html .= '<p class="small">' . e($l['shared']) . '</p>';
    if ($l['discuss']) $html .= '<p class="small">💬 ' . e($l['discuss']) . '</p>';
    return $html . '<p class="tiny muted">Compares day-to-day habits only. It doesn\'t predict how living together will go.</p></div>';
}
