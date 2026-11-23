<?php
/**
 * colleges.php  —  Owner: M3 (Profiles)
 * GET ?q=text -> JSON list of up to 12 matching institutions from
 * data/colleges.txt (70,764 universities, colleges and institutes from the
 * Government of India's AISHE directory). Used by the college search box.
 * Each line of the file: Name|District|State
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$q = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
if (mb_strlen($q) < 3 || mb_strlen($q) > 80) { echo '[]'; exit; }

// Loose matching: ignore punctuation ("Medi-Caps" = "medicaps") and expand common acronyms
function loose(string $s): string { return preg_replace('/[^a-z0-9 ]+/', '', preg_replace('/[-.,()&\/]+/', ' ', mb_strtolower($s))); }
const ACRONYMS = ['iim' => 'indian institute of management', 'iit' => 'indian institute of technology', 'nit' => 'national institute of technology',
    'iiit' => 'indian institute of information technology', 'aiims' => 'all india institute of medical sciences',
    'iiser' => 'indian institute of science education', 'nlu' => 'national law', 'bits' => 'birla institute of technology', 'du' => 'university of delhi', 'davv' => 'devi ahilya', 'sgsits' => 'govindram seksaria', 'jnu' => 'jawaharlal nehru university', 'bhu' => 'banaras hindu'];
$terms = [];
foreach (preg_split('/\s+/', loose($q)) as $w) if ($w !== '') $terms[] = ACRONYMS[$w] ?? $w;
$squash = str_replace(' ', '', loose($q));            // "medicaps" also matches "medi caps"

$hits = [];
$fh = fopen(__DIR__ . '/data/colleges.txt', 'r');
while (($line = fgets($fh)) !== false) {
    [$name, $district, $state] = array_pad(explode('|', rtrim($line, "\n")), 3, '');
    $hay = loose($line);
    $ok = true;
    foreach ($terms as $t) if (!str_contains($hay, $t)) { $ok = false; break; }
    if (!$ok && !str_contains(str_replace(' ', '', $hay), $squash)) continue;
    $n = loose($name);
    $rank = (str_starts_with($n, $terms[0] ?? '') ? 0 : 1) * 1000 + strlen($name);   // starts-with first, then shorter names
    $hits[] = [$rank, ['name' => trim($name, ' "'), 'place' => trim($district . ($district && $state ? ', ' : '') . $state)]];
    if (count($hits) >= 400) break;
}
fclose($fh);
usort($hits, fn($x, $y) => $x[0] <=> $y[0]);
$out = []; $seen = [];
foreach ($hits as [, $h]) { $k = loose($h['name']); if (isset($seen[$k])) continue; $seen[$k] = 1; $out[] = $h; if (count($out) >= 12) break; }
echo json_encode($out, JSON_UNESCAPED_UNICODE);
