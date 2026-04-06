import L from './leaflet-setup.js';

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

    if (!places.length) {
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
        return;
    }

    if (bounds.length === 1) {
        map.setView(bounds[0], SINGLE_MARKER_ZOOM);
    } else {
        map.fitBounds(bounds, { padding: FIT_BOUNDS_PADDING });
    }
}
