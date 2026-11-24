<?php
/**
 * privacy.php  —  Owner: M1 (Lead)
 * The privacy policy. Written from what the code actually does; if you change
 * what is collected, where it goes or how long it is kept, update this page too.
 * Contact address: PRIVACY_CONTACT_EMAIL (Vercel env var).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$contact = getenv('PRIVACY_CONTACT_EMAIL') ?: '';
$updated = '24 November 2026';
$pageTitle = 'Privacy policy';
require __DIR__ . '/includes/header.php';
?>
<div class="container container-narrow policy">
    <h1>Privacy policy</h1>
    <p class="muted">Last updated <?= e($updated) ?></p>

    <p>RoomieMatch helps people find flatmates they would enjoy living with. This page explains what we collect, why, who else sees it, how long we keep it and what you can do about it. We do not sell your data, show ads or use analytics or tracking tools.</p>

    <h2>1. Who we are and how to reach us</h2>
    <p>RoomieMatch is a student project built by a team of students of the Integrated Programme in Management at IIM Indore, and is run by that team. For any privacy question or request, write to
        <?php if ($contact): ?><a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a><?php else: ?>the RoomieMatch team<?php endif; ?>.
        You can also use the <strong>Report</strong> button on any profile, flat or chat to reach the team directly. We aim to reply within 7 days.</p>

    <h2>2. What we collect</h2>
    <table class="policy-table">
        <thead><tr><th>What</th><th>Details</th><th>Who can see it</th></tr></thead>
        <tbody>
            <tr><td>Account</td><td>Name, email address, and your password stored only as a secure hash (we can never see the password itself)</td><td>Your first name is shown to others. Your email is never shown.</td></tr>
            <tr><td>Profile</td><td>Age, gender (or "prefer not to say"), city, budget, languages, short bio, college, picture (an uploaded photo, a cartoon made from it, or an illustrated avatar)</td><td>Other members, depending on your visibility setting (everyone / your college / only in name search)</td></tr>
            <tr><td>Optional details</td><td>Religion, cultural preferences and a note about them, only if you choose to fill them in</td><td>Used for matching and the filters you choose</td></tr>
            <tr><td>Compatibility test</td><td>Your 1–5 answers about daily habits (sleep, cleaning, guests, food, comfort). Every question can be skipped.</td><td>Only used to work out scores. The score explanation shows a match their own and your answers side by side.</td></tr>
            <tr><td>Private locations</td><td>A reference point (e.g. campus or office), the distance you are willing to travel, and the location of a flat you post</td><td>Your reference point is never shown to anyone. A flat's location is shown only as an approximate area (about 1 km) until the owner chooses to share it.</td></tr>
            <tr><td>Contact details</td><td>Phone, a contact email and Instagram handle, only if you add them</td><td>Nobody, until you choose which ones to share with a specific match</td></tr>
            <tr><td>Activity</td><td>Who you liked or passed, your matches, chat messages, reactions, stickers and GIFs, flats you post or shortlist, polls, communities you join and reports you send</td><td>Messages are visible only to the people in that chat or community</td></tr>
            <tr><td>Security records</td><td>Failed login attempts (email and time) to stop password guessing, and a session record that keeps you logged in</td><td>Only the system</td></tr>
        </tbody>
    </table>

    <h2>3. Cookies and storage on your device</h2>
    <p>We use one cookie, a session cookie that keeps you logged in. It is marked secure and cannot be read by scripts. Your browser also remembers a few preferences locally (light or dark mode, map style, recently used emojis), which never leave your device. We use no advertising or analytics cookies.</p>

    <h2>4. Why we use it</h2>
    <ul>
        <li>To run your account and keep it secure (including the human check at login and sign-up).</li>
        <li>To show you people and flats, and to calculate compatibility scores and distances.</li>
        <li>To let you chat, share contact details and plan flats with your matches.</li>
        <li>To act on reports and keep the community safe.</li>
    </ul>
    <p>We rely on your consent, which you give when you sign up and when you choose to fill in optional fields or share details. You can withdraw it at any time by editing your profile or deleting your account.</p>

    <h2>5. Services that handle data for us</h2>
    <table class="policy-table">
        <thead><tr><th>Service</th><th>What it does</th><th>What it receives</th></tr></thead>
        <tbody>
            <tr><td>Vercel</td><td>Hosts the website</td><td>Every page request, including your IP address</td></tr>
            <tr><td>Supabase</td><td>Stores the database</td><td>Everything listed in section 2</td></tr>
            <tr><td>Cloudflare Turnstile</td><td>The "I'm human" check at login and sign-up</td><td>Your IP address and basic browser signals, for that check only</td></tr>
            <tr><td>OpenStreetMap services (Photon, Nominatim), Esri and CARTO</td><td>Place search and map images</td><td>The place you search for (sent from our server, not linked to your name) and, for map images, your IP address</td></tr>
            <tr><td>Google Street View</td><td>Street-level view of a flat, when you open it</td><td>The location being viewed and your IP address</td></tr>
        </tbody>
    </table>
    <p>Flat photos from other websites are copied to our database the first time they are shown, so those websites do not see who is looking at them. We do not share your data with anyone else, except where the law requires it.</p>

    <h2>6. How long we keep it</h2>
    <ul>
        <li>Your account, profile, answers and posts: until you delete your account.</li>
        <li>Chat messages: until either person unmatches (the whole chat is deleted) or one of you deletes their account.</li>
        <li>Failed login records: cleared when you next log in successfully, and only the last <?= (int) LOCKOUT_MINUTES ?> minutes are ever checked.</li>
        <li>Login sessions: end when you log out or close the browser; old ones are cleared automatically.</li>
        <li>Place-search answers: kept up to 30 days as a cache, without any link to who searched.</li>
    </ul>

    <h2>7. How we protect it</h2>
    <ul>
        <li>Passwords are stored only as secure hashes.</li>
        <li>All traffic uses HTTPS, and the database is reached only by our server.</li>
        <li>Forms are protected against cross-site request forgery, and accounts are locked for <?= (int) LOCKOUT_MINUTES ?> minutes after <?= (int) MAX_LOGIN_ATTEMPTS ?> wrong passwords.</li>
        <li>Private fields (reference point, contact details, exact flat location) are checked on the server, so they cannot be read by changing the page.</li>
    </ul>
    <p>No system is perfectly secure. If we learn of a breach that affects your data, we will tell you by email.</p>

    <h2>8. Your rights</h2>
    <ul>
        <li><strong>See and correct</strong> your data at any time from <a href="<?= e(url('profile/edit_profile.php')) ?>">Edit profile</a> and the compatibility test.</li>
        <li><strong>Delete</strong> your account and everything linked to it from <a href="<?= e(url('profile/delete_account.php')) ?>">Delete account</a>. This removes your profile, answers, likes, matches, chats and flats straight away.</li>
        <li><strong>Hide</strong> yourself with the visibility setting, or stop sharing contact details with a match.</li>
        <li><strong>Ask us</strong> for a copy of your data, or about anything on this page, using the contact above.</li>
    </ul>
    <p>We aim to follow India's Digital Personal Data Protection Act, 2023. If you are not satisfied with our answer, you can complain to the Data Protection Board of India.</p>

    <h2>9. Age</h2>
    <p>RoomieMatch is only for people aged 18 or over. We do not knowingly collect data from anyone younger.</p>

    <h2>10. Changes</h2>
    <p>If we change this policy, we will update the date at the top and, for important changes, tell you when you next log in.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
