import L from './leaflet-setup.js';
import { addThemedBasemapToMap } from './themed-basemap.js';

/**
 * @param {import('leaflet').Map} map
 * @param {import('leaflet').Circle} circle
 */
function refreshMapLayout(map, circle) {
    map.invalidateSize({ animate: false });
    map.fitBounds(circle.getBounds(), { padding: [24, 24], animate: false });
    map.eachLayer((layer) => {
        if (typeof layer.redraw === 'function') {
            layer.redraw();
        }
    });
}

/**
 * @param {import('leaflet').Map} map
 * @param {import('leaflet').Circle} circle
 */
function scheduleRefresh(map, circle) {
    requestAnimationFrame(() => refreshMapLayout(map, circle));
    [50, 150, 300, 600].forEach((ms) => {
        setTimeout(() => refreshMapLayout(map, circle), ms);
    });
}

/**
 * Mary tabs use x-show (display:none). DaisyUI sheets also animate translate.
 * Wait until the host has a real laid-out box before creating Leaflet.
 *
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
        { threshold: [0, 0.01, 0.1] },
    );
    io.observe(el);

    const mo = new MutationObserver(() => finish());
    mo.observe(el, { attributes: true, attributeFilter: ['style', 'class'] });
    let parent = el.parentElement;
    while (parent && parent !== document.body) {
        mo.observe(parent, { attributes: true, attributeFilter: ['style', 'class'] });
        parent = parent.parentElement;
    }

    // Modal translate animation / flex layout may settle after the first paint.
    [0, 50, 100, 250, 500].forEach((ms) => setTimeout(finish, ms));
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

    // Ensure the Leaflet host has an explicit pixel height before L.map reads it.
    if (!mapEl.style.height) {
        mapEl.style.height = '280px';
    }
    mapEl.style.width = '100%';

    const map = L.map(mapEl, {
        scrollWheelZoom: false,
        dragging: true,
        zoomControl: true,
        fadeAnimation: false,
    });

    addThemedBasemapToMap(map, {
        osmAttribution: '&copy; OpenStreetMap',
    });
    silenceZoomControlAnchors(map);

    // CSS variables are unreliable as SVG presentation attributes inside some overlay trees.
    const circle = L.circle([lat, lng], {
        radius: radiusKm * 1000,
        color: '#38bdf8',
        fillColor: '#38bdf8',
        fillOpacity: 0.18,
        weight: 2,
    }).addTo(map);

    L.marker([lat, lng]).addTo(map);

    scheduleRefresh(map, circle);

    const ro = new ResizeObserver(() => {
        refreshMapLayout(map, circle);
    });
    ro.observe(mapEl);
    ro.observe(root);

    const io = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting && entry.intersectionRatio > 0) {
                    scheduleRefresh(map, circle);
                    break;
                }
            }
        },
        { threshold: [0, 0.01, 0.5] },
    );
    io.observe(root);

    const onTabClick = (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const tab = target.closest('[data-tab-name="play-preferences"]');
        if (!tab) {
            return;
        }
        scheduleRefresh(map, circle);
    };
    document.addEventListener('click', onTabClick, true);

    root._upapmMap = map;
    root._upapmDestroy = () => {
        ro.disconnect();
        io.disconnect();
        document.removeEventListener('click', onTabClick, true);
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
