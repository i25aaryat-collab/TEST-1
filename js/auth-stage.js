/* ==========================================================================
 * js/auth-stage.js  —  Owner: M2 (Authentication)
 * Brings the login/register characters to life:
 *   - eyes follow the mouse, and the text cursor while you type
 *   - hands cover the eyes while the password box is focused
 *   - one character peeks when the password is shown
 *   - a jump when the form is sent, a head-shake on an error
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

    // Look at a point on the screen
    let target = null, frame = 0;
    function lookAt(x, y) {
        target = { x: x, y: y };
        if (!frame) frame = requestAnimationFrame(function () {
            frame = 0;
            crew.forEach(function (m) {
                const r = m.el.getBoundingClientRect();
                const dx = (target.x - (r.left + r.width / 2)) / (window.innerWidth / 2);
                const dy = (target.y - (r.top + r.height * 0.45)) / (window.innerHeight / 2);
                rmLook(m.svg, dx * 1.6, dy * 1.6);
            });
        });
    }
    if (!reduced) document.addEventListener('pointermove', function (e) { lookAt(e.clientX, e.clientY); });

    // Follow the text cursor in text fields (roughly: 8.5px per character)
    document.querySelectorAll('.auth-form input[type=text], .auth-form input[type=email]').forEach(function (input) {
        function follow() {
            const r = input.getBoundingClientRect();
            lookAt(r.left + 14 + Math.min(input.value.length * 8.5, r.width - 28), r.top + r.height / 2);
        }
        input.addEventListener('input', follow);
        input.addEventListener('focus', follow);
    });

    // Hands over the eyes for password fields; "Show" makes one of them peek
    document.querySelectorAll('.auth-form input[type=password]').forEach(function (input) {
        input.addEventListener('focus', function () { stage.classList.add('is-hiding'); });
        input.addEventListener('blur', function () { stage.classList.remove('is-hiding'); });
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
            stage.classList.add(e.defaultPrevented ? 'is-sad' : 'is-happy');
        }, 0);
    });
    stage.addEventListener('animationend', function (e) {
        if (e.animationName === 'crew-shake') stage.classList.remove('is-sad');
    });
})();
