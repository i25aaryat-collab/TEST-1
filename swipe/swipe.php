<?php
/* ==========================================================================
 * swipe/swipe.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * "Discover": everyone in your city ranked by compatibility, like the
 * results of a compatibility test (not left/right swiping).
 *   - Each card shows a score ring, the reasons behind the score and a
 *     "Send request" button. If they accept, it's a match and chat opens.
 *   - Filters: city, maximum budget, minimum score, and their habits.
 *   - Sort: best match, youngest, lowest budget.
 *
 * Requests are stored in the brief's `swipes` table: a request is a 'like',
 * "Not for me" is a 'pass'. When both have liked each other, swipe_action.php
 * creates the match.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/compatibility.php';
require_once __DIR__ . '/../communities/community_helpers.php';
require_once __DIR__ . '/../groups/group_helpers.php';

$me   = require_profile();
$myId = (int) $_SESSION['user_id'];

// ---------- 1. Filters from the address bar ----------
$cities = $pdo->query("SELECT DISTINCT p.city FROM profiles p JOIN users u ON u.id = p.user_id
                       WHERE u.role = 'user' ORDER BY p.city")->fetchAll(PDO::FETCH_COLUMN);
$city = trim($_GET['city'] ?? $me['city']);
if ($city !== 'any' && !in_array($city, $cities, true)) $city = $me['city'];
$anyCity = $city === 'any';                       // "Any city": everyone, wherever they live

$maxBudget = isset($_GET['max_budget']) && ctype_digit($_GET['max_budget']) ? (int) $_GET['max_budget'] : null;
$minScore  = in_array($_GET['min_score'] ?? '', ['50', '70', '85'], true) ? (int) $_GET['min_score'] : 0;
$yesNo     = fn($key) => in_array($_GET[$key] ?? '', ['yes', 'no'], true) ? $_GET[$key] : '';
$routine   = in_array($_GET['routine'] ?? '', ['open', 'same'], true) ? $_GET['routine'] : '';   // religion & culture filter
$community = (int) ($_GET['community'] ?? 0);
$nameQ     = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 50);              // name search
$gender    = array_key_exists($_GET['gender'] ?? '', GENDER_OPTIONS) ? $_GET['gender'] : '';
$sortOptions = ['best' => 'Best match first', 'age' => 'Youngest first', 'budget' => 'Lowest budget first'];
$sort = array_key_exists($_GET['sort'] ?? '', $sortOptions) ? $_GET['sort'] : 'best';

// ---------- 2. Candidates ----------
// Not me, not admins, not people I passed, not people already matched with me,
// and not people who sent ME a request (they wait on the Matches page).
$sql = "SELECT u.id AS uid, u.name, p.*,
               (SELECT 1 FROM swipes s WHERE s.swiper_id = ? AND s.swiped_id = u.id AND s.direction = 'like') AS requested
        FROM users u
        JOIN profiles p ON p.user_id = u.id
        WHERE u.role = 'user' AND u.id <> ? AND (? = 'any' OR p.city = ?)
          AND p.looking = 1                                   -- settled households have found their people
          AND u.id NOT IN (SELECT swiped_id FROM swipes WHERE swiper_id = ? AND direction = 'pass')
          AND u.id NOT IN (SELECT swiper_id FROM swipes WHERE swiped_id = ? AND direction = 'like'
                           AND swiper_id NOT IN (SELECT swiped_id FROM swipes WHERE swiper_id = ?))
          AND u.id NOT IN (SELECT CASE WHEN user1_id = ? THEN user2_id ELSE user1_id END
                           FROM matches WHERE user1_id = ? OR user2_id = ?)";
$params = [$myId, $myId, $city, $city, $myId, $myId, $myId, $myId, $myId, $myId];
// Visibility: "everyone", "my college only" (same college as me), or "only in search" (found by name)
$sql .= " AND (p.visibility = 'everyone' OR p.visibility IS NULL
           OR (p.visibility = 'college' AND p.college IS NOT NULL AND LOWER(p.college) = LOWER(?))
           OR (p.visibility = 'search' AND ? <> '' AND LOWER(u.name) LIKE LOWER(?)))";
array_push($params, (string) ($me['college'] ?? ''), $nameQ, '%' . $nameQ . '%');
if ($nameQ !== '') { $sql .= ' AND LOWER(u.name) LIKE LOWER(?)'; $params[] = '%' . $nameQ . '%'; }
if ($maxBudget !== null) { $sql .= ' AND p.budget_min <= ?'; $params[] = $maxBudget; }
if ($routine === 'open') { $sql .= " AND p.religion_pref = 'open'"; }
if ($routine === 'same' && !empty($me['religion']) && $me['religion'] !== 'Prefer not to say') { $sql .= ' AND p.religion = ?'; $params[] = $me['religion']; }
if ($community) { $sql .= ' AND p.user_id IN (SELECT a.user_id FROM community_members a JOIN community_members b ON b.community_id = a.community_id WHERE b.user_id = ? AND a.community_id = ?)'; $params[] = $myId; $params[] = $community; }
if ($gender   !== '')    { $sql .= ' AND p.gender = ?';      $params[] = $gender; }
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// ---------- 3. Score, filter by minimum score, sort ----------
$people = [];
foreach ($stmt->fetchAll() as $c) {
    $c['result'] = compatibility_result($me, $c);
    $c['breakdown'] = $c['result']['sections'];
    $c['score'] = $c['result']['score'];                       // null = not enough shared answers
    if (!$minScore || ($c['score'] !== null && $c['score'] >= $minScore)) $people[] = $c;
}
usort($people, fn($x, $y) => match ($sort) {
    'age'    => $x['age'] <=> $y['age'],
    'budget' => $x['budget_min'] <=> $y['budget_min'],
    default  => [$y['score'] ?? -1, $y['result']['shared']] <=> [$x['score'] ?? -1, $x['result']['shared']],   // best first, no-score last
});

/** Short reasons for a score: up to 3 strong points and 1 weak point */
function score_reasons(array $breakdown): array
{
    $good = ['Age' => 'Ages fit', 'Sleep and daily rhythm' => 'Sleep fits', 'Cleanliness and chores' => 'Tidiness fits',
             'Guests and shared space' => 'Guests fit', 'Food and household practices' => 'Food fits', 'Comfort and boundaries' => 'Comfort fits', 'How long you stay' => 'Same length of stay'];
    $bad  = ['Age' => 'Outside age range', 'Sleep and daily rhythm' => 'Different rhythms', 'Cleanliness and chores' => 'Tidiness differs',
             'Guests and shared space' => 'Guests differ', 'Food and household practices' => 'Food habits differ', 'Comfort and boundaries' => 'Boundaries differ', 'How long you stay' => 'Different stay lengths'];
    $plus = $minus = [];
    foreach ($breakdown as $factor => $pts) {
        $max = COMPATIBILITY_MAX[$factor] ?? 100;
        if (!isset($good[$factor]) || !$max) continue;      // unknown section name: skip the tag, never warn
        $share = $pts / $max;
        if ($share >= 0.8 && count($plus) < 3) $plus[] = $good[$factor];
        if ($share <= 0.4 && !$minus) $minus[] = $bad[$factor];
    }
    return [$plus, $minus];
}

$filtersActive = $maxBudget !== null || $minScore || $routine || $community || $nameQ !== '' || $gender || $city !== $me['city'];

function yes_no_select(string $name, string $label, string $value): string
{
    $html = '<div class="form-group"><label for="f-' . $name . '">' . $label . '</label><select id="f-' . $name . '" name="' . $name . '">';
    foreach (['' => 'Any', 'no' => 'No', 'yes' => 'Yes'] as $v => $text) {
        $html .= '<option value="' . $v . '"' . ($value === $v ? ' selected' : '') . '>' . $text . '</option>';
    }
    return $html . '</select></div>';
}

$pageTitle = 'Discover';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <?php if (isset($me['looking']) && (int) $me['looking'] === 0 && ($hh = my_settled_household($pdo, $myId))): ?>
        <div class="card household-status is-settled settled-note">
            <div class="grow"><strong>You've found your people in <a href="<?= e(url('groups/view.php?id=' . (int) $hh['id'])) ?>"><?= e($hh['name']) ?></a>.</strong>
                <div class="muted small">You're hidden from Discover, so nobody new will send you requests. You can still browse.</div></div>
            <form method="post" action="<?= e(url('groups/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $hh['id'] ?>">
                <button class="btn btn-ghost btn-sm" name="action" value="unsettle">Start looking again</button></form>
        </div>
    <?php endif; ?>
    <div class="page-head">
        <div>
            <h1>Your matches <?= $anyCity ? 'in every city' : 'in ' . e($city) ?></h1>
            <p class="muted"><span id="count"><?= count($people) ?></span> <?= count($people) === 1 ? 'person' : 'people' ?>, ranked by how well you'd live together.</p>
        </div>
        <div class="btn-row">
            <div class="view-toggle" role="group" aria-label="View">
                <button type="button" data-view="swipe" aria-pressed="true">Swipe</button>
                <button type="button" data-view="list" aria-pressed="false">List</button>
            </div>
            <a class="btn" href="<?= e(url('profile/edit_profile.php')) ?>">Retake the test</a>
        </div>
    </div>

    <div class="discover-layout">
        <!-- ---------- Filters ---------- -->
        <details class="filters" <?= $filtersActive ? 'open' : '' ?>>
            <summary class="card">Filters and sort<?= $filtersActive ? ' · on' : '' ?></summary>
            <div class="card">
                <form method="get" action="">
                    <div class="form-group">
                        <label for="f-q">Search by name</label>
                        <input id="f-q" name="q" value="<?= e($nameQ) ?>" placeholder="e.g. Aanya">
                    </div>
                    <div class="form-group">
                        <label for="f-city">City</label>
                        <select id="f-city" name="city">
                            <option value="any" <?= $anyCity ? 'selected' : '' ?>>Any city</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= e($c) ?>" <?= $c === $city ? 'selected' : '' ?>><?= e($c) ?><?= $c === $me['city'] ? ' (your city)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="f-gender">Gender</label>
                        <select id="f-gender" name="gender">
                            <option value="">Anyone</option>
                            <?php foreach (GENDER_OPTIONS as $gk => $gl): ?>
                                <option value="<?= e($gk) ?>" <?= $gk === $gender ? 'selected' : '' ?>><?= e($gl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="f-score">Minimum compatibility</label>
                        <select id="f-score" name="min_score">
                            <?php foreach ([0 => 'Any score', 50 => '50% and up', 70 => '70% and up', 85 => '85% and up'] as $v => $t): ?>
                                <option value="<?= $v ?>" <?= $minScore === $v ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="f-budget">Max budget (₹ / month)</label>
                        <input type="number" id="f-budget" name="max_budget" min="1000" max="200000" step="500" placeholder="Any"
                               value="<?= $maxBudget !== null ? (int) $maxBudget : '' ?>">
                        <span class="hint">People whose budget starts at or below this.</span>
                    </div>
                    <div class="form-group"><label for="f-routine">Religious and cultural routines</label><select id="f-routine" name="routine">
                        <option value="">No preference</option>
                        <option value="open" <?= $routine === 'open' ? 'selected' : '' ?>>Comfortable with different routines</option>
                        <option value="same" <?= $routine === 'same' ? 'selected' : '' ?>>Same religion as me</option>
                    </select></div>
                    <?php $myComms = my_communities($pdo, $myId); if ($myComms): ?>
                    <div class="form-group"><label for="f-community">Community</label><select id="f-community" name="community">
                        <option value="0">Everyone</option>
                        <?php foreach ($myComms as $cm): ?><option value="<?= (int) $cm['id'] ?>" <?= $community === (int) $cm['id'] ? 'selected' : '' ?>><?= e($cm['name']) ?></option><?php endforeach; ?>
                    </select></div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="f-sort">Sort by</label>
                        <select id="f-sort" name="sort">
                            <?php foreach ($sortOptions as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a class="btn" href="<?= e(url('swipe/swipe.php')) ?>">Reset</a>
                    </div>
                </form>
            </div>
        </details>

        <!-- ---------- Ranked results ---------- -->
        <section aria-label="Compatible people">
            <?php if (!$people): ?>
                <div class="card empty-state">
                    <div class="empty-icon">✓</div>
                    <h2>No one new here yet</h2>
                    <p>No one else <?= $anyCity ? 'anywhere' : 'in ' . e($city) ?> fits these filters. Try a lower minimum score, another city, or check your requests.</p>
                    <div class="btn-row" style="justify-content:center">
                        <a class="btn" href="<?= e(url('swipe/swipe.php')) ?>">Reset filters</a>
                        <a class="btn btn-primary" href="<?= e(url('swipe/matches.php')) ?>">See requests</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="deck-wrap">
                <p class="deck-hint muted small">Best match first. Drag right (or →) to send a request, left (or ←) to pass.</p>
                <div class="result-grid">
                    <?php foreach ($people as $i => $c):
                        [$plus, $minus] = score_reasons($c['breakdown']);
                        $first = explode(' ', $c['name'])[0]; ?>
                        <article class="card result-card<?= ($c['score'] ?? 0) >= 85 ? ' is-top' : '' ?>" data-score="<?= (int) $c['score'] ?>" data-user-id="<?= (int) $c['uid'] ?>" data-name="<?= e($first) ?>" style="--i: <?= min($i, 8) ?>">
                            <div class="result-top">
                                <img class="avatar avatar-md" src="<?= e(photo_url($c['photo'])) ?>" alt="">
                                <div class="result-who">
                                    <h2><a href="<?= e(url('profile/view_profile.php?id=' . (int) $c['uid'])) ?>"><?= e($first) ?>, <?= (int) $c['age'] ?></a></h2>
                                    <p class="muted small"><?= $anyCity ? e($c['city']) . ' · ' : '' ?><?= gender_label($c) !== '' ? e(gender_label($c)) . ' · ' : '' ?><?= e(rupees((int) $c['budget_min'])) ?>–<?= e(rupees((int) $c['budget_max'])) ?><?= stay_label($c) !== '' ? ' · stays ' . e(stay_label($c)) : '' ?><?= flatmates_label($c) !== '' ? ' · wants ' . e(strtolower(flatmates_label($c))) : '' ?></p>
                                </div>
                                <?= score_ring($c['score']) ?>
                            </div>
                            <div class="tags">
                                <?php foreach ($plus as $r): ?><span class="tag tag-no">✓ <?= e($r) ?></span><?php endforeach; ?>
                                <?php foreach ($minus as $r): ?><span class="tag tag-yes">! <?= e($r) ?></span><?php endforeach; ?>
                            </div>
                            <?= score_line($c['result']) ?>
                            <?php if ($c['result']['same'] || $c['result']['discuss']): ?><p class="small muted"><?= e($c['result']['same'] ?? '') ?><?= $c['result']['same'] && $c['result']['discuss'] ? '<br>' : '' ?><?= e($c['result']['discuss'] ?? '') ?></p><?php endif; ?>
                            <?php if ($c['bio']): ?><p class="result-bio"><?= e($c['bio']) ?></p><?php endif; ?>
                            <details class="breakdown">
                                <summary>Score breakdown</summary>
                                <table>
                                    <?php foreach ($c['breakdown'] as $factor => $pts): ?>
                                        <tr><td><?= e($factor) ?></td><td><span class="mini-bar"><i style="width:<?= round($pts / (COMPATIBILITY_MAX[$factor] ?? 100) * 100) ?>%"></i></span></td><td><?= round($pts) ?>%</td></tr>
                                    <?php endforeach; ?>
                                </table>
                            </details>
                            <div class="btn-row result-actions">
                                <?php if ($c['requested']): ?>
                                    <button type="button" class="btn btn-sm" disabled>Request sent ✓</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary btn-sm js-request">Send request</button>
                                    <button type="button" class="btn btn-ghost btn-sm js-pass">Not for me</button>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="deck-done card empty-state" hidden>
                    <div class="empty-icon">✓</div>
                    <h2>That's everyone for now</h2>
                    <p>Switch to the list, loosen your filters, or check who sent you a request.</p>
                    <a class="btn btn-primary" href="<?= e(url('swipe/matches.php')) ?>">See requests</a>
                </div>
                <div class="deck-controls">
                    <button type="button" class="deck-btn nope" aria-label="Not for me">✕</button>
                    <button type="button" class="deck-btn like" aria-label="Send request">♥</button>
                </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- ---------- "It's a match!" pop-up ---------- -->
<div class="modal-backdrop" id="match-modal" role="dialog" aria-modal="true" aria-labelledby="match-title">
    <div class="modal">
        <h2 id="match-title">Voilà, you two connected!</h2>
        <p class="muted">You and <strong id="match-name"></strong> both said yes. Here's to finding out if home feels right together.</p>
        <div class="match-avatars">
            <img src="<?= e(photo_url($me['photo'])) ?>" alt="You">
            <img id="match-photo" src="" alt="">
        </div>
        <div class="btn-row">
            <button type="button" class="btn" id="keep-going">Keep looking</button>
            <a class="btn btn-primary" id="match-chat" href="#">Continue</a>
        </div>
    </div>
</div>

<?php
$actionUrl = json_encode(url('swipe/swipe_action.php'));
$chatUrl   = json_encode(url('chat/connect.php?match_id='));  // right after matching: choose what to share, then chat
$extraScripts = <<<HTML
<script>
/* ---------- Requests (M4): sent in the background with fetch() ---------- */
const ACTION_URL = $actionUrl, CHAT_URL = $chatUrl;
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const modal = document.getElementById('match-modal');
let lastFocus = null;

/** Save a request ('like') or a pass, and update the card */
function act(card, direction) {
    const buttons = card.querySelectorAll('.result-actions button');
    buttons.forEach(function (b) { b.disabled = true; });
    fetch(ACTION_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ swiped_id: parseInt(card.dataset.userId, 10), direction: direction })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (!data.ok) { buttons.forEach(function (b) { b.disabled = false; }); if (card.classList.contains('gone')) { card.hidden = false; card.classList.remove('gone', 'show-like', 'show-nope'); card.style.cssText = ''; layoutDeck(); } RM.toast(data.error || 'Something went wrong.', 'error'); return; }
        if (deckMode && card.classList.contains('gone')) {
            if (data.matched) showMatch(card, data.match_id);
            else if (direction === 'like') RM.toast('Request sent to ' + card.dataset.name + '.', 'success');
            return;
        }
        if (direction === 'pass') { card.classList.add('leaving'); setTimeout(function () { card.remove(); }, 300); return; }
        card.querySelector('.result-actions').innerHTML = '<button type="button" class="btn btn-sm" disabled>Request sent ✓</button>';
        if (data.matched) showMatch(card, data.match_id);
        else RM.toast('Request sent. You\'ll match if they accept.', 'success');
    })
    .catch(function () { buttons.forEach(function (b) { b.disabled = false; }); RM.toast('Connection problem, please try again.', 'error'); });
}

function showMatch(card, matchId) {
    lastFocus = document.activeElement;
    document.getElementById('match-name').textContent = card.dataset.name;
    document.getElementById('match-photo').src = card.querySelector('img.avatar').src;
    document.getElementById('match-chat').href = CHAT_URL + encodeURIComponent(matchId);
    modal.classList.add('show');
    document.getElementById('match-chat').focus();
}
function closeMatch() { modal.classList.remove('show'); if (lastFocus) lastFocus.focus(); }

/* ---------- Swipe deck (M4): the same ranked cards, stacked ---------- */
const grid = document.querySelector('.result-grid');
const wrap = document.querySelector('.deck-wrap');
let deckMode = false;

function deckCards() {
    return grid ? Array.from(grid.querySelectorAll('.result-card:not(.gone)')).filter(function (c) { return c.querySelector('.js-request'); }) : [];
}
function layoutDeck() {
    const cards = deckCards();
    grid.querySelectorAll('.result-card').forEach(function (c) { c.classList.remove('top'); c.style.removeProperty('--pos'); });
    cards.forEach(function (c, i) { c.style.setProperty('--pos', Math.min(i, 3)); if (i === 0) c.classList.add('top'); });
    wrap.querySelector('.deck-done').hidden = !deckMode || cards.length > 0;
    wrap.querySelector('.deck-controls').hidden = !deckMode || cards.length === 0;
}
function setView(view) {
    deckMode = view === 'swipe';
    if (!wrap) return;
    wrap.classList.toggle('deck', deckMode);
    document.querySelectorAll('.view-toggle button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.view === view)); });
    try { localStorage.setItem('rm-view', view); } catch (e) {}
    layoutDeck();
}
document.querySelectorAll('.view-toggle button').forEach(function (b) { b.addEventListener('click', function () { setView(b.dataset.view); }); });

/** Throw the top card off screen, then save the choice */
function swipeOut(card, direction) {
    const dir = direction === 'like' ? 1 : -1;
    card.classList.add('gone', direction === 'like' ? 'show-like' : 'show-nope');
    card.style.transition = 'transform .5s cubic-bezier(.22,1,.36,1), opacity .5s';
    card.style.transform = 'translate(' + (dir * 140) + '%, -6%) rotate(' + (dir * 24) + 'deg)';
    card.style.opacity = '0';
    layoutDeck();
    act(card, direction);
    setTimeout(function () { card.hidden = true; }, 500);
    const count = document.getElementById('count'); if (count) count.textContent = Math.max(0, parseInt(count.textContent, 10) - 1);
}

// Dragging the top card
if (grid) grid.addEventListener('pointerdown', function (e) {
    const card = e.target.closest('.result-card.top');
    if (!deckMode || !card || e.target.closest('button, a, summary, details[open] table')) return;
    const startX = e.clientX, startY = e.clientY; let dx = 0;
    card.setPointerCapture(e.pointerId);
    card.style.transition = 'none';
    function move(ev) {
        dx = ev.clientX - startX;
        const dy = (ev.clientY - startY) * 0.3;
        card.style.transform = 'translate(' + dx + 'px,' + dy + 'px) rotate(' + (dx / 18) + 'deg)';
        card.style.setProperty('--like', Math.max(0, Math.min(1, dx / 110)));
        card.style.setProperty('--nope', Math.max(0, Math.min(1, -dx / 110)));
    }
    function up() {
        card.removeEventListener('pointermove', move);
        card.removeEventListener('pointerup', up); card.removeEventListener('pointercancel', up);
        if (Math.abs(dx) > 110) { swipeOut(card, dx > 0 ? 'like' : 'pass'); return; }
        card.style.transition = 'transform .45s cubic-bezier(.34,1.56,.64,1)';
        card.style.transform = ''; card.style.setProperty('--like', 0); card.style.setProperty('--nope', 0);
    }
    card.addEventListener('pointermove', move);
    card.addEventListener('pointerup', up); card.addEventListener('pointercancel', up);
});
function deckAction(direction) { const top = deckCards()[0]; if (top) swipeOut(top, direction); }
if (wrap) {
    wrap.querySelector('.deck-btn.like').addEventListener('click', function () { deckAction('like'); });
    wrap.querySelector('.deck-btn.nope').addEventListener('click', function () { deckAction('pass'); });
}
document.addEventListener('keydown', function (e) {
    if (!deckMode || modal.classList.contains('show') || e.target.closest('input, select, textarea')) return;
    if (e.key === 'ArrowRight') deckAction('like');
    if (e.key === 'ArrowLeft') deckAction('pass');
});
let savedView = 'swipe'; try { savedView = localStorage.getItem('rm-view') || 'swipe'; } catch (e) {}
setView(savedView);

document.querySelectorAll('.result-card').forEach(function (card) {
    const req = card.querySelector('.js-request'), pass = card.querySelector('.js-pass');
    if (req)  req.addEventListener('click', function () { act(card, 'like'); });
    if (pass) pass.addEventListener('click', function () { act(card, 'pass'); });
});
document.getElementById('keep-going').addEventListener('click', closeMatch);
modal.addEventListener('click', function (e) { if (e.target === modal) closeMatch(); });
document.addEventListener('keydown', function (e) {
    if (!modal.classList.contains('show')) return;
    if (e.key === 'Escape') closeMatch();
    if (e.key === 'Tab') RM.trapFocus(modal.querySelector('.modal'), e);   // keep keyboard focus inside the pop-up
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
