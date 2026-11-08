import axios from 'axios';
import L from './leaflet-setup.js';
import { addThemedBasemapToMap } from './themed-basemap.js';

const DEFAULT_CENTER = [52.2297, 21.0122];
const DEFAULT_ZOOM = 6;
const FOCUS_ZOOM = 11;
const SEARCH_DEBOUNCE_MS = 280;
const RADIUS_SYNC_DEBOUNCE_MS = 120;

/**
 * @param {HTMLElement} root
 * @param {{ latitude: number|null, longitude: number|null, radiusKm: number, label: string|null }} payload
 */
function syncLivewirePlayArea(root, payload) {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.find !== 'function') {
        return;
    }

    const host = root.closest('[wire\\:id]');
    if (!host) {
        return;
    }

    const id = host.getAttribute('wire:id');
    if (!id) {
        return;
    }

    const wire = window.Livewire.find(id);
    if (!wire || typeof wire.call !== 'function') {
        return;
    }

    wire.call(
        'setPlayAreaDraft',
        payload.latitude,
        payload.longitude,
        payload.radiusKm,
        payload.label,
    );
}

/**
 * Leaflet zoom controls use <a href="#"> which scrolls the page to the top.
 *
 * @param {import('leaflet').Map} map
 */
function silenceZoomControlAnchors(map) {
    const container = map.zoomControl?.getContainer?.();
    if (!container) {
        return;
    }

    L.DomEvent.disableClickPropagation(container);
    L.DomEvent.disableScrollPropagation(container);
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
export function initPlayAreaMap(root) {
    if (root.dataset.pamInitialized === '1') {
        return;
    }

    const jsonEl = root.querySelector('script[type="application/json"][data-pam-config]');
    let cfg = {};
    try {
        cfg = jsonEl?.textContent ? JSON.parse(jsonEl.textContent) : {};
    } catch {
        cfg = {};
    }

    const mapEl = root.querySelector('[data-pam-map]');
    const searchInput = root.querySelector('[data-pam-search]');
    const resultsEl = root.querySelector('[data-pam-results]');
    const radiusInput = root.querySelector('[data-pam-radius]');
    const radiusValueEl = root.querySelector('[data-pam-radius-value]');
    const labelEl = root.querySelector('[data-pam-label]');
    const clearBtn = root.querySelector('[data-pam-clear]');

    if (!mapEl) {
        return;
    }

    root.dataset.pamInitialized = '1';

    const minRadius = Number(cfg.minRadiusKm) || 1;
    const maxRadius = Number(cfg.maxRadiusKm) || 300;
    const defaultRadius = Number(cfg.defaultRadiusKm) || 25;

    const initialLat = cfg.latitude != null && cfg.latitude !== '' ? Number(cfg.latitude) : null;
    const initialLng = cfg.longitude != null && cfg.longitude !== '' ? Number(cfg.longitude) : null;
    const hasInitial = Number.isFinite(initialLat) && Number.isFinite(initialLng);
    let radiusKm = Number(cfg.radiusKm);
    if (!Number.isFinite(radiusKm) || radiusKm < minRadius) {
        radiusKm = defaultRadius;
    }
    radiusKm = Math.min(maxRadius, Math.max(minRadius, Math.round(radiusKm)));

    if (radiusInput instanceof HTMLInputElement) {
        radiusInput.min = String(minRadius);
        radiusInput.max = String(maxRadius);
        radiusInput.value = String(radiusKm);
    }

    const map = L.map(mapEl, {
        zoomControl: true,
        doubleClickZoom: false,
    }).setView(
        hasInitial ? [initialLat, initialLng] : DEFAULT_CENTER,
        hasInitial ? FOCUS_ZOOM : DEFAULT_ZOOM,
    );

    addThemedBasemapToMap(map);
    silenceZoomControlAnchors(map);

    /** @type {import('leaflet').Marker|null} */
    let marker = null;
    /** @type {import('leaflet').Circle|null} */
    let circle = null;
    let radiusSyncTimer = null;

    /** @type {{ latitude: number|null, longitude: number|null, radiusKm: number, label: string|null }} */
    let state = {
        latitude: hasInitial ? initialLat : null,
        longitude: hasInitial ? initialLng : null,
        radiusKm,
        label: cfg.label || null,
    };

    function updateLabelDisplay() {
        if (!labelEl) {
            return;
        }
        labelEl.textContent = state.label || cfg.strings?.noLocation || '';
    }

    function updateRadiusDisplay() {
        if (radiusValueEl) {
            radiusValueEl.textContent = String(state.radiusKm);
        }
        if (radiusInput instanceof HTMLInputElement && radiusInput.value !== String(state.radiusKm)) {
            radiusInput.value = String(state.radiusKm);
        }
    }

    function updateClearVisibility() {
        if (!(clearBtn instanceof HTMLElement)) {
            return;
        }
        clearBtn.classList.toggle('hidden', state.latitude === null || state.longitude === null);
    }

    function redrawCircle() {
        if (state.latitude === null || state.longitude === null) {
            if (circle) {
                map.removeLayer(circle);
                circle = null;
            }

            return;
        }

        const radiusM = state.radiusKm * 1000;
        if (circle) {
            circle.setLatLng([state.latitude, state.longitude]);
            circle.setRadius(radiusM);
        } else {
            circle = L.circle([state.latitude, state.longitude], {
                radius: radiusM,
                color: 'var(--color-primary)',
                fillColor: 'var(--color-primary)',
                fillOpacity: 0.12,
                weight: 2,
            }).addTo(map);
        }
    }

    function placeMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const { lat: dLat, lng: dLng } = marker.getLatLng();
                void setLocationFromCoords(dLat, dLng, { reverse: true });
            });
        }
        map.setView([lat, lng], Math.max(map.getZoom(), FOCUS_ZOOM));
        redrawCircle();
    }

    function clearMapLayers() {
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
        if (circle) {
            map.removeLayer(circle);
            circle = null;
        }
        state.latitude = null;
        state.longitude = null;
        state.label = null;
        updateLabelDisplay();
        updateClearVisibility();
    }

    function pushStateToLivewire() {
        syncLivewirePlayArea(root, state);
    }

    /**
     * @param {number} lat
     * @param {number} lng
     * @param {{ reverse?: boolean, label?: string|null }} options
     */
    async function setLocationFromCoords(lat, lng, options = {}) {
        state.latitude = lat;
        state.longitude = lng;
        placeMarker(lat, lng);
        updateClearVisibility();

        if (options.label !== undefined) {
            state.label = options.label;
            updateLabelDisplay();
        }

        if (options.reverse && cfg.reverseUrl) {
            try {
                const { data } = await axios.get(cfg.reverseUrl, {
                    params: { lat, lng },
                });
                state.label = data.address_short || data.display_name || state.label;
                updateLabelDisplay();
            } catch {
                // Keep coords even if reverse geocode fails.
            }
        }

        pushStateToLivewire();
    }

    function onRadiusChange() {
        if (!(radiusInput instanceof HTMLInputElement)) {
            return;
        }

        let next = Math.round(Number(radiusInput.value));
        if (!Number.isFinite(next)) {
            next = defaultRadius;
        }
        next = Math.min(maxRadius, Math.max(minRadius, next));
        state.radiusKm = next;
        updateRadiusDisplay();
        redrawCircle();

        clearTimeout(radiusSyncTimer);
        radiusSyncTimer = setTimeout(() => {
            if (state.latitude !== null && state.longitude !== null) {
                pushStateToLivewire();
            } else {
                pushStateToLivewire();
            }
        }, RADIUS_SYNC_DEBOUNCE_MS);
    }

    updateLabelDisplay();
    updateRadiusDisplay();
    updateClearVisibility();

    if (radiusInput instanceof HTMLInputElement) {
        L.DomEvent.disableClickPropagation(radiusInput);
        radiusInput.addEventListener('input', onRadiusChange);
        radiusInput.addEventListener('change', onRadiusChange);
    }

    if (clearBtn instanceof HTMLElement) {
        clearBtn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            clearTimeout(radiusSyncTimer);
            clearMapLayers();
            state.radiusKm = defaultRadius;
            updateRadiusDisplay();
            map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            pushStateToLivewire();
        });
    }

    if (hasInitial) {
        placeMarker(initialLat, initialLng);
        redrawCircle();
        updateClearVisibility();
    }

    map.on('click', (e) => {
        void setLocationFromCoords(e.latlng.lat, e.latlng.lng, { reverse: true });
    });

    let searchTimer = null;

    function hideResults() {
        if (resultsEl) {
            resultsEl.classList.add('hidden');
            resultsEl.innerHTML = '';
        }
    }

    async function runSearch(query) {
        if (!resultsEl || !cfg.searchUrl) {
            return;
        }

        const q = String(query || '').trim();
        if (q.length < 2) {
            hideResults();

            return;
        }

        let remote = [];
        try {
            const { data } = await axios.get(cfg.searchUrl, { params: { q } });
            remote = Array.isArray(data.results) ? data.results : [];
        } catch {
            remote = [];
        }

        resultsEl.innerHTML = '';
        if (!remote.length) {
            hideResults();

            return;
        }

        const frag = document.createDocumentFragment();
        remote.forEach((r) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full cursor-pointer px-3 py-2 text-left text-sm hover:bg-base-200';
            b.textContent = r.label || r.address_short || '';
            b.addEventListener('click', () => {
                const lat = Number(r.lat);
                const lng = Number(r.lon);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }
                void setLocationFromCoords(lat, lng, {
                    reverse: false,
                    label: r.address_short || r.label || null,
                });
                if (searchInput) {
                    searchInput.value = '';
                }
                hideResults();
            });
            frag.appendChild(b);
        });
        resultsEl.appendChild(frag);
        resultsEl.classList.remove('hidden');
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                void runSearch(searchInput.value);
            }, SEARCH_DEBOUNCE_MS);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideResults();
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (!resultsEl || resultsEl.classList.contains('hidden')) {
            return;
        }
        if (root.contains(e.target)) {
            return;
        }
        hideResults();
    });

    requestAnimationFrame(() => {
        map.invalidateSize();
    });

    root._pamMap = map;
}
