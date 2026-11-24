<?php
/* ==========================================================================
 * swipe/matches.php  —  Owner: M4 (Swipe and matching)
 * --------------------------------------------------------------------------
 * 1. Requests waiting for you: Accept (it's a match) or Decline.
 * 2. Everyone you have matched with: picture, name, compatibility % and a
 *    Chat button. You can search by name and sort the list.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/compatibility.php';

$me = require_profile();
require_once __DIR__ . '/../listings/listing_helpers.php';

// People asking for the exact location of my flats (answered here or on the flat page)
$locAsks = [];
try {
    $s = $pdo->prepare("SELECT r.id, r.created_at, l.id AS listing_id, l.title, l.area, u.id AS uid, u.name, p.photo, p.answers, p.age, p.stay
                        FROM location_requests r JOIN listings l ON l.id = r.listing_id JOIN users u ON u.id = r.requester_id
                        LEFT JOIN profiles p ON p.user_id = u.id
                        WHERE l.user_id = ? AND r.status = 'pending' ORDER BY r.created_at DESC");
    $s->execute([$_SESSION['user_id']]);
    $locAsks = $s->fetchAll();
} catch (PDOException $e) { /* table not created yet */ }
$myId = (int) $_SESSION['user_id'];

$search = trim($_GET['q'] ?? '');
$sortOptions = ['newest' => 'Newest match first', 'best' => 'Most compatible first', 'name' => 'Name A to Z'];
$sort = array_key_exists($_GET['sort'] ?? '', $sortOptions) ? $_GET['sort'] : 'newest';

// The "other person" in a match is whichever of user1/user2 is not me
$sql = "SELECT m.id AS match_id, m.created_at AS matched_at, u.id AS uid, u.name, p.*
        FROM matches m
        JOIN users u    ON u.id = CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END
        JOIN profiles p ON p.user_id = u.id
        WHERE (m.user1_id = ? OR m.user2_id = ?)";
$params = [$myId, $myId, $myId];
if ($search !== '') {
    $sql .= ' AND LOWER(u.name) LIKE LOWER(?)';
    $params[] = '%' . $search . '%';           // the % signs are added here, the value stays a parameter
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$matches = $stmt->fetchAll();

// ---- Requests: people who sent me a request that I haven't answered yet ----
$stmt = $pdo->prepare("SELECT u.id AS uid, u.name, s.created_at AS sent_at, p.*
                       FROM swipes s
                       JOIN users u    ON u.id = s.swiper_id
                       JOIN profiles p ON p.user_id = u.id
                       WHERE s.swiped_id = ? AND s.direction = 'like'
                         AND s.swiper_id NOT IN (SELECT swiped_id FROM swipes WHERE swiper_id = ?)
                       ORDER BY s.created_at DESC");
$stmt->execute([$myId, $myId]);
$requests = $stmt->fetchAll();
foreach ($requests as &$r) {
    $r['score'] = compatibility_score($me, $r);
}
unset($r);

foreach ($matches as &$m) {
    $m['result'] = compatibility_result($me, $m);
    $m['score'] = $m['result']['score'];
}
unset($m);

usort($matches, fn($a, $b) => match ($sort) {
    'best'  => ($b['score'] ?? -1) <=> ($a['score'] ?? -1),
    'name'  => strcasecmp($a['name'], $b['name']),
    default => strcmp($b['matched_at'], $a['matched_at']),
});

$pageTitle = 'Matches';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <div>
            <h1>Requests and matches</h1>
            <p class="muted">Accept a request to match. Matches can chat. <a href="<?= e(url('groups/index.php')) ?>">Households →</a> · <a href="<?= e(url('communities/index.php')) ?>">Communities →</a></p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('swipe/swipe.php')) ?>">Find more people</a>
    </div>

    <?php if ($requests): ?>
        <section class="card requests" aria-labelledby="req-title">
            <h2 id="req-title">Requests for you <span class="count-pill"><?= count($requests) ?></span></h2>
            <ul class="request-list">
                <?php foreach ($requests as $r): ?>
                    <li class="request-item" data-user-id="<?= (int) $r['uid'] ?>" data-name="<?= e(explode(' ', $r['name'])[0]) ?>">
                        <img class="avatar" src="<?= e(photo_url($r['photo'])) ?>" alt="">
                        <div class="grow">
                            <a href="<?= e(url('profile/view_profile.php?id=' . (int) $r['uid'])) ?>"><strong><?= e($r['name']) ?>, <?= (int) $r['age'] ?></strong></a>
                            <div class="muted small"><?= e($r['city']) ?> · sent <?= e(nice_date($r['sent_at'])) ?></div>
                        </div>
                        <span class="score <?= score_class($r['score']) ?>"><?= $r['score'] === null ? 'Not enough answers' : $r['score'] . '%' ?></span>
                        <div class="btn-row">
                            <button type="button" class="btn btn-primary btn-sm js-accept">Accept</button>
                            <button type="button" class="btn btn-ghost btn-sm js-decline">Decline</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($locAsks): ?>
        <section class="card requests" aria-labelledby="loc-title">
            <h2 id="loc-title">Asking for your flat's exact location <span class="count-pill"><?= count($locAsks) ?></span></h2>
            <p class="muted small">They only see the approximate area until you share. Sharing also shows them street photos of the spot.</p>
            <ul class="request-list">
                <?php foreach ($locAsks as $q): $qs = $q['answers'] !== null ? compatibility_score($me, $q) : null; ?>
                    <li class="request-item">
                        <img class="avatar" src="<?= e(photo_url($q['photo'])) ?>" alt="">
                        <div class="grow">
                            <a href="<?= e(url('profile/view_profile.php?id=' . (int) $q['uid'])) ?>"><strong><?= e($q['name']) ?><?= $q['age'] ? ', ' . (int) $q['age'] : '' ?></strong></a>
                            <div class="muted small">for <a href="<?= e(url('listings/view.php?id=' . (int) $q['listing_id'])) ?>"><?= e($q['title']) ?></a> · asked <?= e(nice_date($q['created_at'])) ?></div>
                        </div>
                        <span class="score <?= score_class($qs) ?>"><?= $qs === null ? 'Not enough answers' : $qs . '%' ?></span>
                        <form method="post" action="<?= e(url('listings/location_request.php')) ?>" class="btn-row"><?= csrf_field() ?>
                            <input type="hidden" name="listing_id" value="<?= (int) $q['listing_id'] ?>"><input type="hidden" name="request_id" value="<?= (int) $q['id'] ?>"><input type="hidden" name="back" value="matches">
                            <button class="btn btn-primary btn-sm" name="action" value="approve">Share location</button>
                            <button class="btn btn-ghost btn-sm" name="action" value="decline">Not now</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <h2 class="section-title">Your matches</h2>
    <form class="toolbar" method="get" action="" role="search">
        <label class="sr-only" for="q">Search matches by name</label>
        <input type="search" id="q" name="q" placeholder="Search by name…" value="<?= e($search) ?>">
        <label class="sr-only" for="sort">Sort</label>
        <select id="sort" name="sort" onchange="this.form.submit()">
            <?php foreach ($sortOptions as $key => $label): ?>
                <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Search</button>
    </form>

    <?php if (!$matches): ?>
        <div class="card empty-state">
            <div class="empty-icon">♥</div>
            <?php if ($search !== ''): ?>
                <h2>No matches called "<?= e($search) ?>"</h2>
                <p>Try a different name.</p>
                <a class="btn" href="<?= e(url('swipe/matches.php')) ?>">Clear search</a>
            <?php else: ?>
                <h2>No matches yet</h2>
                <p>Send requests from Discover. When someone accepts, they show up here.</p>
                <a class="btn btn-primary" href="<?= e(url('swipe/swipe.php')) ?>">Discover people</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="match-grid">
            <?php foreach ($matches as $m): ?>
                <article class="card match-card">
                    <img src="<?= e(photo_url($m['photo'])) ?>" alt="Photo of <?= e($m['name']) ?>" loading="lazy">
                    <div class="body">
                        <div>
                            <h3><?= e($m['name']) ?>, <?= (int) $m['age'] ?></h3>
                            <div class="muted small"><?= e($m['city']) ?> · matched <?= e(nice_date($m['matched_at'])) ?></div>
                        </div>
                        <div><span class="score <?= score_class($m['score']) ?>"><?= $m['score'] === null ? 'Not enough answers yet' : $m['score'] . '% compatible' ?></span> <?= score_explain($m['result']) ?></div>
                        <div class="btn-row">
                            <a class="btn btn-primary btn-sm" href="<?= e(url('chat/conversation.php?match_id=' . (int) $m['match_id'])) ?>">Chat</a>
                            <a class="btn btn-sm" href="<?= e(url('profile/view_profile.php?id=' . (int) $m['uid'])) ?>">View profile</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$actionUrl = json_encode(url('swipe/swipe_action.php'));
$chatUrl   = json_encode(url('chat/connect.php?match_id='));  // right after matching: choose what to share, then chat
$extraScripts = <<<HTML
<script>
/* Accept = send a request back (instant match). Decline = a 'pass'. */
const ACTION_URL = $actionUrl, CHAT_URL = $chatUrl;
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
document.querySelectorAll('.request-item').forEach(function (item) {
    function answer(direction) {
        item.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        fetch(ACTION_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ swiped_id: parseInt(item.dataset.userId, 10), direction: direction }) })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { RM.toast(data.error || 'Something went wrong.', 'error'); return; }
            if (data.matched) { window.location.href = CHAT_URL + data.match_id; return; }
            item.remove(); RM.toast('Request declined.', 'success');
        });
    }
    item.querySelector('.js-accept').addEventListener('click', function () { answer('like'); });
    item.querySelector('.js-decline').addEventListener('click', function () { answer('pass'); });
});
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
