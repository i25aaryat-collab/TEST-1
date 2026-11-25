/* ==========================================================================
 * js/chat-safety.js  —  Owner: M5 (Chat and admin)
 * --------------------------------------------------------------------------
 * Safety help in chats (one-to-one and community):
 *   1. The "Stay safe" card at the top of a chat can be closed; it then stays
 *      closed on this device (the "Safety tips" link brings it back).
 *   2. Before you send a message, if it looks like it has a phone number,
 *      email, UPI ID, card/account number, OTP or password, a short note asks
 *      "send anyway?" first. Nothing is blocked and nothing is sent anywhere
 *      else: the check runs only in your browser.
 *   3. Messages you receive that ask for money up front, an OTP or ID
 *      documents get a small warning underneath.
 * ========================================================================== */
(function () {
    'use strict';
    var KEY = 'rm-safety-closed';
    var store = { get: function () { try { return localStorage.getItem(KEY); } catch (e) { return null; } },
                  set: function (v) { try { v ? localStorage.setItem(KEY, '1') : localStorage.removeItem(KEY); } catch (e) {} } };

    // ---- 1. The card ----
    var card = document.getElementById('safety-card');
    if (card) {
        if (store.get()) card.hidden = true;
        card.querySelector('.js-safety-close').addEventListener('click', function () { card.hidden = true; store.set(true); });
        document.querySelectorAll('.js-safety-open').forEach(function (b) {
            b.addEventListener('click', function () { card.hidden = false; store.set(false); card.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); });
        });
    }

    // ---- 2. Before sending ----
    var OUT = [
        [/(?:\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}\b/, 'your number', "Maybe hang on to it until you've met. When you're ready, Share contact lets you take it back later."],
        [/[\w.+-]+@[\w-]+\.[a-z]{2,}/i, 'your email', 'No rush to share it. You can keep talking here for now.'],
        [/\b[\w.-]{2,}@(?:ok\w+|ybl|ibl|axl|paytm|upi|apl|yapl|icici|sbi|hdfc\w*|axis\w*|kotak|fbl|ptyes|ptaxis|pthdfc|ptsbi)\b/i, 'a UPI ID', "Money can wait until you've seen the flat and read the agreement."],
        [/\b(?:\d[ -]?){12,19}\b/, 'a card, account or Aadhaar number', "That's one to keep off chats, even with someone nice."],
        [/\b(?:otp|one[- ]time password|password|cvv|pin\s*(?:no|number)?|aadha?ar|pan\s*(?:card|no|number))\b/i, 'something private', 'Nobody genuine needs an OTP, password, PIN or ID number from you.'],
    ];
    function risky(text) { for (var i = 0; i < OUT.length; i++) if (OUT[i][0].test(text)) return OUT[i]; return null; }

    var form = document.getElementById('chat-form');
    if (form) {
        var box = form.querySelector('textarea, input[name=body]');
        var note = document.createElement('div');
        note.className = 'safety-nudge'; note.hidden = true; note.setAttribute('role', 'alert');
        form.parentNode.insertBefore(note, form);
        var acknowledged = {};       // each kind of warning is shown once per chat visit

        document.addEventListener('submit', function (e) {
            if (e.target !== form) return;
            if (form.dataset.safetyOk) { delete form.dataset.safetyOk; note.hidden = true; return; }
            var hit = risky(box.value);
            if (!hit || acknowledged[hit[1]]) { note.hidden = true; return; }
            e.preventDefault(); e.stopImmediatePropagation();
            note.innerHTML = '';
            var p = document.createElement('p');
            p.innerHTML = '<strong>Is that ' + hit[1] + '?</strong> ';
            p.appendChild(document.createTextNode(hit[2]));
            var row = document.createElement('div'); row.className = 'btn-row';
            var edit = document.createElement('button'); edit.type = 'button'; edit.className = 'btn btn-sm'; edit.textContent = 'Edit';
            var anyway = document.createElement('button'); anyway.type = 'button'; anyway.className = 'btn btn-ghost btn-sm'; anyway.textContent = 'Send it';
            edit.addEventListener('click', function () { note.hidden = true; box.focus(); });
            anyway.addEventListener('click', function () { acknowledged[hit[1]] = true; form.dataset.safetyOk = '1'; form.requestSubmit(); });
            row.append(edit, anyway); note.append(p, row); note.hidden = false;
            edit.focus();
        }, true);                    // capture: runs before the chat's own send handler
        box.addEventListener('input', function () { if (!note.hidden && !risky(box.value)) note.hidden = true; });
    }

    // ---- 3. Messages from others ----
    var IN = [
        [/\b(?:advance|token amount|booking amount|deposit|pay(?:ment)?|transfer|send money|upi|gpay|google pay|phonepe|paytm)\b/i, "Talk of money? Hold off until you've seen the flat and the agreement."],
        [/\b(?:otp|one[- ]time password|password|cvv|pin)\b/i, 'Heads up: never share an OTP, password or PIN.'],
        [/\b(?:aadha?ar|pan card|passport|bank details|account number)\b/i, 'ID documents belong with a signed agreement, not a chat.'],
    ];
    var log = document.getElementById('chat-messages');
    function check(row) {
        if (!row.matches || !row.matches('.bubble-row:not(.mine)') || row.dataset.safetyChecked) return;
        row.dataset.safetyChecked = '1';
        var b = row.querySelector('.bubble'); if (!b) return;
        var text = b.textContent;
        for (var i = 0; i < IN.length; i++) if (IN[i][0].test(text)) {
            var w = document.createElement('div'); w.className = 'safety-flag'; w.textContent = IN[i][1];
            b.after(w); row.classList.add('has-flag'); return;
        }
    }
    if (log) {
        log.querySelectorAll('.bubble-row').forEach(check);
        new MutationObserver(function (list) {
            list.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { check(n); n.querySelectorAll && n.querySelectorAll('.bubble-row').forEach(check); } }); });
        }).observe(log, { childList: true, subtree: true });
    }
})();
