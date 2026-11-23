<?php
/* ==========================================================================
 * includes/footer.php  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * Bottom of every page: footer bar, shared scripts, and the small script
 * that opens/closes the mobile menu and dismisses flash messages.
 * A page can set $extraScripts to add its own <script> tags.
 * ========================================================================== */
?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <p><strong>RoomieMatch</strong> · Find a flatmate you actually get along with.</p>
        <p class="muted">Built with PHP, MySQL and vanilla JavaScript · Character clips generated with Kling AI · Web Development project, IPM, IIM Indore</p>
    </div>
</footer>

<script src="<?= e(url('js/validate.js')) ?>?v=1"></script>
<script>
    // Mobile menu: the hamburger button shows or hides the links
    (function () {
        const toggle = document.querySelector('.nav-toggle');
        const links  = document.getElementById('nav-links');
        if (toggle && links) {
            toggle.addEventListener('click', function () {
                const open = links.classList.toggle('open');
                toggle.classList.toggle('open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
        // The x on a flash message hides it
        document.querySelectorAll('.flash-close').forEach(function (btn) {
            btn.addEventListener('click', function () { btn.parentElement.remove(); });
        });
    })();
</script>
<?php if (is_logged_in() && !is_admin()): ?>
<script>
    // Keep the red badges (unread chats, waiting requests) up to date every 5 seconds
    (function () {
        const url = <?= json_encode(url('chat/unread.php')) ?>;
        function refresh() {
            fetch(url, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
                RM.setBadge('chats', d.unread); RM.setBadge('requests', d.requests);
            }).catch(function () {});
        }
        setInterval(refresh, 5000);
    })();
</script>
<?php endif; ?>
<?= $extraScripts ?? '' ?>
<script src="<?= e(url('js/pullcord.js')) ?>?v=1"></script>
</body>
</html>
