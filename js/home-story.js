/* ==========================================================================
 * js/home-story.js  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * "Find your people": the pinned scroll story on the landing page.
 *   Act 1  The building is quiet. As you scroll, its windows light up one by
 *          one, like people coming home.
 *   Act 2  The camera picks one window and moves through it into the room.
 * Light theme and dark theme each have their own building and their own
 * hand-mapped windows (MAPS below, in the image's own pixels).
 * Plain JavaScript: scroll position -> progress -> draw. Scrolling back up
 * plays it in reverse. Reduced motion: no flicker, no smoothing.
 * ========================================================================== */

(function () {
    const story = document.getElementById('find-your-people');
    if (!story) return;
    const base = story.dataset.base;                       // e.g. /media/story/
    const view = story.querySelector('.fyp-view');
    const stage = story.querySelector('.fyp-stage');
    const backdrop = story.querySelector('.fyp-backdrop');
    const veil = story.querySelector('.fyp-veil');
    const room = story.querySelector('.fyp-room');
    const shot = story.querySelector('.fyp-shot');
    const copy = Array.from(story.querySelectorAll('.fyp-copy > *'));
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Window rectangles [x, y, w, h] in image pixels; order = lighting sequence; target = the window we enter
    const MAPS = {
        dark: { W: 717, H: 1280, target: 'top-center',
            wins: { 'door': [498, 893, 118, 222], 'top-left': [118, 210, 90, 213], 'top-center': [285, 203, 148, 223], 'top-right': [494, 214, 144, 200], 'mid-left': [118, 560, 89, 203], 'mid-center': [265, 552, 142, 215], 'mid-right': [507, 556, 93, 209], 'bot-left': [111, 916, 97, 216], 'bot-center': [288, 907, 120, 229] },
            order: ['door', 'bot-left', 'mid-right', 'top-left', 'bot-center', 'mid-left', 'top-right', 'mid-center', 'top-center'] },
        light: { W: 736, H: 1308, target: 'c3r4',
            wins: { c2r1: [144, 495, 72, 86], c3r1: [282, 495, 82, 88], c4r1: [424, 495, 72, 86], c2r2: [144, 623, 72, 88], c3r2: [282, 623, 82, 90], c4r2: [424, 623, 72, 88], c2r3: [144, 753, 72, 95], c3r3: [282, 753, 82, 96], c4r3: [424, 753, 72, 95], c2r4: [144, 889, 72, 89], c3r4: [282, 889, 82, 90], c4r4: [424, 889, 72, 89], c2r5: [144, 1017, 72, 88], c3r5: [282, 1017, 82, 88], c4r5: [424, 1017, 72, 88], shop: [42, 1210, 186, 91], door: [290, 1175, 68, 132], g4: [424, 1177, 72, 88] },
            order: ['door', 'shop', 'c4r5', 'c2r2', 'c3r1', 'c4r3', 'c2r4', 'g4', 'c3r2', 'c2r1', 'c4r1', 'c3r5', 'c2r3', 'c4r2', 'c2r5', 'c4r4', 'c3r3', 'c3r4'] }
    };

    const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
    const lerp = (a, b, t) => a + (b - a) * t;
    const ease = (t) => t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
    const span = (p, a, b) => clamp((p - a) / (b - a));   // 0..1 while p moves from a to b

    let theme, M, L = {}, wins = [], target = 0, shown = 0, frame = 0;

    function build() {
        theme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
        M = MAPS[theme];
        const { W, H } = M;
        stage.innerHTML = '<img src="' + base + theme + '-unlit.jpg" alt="An apartment building at ' + (theme === 'dark' ? 'night' : 'sunset') + '" draggable="false">' +
            M.order.map(function (k) {
                const [x, y, w, h] = M.wins[k];
                return '<div class="fyp-win" style="left:' + x / W * 100 + '%;top:' + y / H * 100 + '%;width:' + w / W * 100 + '%;height:' + h / H * 100 + '%;' +
                    'background-image:url(' + base + theme + '.jpg);background-size:' + W / w * 100 + '% ' + H / h * 100 + '%;' +
                    'background-position:' + x / (W - w) * 100 + '% ' + y / (H - h) * 100 + '%"></div>';
            }).join('');
        backdrop.style.backgroundImage = 'url(' + base + theme + '-unlit.jpg)';
        wins = Array.from(stage.querySelectorAll('.fyp-win'));
        layout();
    }

    function layout() {
        const vw = view.clientWidth, vh = view.clientHeight, { W, H } = M;
        const k = Math.min(vw * .9 / W, vh * .9 / H);
        stage.style.width = W * k + 'px'; stage.style.height = H * k + 'px';
        const [x, y, w, h] = M.wins[M.target];
        L = { vw, vh, X0: (vw - W * k) / 2, Y0: (vh - H * k) / 2, cx: (x + w / 2) * k, cy: (y + h / 2) * k, wx: x * k, wy: y * k, ww: w * k, wh: h * k };
        L.s2 = Math.max(vw / L.ww, vh / L.wh) * 1.12;      // the window overfills the screen: we are inside
        draw(shown);
    }

    /** Draw everything for progress p (0 = quiet building, 1 = inside the room) */
    function draw(p) {
        // Act 1: each window switches on at its own moment, with a short flicker
        const n = wins.length;
        wins.forEach(function (el, i) {
            const at = .04 + (i / n) * .36 + (i % 3 === 1 ? .006 : 0);
            const u = span(p, at, at + .045);
            let o;
            if (reduced) o = u > 0 ? 1 : 0;
            else o = u < .15 ? u / .15 * .6 : u < .3 ? lerp(.6, .18, (u - .15) / .15) : u < .5 ? lerp(.18, 1, (u - .3) / .2) : 1;
            el.style.opacity = o;
            el.style.filter = reduced || u >= 1 || u <= 0 ? '' : 'brightness(' + lerp(1.45, 1, span(u, .3, 1)) + ')';
        });

        // Act 2: a log-space dolly into the target window
        const t = span(p, .46, .86), e = ease(t);
        const s = Math.pow(L.s2, e);
        const fp = ease(clamp(t * 1.25));
        const px = lerp(L.X0 + L.cx, L.vw / 2, fp), py = lerp(L.Y0 + L.cy, L.vh / 2, fp);
        const X = px - s * L.cx, Y = py - s * L.cy;
        stage.style.transform = 'translate3d(' + X + 'px,' + Y + 'px,0) scale(' + s + ')';
        const blur = clamp((s - 3) * .9, 0, 12);                 // depth of field as the facade passes the lens
        stage.style.filter = blur > .2 ? 'blur(' + blur / s + 'px)' : '';

        const rx = X + s * L.wx, ry = Y + s * L.wy, rw = s * L.ww, rh = s * L.wh;
        const f = ease(clamp(t / .3));                            // the street falls into shadow around our window
        veil.style.setProperty('--vx', px + 'px'); veil.style.setProperty('--vy', py + 'px');
        veil.style.setProperty('--vr', Math.max(rw, rh) * .62 + 'px');
        veil.style.opacity = f * (1 - clamp((t - .7) / .25));

        // The room lives inside that window's glass the whole way, slightly deeper than the facade
        room.style.visibility = t > .02 ? 'visible' : 'hidden';
        room.style.clipPath = 'inset(' + Math.max(0, ry) + 'px ' + Math.max(0, L.vw - rx - rw) + 'px ' + Math.max(0, L.vh - ry - rh) + 'px ' + Math.max(0, rx) + 'px round ' + clamp(4 * s, 2, 14) * (1 - clamp((t - .85) / .15)) + 'px)';
        shot.style.opacity = ease(clamp((rw / L.vw - .14) / .2));
        const ps = Math.min(1, Math.pow(rw / L.vw, .55)) * lerp(1.08, 1, clamp((t - .8) / .2));
        shot.style.transform = 'translate(' + (rx + rw / 2 - L.vw / 2) + 'px,' + (ry + rh / 2 - L.vh / 2) + 'px) scale(' + ps + ')';

        // The words arrive last
        copy.forEach(function (el, i) {
            const c = ease(span(p, .86 + i * .025, .93 + i * .025));
            el.style.opacity = c; el.style.transform = 'translateY(' + (1 - c) * 24 + 'px)';
        });
        setInside(p > .9);
    }

    // Once inside, the room becomes a slow slideshow of shared moments (dots jump to one)
    const slides = Array.from(story.querySelectorAll('.fyp-slide'));
    const dots = story.querySelector('.fyp-dots');
    dots.innerHTML = slides.map(function (img, i) { return '<button type="button" aria-label="Photo ' + (i + 1) + ': ' + img.alt + '"' + (i ? '' : ' aria-current="true"') + '></button>'; }).join('');
    const dotBtns = Array.from(dots.children);
    let slide = 0, timer = 0, inside = false;
    function show(i) {
        slides[slide].classList.remove('on'); dotBtns[slide].removeAttribute('aria-current');
        slide = (i + slides.length) % slides.length;
        slides[slide].classList.add('on'); dotBtns[slide].setAttribute('aria-current', 'true');
    }
    function play() { clearInterval(timer); if (!reduced) timer = setInterval(function () { show(slide + 1); }, 4800); }
    function setInside(now) {
        if (now === inside) return;
        inside = now; story.classList.toggle('fyp-inside', now);
        if (now) { slides.forEach(function (img) { img.loading = 'eager'; }); play(); }
        else { clearInterval(timer); show(0); }            // going back out: the window shows the first room again
    }
    dotBtns.forEach(function (b, i) { b.addEventListener('click', function () { show(i); play(); }); });

    function progress() {
        const r = story.getBoundingClientRect();
        return clamp(-r.top / (story.offsetHeight - view.offsetHeight));
    }
    // Smooth follow (like a camera on a slider); reduced motion jumps straight there
    function tick() {
        frame = 0;
        shown = reduced ? target : shown + (target - shown) * .14;
        if (Math.abs(target - shown) < .0005) shown = target;
        draw(shown);
        if (shown !== target) frame = requestAnimationFrame(tick);
    }
    function onScroll() { target = progress(); if (!frame) frame = requestAnimationFrame(tick); }

    build();
    target = shown = progress(); draw(shown);
    window.addEventListener('scroll', onScroll, { passive: true });
    new ResizeObserver(function () { layout(); }).observe(view);
    // The theme pull-cord changes data-theme on <html>: swap to the other building
    new MutationObserver(build).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
