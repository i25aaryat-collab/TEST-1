<?php
/**
 * chat/pact.php  —  Owner: M5 (Chat)
 * The Roomie Pact: a few informal house rules a connected pair can agree on.
 * A suggestion is not an agreement until the OTHER person accepts that exact
 * version; any edit becomes a new version that needs accepting again.
 * Optional: "Skip for now" just goes back to the chat; nothing is stored.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/chat_helpers.php';

require_profile();
$myId = (int) $_SESSION['user_id'];
$matchId = (int) ($_GET['match_id'] ?? $_POST['match_id'] ?? 0);
$match = find_my_match($pdo, $matchId, $myId);
if (!$match) { set_flash('error', 'That chat does not exist or is not yours.'); redirect('chat/inbox.php'); }
$first = explode(' ', $match['other_name'])[0];
$back = url('chat/conversation.php?match_id=' . $matchId);

const PACT_SUGGESTIONS = ['Headphones after 11', 'Whoever finishes the milk adds it to the list',
    'Give a heads-up before friends stay over', 'Dishes done before bed', 'Split bills by the 5th of the month'];

$load = $pdo->prepare('SELECT * FROM pacts WHERE match_id = ?');
$load->execute([$matchId]);
$pact = $load->fetch() ?: null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'accept' && $pact && (int) $pact['proposed_by'] !== $myId && (int) $pact['version'] === (int) ($_POST['version'] ?? 0)) {
        $pdo->prepare('UPDATE pacts SET accepted_by = ? WHERE match_id = ? AND version = ?')->execute([$myId, $matchId, (int) $pact['version']]);
        set_flash('success', 'Pact agreed. Nice one!');
        redirect('chat/conversation.php?match_id=' . $matchId);
    }
    if ($action === 'save') {
        $rules = array_merge((array) ($_POST['picked'] ?? []), preg_split('/\R/', (string) ($_POST['custom'] ?? '')));
        $rules = array_values(array_unique(array_filter(array_map(fn($r) => trim(mb_substr((string) $r, 0, 120)), $rules))));
        if (count($rules) < 1) $errors[] = 'Pick or write at least one little rule.';
        if (count($rules) > 10) $errors[] = 'Keep it to 10 rules or fewer.';
        if (!$errors) {
            $text = implode("\n", $rules);
            if ($pact) {
                $pdo->prepare('UPDATE pacts SET rules = ?, version = version + 1, proposed_by = ?, accepted_by = NULL, updated_at = CURRENT_TIMESTAMP WHERE match_id = ?')
                    ->execute([$text, $myId, $matchId]);
            } else {
                $pdo->prepare('INSERT INTO pacts (match_id, rules, proposed_by) VALUES (?, ?, ?)')->execute([$matchId, $text, $myId]);
            }
            set_flash('success', 'Suggested to ' . $first . '. It becomes your pact once they accept it.');
            redirect('chat/conversation.php?match_id=' . $matchId);
        }
    }
}

$pactRules = $pact ? explode("\n", $pact['rules']) : [];
$editing = !$pact || isset($_GET['edit']) || $errors;
$pageTitle = 'Roomie Pact';
require __DIR__ . '/../includes/header.php';
?>
<div class="container container-narrow">
    <div class="card pact-card">
        <div class="page-head" style="margin-bottom:8px">
            <div><h1>Roomie Pact 🤝</h1><p class="muted">A few little things that make sharing a home easier. With <?= e($first) ?>.</p></div>
            <a class="btn btn-ghost btn-sm" href="<?= e($back) ?>">Skip for now</a>
        </div>
        <?php foreach ($errors as $err): ?><p class="error-text"><?= e($err) ?></p><?php endforeach; ?>

        <?php if (!$editing): ?>
            <ul class="pact-list"><?php foreach ($pactRules as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
            <?php if ($pact['accepted_by']): ?>
                <p class="pact-status ok">✓ You've both agreed to this version.</p>
            <?php elseif ((int) $pact['proposed_by'] === $myId): ?>
                <p class="pact-status">Waiting for <?= e($first) ?> to accept your suggestion.</p>
            <?php else: ?>
                <p class="pact-status"><?= e($first) ?> suggested this. Happy with it?</p>
                <form method="post" class="btn-row"><?= csrf_field() ?>
                    <input type="hidden" name="match_id" value="<?= (int) $matchId ?>"><input type="hidden" name="version" value="<?= (int) $pact['version'] ?>">
                    <button class="btn btn-primary" name="action" value="accept">Accept this pact</button>
                </form>
            <?php endif; ?>
            <div class="btn-row" style="margin-top:12px">
                <a class="btn" href="<?= e(url('chat/pact.php?edit=1&match_id=' . (int) $matchId)) ?>">Edit</a>
                <a class="btn btn-ghost" href="<?= e($back) ?>">Back to chat</a>
            </div>
        <?php else: ?>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="match_id" value="<?= (int) $matchId ?>">
                <fieldset class="form-group"><legend>Pick a few</legend>
                    <div class="pill-group">
                        <?php foreach (PACT_SUGGESTIONS as $s): ?>
                            <label class="pill"><input type="checkbox" name="picked[]" value="<?= e($s) ?>" <?= in_array($s, $pactRules, true) ? 'checked' : '' ?>><span><?= e($s) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="form-group">
                    <label for="custom">Or write your own <span class="muted">(one per line)</span></label>
                    <textarea id="custom" name="custom"><?= e(implode("\n", array_diff($pactRules, PACT_SUGGESTIONS))) ?></textarea>
                </div>
                <div class="btn-row">
                    <a class="btn btn-ghost" href="<?= e($back) ?>">Skip for now</a>
                    <button class="btn btn-primary" name="action" value="save">Suggest to <?= e($first) ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
