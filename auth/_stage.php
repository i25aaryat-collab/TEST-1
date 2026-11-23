<?php
/**
 * auth/_stage.php  —  Owner: M2 (Authentication)
 * The animated side panel shared by the login and register pages.
 * Three characters watch what you type, cover their eyes while you type a
 * password, peek when you press "Show", wince at an error and cheer when
 * you submit. Drawn by js/characters.js, moved by js/auth-stage.js.
 *
 * Before including, set: $stageTitle, $stageText, $stageMood ('' or 'sad')
 */
?>
<aside class="auth-stage<?= ($stageMood ?? '') === 'sad' ? ' is-sad' : '' ?>" aria-hidden="true">
    <div class="stage-blob b1"></div><div class="stage-blob b2"></div><div class="stage-blob b3"></div>

    <div class="stage-copy">
        <span class="stage-kicker">RoomieMatch</span>
        <h2><?= $stageTitle ?></h2>
        <p><?= e($stageText) ?></p>
    </div>

    <div class="stage-bubbles">
        <span style="--d:0s;--x:8%">98% match!</span>
        <span style="--d:2.2s;--x:58%">Chai at 6?</span>
        <span style="--d:4.4s;--x:30%">Who took my charger</span>
        <span style="--d:6.6s;--x:66%">Night owl here 🦉</span>
    </div>

    <div class="stage-crew">
        <?php foreach ([0, 2, 1] as $i): ?>
            <div class="crew-member" data-char="<?= $i ?>">
                <div class="crew-svg"></div>
                <span class="blindfold" aria-hidden="true"><i></i></span>
            </div>
        <?php endforeach; ?>
    </div>
</aside>
