import L from './leaflet-setup.js';

function scheduleInvalidateSize(map) {
    requestAnimationFrame(() => map.invalidateSize());
    [50, 200].forEach((ms) => {
        setTimeout(() => map.invalidateSize(), ms);
    });
}

export function initEventShowMap(root) {
    if (root.dataset.eventShowMapInit) return;
    root.dataset.eventShowMapInit = '1';

    const INITIAL_CENTER = [52.1, 19.4];
    const INITIAL_ZOOM = 5;
    const SINGLE_MARKER_ZOOM = 13;
    const FIT_BOUNDS_PADDING = [28, 28];

    const cfgEl = root.querySelector('script[type="application/json"][data-event-show-map-config]');
    const mapEl = root.querySelector('[data-event-show-map]');
    if (!cfgEl || !mapEl) return;

    let cfg = {};
    try {
        cfg = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        cfg = {};
    }

    const places = Array.isArray(cfg.places) ? cfg.places : [];
    const map = L.map(mapEl, { scrollWheelZoom: true }).setView(INITIAL_CENTER, INITIAL_ZOOM);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    /** @type {import('leaflet').Map} */
    root._leafletEventShowMap = map;

    const onResize = () => map.invalidateSize();
    window.addEventListener('resize', onResize);

    const io = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting && entry.intersectionRatio > 0) {
                    scheduleInvalidateSize(map);
                    break;
                }
            }
        },
        { threshold: [0, 0.01] },
    );
    io.observe(root);

    if (!places.length) {
        scheduleInvalidateSize(map);
        return;
    }

    const bounds = [];
    places.forEach((p) => {
        const lat = Number(p.lat);
        const lng = Number(p.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        bounds.push([lat, lng]);
        L.marker([lat, lng]).addTo(map).bindPopup(p.name || '');
    });

    if (!bounds.length) {
        scheduleInvalidateSize(map);
        return;
    }

    if (bounds.length === 1) {
        map.setView(bounds[0], SINGLE_MARKER_ZOOM);
    } else {
        map.fitBounds(bounds, { padding: FIT_BOUNDS_PADDING });
    }

    scheduleInvalidateSize(map);
}

/** After Livewire morph or layout changes; safe if maps are not inited yet. */
export function invalidateAllEventShowMaps() {
    document.querySelectorAll('[data-event-show-map-root]').forEach((root) => {
        const m = root._leafletEventShowMap;
        if (m && typeof m.invalidateSize === 'function') {
            scheduleInvalidateSize(m);
        }
    });
}
