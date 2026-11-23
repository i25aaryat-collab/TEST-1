<?php
/**
 * chat/_connection_panel.php  —  Owner: M5 (Chat)
 * One slim line under the chat header:
 *   - any contact details the other person chose to share with me
 *   - links to the Roomie Pact, a shared flat shortlist and contact sharing
 * Choosing what I share happens right after matching (chat/connect.php).
 * Expects: $pdo, $match, $myId, $matchId, $firstName
 */
const CONTACT_FIELDS = ['phone' => ['📞', 'Phone'], 'email' => ['✉️', 'Email'], 'instagram' => ['📷', 'Instagram']];

// I only ever see a detail of THEIRS if THEY created a share row for me (checked here, on the server).
$sh = $pdo->prepare('SELECT field FROM contact_shares WHERE sharer_id = ? AND viewer_id = ?');
$sh->execute([$match['other_id'], $myId]);
$theyShared = $sh->fetchAll(PDO::FETCH_COLUMN);
$themP = [];
if ($theyShared) {
    $pp = $pdo->prepare('SELECT phone, contact_email, instagram FROM profiles WHERE user_id = ?');
    $pp->execute([$match['other_id']]);
    $themP = $pp->fetch() ?: [];
}
$contactValue = fn(array $p, string $f) => (string) ($f === 'email' ? ($p['contact_email'] ?? '') : ($p[$f] ?? ''));

$pact = $pdo->prepare('SELECT accepted_by, proposed_by FROM pacts WHERE match_id = ?');
$pact->execute([$matchId]); $pactRow = $pact->fetch();
$pactLabel = !$pactRow ? 'Roomie Pact' : ($pactRow['accepted_by'] ? 'Roomie Pact ✓' : ((int) $pactRow['proposed_by'] === $myId ? 'Pact: waiting for ' . $firstName : 'Pact: review ' . $firstName . '\'s suggestion'));
?>
<div class="connection-panel small">
    <?php foreach ($theyShared as $f): if ($contactValue($themP, $f) === '') continue; ?>
        <span><?= CONTACT_FIELDS[$f][0] ?> <strong><?= e($contactValue($themP, $f)) ?></strong></span>
    <?php endforeach; ?>
    <nav class="panel-links" aria-label="Chat tools">
        <a href="<?= e(url('chat/pact.php?match_id=' . (int) $matchId)) ?>"><?= e($pactLabel) ?></a>
        <a href="<?= e(url('groups/index.php?with=' . (int) $match['other_id'])) ?>">Shortlist flats</a>
        <a href="<?= e(url('chat/connect.php?match_id=' . (int) $matchId)) ?>">Contact sharing</a>
    </nav>
</div>
