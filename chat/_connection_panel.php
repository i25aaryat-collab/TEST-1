<?php
/**
 * chat/_connection_panel.php  —  Owner: M5 (Chat)
 * Shown at the top of a chat (the connection page):
 *   - a celebration while the chat is still empty
 *   - a short discussion agenda (first item from a lifestyle difference)
 *   - optional, one-way phone sharing (checked on the server)
 *   - links to the Roomie Pact and a shared flat shortlist
 * Expects: $pdo, $match, $myId, $matchId, $firstName, $messages
 */
require_once __DIR__ . '/../swipe/lifestyle.php';

$pp = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
$pp->execute([$myId]);               $meP   = $pp->fetch() ?: [];
$pp->execute([$match['other_id']]);  $themP = $pp->fetch() ?: [];
$align = lifestyle_alignment($meP, $themP);

// Phone sharing: each direction is its own row. I only ever receive THEIR number
// if THEY created a share row for me.
$sh = $pdo->prepare('SELECT 1 FROM phone_shares WHERE sharer_id = ? AND viewer_id = ?');
$sh->execute([$myId, $match['other_id']]);  $iShared = (bool) $sh->fetchColumn();
$sh->execute([$match['other_id'], $myId]);  $theyShared = (bool) $sh->fetchColumn();
$theirPhone = $theyShared ? ($themP['phone'] ?? '') : '';
$myPhone = $meP['phone'] ?? '';

$agenda = [];
if ($align['discuss']) $agenda[] = $align['discuss'];
$agenda[] = 'What does your monthly budget need to cover (rent, food, Wi-Fi, help)?';
$agenda[] = 'How much notice feels fair before guests come over?';
if (!empty($themP['household_note'])) $agenda[] = 'Ask about the routines ' . $firstName . ' mentioned: "' . $themP['household_note'] . '"';

$pact = $pdo->prepare('SELECT accepted_by, proposed_by FROM pacts WHERE match_id = ?');
$pact->execute([$matchId]); $pactRow = $pact->fetch();
$pactLabel = !$pactRow ? 'Make a Roomie Pact' : ($pactRow['accepted_by'] ? 'View your Roomie Pact ✓' : ((int) $pactRow['proposed_by'] === $myId ? 'Pact: waiting for ' . $firstName : 'Pact: review ' . $firstName . '\'s suggestion'));
?>
<section class="connection-panel" data-match="<?= (int) $matchId ?>">
    <?php if (!$messages): ?>
        <div class="connect-celebrate">
            <strong>Voilà, you two connected! 🎉</strong>
            <p class="small">Here's to finding out if home feels right together. No pressure: this is a chat, not a lease.</p>
        </div>
    <?php endif; ?>

    <details class="panel-block" open>
        <summary>Things worth talking about</summary>
        <?php if ($align['score'] !== null): ?>
            <p class="small"><strong>Lifestyle alignment <?= $align['score'] ?>%</strong> <span class="muted">· <?= $align['compared'] ?> of 5 compared</span><?= $align['shared'] ? ' · ' . e($align['shared']) : '' ?></p>
        <?php endif; ?>
        <ol class="agenda small"><?php foreach ($agenda as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ol>
        <p class="tiny muted">Just prompts. You don't need to tick anything off.</p>
    </details>

    <div class="panel-block phone-block" id="phone-block">
        <?php if ($theirPhone): ?>
            <p class="small">📞 <?= e($firstName) ?> shared their number with you: <strong><?= e($theirPhone) ?></strong></p>
        <?php endif; ?>
        <?php if ($iShared): ?>
            <form method="post" action="<?= e(url('chat/phone_share.php')) ?>" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="match_id" value="<?= (int) $matchId ?>"><input type="hidden" name="action" value="unshare">
                <span class="small">You shared your number with <?= e($firstName) ?>.</span>
                <button class="link-btn small" type="submit">Stop sharing</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('chat/phone_share.php')) ?>" class="inline-form phone-share-form"
                  data-confirm="<?= e($firstName) ?> will see <?= $myPhone ? 'your number ' . e($myPhone) : 'the number you entered' ?> in this chat. Nobody else will. You won't see <?= e($firstName) ?>'s number unless they share it too."
                  data-confirm-button="Share my number">
                <?= csrf_field() ?><input type="hidden" name="match_id" value="<?= (int) $matchId ?>"><input type="hidden" name="action" value="share">
                <?php if (!$myPhone): ?><input type="tel" name="phone" placeholder="Your mobile number" aria-label="Your mobile number" required><?php endif; ?>
                <button class="btn btn-sm" type="submit">Share my phone number with <?= e($firstName) ?></button>
                <button class="link-btn small js-not-now" type="button">Not now</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="panel-links small">
        <a href="<?= e(url('chat/pact.php?match_id=' . (int) $matchId)) ?>"><?= e($pactLabel) ?></a>
        <span aria-hidden="true">·</span>
        <a href="<?= e(url('groups/index.php?with=' . (int) $match['other_id'])) ?>">Shortlist flats together</a>
    </div>
</section>
<script>
(function () {
    const b = document.querySelector('.js-not-now'); if (!b) return;
    const key = 'rm-phone-notnow-' + <?= (int) $matchId ?>;
    try { if (localStorage.getItem(key)) b.closest('form').hidden = true; } catch (e) {}
    b.addEventListener('click', function () { b.closest('form').hidden = true; try { localStorage.setItem(key, '1'); } catch (e) {} });
})();
</script>
