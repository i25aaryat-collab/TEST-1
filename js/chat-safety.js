/* ==========================================================================
 * js/chat-safety.js  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * The "Heads up" note at the top of a chat (one-to-one and community) can be
 * closed; it then stays closed on this device, and the shield button in the
 * chat header brings it back. Messages themselves are never scanned.
 * ========================================================================== */
(function () {
    'use strict';
    var KEY = 'rm-safety-closed';
    var store = { get: function () { try { return localStorage.getItem(KEY); } catch (e) { return null; } },
                  set: function (v) { try { v ? localStorage.setItem(KEY, '1') : localStorage.removeItem(KEY); } catch (e) {} } };

    var card = document.getElementById('safety-card');
    if (card) {
        if (store.get()) card.hidden = true;
        card.querySelector('.js-safety-close').addEventListener('click', function () { card.hidden = true; store.set(true); });
        document.querySelectorAll('.js-safety-open').forEach(function (b) {
            b.addEventListener('click', function () { card.hidden = false; store.set(false); card.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); });
        });
    }

})();
