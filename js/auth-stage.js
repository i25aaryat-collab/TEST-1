/* ==========================================================================
 * js/auth-stage.js  —  Owner: M2 (Authentication)
 * Brings the login/register characters to life:
 *   - heads stay still; only the eyes follow the cursor (and the email box while typing)
 *   - a blindfold slides over their eyes while the password box is focused, and the eyes stop
 *   - one of them lifts it to peek when the password is shown
 * ========================================================================== */
(function () {
    const stage = document.querySelector('.auth-stage');
    if (!stage || typeof rmCharacterSVG !== 'function') return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Draw the three characters with their own skin colour on the hands
    const crew = Array.from(stage.querySelectorAll('.crew-member')).map(function (el) {
        const c = RM_CHARACTERS[+el.dataset.char];
        el.querySelector('.crew-svg').innerHTML = rmCharacterSVG(c, { x: 0, y: 0 }, false);
        el.style.setProperty('--skin', c.skin);
        return { el: el, svg: el.querySelector('svg') };
    });

    // Eyes follow the pointer until the password box is focused (heads stay still)
    let hiding = false, frame = 0, gx = 0, gy = 0;
    function lookAt(px, py) {
        gx = px; gy = py;
        if (frame || hiding || reduced) return;
        frame = requestAnimationFrame(function () {
            frame = 0;
            if (hiding) return;                                  // blindfolded: eyes stay put
            crew.forEach(function (m) {
                const r = m.svg.getBoundingClientRect(), eyes = m.svg.querySelector('.rm-eyes');
                if (!eyes || !r.width) return;
                const dx = (gx - (r.left + r.width / 2)) / (window.innerWidth / 2), dy = (gy - (r.top + r.height * .45)) / (window.innerHeight / 2);
                eyes.setAttribute('transform', 'translate(' + (Math.max(-1, Math.min(1, dx)) * 6).toFixed(2) + ' ' + (Math.max(-1, Math.min(1, dy)) * 5).toFixed(2) + ')');
                eyes.style.transition = 'transform .12s ease-out';
            });
        });
    }
    function eyesHome() { crew.forEach(function (m) { const e = m.svg.querySelector('.rm-eyes'); if (e) e.setAttribute('transform', 'translate(0 0)'); }); }
    window.addEventListener('pointermove', function (e) { lookAt(e.clientX, e.clientY); }, { passive: true });
    window.addEventListener('pointerdown', function (e) { lookAt(e.clientX, e.clientY); }, { passive: true });
    // While typing an email/username they read along with you
    document.querySelectorAll('.auth-form input:not([type=password]):not([type=hidden])').forEach(function (input) {
        const follow = function () {
            const r = input.getBoundingClientRect();
            const len = Math.min(input.value.length, 40) / 40;
            lookAt(r.left + 12 + (r.width - 24) * len, r.top + r.height / 2);
        };
        input.addEventListener('input', follow);
        input.addEventListener('focus', follow);
    });

    // Blindfolds over the eyes for password fields; "Show" lets one of them peek
    document.querySelectorAll('.auth-form input[type=password]').forEach(function (input) {
        input.addEventListener('focus', function () { hiding = true; eyesHome(); stage.classList.add('is-hiding'); });
        input.addEventListener('blur', function () { hiding = false; stage.classList.remove('is-hiding'); });
        const toggle = input.parentElement.querySelector('.password-toggle');
        if (toggle) toggle.addEventListener('click', function () {
            setTimeout(function () { stage.classList.toggle('is-peeking', input.type === 'text'); }, 0);
            input.focus();
        });
    });

    // Cheer when the form actually goes (validation passed), shake on errors
    const form = document.querySelector('.auth-form form');
    if (form) form.addEventListener('submit', function (e) {
        setTimeout(function () {
            stage.classList.remove('is-hiding', 'is-peeking', 'is-sad');
            void stage.offsetWidth;                                // restart the animation
            if (e.defaultPrevented) stage.classList.add('is-sad');   // a gentle nod, no jumping
        }, 0);
    });
    stage.addEventListener('animationend', function (e) {
        if (e.animationName === 'crew-shake') stage.classList.remove('is-sad');
    });
})();
