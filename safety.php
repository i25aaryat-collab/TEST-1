<?php
/* ==========================================================================
 * safety.php  —  Owner: M5 (Chat and admin)
 * Safety tips for meeting flatmates and renting, linked from every chat and
 * the footer. Public: anyone can read it before signing up.
 * ========================================================================== */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$tips = [
    ['coin', 'Money last', 'See the flat and the agreement before paying anything. Pay the owner, not someone\'s UPI to "hold the room".'],
    ['lock', 'Private stays private', 'OTPs, passwords, card and bank details, Aadhaar and PAN never need to be in a chat.'],
    ['chat', 'Share contact when you want', "<em>Share contact</em> in the chat gives your number, and takes it back if you change your mind."],
];
$icons = [
    'chat' => '<path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v6a2.5 2.5 0 0 1-2.5 2.5H11l-4 3.5V15h0A2 2 0 0 1 5 13Z"/>',
    'coin' => '<circle cx="12" cy="12" r="7.5"/><path d="M9.5 9h4.2M9.5 11.5h4.2M12 9c1.6 0 1.6 2.5 0 2.5H9.6l3.6 4"/>',
    'lock' => '<rect x="5.5" y="10.5" width="13" height="9" rx="2.2"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
];

$pageTitle = 'Safety tips';
require __DIR__ . '/includes/header.php';
?>
<div class="container safety-page">
    <header class="safety-hero">
        <p class="eyebrow">Safety</p>
        <h1>The short version.</h1>
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
            <h2>Something off?</h2>
            <p><strong>Report</strong> them (they won't know it was you) or <strong>Unmatch</strong>.</p>
        </div>
        <div class="safety-numbers">
            <div><span>1930</span>Cyber crime helpline · cybercrime.gov.in</div>
            <div><span>112</span>Emergency</div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
