/* ==========================================================================
 * js/landing.js  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * Animations for the landing page (index.php):
 *   1. The crowd: each character tile turns in 3D to face the cursor
 *   2. The story: a section pinned while you scroll, moving through 4 beats
 *   3. Reveals: features animate in when they scroll into view
 *   4. Counters: the hero numbers count up once
 * People who ask their device for reduced motion get a still page.
 * ========================================================================== */

(function () {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.body.classList.add('js-anim');

    // ---------- 1. Crowd tiles face the cursor ----------
    const crowd = document.getElementById('crowd');
    const tiles = crowd ? Array.from(crowd.querySelectorAll('.crowd-tile')) : [];
    let pointer = null, frame = 0;

    /** Tilt every tile toward the pointer: the closer it is, the more it turns */
    function aim() {
        frame = 0;
        tiles.forEach(function (tile) {
            const r = tile.getBoundingClientRect();
            const dx = pointer.x - (r.left + r.width / 2);
            const dy = pointer.y - (r.top + r.height / 2);
            const clamp = (v) => Math.max(-1, Math.min(1, v));
            const ry = clamp(dx / 500) * 22;          // turn left/right
            const rx = clamp(-dy / 500) * 16;         // look up/down
            tile.style.transform = 'rotateY(' + ry.toFixed(1) + 'deg) rotateX(' + rx.toFixed(1) + 'deg)';
            tile.style.setProperty('--shine-x', (50 + clamp(dx / 500) * 30).toFixed(0) + '%');
        });
    }
    if (!reduced && tiles.length && window.matchMedia('(pointer: fine)').matches) {
        window.addEventListener('pointermove', function (e) {
            pointer = { x: e.clientX, y: e.clientY };
            if (!frame) frame = requestAnimationFrame(aim);   // at most once per screen refresh
        });
    }

    // Videos only play while they are on screen (saves battery and data)
    const videos = Array.from(document.querySelectorAll('video'));
    if (reduced) {
        videos.forEach(function (v) { v.removeAttribute('autoplay'); v.pause(); });
    } else if ('IntersectionObserver' in window) {
        const vo = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { en.isIntersecting ? en.target.play().catch(function () {}) : en.target.pause(); });
        }, { threshold: 0.05 });
        videos.forEach(function (v) { vo.observe(v); });
    }

    // ---------- 2. The pinned story ----------
    const story = document.querySelector('.story');
    const stage = story && story.querySelector('.story-stage');
    const bar = story && story.querySelector('.story-progress i');
    function onScroll() {
        if (!story) return;
        const r = story.getBoundingClientRect();
        const total = story.offsetHeight - window.innerHeight;
        const p = Math.max(0, Math.min(1, -r.top / total));          // 0 at the start, 1 at the end
        const beat = Math.min(3, Math.floor(p * 4));
        if (stage.dataset.beat !== String(beat)) stage.dataset.beat = beat;
        bar.style.transform = 'scaleX(' + p.toFixed(3) + ')';
    }
    if (story) {
        if (reduced) {
            stage.dataset.beat = 3;
        } else {
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }
    }

    // ---------- 3. Reveal features as they scroll in ----------
    const reveals = document.querySelectorAll('.reveal');
    if (!reduced && 'IntersectionObserver' in window) {
        const ro = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('in'); ro.unobserve(en.target); }
            });
        }, { threshold: 0.25 });
        reveals.forEach(function (el) { ro.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('in'); });
    }

    // ---------- 4. Count the hero numbers up ----------
    if (!reduced) {
        document.querySelectorAll('[data-count]').forEach(function (el) {
            const end = parseInt(el.dataset.count, 10), start = performance.now();
            (function step(now) {
                const t = Math.min(1, (now - start) / 1200);
                el.textContent = Math.round(end * (1 - Math.pow(1 - t, 3)));
                if (t < 1) requestAnimationFrame(step);
            })(start);
        });
    }
})();
