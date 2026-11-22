/* ==========================================================================
 * js/validate.js  —  Owner: M3 (Profiles and validation)
 * --------------------------------------------------------------------------
 * Reusable client-side validation helpers, loaded on every page by the
 * footer. Everything lives inside one object, RM, so names never clash.
 *
 * How a form uses it (see profile/_profile_form.php or auth/register.php):
 *   RM.validateForm(formElement, function (form) {
 *       const errors = {};
 *       if (!RM.isEmail(form.email.value)) errors.email = 'Enter a valid email.';
 *       return errors;                     // { fieldName: 'message' }
 *   });
 *
 * Errors appear in red NEXT TO the field (in <div data-error-for="name">),
 * not as pop-up alerts. The server checks everything again in PHP, because
 * anyone can switch JavaScript off.
 *
 * Demo tip: add ?nojs=1 to a form page's address to switch these checks
 * off, then submit bad data to show the PHP (server-side) checks working.
 * ========================================================================== */

const RM = {

    // ---------- Simple checks: each returns true (OK) or false ----------

    /** Something other than spaces was typed */
    isFilled: function (value) {
        return String(value).trim().length > 0;
    },

    /** Looks like name@example.com */
    isEmail: function (value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(value).trim());
    },

    /** At least 8 characters, with at least one letter and one number */
    isStrongPassword: function (value) {
        return value.length >= 8 && /[A-Za-z]/.test(value) && /[0-9]/.test(value);
    },

    /** Only letters and spaces, e.g. a city name like "New Delhi" */
    isLettersAndSpaces: function (value) {
        return /^[A-Za-z ]+$/.test(String(value).trim());
    },

    /** A whole number (no decimals, no letters) */
    isWholeNumber: function (value) {
        return /^\d+$/.test(String(value).trim());
    },

    /** A whole number between min and max (both included) */
    isIntInRange: function (value, min, max) {
        if (!RM.isWholeNumber(value)) return false;
        const n = parseInt(value, 10);
        return n >= min && n <= max;
    },

    /** Not longer than max characters */
    maxLength: function (value, max) {
        return String(value).length <= max;
    },

    /** Value of the ticked radio button in a group ('' if none) */
    radioValue: function (form, name) {
        const checked = form.querySelector('input[name="' + name + '"]:checked');
        return checked ? checked.value : '';
    },

    /** Values of all ticked checkboxes in a group, e.g. languages[] */
    checkedValues: function (form, name) {
        return Array.from(form.querySelectorAll('input[name="' + name + '"]:checked')).map(function (box) { return box.value; });
    },

    // ---------- Showing and clearing errors ----------

    /** The element to outline in red: a [data-field] wrapper or the input itself */
    _target: function (form, name) {
        return form.querySelector('[data-field="' + name + '"]') || form.querySelector('[name="' + name + '"]');
    },

    /** Show a red message next to one field */
    showError: function (form, name, message) {
        const box = form.querySelector('[data-error-for="' + name + '"]');
        const target = RM._target(form, name);
        if (box) box.textContent = message;
        if (target) {
            target.classList.add('is-invalid');
            target.setAttribute('aria-invalid', 'true');
        }
    },

    /** Remove the message and red outline from one field */
    clearError: function (form, name) {
        const box = form.querySelector('[data-error-for="' + name + '"]');
        const target = RM._target(form, name);
        if (box) box.textContent = '';
        if (target) {
            target.classList.remove('is-invalid');
            target.removeAttribute('aria-invalid');
        }
    },

    /**
     * Connect a form to its rules.
     *  - On submit: run all rules; if any fail, show them and stop the submit.
     *  - While typing: once a field has an error, re-check it on every change
     *    so the message disappears as soon as it is fixed.
     */
    validateForm: function (form, rulesFn) {
        if (!form) return;

        // Demo switch: ?nojs=1 turns the client-side checks off
        if (new URLSearchParams(window.location.search).has('nojs')) {
            const note = document.createElement('p');
            note.className = 'flash flash-error';
            note.textContent = 'Demo mode: JavaScript checks are OFF. Only the server (PHP) is checking this form now.';
            form.prepend(note);
            return;
        }

        form.setAttribute('novalidate', 'novalidate'); // we show our own messages

        form.addEventListener('submit', function (event) {
            const errors = rulesFn(form);
            // Clear every old message first
            form.querySelectorAll('[data-error-for]').forEach(function (box) {
                RM.clearError(form, box.getAttribute('data-error-for'));
            });
            const names = Object.keys(errors);
            if (names.length > 0) {
                event.preventDefault();
                names.forEach(function (name) { RM.showError(form, name, errors[name]); });
                // Move to the first problem so the user sees it
                const first = RM._target(form, names[0]);
                if (first) {
                    first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const focusable = first.matches('input, select, textarea') ? first : first.querySelector('input, select, textarea');
                    if (focusable) focusable.focus({ preventScroll: true });
                }
            }
        });

        // Live re-check: only for fields that are currently showing an error
        ['input', 'change'].forEach(function (type) {
            form.addEventListener(type, function (event) {
                const name = event.target.name;
                if (!name) return;
                const box = form.querySelector('[data-error-for="' + name + '"]');
                if (!box || box.textContent === '') return;
                const errors = rulesFn(form);
                if (errors[name]) RM.showError(form, name, errors[name]);
                else RM.clearError(form, name);
            });
        });
    },

    // ---------- Small extras used by several pages ----------

    /** Live "123 / 300" counter under a textarea */
    charCounter: function (textarea, counter, max) {
        if (!textarea || !counter) return;
        function update() {
            const len = textarea.value.length;
            counter.textContent = len + ' / ' + max;
            counter.classList.toggle('over', len > max);
        }
        textarea.addEventListener('input', update);
        update();
    },

    /** A short message that slides in at the bottom and disappears (status = role=status) */
    toast: function (text, type) {
        let holder = document.getElementById('toasts');
        if (!holder) {
            holder = document.createElement('div');
            holder.id = 'toasts';
            holder.setAttribute('role', 'status');
            holder.setAttribute('aria-live', 'polite');
            document.body.appendChild(holder);
        }
        const t = document.createElement('div');
        t.className = 'toast toast-' + (type || 'success');
        t.textContent = text;
        holder.appendChild(t);
        setTimeout(function () { t.remove(); }, 4000);
    },

    /** Set a red count badge in the menu ('chats' or 'requests'); 0 hides it */
    setBadge: function (name, count) {
        document.querySelectorAll('[data-badge="' + name + '"]').forEach(function (b) {
            b.firstChild.nodeValue = String(count);
            b.hidden = !count;
        });
    },

    /** Keep Tab / Shift+Tab inside a dialog while it is open */
    trapFocus: function (dialog, event) {
        const items = dialog.querySelectorAll('a[href], button:not([disabled]), textarea, input, select');
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    },

    /**
     * A styled box asking for a short text (e.g. a report reason).
     * Resolves to the text, or null if cancelled. 5-300 characters.
     */
    promptBox: function (title, message, okText) {
        return new Promise(function (resolve) {
            const opener = document.activeElement;
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop show';
            backdrop.innerHTML =
                '<div class="modal confirm-modal" role="dialog" aria-modal="true" aria-labelledby="pb-title">' +
                '<h2 id="pb-title" style="color:var(--navy);font-size:1.4rem"></h2><p></p>' +
                '<label class="sr-only" for="pb-text">Reason</label>' +
                '<textarea id="pb-text" maxlength="300" style="text-align:left"></textarea>' +
                '<div class="field-error" id="pb-error"></div>' +
                '<div class="btn-row" style="margin-top:12px"><button type="button" class="btn" data-no>Cancel</button>' +
                '<button type="button" class="btn btn-danger" data-yes></button></div></div>';
            backdrop.querySelector('h2').textContent = title;
            backdrop.querySelector('p').textContent = message;
            backdrop.querySelector('[data-yes]').textContent = okText || 'Send';
            document.body.appendChild(backdrop);
            const text = backdrop.querySelector('textarea');
            text.focus();
            function close(value) { backdrop.remove(); if (opener) opener.focus(); resolve(value); }
            backdrop.querySelector('[data-no]').addEventListener('click', function () { close(null); });
            backdrop.querySelector('[data-yes]').addEventListener('click', function () {
                const v = text.value.trim();
                if (v.length < 5) { backdrop.querySelector('#pb-error').textContent = 'Please write at least 5 characters.'; text.focus(); return; }
                close(v);
            });
            backdrop.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') close(null);
                if (e.key === 'Tab') RM.trapFocus(backdrop.querySelector('.modal'), e);
            });
        });
    },

    /**
     * A styled "Are you sure?" box (instead of the browser's confirm()).
     * Returns a Promise that resolves to true (confirmed) or false.
     */
    confirmBox: function (message, confirmText) {
        return new Promise(function (resolve) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop show';
            backdrop.innerHTML =
                '<div class="modal confirm-modal" role="dialog" aria-modal="true" aria-labelledby="cb-title">' +
                '<h2 id="cb-title" style="color:var(--navy);font-size:1.4rem">Are you sure?</h2>' +
                '<p></p>' +
                '<div class="btn-row"><button type="button" class="btn" data-no>Cancel</button>' +
                '<button type="button" class="btn btn-danger" data-yes></button></div></div>';
            backdrop.querySelector('p').textContent = message;            // textContent = safe, no HTML
            backdrop.querySelector('[data-yes]').textContent = confirmText || 'Yes, delete';
            document.body.appendChild(backdrop);
            backdrop.querySelector('[data-no]').focus();

            const opener = document.activeElement;
            function close(answer) { backdrop.remove(); if (opener) opener.focus(); resolve(answer); }
            backdrop.addEventListener('keydown', function (e) { if (e.key === 'Tab') RM.trapFocus(backdrop.querySelector('.modal'), e); });
            backdrop.querySelector('[data-yes]').addEventListener('click', function () { close(true); });
            backdrop.querySelector('[data-no]').addEventListener('click', function () { close(false); });
            backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(false); });
            document.addEventListener('keydown', function esc(e) {
                if (e.key === 'Escape') { document.removeEventListener('keydown', esc); close(false); }
            });
        });
    }
};

// ---------- Automatic behaviour for every page ----------
document.addEventListener('DOMContentLoaded', function () {

    // Any form with data-confirm="message" asks before it submits
    // (used for delete user, unmatch, delete message, delete account).
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;                // validation already stopped it
            if (form.dataset.confirmed === 'yes') return;      // already confirmed
            event.preventDefault();
            RM.confirmBox(form.dataset.confirm, form.dataset.confirmButton).then(function (ok) {
                if (ok) { form.dataset.confirmed = 'yes'; form.requestSubmit ? form.requestSubmit() : form.submit(); }
            });
        });
    });

    // "Show / Hide" buttons inside password boxes
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = btn.parentElement.querySelector('input');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
});
