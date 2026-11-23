/* ==========================================================================
 * js/auth-stage.js  —  Owner: M2 (Authentication)
 * Brings the login/register characters to life:
 *   - the characters stay still
 *   - a blindfold slides over their eyes while the password box is focused
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

    // The characters stay still (no head or eye tracking).

    // Blindfolds over the eyes for password fields; "Show" lets one of them peek
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
            if (e.defaultPrevented) stage.classList.add('is-sad');   // a gentle nod, no jumping
        }, 0);
    });
    stage.addEventListener('animationend', function (e) {
        if (e.animationName === 'crew-shake') stage.classList.remove('is-sad');
    });
})();
