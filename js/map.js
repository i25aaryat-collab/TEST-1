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

    async function search(q) {
        const url = KEY ? 'https://api.maptiler.com/geocoding/' + encodeURIComponent(q) + '.json?country=in&limit=5&key=' + KEY
                        : 'https://nominatim.openstreetmap.org/search?format=json&countrycodes=in&limit=5&q=' + encodeURIComponent(q);
        const j = await (await fetch(url)).json();
        return KEY ? j.features.map(f => ({ name: f.place_name, lat: f.center[1], lng: f.center[0] }))
                   : j.map(x => ({ name: x.display_name, lat: +x.lat, lng: +x.lon }));
    }
    async function reverse(lat, lng) {
        try {
            const url = KEY ? 'https://api.maptiler.com/geocoding/' + lng + ',' + lat + '.json?limit=1&key=' + KEY
                            : 'https://nominatim.openstreetmap.org/reverse?format=json&zoom=16&lat=' + lat + '&lon=' + lng;
            const j = await (await fetch(url)).json();
            return KEY ? (j.features[0] || {}).place_name : j.display_name;
        } catch (e) { return ''; }
    }

    // ---------- Fixed-pin location pickers ----------
    function wirePicker(box) {
        const p = box.dataset.prefix;
        const lat = box.querySelector('[name=' + p + '_lat]'), lng = box.querySelector('[name=' + p + '_lng]');
        const panel = box.querySelector('.loc-panel'), live = box.querySelector('.loc-live'), pin = box.querySelector('.loc-pin');
        const addr = box.querySelector('.loc-address');
        let map = null, chosen = '';
        box.querySelector('.js-loc-open').addEventListener('click', function () {
            panel.hidden = false;
            if (!map) {
                map = create(box.querySelector('.loc-canvas'), +lat.value || INDORE[0], +lng.value || INDORE[1], lat.value ? 15 : 12);
                map.onMove(() => pin.classList.add('lifting'), async () => {
                    pin.classList.remove('lifting'); pin.classList.add('landed'); setTimeout(() => pin.classList.remove('landed'), 400);
                    const [a, b] = map.center(); live.textContent = 'Finding the address…';
                    chosen = await reverse(a.toFixed(5), b.toFixed(5)); live.textContent = chosen || 'Pin placed. Confirm when ready.';
                });
            }
            setTimeout(() => map.resize(), 50);
        });
        box.querySelector('.js-loc-cancel').addEventListener('click', () => { panel.hidden = true; });
        box.querySelector('.js-loc-confirm').addEventListener('click', function () {
            const [a, b] = map.center();
            lat.value = a.toFixed(6); lng.value = b.toFixed(6);
            addr.textContent = '📍 ' + (chosen ? chosen.split(',').slice(0, 3).join(',') : 'Location set ✓');
            panel.hidden = true;
        });
        const clear = box.querySelector('.js-loc-clear');
        if (clear) clear.addEventListener('click', () => { lat.value = lng.value = ''; addr.textContent = 'No location yet'; clear.remove(); });
        const q = box.querySelector('.loc-search'), list = box.querySelector('.loc-results'); let t;
        q.addEventListener('input', function () {
            clearTimeout(t); if (q.value.length < 3) { list.hidden = true; return; }
            t = setTimeout(async () => {
                const items = await search(q.value);
                list.innerHTML = items.map((x, i) => '<li data-i="' + i + '">' + x.name.replace(/</g, '&lt;') + '</li>').join('');
                list.hidden = !items.length;
                list.onclick = e => { const x = items[e.target.dataset.i]; if (x) { map.setCenter(x.lat, x.lng, 15); list.hidden = true; q.value = x.name.split(',')[0]; } };
            }, 350);
        });
        q.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });   // don't submit the form
    }
    document.querySelectorAll('.loc-picker').forEach(wirePicker);

    return { create, search, reverse, INDORE };
})();
window.RMMap = RMMap;
