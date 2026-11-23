/* ==========================================================================
 * js/pullcord.js  —  Owner: M1 (Lead)
 * The light/dark switch: a lamp hanging in the top-right corner. Pull its
 * cord (or Tab to it and press Enter) to switch themes; the choice is saved
 * per browser. Ported to plain JavaScript from Kokonut UI's "Pull Cord"
 * (MIT licence, @dorianbaffier). A chain of points joined by fixed-length
 * links (Verlet physics); pulling far enough clicks the switch.
 * ========================================================================== */
(function () {
  const root = document.getElementById('lamp'); if (!root) return; const svg = root.querySelector('svg'), btn = root.querySelector('button');
  const cord = svg.querySelector('.cord'), hit = svg.querySelector('.hit'), bead = svg.querySelector('.bead');
  const W = 96, H = 170, A = { x: 62, y: 58 }, N = 9, LINK = 7, STEP = 1 / 120, G = 1400, DAMP = .992, MAXV = 4;
  const BEND = LINK * 1.9, STIFF = .8, IT = 12, CLICK = 18, STRETCH = 28;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const xs = new Float64Array(N + 1), ys = new Float64Array(N + 1);
  for (let i = 0; i <= N; i++) { xs[i] = A.x; ys[i] = A.y + i * LINK; }
  const rest = [Float64Array.from(xs), Float64Array.from(ys)];
  const px = Float64Array.from(xs), py = Float64Array.from(ys);
  let held = -1, tx = 0, ty = 0, armed = true, raf = 0, last = 0, carry = 0, still = 0;

  let dark = (() => { try { return localStorage.getItem('rm-theme') === 'dark'; } catch (e) { return false; } })();
  function apply() {
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    btn.setAttribute('aria-checked', String(!dark)); btn.setAttribute('aria-label', dark ? 'Dark mode (pull for light)' : 'Light mode (pull for dark)');
    svg.querySelector('.bulb').setAttribute('fill', dark ? '#4B4E5E' : '#FCD34D');
    try { localStorage.setItem('rm-theme', dark ? 'dark' : 'light'); } catch (e) {}
  }
  function toggle() { dark = !dark; apply(); if (navigator.vibrate) navigator.vibrate(8); }
  apply();

  function relax(a, b, len, bend) {
    const af = a === 0 || a === held, bf = b === held; if (af && bf) return;
    const dx = xs[b] - xs[a], dy = ys[b] - ys[a], d = Math.hypot(dx, dy) || 1e-4;
    if (bend && d >= len) return;
    const diff = (d - len) / d * (bend ? STIFF : 1), wa = af ? 0 : bf ? 1 : .5;
    xs[a] += dx * diff * wa; ys[a] += dy * diff * wa; xs[b] -= dx * diff * (1 - wa); ys[b] -= dy * diff * (1 - wa);
  }
  function step() {
    let moved = 0;
    for (let i = 1; i <= N; i++) {
      if (i === held) continue;
      let vx = (xs[i] - px[i]) * DAMP, vy = (ys[i] - py[i]) * DAMP; const sp = Math.hypot(vx, vy);
      if (sp > MAXV) { vx *= MAXV / sp; vy *= MAXV / sp; }
      px[i] = xs[i]; py[i] = ys[i]; xs[i] += vx; ys[i] += vy + G * STEP * STEP; moved = Math.max(moved, Math.abs(vx) + Math.abs(vy));
    }
    if (held > 0) { moved = Math.max(moved, Math.abs(tx - xs[held]) + Math.abs(ty - ys[held])); px[held] = xs[held]; py[held] = ys[held]; xs[held] = tx; ys[held] = ty; }
    for (let k = 0; k < IT; k++) { for (let i = 0; i < N; i++) relax(i, i + 1, LINK, false); for (let i = 0; i + 2 <= N; i++) relax(i, i + 2, BEND, true); }
    return moved;
  }
  function draw() {
    let d = 'M' + xs[0] + ' ' + ys[0];
    for (let i = 1; i < N; i++) d += ' Q' + xs[i].toFixed(2) + ' ' + ys[i].toFixed(2) + ' ' + ((xs[i] + xs[i + 1]) / 2).toFixed(2) + ' ' + ((ys[i] + ys[i + 1]) / 2).toFixed(2);
    d += ' L' + xs[N].toFixed(2) + ' ' + ys[N].toFixed(2);
    cord.setAttribute('d', d); hit.setAttribute('d', d);
    const ang = Math.atan2(xs[N] - xs[N - 1], ys[N] - ys[N - 1]) * 180 / Math.PI;
    bead.setAttribute('transform', 'translate(' + xs[N].toFixed(2) + ' ' + ys[N].toFixed(2) + ') rotate(' + (-ang).toFixed(2) + ')');
  }
  function tick(now) {
    raf = 0; const el = last ? Math.min((now - last) / 1000, .05) : STEP; last = now; carry += el; let moved = 0;
    while (carry >= STEP) { moved = Math.max(moved, step()); carry -= STEP; }
    draw(); still = moved < .015 ? still + 1 : 0;
    if (held > 0 || still < 24) raf = requestAnimationFrame(tick); else last = 0;
  }
  function wake() { still = 0; if (!raf) raf = requestAnimationFrame(tick); }
  function local(e) { const r = svg.getBoundingClientRect(); return { x: (e.clientX - r.left) / r.width * W, y: (e.clientY - r.top) / r.height * H }; }
  function reach(i, x, y) {
    const dx = x - A.x, dy = y - A.y, d = Math.hypot(dx, dy) || 1e-4, len = i * LINK;
    if (d <= len) return { x, y, pull: 0 };
    const ok = len + STRETCH * (1 - Math.exp(-(d - len) / STRETCH));
    return { x: A.x + dx / d * ok, y: A.y + dy / d * ok, pull: dy > 0 ? (ok - len) * dy / d : 0 };
  }
  function down(e) {
    if (held > 0 || (e.pointerType === 'mouse' && e.button !== 0)) return;
    e.preventDefault(); const p = local(e); let best = 1e9, n = N;
    for (let i = 1; i <= N; i++) { const d = Math.hypot(xs[i] - p.x, ys[i] - p.y); if (d < best) { best = d; n = i; } }
    svg.setPointerCapture(e.pointerId); held = n; tx = xs[n]; ty = ys[n]; armed = true; svg.style.cursor = 'grabbing'; wake();
    const h = root.querySelector('.hint'); if (h) h.style.opacity = 0;
  }
  function up() {
    if (held < 0) return; held = -1; svg.style.cursor = '';
    if (reduce) { xs.set(rest[0]); ys.set(rest[1]); px.set(rest[0]); py.set(rest[1]); draw(); return; }
    wake();
  }
  hit.addEventListener('pointerdown', down); bead.addEventListener('pointerdown', down);
  svg.addEventListener('pointermove', function (e) {
    if (held < 0) return; const t = reach(held, ...Object.values(local(e))); tx = t.x; ty = t.y;
    if (armed && t.pull > CLICK) { armed = false; toggle(); } else if (!armed && t.pull < CLICK * .4) armed = true;
    wake();
  });
  svg.addEventListener('pointerup', up); svg.addEventListener('pointercancel', up); svg.addEventListener('lostpointercapture', up);
  btn.addEventListener('click', function () { toggle(); if (!reduce) { py[N] = ys[N] - 5; wake(); } });
  draw();
})();
