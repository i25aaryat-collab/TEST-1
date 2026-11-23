<?php
/**
 * chat/connect.php  —  Owner: M5 (Chat)
 * The step right after two people match: choose which contact details
 * (phone, email, Instagram) to share, or skip. Then the chat opens.
 * Also reachable later from the chat ("Contact sharing") to change it.
 * Saving posts to chat/contact_share.php, which checks everything on the server.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

require_profile();
$myId    = (int) $_SESSION['user_id'];
$matchId = (int) ($_GET['match_id'] ?? 0);
$match   = find_my_match($pdo, $matchId, $myId);
if (!$match) { set_flash('error', 'That chat does not exist or is not yours.'); redirect('chat/inbox.php'); }
$firstName = explode(' ', $match['other_name'])[0];
$chatHref  = url('chat/conversation.php?match_id=' . $matchId);

$p = $pdo->prepare('SELECT phone, contact_email, instagram FROM profiles WHERE user_id = ?');
$p->execute([$myId]);
$me = $p->fetch() ?: [];
$values = ['phone' => $me['phone'] ?? '', 'email' => $me['contact_email'] ?? '', 'instagram' => $me['instagram'] ?? ''];
$labels = ['phone' => ['📞', 'Phone'], 'email' => ['✉️', 'Email'], 'instagram' => ['📷', 'Instagram']];

$sh = $pdo->prepare('SELECT field FROM contact_shares WHERE sharer_id = ? AND viewer_id = ?');
$sh->execute([$myId, $match['other_id']]);
$iShared = $sh->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Share contact details';
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow">
    <div class="card connect-step">
        <img class="avatar" src="<?= e(photo_url($match['other_photo'])) ?>" alt="">
        <h1>Share your contact details with <?= e($firstName) ?>?</h1>
        <p class="muted">Off unless you switch it on. <?= e($firstName) ?> sees only what you choose, and you can change it any time.</p>
        <form method="post" action="<?= e(url('chat/contact_share.php')) ?>" class="share-form">
            <?= csrf_field() ?><input type="hidden" name="match_id" value="<?= $matchId ?>">
            <?php foreach ($labels as $f => [$icon, $label]): $v = (string) $values[$f]; ?>
                <label class="share-row">
                    <span><?= $icon ?> <?= $label ?> · <?= $v !== '' ? '<span class="muted">' . e($v) . '</span>' : '<a href="' . e(url('profile/edit_profile.php')) . '">add on your profile</a>' ?></span>
                    <span class="switch"><input type="checkbox" name="fields[]" value="<?= $f ?>" <?= in_array($f, $iShared, true) ? 'checked' : '' ?> <?= $v === '' ? 'disabled' : '' ?>><span></span></span>
                </label>
            <?php endforeach; ?>
            <div class="btn-row">
                <button class="btn btn-primary">Save and open chat</button>
                <a class="btn btn-ghost" href="<?= e($chatHref) ?>">Skip for now</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
