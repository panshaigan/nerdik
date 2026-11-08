import L from './leaflet-setup.js';
import { addThemedBasemapToMap } from './themed-basemap.js';

function scheduleInvalidateSize(map) {
    requestAnimationFrame(() => map.invalidateSize());
    [50, 200].forEach((ms) => {
        setTimeout(() => map.invalidateSize(), ms);
    });
}

/**
 * @param {HTMLElement} root
 */
export function initUserPlayAreaPreviewMap(root) {
    if (root.dataset.upapmInitialized === '1') {
        return;
    }

    const cfgEl = root.querySelector('script[type="application/json"][data-upapm-config]');
    const mapEl = root.querySelector('[data-upapm-map]');
    if (!cfgEl || !mapEl) {
        return;
    }

    let cfg = {};
    try {
        cfg = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        cfg = {};
    }

    const lat = Number(cfg.latitude);
    const lng = Number(cfg.longitude);
    const radiusKm = Number(cfg.radiusKm);

    if (!Number.isFinite(lat) || !Number.isFinite(lng) || !Number.isFinite(radiusKm) || radiusKm <= 0) {
        return;
    }

    root.dataset.upapmInitialized = '1';

    const map = L.map(mapEl, { scrollWheelZoom: false, dragging: true, zoomControl: true });
    addThemedBasemapToMap(map);

    const circle = L.circle([lat, lng], {
        radius: radiusKm * 1000,
        color: 'var(--color-primary)',
        fillColor: 'var(--color-primary)',
        fillOpacity: 0.12,
        weight: 2,
    }).addTo(map);

    map.fitBounds(circle.getBounds(), { padding: [24, 24] });

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

    scheduleInvalidateSize(map);
    root._upapmMap = map;
}

export function initAllUserPlayAreaPreviewMaps() {
    document.querySelectorAll('[data-user-play-area-preview-map]').forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }
        initUserPlayAreaPreviewMap(root);
    });
}
