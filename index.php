<?php
/* ==========================================================================
 * index.php  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * Landing page, told as a story while you scroll:
 *   1. Hero: a crowd of characters that turn to face your cursor
 *   2. Story: a quiet building lights up window by window, then we step inside (js/home-story.js)
 *   3. Five features, each revealed with its own small animation
 *   4. Call to action
 * The animation code is in js/landing.js; all of it switches off for
 * people who ask their device for reduced motion.
 * ========================================================================== */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/swipe/compatibility.php';

// Message after an account is deleted (session is gone, so we use the URL)
if (isset($_GET['deleted'])) {
    set_flash('success', 'Your account and all your data have been deleted. Sorry to see you go!');
}

// ---- Live numbers from the database ----
$stats = $pdo->query("
    SELECT (SELECT COUNT(*) FROM users WHERE role = 'user') AS members,
           (SELECT COUNT(*) FROM matches)                    AS matches,
           (SELECT COUNT(*) FROM listings WHERE status = 'open' AND user_id NOT IN (SELECT id FROM users WHERE email = 'listings@roomiematch.test')) AS flats
")->fetch();

// Two real rooms for the "Flats" feature
$rooms = $pdo->query("SELECT id, title, area, city, rent, room_type, photo FROM listings WHERE status = 'open' AND user_id NOT IN (SELECT id FROM users WHERE email = 'listings@roomiematch.test') ORDER BY created_at DESC LIMIT 2")->fetchAll();

// Main button depends on who is visiting
if (!is_logged_in())    { $ctaUrl = url('auth/register.php');   $ctaText = 'Take the compatibility test'; }
elseif (is_admin())     { $ctaUrl = url('admin/dashboard.php'); $ctaText = 'Open admin dashboard'; }
else                    { $ctaUrl = url('swipe/swipe.php');     $ctaText = 'See my matches'; }

$clips = ['v03', 'v02', 'v07', 'v05', 'v08', 'v11', 'v01', 'v09', 'v04', 'v06'];   // media/characters/*.mp4
$bodyClass = 'page-home';
require __DIR__ . '/includes/header.php';

/** One character clip (muted, looping, with a still poster for slow connections) */
function clip(string $id, string $class = ''): string
{
    return '<video class="' . $class . '" src="' . e(url('media/characters/' . $id . '.mp4')) . '" poster="' . e(url('media/characters/' . $id . '.jpg'))
         . '" muted loop playsinline autoplay preload="metadata" disablepictureinpicture disableremoteplayback aria-hidden="true" tabindex="-1"></video>';
}
?>

<!-- ========== 1. Hero ========== -->
<section class="hero" aria-labelledby="hero-title">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">A compatibility test for flatmates</span>
            <h1 id="hero-title">Find a flatmate you'll <em>actually</em> get along with.</h1>
            <p class="lead">Answer what you're okay with: smoking, mess, 3 AM guitar. We score everyone in your city out of 100. Send a request, match, and chat live.</p>
            <div class="btn-row">
                <a class="btn btn-primary btn-lg" href="<?= e($ctaUrl) ?>"><?= e($ctaText) ?></a>
                <a class="btn btn-lg" href="#story">See how it works</a>
            </div>
            <dl class="hero-stats">
                <div><dt>members</dt><dd data-count="<?= (int) $stats['members'] ?>"><?= (int) $stats['members'] ?></dd></div>
                <div><dt>matches made</dt><dd data-count="<?= (int) $stats['matches'] ?>"><?= (int) $stats['matches'] ?></dd></div>
                <div><dt>rooms listed</dt><dd data-count="<?= (int) $stats['flats'] ?>"><?= (int) $stats['flats'] ?></dd></div>
            </dl>
        </div>

        <!-- The crowd: each tile turns to face the cursor -->
        <div class="crowd" id="crowd" aria-hidden="true">
            <?php foreach ($clips as $i => $id): ?>
                <div class="crowd-tile" style="--i: <?= $i ?>"><?= clip($id) ?></div>
            <?php endforeach; ?>
            <a class="crowd-tile crowd-you" style="--i: 10" href="<?= e($ctaUrl) ?>" tabindex="-1"><span>You?</span></a>
            <div class="crowd-tile crowd-score" style="--i: 11"><?= score_ring(94) ?><small>Sunny + Meher</small></div>
        </div>
    </div>
    <a class="scroll-cue" href="#find-your-people" aria-label="Scroll to the story"><span></span></a>
</section>

<!-- ========== 2. Find your people (pinned while you scroll; js/home-story.js) ========== -->
<section class="fyp" id="find-your-people" aria-label="Find your people" data-base="<?= e(url('media/story/')) ?>">
    <div class="fyp-view">
        <div class="fyp-backdrop" aria-hidden="true"></div>
        <div class="fyp-stage"></div>
        <div class="fyp-veil" aria-hidden="true"></div>
        <div class="fyp-room">
            <div class="fyp-shot">
                <?php foreach (['room.jpg' => 'Flatmates hanging out in their living room', 'room2.jpg' => 'Friends sharing popcorn on the sofa', 'room3.jpg' => 'A sleepover under paper bunting', 'room4.jpg' => 'Friends talking around a coffee table', 'room5.jpg' => 'Flatmates laughing on the living-room floor', 'room6.jpg' => 'Late-night chai and giggles on the bed'] as $i => $alt): ?>
                    <img class="fyp-slide<?= $i === 'room.jpg' ? ' on' : '' ?>" src="<?= e(url('media/story/' . $i)) ?>" alt="<?= e($alt) ?>" loading="lazy" decoding="async">
                <?php endforeach; ?>
                <div class="fyp-scrim"></div>
            </div>
            <div class="fyp-copy">
                <h2>Don’t just find a room. <em>Find your people.</em></h2>
                <p>Find people who share your vibe, your interests and your idea of home.</p>
                <a class="btn btn-primary btn-lg" href="<?= e($ctaUrl) ?>"><?= e($ctaText) ?></a>
            </div>
            <div class="fyp-dots" role="group" aria-label="Choose a photo"></div>
        </div>
    </div>
</section>

<!-- ========== 3. Features ========== -->
<section class="features container" aria-label="Features">

    <article class="feature reveal">
        <div class="feature-copy">
            <span class="feature-no">01 · The test</span>
            <h2>Say what you can live with, not just how you live.</h2>
            <p>Not a smoker but fine with one? Tell us. Every question asks what you're okay with in a flatmate, so open-minded people get more good matches.</p>
        </div>
        <div class="feature-demo demo-test card">
            <p class="demo-q">Living with someone who smokes?</p>
            <div class="demo-pills"><span>Fine with it</span><span>Rather not</span><span>Not okay</span></div>
            <p class="demo-q">The messiest flatmate you could live with</p>
            <div class="demo-pills five"><span>Untidy</span><span>A bit messy</span><span>Average</span><span>Tidy</span><span>Very tidy</span></div>
        </div>
    </article>

    <article class="feature reveal flip">
        <div class="feature-copy">
            <span class="feature-no">02 · Your ranking</span>
            <h2>Everyone in your city, scored out of 100.</h2>
            <p>Budget, bedtime, tidiness, smoking, drinking, pets and languages. Filter by score or habits, open the breakdown, and send a request to the ones you like.</p>
        </div>
        <div class="feature-demo demo-rank">
            <?php foreach ([['c03', 'Meher, 23', 94, 'Bedtimes fit'], ['c11', 'Lina, 27', 81, 'Tidiness fits'], ['c06', 'Aman, 26', 58, 'Budgets differ']] as $k => [$av, $who, $sc, $why]): ?>
                <div class="card demo-row" style="--k: <?= $k ?>">
                    <img class="avatar" src="<?= e(avatar_src($av)) ?>" alt="">
                    <div class="grow"><strong><?= $who ?></strong><div class="muted small"><?= $why ?></div></div>
                    <?= score_ring($sc) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="feature reveal">
        <div class="feature-copy">
            <span class="feature-no">03 · Live chat</span>
            <h2>Match, then talk in real time.</h2>
            <p>Messages arrive within seconds, an unread count lights up like on Instagram, and you can send emojis, stickers and GIFs. Anyone being rude can be reported in one tap.</p>
        </div>
        <div class="feature-demo demo-chat card">
            <div class="demo-chat-head"><img class="avatar" src="<?= e(avatar_src('p06')) ?>" alt=""> <strong>Meher</strong> <span class="nav-badge static">2</span></div>
            <div class="demo-bubble">Hi! Saw you're 94% 😄</div>
            <div class="demo-bubble mine">Haha yes! Flat hunting this weekend?</div>
            <img class="demo-sticker" src="<?= e(url('css/stickers/c03.svg')) ?>" alt="">
            <img class="demo-gif" src="<?= e(url('css/gifs/deal.svg')) ?>" alt="">
        </div>
    </article>

    <article class="feature reveal flip">
        <div class="feature-copy">
            <span class="feature-no">04 · Flats</span>
            <h2>Have a room? List it. Need one? Browse.</h2>
            <p>People with a flat post a room with rent, area and move-in date. Seekers search and filter, see how compatible they'd be with the owner, and message them.</p>
            <a class="btn" href="<?= e(url('listings/index.php')) ?>">Browse rooms</a>
        </div>
        <div class="feature-demo demo-flats">
            <?php foreach ($rooms as $k => $r): ?>
                <a class="card demo-room" style="--k: <?= $k ?>" href="<?= e(url('listings/view.php?id=' . (int) $r['id'])) ?>">
                    <span class="demo-room-pic"><?php if (!empty($r['photo'])): ?><img src="<?= e(photo_url($r['photo'])) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><?= house_icon() ?><?php endif; ?></span>
                    <span class="listing-price"><?= $r['rent'] ? e(rupees((int) $r['rent'])) . '<small>/month</small>' : 'Rent on request' ?></span>
                    <strong><?= e($r['title']) ?></strong>
                    <span class="muted small"><?= e($r['area']) ?>, <?= e($r['city']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="feature reveal">
        <div class="feature-copy">
            <span class="feature-no">05 · Safe by design</span>
            <h2>Reports go straight to a human.</h2>
            <p>Report a message, a listing or a profile. Admins review every report and remove what breaks the rules. Contact details are never shown on your profile.</p>
        </div>
        <div class="feature-demo demo-mod">
            <div class="card demo-report"><strong>Reported message</strong><span class="muted small">"Pay the deposit to this number first…"</span></div>
            <div class="demo-arrow">→</div>
            <div class="card demo-admin"><span class="badge badge-removed">removed</span><span class="small">Reviewed by admin</span></div>
        </div>
    </article>
</section>

<!-- ========== 4. Call to action ========== -->
<section class="section container reveal">
    <div class="cta-band">
        <div class="cta-faces" aria-hidden="true">
            <?php foreach (['c01', 'c03', 'c04', 'c07', 'c09', 'c11'] as $k => $av): ?>
                <span class="look-face" data-char="<?= (int) substr($av, 1) - 1 ?>" style="--k: <?= $k ?>"></span>
            <?php endforeach; ?>
        </div>
        <h2>Your next flatmate might already be here.</h2>
        <p>The test takes about two minutes.</p>
        <a class="btn btn-primary btn-lg" href="<?= e($ctaUrl) ?>"><?= e($ctaText) ?></a>
    </div>
</section>

<?php
$extraScripts = '<script src="' . e(url('js/characters.js')) . '"></script><script src="' . e(url('js/landing.js')) . '?v=' . substr(md5_file(__DIR__ . '/js/landing.js'), 0, 8) . '"></script><script src="' . e(url('js/home-story.js')) . '?v=' . substr(md5_file(__DIR__ . '/js/home-story.js'), 0, 8) . '"></script>'
  . '<script>document.querySelectorAll(".look-face").forEach(function(el){el.innerHTML=rmCharacterSVG(RM_CHARACTERS[+el.dataset.char],{x:0,y:0},true)});'
  . 'addEventListener("pointermove",function(e){document.querySelectorAll(".look-face svg").forEach(function(s){var r=s.getBoundingClientRect();rmLook(s,(e.clientX-r.left-r.width/2)/innerWidth*3,(e.clientY-r.top-r.height/2)/innerHeight*3)})});</script>';
require __DIR__ . '/includes/footer.php';
