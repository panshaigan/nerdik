import L from './leaflet-setup.js';
import { addThemedBasemapToMap } from './themed-basemap.js';

/**
 * @param {number} radiusKm
 */
function zoomForRadiusKm(radiusKm) {
    const targetKm = Math.max(radiusKm * 2.8, 2);
    const zoom = Math.log2(40_075 / targetKm);

    return Math.max(4, Math.min(15, Math.round(zoom)));
}

/**
 * @param {HTMLElement} root
 * @returns {{ lat: number, lng: number, radiusKm: number }|null}
 */
function readPlayAreaConfig(root) {
    const fromData = {
        lat: Number(root.dataset.latitude),
        lng: Number(root.dataset.longitude),
        radiusKm: Number(root.dataset.radiusKm),
    };

    if (
        Number.isFinite(fromData.lat)
        && Number.isFinite(fromData.lng)
        && Number.isFinite(fromData.radiusKm)
        && fromData.radiusKm > 0
    ) {
        return fromData;
    }

    const cfgEl = root.querySelector('script[type="application/json"][data-upapm-config]');
    if (!cfgEl) {
        return null;
    }

    let cfg = {};
    try {
        cfg = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        return null;
    }

    const lat = Number(cfg.latitude);
    const lng = Number(cfg.longitude);
    const radiusKm = Number(cfg.radiusKm);

    if (!Number.isFinite(lat) || !Number.isFinite(lng) || !Number.isFinite(radiusKm) || radiusKm <= 0) {
        return null;
    }

    return { lat, lng, radiusKm };
}

/**
 * @param {HTMLElement} el
 * @param {() => void} callback
 */
function whenReadyToMount(el, callback) {
    let done = false;
    const finish = () => {
        if (done) {
            return;
        }
        const rect = el.getBoundingClientRect();
        if (rect.width < 32 || rect.height < 32) {
            return;
        }
        done = true;
        io.disconnect();
        mo.disconnect();
        callback();
    };

    const io = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting && entry.intersectionRatio > 0) {
                    finish();
                    break;
                }
            }
        },
        { threshold: [0, 0.01] },
    );
    io.observe(el);

    const mo = new MutationObserver(() => finish());
    mo.observe(el, { attributes: true, attributeFilter: ['style', 'class'] });
    let parent = el.parentElement;
    while (parent && parent !== document.body) {
        mo.observe(parent, { attributes: true, attributeFilter: ['style', 'class'] });
        parent = parent.parentElement;
    }

    finish();
}

/**
 * @param {import('leaflet').Map} map
 */
function silenceZoomControlAnchors(map) {
    const container = map.zoomControl?.getContainer?.();
    if (!container) {
        return;
    }

    L.DomEvent.disableClickPropagation(container);
    container.querySelectorAll('a').forEach((anchor) => {
        L.DomEvent.on(anchor, 'click', (event) => {
            L.DomEvent.preventDefault(event);
            L.DomEvent.stopPropagation(event);
        });
    });
}

/**
 * @param {HTMLElement} root
 */
function mountMap(root) {
    const mapEl = root.querySelector('[data-upapm-map]');
    const config = readPlayAreaConfig(root);
    if (!mapEl || config === null) {
        return;
    }

    const { lat, lng, radiusKm } = config;
    const zoom = zoomForRadiusKm(radiusKm);

    mapEl.style.height = mapEl.style.height || '280px';
    mapEl.style.width = '100%';

    const map = L.map(mapEl, {
        scrollWheelZoom: false,
        dragging: true,
        zoomControl: true,
        fadeAnimation: false,
        zoomAnimation: false,
        markerZoomAnimation: false,
    }).setView([lat, lng], zoom);

    addThemedBasemapToMap(map, {
        osmAttribution: '&copy; OpenStreetMap',
    });
    silenceZoomControlAnchors(map);

    const circle = L.circle([lat, lng], {
        radius: radiusKm * 1000,
        color: '#38bdf8',
        fillColor: '#38bdf8',
        fillOpacity: 0.18,
        weight: 2,
    }).addTo(map);

    L.marker([lat, lng]).addTo(map);
    map.fitBounds(circle.getBounds(), { padding: [28, 28], animate: false, maxZoom: zoom });

    // One layout settle after the tab panel finishes showing — avoid repeated setView blinks.
    requestAnimationFrame(() => {
        map.invalidateSize({ animate: false });
        map.fitBounds(circle.getBounds(), { padding: [28, 28], animate: false, maxZoom: zoom });
    });

    root._upapmMap = map;
    root._upapmDestroy = () => {
        map.remove();
        delete root._upapmMap;
        delete root._upapmDestroy;
    };
}

/**
 * @param {HTMLElement} root
 */
export function initUserPlayAreaPreviewMap(root) {
    if (root.dataset.upapmInitialized === '1' || root.dataset.upapmPending === '1') {
        return;
    }

    root.dataset.upapmPending = '1';

    whenReadyToMount(root, () => {
        if (root.dataset.upapmInitialized === '1') {
            return;
        }
        root.dataset.upapmInitialized = '1';
        delete root.dataset.upapmPending;
        mountMap(root);
    });
}

export function initAllUserPlayAreaPreviewMaps() {
    document.querySelectorAll('[data-user-play-area-preview-map]').forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }
        initUserPlayAreaPreviewMap(root);
    });
}
