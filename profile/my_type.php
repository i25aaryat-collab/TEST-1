<?php
/* ==========================================================================
 * profile/my_type.php  —  Owner: M3 (Profiles and test)
 * --------------------------------------------------------------------------
 * "You're The Host": the reveal after the compatibility test, and a card to
 * share. The card is drawn in the browser (canvas, 1080x1920 story size) and
 * can be shared with the phone's share sheet or downloaded. The invite link
 * (type.php?t=host&by=CODE) shows the type to friends and credits sign-ups.
 * ========================================================================== */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/flatmate_type.php';

$me = require_profile();
$type = flatmate_type($me);
if (!$type) { set_flash('error', 'Finish the compatibility test first to see your flatmate type.'); redirect('profile/test.php'); }

$code = ref_code($pdo, (int) $_SESSION['user_id']);
$link = (getenv('APP_URL') ?: ((isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . BASE_URL : 'https://roomiezmatch.vercel.app')))
      . '/type.php?t=' . $type['key'] . '&by=' . $code;
$joined = $pdo->prepare('SELECT COUNT(*) FROM users WHERE referred_by = ?');
$joined->execute([$_SESSION['user_id']]);
$joined = (int) $joined->fetchColumn();
$fresh = isset($_GET['fresh']);
$full = trim($type['prefix'] . ' ' . $type['name']);
if ($type['prefix']) $full = $type['prefix'] . ' ' . preg_replace('/^The /', '', $type['name']);

$pageTitle = 'Your flatmate type';
require __DIR__ . '/../includes/header.php';
?>

<div class="container type-page" style="--t1: <?= e($type['colors'][0]) ?>; --t2: <?= e($type['colors'][1]) ?>">
    <section class="type-reveal">
        <p class="type-eyebrow"><?= $fresh ? 'Test done. Here is what it says about you.' : 'Your flatmate type' ?></p>
        <h1><?= $type['prefix'] ? e($type['prefix']) . '<br>' : '' ?><?= e($type['name']) ?></h1>
        <p class="type-line"><?= e($type['line']) ?></p>
        <p class="type-about"><?= e($type['about']) ?></p>
        <div class="type-dials">
            <?php foreach ($type['dials'] as [$left, $right, $v]): ?>
                <div class="type-dial"><span><?= e($left) ?></span><div class="type-track"><i style="left: <?= (int) $v ?>%"></i></div><span><?= e($right) ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="type-share">
        <canvas id="type-card" width="1080" height="1920" aria-label="Your flatmate type card"></canvas>
        <div class="type-actions">
            <h2>Send it to your future flatmate</h2>
            <p class="muted">Friends who open your link see your type and can find theirs.</p>
            <div class="btn-row">
                <button class="btn btn-primary" id="share-card">Share card</button>
                <button class="btn" id="download-card">Download for story</button>
            </div>
            <div class="copy-link"><input type="text" id="invite-link" value="<?= e($link) ?>" readonly aria-label="Your invite link"><button class="btn btn-sm" id="copy-link">Copy</button></div>
            <p class="muted small"><?= $joined ? $joined . ' ' . ($joined === 1 ? 'friend has' : 'friends have') . ' joined from your link.' : 'Nobody has joined from your link yet.' ?></p>
            <?php if ($fresh): ?><a class="btn btn-ghost" href="<?= e(url('swipe/swipe.php')) ?>">See my matches →</a><?php endif; ?>
        </div>
    </section>
</div>

<?php
$cardData = json_encode(['name' => $type['name'], 'prefix' => $type['prefix'], 'line' => $type['line'], 'colors' => $type['colors'],
    'dials' => $type['dials'], 'url' => parse_url($link, PHP_URL_HOST), 'first' => explode(' ', $me['name'] ?? $_SESSION['name'])[0], 'link' => $link]);
$extraScripts = <<<HTML
<script>
(function () {
const T = $cardData, c = document.getElementById('type-card'), x = c.getContext('2d');
const font = getComputedStyle(document.body).fontFamily;
function wrap(text, maxW) { const words = text.split(' '), lines = []; let l = '';
    words.forEach(function (w) { const t = l ? l + ' ' + w : w; if (x.measureText(t).width > maxW && l) { lines.push(l); l = w; } else l = t; }); lines.push(l); return lines; }
function draw() {
    const g = x.createLinearGradient(0, 0, 1080, 1920); g.addColorStop(0, T.colors[0]); g.addColorStop(1, T.colors[1]);
    x.fillStyle = g; x.fillRect(0, 0, 1080, 1920);
    // soft light circles for depth
    [[880, 300, 360, .16], [160, 1500, 420, .12], [940, 1700, 220, .1]].forEach(function (k) { x.beginPath(); x.arc(k[0], k[1], k[2], 0, 7); x.fillStyle = 'rgba(255,255,255,' + k[3] + ')'; x.fill(); });
    x.fillStyle = '#fff'; x.textBaseline = 'top';
    x.font = '700 40px ' + font; x.globalAlpha = .9; x.fillText('WHAT KIND OF FLATMATE ARE YOU?', 96, 200); x.globalAlpha = 1;
    x.font = '400 64px ' + font; x.fillText(T.first + ' is', 96, 380);
    let y = 470; x.font = '800 132px ' + font;
    if (T.prefix) { x.fillText(T.prefix, 96, y); y += 150; }
    wrap(T.name, 900).forEach(function (l) { x.fillText(l, 96, y); y += 150; });
    x.font = '400 56px ' + font; y += 30;
    wrap(T.line, 880).forEach(function (l) { x.fillText(l, 96, y); y += 72; });
    y = Math.max(y + 110, 1180);
    T.dials.forEach(function (d) {
        x.font = '600 38px ' + font; x.globalAlpha = .92;
        x.textAlign = 'left'; x.fillText(d[0], 96, y); x.textAlign = 'right'; x.fillText(d[1], 984, y); x.textAlign = 'left'; x.globalAlpha = 1;
        x.fillStyle = 'rgba(255,255,255,.35)'; x.beginPath(); x.roundRect(96, y + 62, 888, 14, 7); x.fill();
        x.fillStyle = '#fff'; x.beginPath(); x.arc(96 + 888 * d[2] / 100, y + 69, 26, 0, 7); x.fill();
        y += 150;
    });
    x.font = '700 44px ' + font; x.fillText('Find yours on RoomieMatch', 96, 1720);
    x.font = '400 36px ' + font; x.globalAlpha = .85; x.fillText(T.url, 96, 1782); x.globalAlpha = 1;
}
draw();
function blob() { return new Promise(function (ok) { c.toBlob(ok, 'image/png'); }); }
document.getElementById('download-card').addEventListener('click', function () {
    blob().then(function (b) { const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'my-flatmate-type.png'; a.click(); });
});
document.getElementById('share-card').addEventListener('click', function () {
    const text = "I'm " + (T.prefix ? T.prefix + ' ' : '') + T.name.replace(/^The /, 'the ') + ' on RoomieMatch. What kind of flatmate are you?';
    blob().then(function (b) {
        const file = new File([b], 'my-flatmate-type.png', { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) return navigator.share({ files: [file], text: text + ' ' + T.link });
        if (navigator.share) return navigator.share({ text: text, url: T.link });
        return navigator.clipboard.writeText(text + ' ' + T.link).then(function () { RM.toast('Link copied. Paste it anywhere.', 'success'); });
    }).catch(function () {});
});
document.getElementById('copy-link').addEventListener('click', function () {
    navigator.clipboard.writeText(T.link).then(function () { RM.toast('Link copied', 'success'); });
});
})();
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
