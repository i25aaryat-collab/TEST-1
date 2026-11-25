<?php
/* includes/_chat_safety.php  —  Owner: M5 (Chat and admin)
 * A quiet safety note pinned at the top of every chat, like a system message.
 * js/chat-safety.js closes it (remembered on this device) and the header's
 * shield button brings it back. */
?>
<div class="safety-strip" id="safety-card"><div class="safety-note" role="note" aria-labelledby="safety-title">
    <svg class="safety-shield" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5 5 5.2v5.6c0 4.5 3 8.4 7 9.7 4-1.3 7-5.2 7-9.7V5.2l-7-2.7Z"/></svg>
    <p><strong id="safety-title">Before you get chatting</strong>
        Keep things here until you know them a little. Never pay an advance for a flat you haven't seen, and keep your OTPs and bank details to yourself.
        <a href="<?= e(url('safety.php')) ?>" target="_blank" rel="noopener">More tips</a></p>
    <button type="button" class="safety-x js-safety-close" aria-label="Hide safety note">&times;</button>
</div></div>
