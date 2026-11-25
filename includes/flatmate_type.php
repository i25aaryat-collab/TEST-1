<?php
/* ==========================================================================
 * includes/flatmate_type.php  —  Owner: M3 (Profiles and test)
 * --------------------------------------------------------------------------
 * "What kind of flatmate are you?" Turns compatibility-test answers into a
 * shareable type. Three dials, each 0..100:
 *   rhythm  early bird (0) .. night owl (100)   bedtime, waketime
 *   neat    easygoing (0) .. spotless (100)     tidy, dishes, chores
 *   social  quiet (0) .. social (100)           day guests, overnight, shared space
 * The type is the neat x social corner you're closest to (or the middle),
 * and rhythm adds a first word ("Night-owl", "Early-bird").
 * ========================================================================== */

require_once __DIR__ . '/../swipe/compatibility.php';

const FLATMATE_TYPES = [
    'host'      => ['The Host',          'Spotless kitchen, open door.',            'You like a home people want to come back to: clean enough to show off, lively enough to have friends over on a weeknight.', ['#FF5A5F', '#FF9A6B']],
    'sanctuary' => ['The Sanctuary',     'Calm, clean, candle-lit.',                'Home is where you recharge. Everything has a place, the evenings are quiet, and guests are an occasion, not a habit.',     ['#5B6CFF', '#8EC5FF']],
    'lounge'    => ['The Living Room',   "There's always someone on the couch.",     "You're easy about the dishes and big on company. Your ideal flat has a full sofa, a pot of chai and a door that's rarely locked.", ['#FF8A00', '#FFD15C']],
    'lowkey'    => ['The Low-Key Legend', 'Zero drama, zero judgement.',             "You keep to yourself, don't sweat a messy counter, and get along with almost anyone. The easiest flatmate there is.",          ['#14A38B', '#8FE3C9']],
    'balanced'  => ['The All-Rounder',   'A bit of everything, in the right dose.', "Tidy when it matters, social when it's fun, quiet when it's late. You fit into most homes without changing a thing.",        ['#8B5CF6', '#F0A8FF']],
];

/** Average of some answers (1..5) as 0..100, or null when none were answered */
function ft_dial(array $answers, array $keys): ?int
{
    $v = array_values(array_intersect_key($answers, array_flip($keys)));
    return $v ? (int) round((array_sum($v) / count($v) - 1) * 25) : null;
}

/** The flatmate type for a profile, or null if the test isn't done enough */
function flatmate_type(array $profile): ?array
{
    $a = test_answers($profile);
    $rhythm = ft_dial($a, ['bedtime', 'waketime']);
    $neat   = ft_dial($a, ['tidy', 'dishes', 'chores']);
    $social = ft_dial($a, ['day_guests', 'overnight', 'shared']);
    if ($neat === null || $social === null) return null;

    if (abs($neat - 50) <= 15 && abs($social - 50) <= 15) $key = 'balanced';
    elseif ($neat >= 50) $key = $social >= 50 ? 'host' : 'sanctuary';
    else                 $key = $social >= 50 ? 'lounge' : 'lowkey';

    $prefix = $rhythm === null ? '' : ($rhythm >= 70 ? 'Night-owl' : ($rhythm <= 30 ? 'Early-bird' : ''));
    [$name, $line, $about, $colors] = FLATMATE_TYPES[$key];
    return [
        'key' => $key, 'name' => $name, 'prefix' => $prefix, 'line' => $line, 'about' => $about, 'colors' => $colors,
        'dials' => [
            ['Early bird', 'Night owl', $rhythm ?? 50],
            ['Easygoing', 'Spotless', $neat],
            ['Quiet', 'Social', $social],
        ],
    ];
}

/** This user's invite code (made the first time it's needed) */
function ref_code(PDO $pdo, int $userId): string
{
    $s = $pdo->prepare('SELECT ref_code FROM users WHERE id = ?');
    $s->execute([$userId]);
    if ($code = $s->fetchColumn()) return $code;
    for ($i = 0; $i < 5; $i++) {
        $code = substr(strtr(base64_encode(random_bytes(6)), '+/', 'kz'), 0, 7);
        try {
            $u = $pdo->prepare('UPDATE users SET ref_code = ? WHERE id = ? AND ref_code IS NULL');
            $u->execute([$code, $userId]);
            if ($u->rowCount()) return $code;
            $s->execute([$userId]); return (string) $s->fetchColumn();
        } catch (PDOException $e) { if (!is_duplicate_error($e)) throw $e; }
    }
    throw new RuntimeException('Could not make an invite code');
}
