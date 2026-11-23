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
require_once __DIR__ . '/../swipe/compatibility.php';

$pp = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
$pp->execute([$myId]);               $meP   = $pp->fetch() ?: [];
$pp->execute([$match['other_id']]);  $themP = $pp->fetch() ?: [];
$align = compatibility_result($meP, $themP);

// Contact sharing, per field and per direction. I only ever receive a detail of
// THEIRS if THEY created a share row for me (checked here, on the server).
const CONTACT_FIELDS = ['phone' => ['📞', 'Phone'], 'email' => ['✉️', 'Email'], 'instagram' => ['📷', 'Instagram']];
$contactValue = fn(array $p, string $f) => (string) ($f === 'email' ? ($p['contact_email'] ?? '') : ($p[$f] ?? ''));
$sh = $pdo->prepare('SELECT field FROM contact_shares WHERE sharer_id = ? AND viewer_id = ?');
$sh->execute([$myId, $match['other_id']]);  $iShared = $sh->fetchAll(PDO::FETCH_COLUMN);
$sh->execute([$match['other_id'], $myId]);  $theyShared = $sh->fetchAll(PDO::FETCH_COLUMN);

$agenda = [];
if ($align['discuss']) $agenda[] = $align['discuss'];
$agenda[] = 'What does your monthly budget need to cover (rent, food, Wi-Fi, help)?';
$agenda[] = 'How much notice feels fair before guests come over?';
if (!empty($themP['culture_note'])) $agenda[] = 'Ask about what ' . $firstName . ' mentioned: "' . $themP['culture_note'] . '"';

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

    <details class="panel-block" <?= $messages ? '' : 'open' ?>>
        <summary>Things worth talking about</summary>
        <?php if ($align['score'] !== null): ?>
            <?= score_line($align) ?>
        <?php endif; ?>
        <ol class="agenda small"><?php foreach ($agenda as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ol>
        <p class="tiny muted">Just prompts. You don't need to tick anything off.</p>
    </details>

    <div class="panel-block contact-block">
        <?php foreach ($theyShared as $f): if ($contactValue($themP, $f) === '') continue; ?>
            <p class="small"><?= CONTACT_FIELDS[$f][0] ?> <?= e($firstName) ?> shared their <?= strtolower(CONTACT_FIELDS[$f][1]) ?>: <strong><?= e($contactValue($themP, $f)) ?></strong></p>
        <?php endforeach; ?>
        <details class="share-box" <?= $iShared ? '' : 'open' ?>>
            <summary><strong>Share contact details with <?= e($firstName) ?></strong> <span class="muted small">(off unless you switch it on)</span></summary>
            <form method="post" action="<?= e(url('chat/contact_share.php')) ?>" class="share-form"
                  data-confirm="<?= e($firstName) ?> will see exactly the details you switched on, in this chat only. You won't see <?= e($firstName) ?>'s unless they share too. You can turn these off any time."
                  data-confirm-button="Save sharing">
                <?= csrf_field() ?><input type="hidden" name="match_id" value="<?= (int) $matchId ?>">
                <?php foreach (CONTACT_FIELDS as $f => [$icon, $label]): $v = $contactValue($meP, $f); ?>
                    <label class="share-row">
                        <span><?= $icon ?> <?= $label ?> · <?= $v !== '' ? '<span class="muted">' . e($v) . '</span>' : '<a href="' . e(url('profile/edit_profile.php')) . '">add on your profile</a>' ?></span>
                        <span class="switch"><input type="checkbox" name="fields[]" value="<?= $f ?>" <?= in_array($f, $iShared, true) ? 'checked' : '' ?> <?= $v === '' ? 'disabled' : '' ?>><span></span></span>
                    </label>
                <?php endforeach; ?>
                <div class="btn-row"><button class="btn btn-sm">Review and save</button><button class="link-btn small js-not-now" type="button">Not now</button></div>
            </form>
        </details>
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
    const key = 'rm-share-notnow-' + <?= (int) $matchId ?>;
    try { if (localStorage.getItem(key)) b.closest('details').open = false; } catch (e) {}
    b.addEventListener('click', function () { b.closest('details').open = false; try { localStorage.setItem(key, '1'); } catch (e) {} });
})();
</script>
