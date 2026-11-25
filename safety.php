<?php
/* ==========================================================================
 * safety.php  —  Owner: M5 (Chat and admin)
 * Safety tips for meeting flatmates and renting, linked from every chat and
 * the footer. Public: anyone can read it before signing up.
 * ========================================================================== */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$tips = [
    ['chat', 'Take your time in the chat', "Most people here are students and young professionals like you. Still, there's no rush: keep talking on RoomieMatch until you've got a feel for them, and share your number with <em>Share contact</em> when you're ready. You can take it back."],
    ['coin', 'Money comes last', "See the flat in person and read the agreement before any deposit, token amount or “advance” changes hands. Pay the owner, not someone's personal UPI “to hold the room”, and keep every receipt."],
    ['lock', 'Some things stay private', "Nobody genuine needs your OTP, passwords, PIN, card or bank details, or your Aadhaar and PAN numbers over chat. Your home address and daily routine can wait too."],
    ['pin', 'Meet in daylight, bring a friend', "Say hello somewhere public first, like a café or your campus. Visit flats in the day with someone you trust, tell a friend where you're going, and leave if anything feels off."],
];
$icons = [
    'chat' => '<path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v6a2.5 2.5 0 0 1-2.5 2.5H11l-4 3.5V15h0A2 2 0 0 1 5 13Z"/>',
    'coin' => '<circle cx="12" cy="12" r="7.5"/><path d="M9.5 9h4.2M9.5 11.5h4.2M12 9c1.6 0 1.6 2.5 0 2.5H9.6l3.6 4"/>',
    'lock' => '<rect x="5.5" y="10.5" width="13" height="9" rx="2.2"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
    'pin'  => '<path d="M12 20s-6-5.2-6-10a6 6 0 0 1 12 0c0 4.8-6 10-6 10Z"/><circle cx="12" cy="10" r="2.2"/>',
];

$pageTitle = 'Safety tips';
require __DIR__ . '/includes/header.php';
?>
<div class="container safety-page">
    <header class="safety-hero">
        <p class="eyebrow">Safety</p>
        <h1>Finding a flatmate should feel exciting, not risky.</h1>
        <p class="muted">A few habits that keep it that way. None of them take long.</p>
    </header>

    <div class="safety-grid">
        <?php foreach ($tips as [$icon, $title, $text]): ?>
            <section class="safety-tip">
                <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icons[$icon] ?></svg>
                <h2><?= e($title) ?></h2>
                <p><?= $text ?></p>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="safety-help">
        <div>
            <h2>If something doesn't feel right</h2>
            <p>Tap <strong>Report</strong> on their profile or a message: our team reads every report, and they won't know it was you. You can <strong>Unmatch</strong> anytime, and they won't be able to message you again.</p>
        </div>
        <div class="safety-numbers">
            <div><span>1930</span>Lost money online? Call the National Cyber Crime Helpline, or report at cybercrime.gov.in</div>
            <div><span>112</span>Any emergency, anywhere in India</div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
