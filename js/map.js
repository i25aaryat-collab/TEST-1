/* ==========================================================================
 * js/map.js  —  Owner: M1 (Lead)
 * One small wrapper so pages don't care which map library is loaded:
 *   - MapTiler SDK (window.maptilersdk) when the server has a MAPTILER_KEY
 *   - Leaflet + OpenStreetMap otherwise
 * RMMap.create(el, lat, lng, zoom) -> { center(), setCenter(), onMove(fn), marker(lat,lng,html,popup), circle(lat,lng,m) }
 * RMMap.search(text) / RMMap.reverse(lat,lng) -> geocoding
 * Also wires every .loc-picker on the page (fixed centre pin + confirm).
 * ========================================================================== */
const RMMap = (function () {
    const KEY = (window.RM_MAP || {}).key;
    const INDORE = [22.7196, 75.8577];

    /** Last map look the person chose (or Dark when the site is in dark mode) */
    function pickLook(names) {
        let saved = null;
        try { saved = localStorage.getItem('rm-map-look'); } catch (e) {}
        if (saved && names.includes(saved)) return saved;
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'Dark' : 'Streets';
    }

    /** The "Map style" chooser in the corner of every map */
    function lookSwitch(el, names, current, onPick) {
        const box = document.createElement('div');
        box.className = 'map-look';
        box.innerHTML = '<label><span class="sr-only">Map style</span><select aria-label="Map style">' +
            names.map(n => '<option' + (n === current ? ' selected' : '') + '>' + n + '</option>').join('') + '</select></label>';
        const sel = box.querySelector('select');
        ['mousedown', 'touchstart', 'dblclick', 'wheel', 'click'].forEach(t => box.addEventListener(t, e => e.stopPropagation()));
        sel.addEventListener('change', () => { onPick(sel.value); try { localStorage.setItem('rm-map-look', sel.value); } catch (e) {} });
        if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
        el.appendChild(box);
    }

    function create(el, lat, lng, zoom) {
        if (KEY && window.maptilersdk) {
            maptilersdk.config.apiKey = KEY;
            const S = maptilersdk.MapStyle;
            const looks = { 'Streets': S.STREETS, 'Satellite': S.SATELLITE, 'Satellite + labels': S.HYBRID, 'Terrain': S.OUTDOOR, 'Topographic': S.TOPO, 'Light': S.DATAVIZ ? S.DATAVIZ.LIGHT : S.BRIGHT, 'Dark': S.DATAVIZ ? S.DATAVIZ.DARK : S.STREETS };
            const start = pickLook(Object.keys(looks));
            const map = new maptilersdk.Map({ container: el, style: looks[start], center: [lng, lat], zoom: zoom - 1 });
            const redraw = [];
            map.on('style.load', () => redraw.forEach(f => f()));
            lookSwitch(el, Object.keys(looks), start, name => map.setStyle(looks[name]));
            return {
                raw: map,
                center: () => { const c = map.getCenter(); return [c.lat, c.lng]; },
                setCenter: (a, b, z) => map.flyTo({ center: [b, a], zoom: z ? z - 1 : map.getZoom() }),
                onMove: (start, end) => { map.on('movestart', start); map.on('moveend', end); },
                marker: (a, b, html, popup) => { const d = document.createElement('div'); d.innerHTML = html; const m = new maptilersdk.Marker({ element: d.firstChild }).setLngLat([b, a]); if (popup) m.setPopup(new maptilersdk.Popup({ offset: 18 }).setHTML(popup)); return m.addTo(map); },
                circle: (a, b, metres) => {         // drawn as a GeoJSON polygon
                    const pts = []; for (let i = 0; i <= 48; i++) { const t = i / 48 * 2 * Math.PI; pts.push([b + metres / 111320 / Math.cos(a * Math.PI / 180) * Math.cos(t), a + metres / 110540 * Math.sin(t)]); }
                    const id = 'c' + Math.random().toString(36).slice(2);
                    const add = () => { map.addSource(id, { type: 'geojson', data: { type: 'Feature', geometry: { type: 'Polygon', coordinates: [pts] } } }); map.addLayer({ id, type: 'fill', source: id, paint: { 'fill-color': '#FF5A5F', 'fill-opacity': .12 } }); };
                    redraw.push(() => { if (!map.getSource(id)) add(); });
                    if (map.isStyleLoaded()) add();
                },
                resize: () => map.resize(),
            };
        }
        const map = L.map(el, { zoomControl: true }).setView([lat, lng], zoom);
        const esri = 'https://server.arcgisonline.com/ArcGIS/rest/services/';
        const looks = {
            'Streets': () => L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }),
            'Satellite': () => L.tileLayer(esri + 'World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Imagery © Esri, Maxar, Earthstar Geographics' }),
            'Satellite + labels': () => L.layerGroup([
                L.tileLayer(esri + 'World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Imagery © Esri, Maxar, Earthstar Geographics' }),
                L.tileLayer(esri + 'Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 }),
                L.tileLayer(esri + 'Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, opacity: .7 })]),
            'Terrain': () => L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', { maxZoom: 17, subdomains: 'abc', attribution: '© OpenStreetMap, SRTM · style © OpenTopoMap (CC-BY-SA)' }),
            'Topographic': () => L.tileLayer(esri + 'World_Topo_Map/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Tiles © Esri' }),
            'Light': () => L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { maxZoom: 20, subdomains: 'abcd', attribution: '© OpenStreetMap © CARTO' }),
            'Dark': () => L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 20, subdomains: 'abcd', attribution: '© OpenStreetMap © CARTO' }),
        };
        let base = null;
        const use = name => { if (base) map.removeLayer(base); base = looks[name]().addTo(map); };
        const start = pickLook(Object.keys(looks));
        use(start);
        lookSwitch(el, Object.keys(looks), start, use);
        return {
            raw: map,
            center: () => { const c = map.getCenter(); return [c.lat, c.lng]; },
            setCenter: (a, b, z) => map.setView([a, b], z || map.getZoom()),
            onMove: (start, end) => { map.on('movestart', start); map.on('moveend', end); },
            marker: (a, b, html, popup) => { const m = L.marker([a, b], { icon: L.divIcon({ className: '', html, iconSize: null }) }).addTo(map); if (popup) m.bindPopup(popup); return m; },
            circle: (a, b, metres) => L.circle([a, b], { radius: metres, color: '#FF5A5F', weight: 1, fillOpacity: .12 }).addTo(map),
            resize: () => map.invalidateSize(),
        };
    }

    // Place search goes through our own geo.php (identifies us to OpenStreetMap, caches, max 1 req/s)
    const GEO = (document.querySelector('.loc-picker') || { dataset: {} }).dataset.geo || 'geo.php';
    async function search(q, near) {
        const u = GEO + '?q=' + encodeURIComponent(q) + (near ? '&lat=' + near[0].toFixed(3) + '&lng=' + near[1].toFixed(3) : '');
        const r = await fetch(u, { credentials: 'same-origin' });
        return r.ok ? r.json() : [];
    }
    async function reverse(lat, lng) {
        try { const r = await fetch(GEO + '?lat=' + lat + '&lng=' + lng, { credentials: 'same-origin' }); return r.ok ? ((await r.json()).name || '') : ''; }
        catch (e) { return ''; }
    }

    // ---------- Fixed-pin location pickers ----------
    function wirePicker(box) {
        const p = box.dataset.prefix;
        const lat = box.querySelector('[name=' + p + '_lat]'), lng = box.querySelector('[name=' + p + '_lng]');
        const panel = box.querySelector('.loc-panel'), live = box.querySelector('.loc-live'), pin = box.querySelector('.loc-pin');
        const addr = box.querySelector('.loc-address'), openBtn = box.querySelector('.js-loc-open');
        let map = null, chosen = '', moving = false;

        function setPoint(a, b, label) {
            lat.value = (+a).toFixed(6); lng.value = (+b).toFixed(6);
            addr.textContent = label ? 'Set: ' + label : 'Location set';
            openBtn.textContent = 'Adjust on map';
            if (!box.querySelector('.js-loc-clear')) {
                const c = document.createElement('button'); c.type = 'button'; c.className = 'link-btn small js-loc-clear'; c.textContent = 'Remove';
                c.addEventListener('click', clearPoint); openBtn.after(' ', c);
            }
        }
        function clearPoint() { lat.value = lng.value = ''; addr.textContent = 'No location yet'; const c = box.querySelector('.js-loc-clear'); if (c) c.remove(); }
        function openMap(a, b, z) {
            panel.hidden = false;
            if (!map) {
                map = create(box.querySelector('.loc-canvas'), a, b, z);
                map.onMove(() => pin.classList.add('lifting'), async () => {
                    pin.classList.remove('lifting'); pin.classList.add('landed'); setTimeout(() => pin.classList.remove('landed'), 400);
                    if (moving) { moving = false; return; }                 // we moved it to a search result: keep that name
                    const [x, y] = map.center(); live.textContent = 'Finding the address…';
                    chosen = await reverse(x.toFixed(5), y.toFixed(5)); live.textContent = chosen || 'Pin placed. Confirm when ready.';
                });
            } else { moving = true; map.setCenter(a, b, z); }
            setTimeout(() => map.resize(), 50);
        }

        openBtn.addEventListener('click', () => openMap(+lat.value || INDORE[0], +lng.value || INDORE[1], lat.value ? 15 : 12));
        box.querySelector('.js-loc-cancel').addEventListener('click', () => { panel.hidden = true; });
        box.querySelector('.js-loc-confirm').addEventListener('click', function () {
            const [a, b] = map.center();
            setPoint(a, b, chosen ? chosen.split(',').slice(0, 3).join(',') : '');
            panel.hidden = true;
        });
        const clear = box.querySelector('.js-loc-clear');
        if (clear) clear.addEventListener('click', clearPoint);

        // ---- Search as you type (debounced; geo.php caches and paces requests to OpenStreetMap) ----
        const q = box.querySelector('.loc-search'), list = box.querySelector('.loc-results');
        let t, items = [], active = -1, seq = 0;
        const show = on => { list.hidden = !on; q.setAttribute('aria-expanded', on ? 'true' : 'false'); };
        const esc = x => String(x).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        function render(msg) {
            if (msg) { list.innerHTML = '<li class="loc-note" role="presentation">' + esc(msg) + '</li>'; show(true); return; }
            list.innerHTML = items.map((x, i) => '<li role="option" id="' + q.id + '-o' + i + '" data-i="' + i + '" aria-selected="' + (i === active) + '">' +
                '<strong>' + esc(x.short || x.name.split(',')[0]) + '</strong><span>' + esc(x.area || x.name) + '</span></li>').join('');
            q.setAttribute('aria-activedescendant', active >= 0 ? q.id + '-o' + active : '');
            show(items.length > 0);
        }
        function pick(i) {
            const x = items[i]; if (!x) return;
            q.value = x.short || x.name.split(',')[0];
            show(false);
            chosen = x.name;
            setPoint(x.lat, x.lng, x.short || x.name.split(',').slice(0, 3).join(','));
            openMap(x.lat, x.lng, 16);
            live.textContent = 'Pin dropped at ' + (x.short || x.name) + '. Drag the map to fine-tune, then confirm.';
        }
        q.addEventListener('input', function () {
            clearTimeout(t); active = -1;
            const text = q.value.trim();
            if (text.length < 3) { items = []; show(false); return; }
            t = setTimeout(async () => {
                const mine = ++seq;
                render('Searching…');
                const near = lat.value ? [+lat.value, +lng.value] : (map ? map.center() : INDORE);
                let found = [];
                try { found = await search(text, near); } catch (e) { found = null; }
                if (mine !== seq) return;                                    // a newer search has started
                items = found || [];
                if (found === null) render('Search is unavailable right now. Use "Adjust on map" instead.');
                else if (!items.length) render('No places found. Try a nearby landmark or area.');
                else render();
            }, 250);
        });
        q.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); if (!list.hidden && items.length) pick(active >= 0 ? active : 0); return; }   // never submits the form
            if (list.hidden || !items.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % items.length; render(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + items.length) % items.length; render(); }
            else if (e.key === 'Escape') { show(false); }
        });
        list.addEventListener('mousedown', e => { const li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); pick(+li.dataset.i); } });
        q.addEventListener('blur', () => setTimeout(() => show(false), 150));
    }
    document.querySelectorAll('.loc-picker').forEach(wirePicker);

    // ---------- A flat's own page: exact pin (owner / matched) or a circle over the rough area ----------
    const lm = document.getElementById('listing-map');
    if (lm) {
        const a = +lm.dataset.lat, b = +lm.dataset.lng, exact = lm.dataset.exact === '1';
        const m = create(lm, a, b, exact ? 15 : 14);
        if (exact) m.marker(a, b, '<span class="flat-pin" aria-hidden="true"></span>');
        else m.circle(a, b, 700);
        setTimeout(() => m.resize(), 60);
    }

    return { create, search, reverse, INDORE };
})();
window.RMMap = RMMap;

/* Flat page: street-level photos from Mapillary (free). Finds the photo nearest the flat
   within about 300 m and opens it in the MapillaryJS viewer; says so if there is none. */
(function () {
    var box = document.getElementById('mly-view');
    if (!box) return;
    var lat = parseFloat(box.dataset.lat), lng = parseFloat(box.dataset.lng), token = box.dataset.token, d = 0.003;
    var msg = function (t) { box.innerHTML = '<p class="muted small sv-msg">' + t + '</p>'; };
    var url = 'https://graph.mapillary.com/images?access_token=' + encodeURIComponent(token) + '&fields=id,computed_geometry&limit=20&bbox='
            + [lng - d, lat - d, lng + d, lat + d].join(',');
    fetch(url).then(function (r) { return r.json(); }).then(function (j) {
        var best = null, bestD = Infinity;
        (j.data || []).forEach(function (im) {
            var c = (im.computed_geometry || {}).coordinates; if (!c) return;
            var dd = Math.pow(c[0] - lng, 2) + Math.pow(c[1] - lat, 2);
            if (dd < bestD) { bestD = dd; best = im.id; }
        });
        if (!best) return msg('No street-level photos near this flat yet. Try the link below.');
        // Point "Open in Mapillary" at this exact photo, full screen, instead of the general map
        var link = document.getElementById('mly-link');
        if (link) link.href = 'https://www.mapillary.com/app/?pKey=' + encodeURIComponent(best) + '&focus=photo';
        var css = document.createElement('link'); css.rel = 'stylesheet'; css.href = 'https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.css';
        document.head.appendChild(css);
        var s = document.createElement('script'); s.src = 'https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.js';
        s.onload = function () { box.innerHTML = ''; new mapillary.Viewer({ accessToken: token, container: box, imageId: best }); };
        s.onerror = function () { msg('Street view could not load. Try the link below.'); };
        document.head.appendChild(s);
    }).catch(function () { msg('Street view could not load. Try the link below.'); });
})();
