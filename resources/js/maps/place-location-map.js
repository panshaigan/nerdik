import axios from 'axios';
import L from './leaflet-setup.js';
import { addThemedBasemapToMap } from './themed-basemap.js';

const DEFAULT_CENTER = [52.2297, 21.0122];
const DEFAULT_ZOOM = 6;
const FOCUS_ZOOM = 15;
const SEARCH_DEBOUNCE_MS = 280;

/**
 * Sync location fields into the nearest Livewire component.
 *
 * @param {HTMLElement} root
 * @param {{ latitude: number|null, longitude: number|null, address: string|null, city_id: number|null, country_id: number|null }} payload
 */
function syncLivewireLocation(root, payload) {
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
    if (!wire || typeof wire.set !== 'function') {
        return;
    }

    wire.set('latitude', payload.latitude);
    wire.set('longitude', payload.longitude);
    wire.set('address', payload.address);
    wire.set('city_id', payload.city_id);
    wire.set('country_id', payload.country_id);
}

/**
 * @param {HTMLElement} root
 */
export function initPlaceLocationMap(root) {
    if (root.dataset.plmInitialized === '1') {
        return;
    }

    const jsonEl = root.querySelector('script[type="application/json"][data-plm-config]');
    let cfg = {};
    try {
        cfg = jsonEl?.textContent ? JSON.parse(jsonEl.textContent) : {};
    } catch {
        cfg = {};
    }

    const mapEl = root.querySelector('[data-plm-map]');
    const searchInput = root.querySelector('[data-plm-search]');
    const resultsEl = root.querySelector('[data-plm-results]');

    if (!mapEl) {
        return;
    }

    root.dataset.plmInitialized = '1';

    const initialLat = cfg.latitude != null && cfg.latitude !== '' ? Number(cfg.latitude) : null;
    const initialLng = cfg.longitude != null && cfg.longitude !== '' ? Number(cfg.longitude) : null;
    const hasInitial = Number.isFinite(initialLat) && Number.isFinite(initialLng);

    const map = L.map(mapEl, {
        zoomControl: true,
        doubleClickZoom: false,
    }).setView(
        hasInitial ? [initialLat, initialLng] : DEFAULT_CENTER,
        hasInitial ? FOCUS_ZOOM : DEFAULT_ZOOM,
    );

    addThemedBasemapToMap(map);

    /** @type {import('leaflet').Marker|null} */
    let marker = null;

    /** @type {{ latitude: number|null, longitude: number|null, address: string|null, city_id: number|null, country_id: number|null }} */
    let state = {
        latitude: hasInitial ? initialLat : null,
        longitude: hasInitial ? initialLng : null,
        address: cfg.address || null,
        city_id: null,
        country_id: null,
    };

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
    }

    /**
     * @param {number} lat
     * @param {number} lng
     * @param {{ reverse?: boolean, address?: string|null, city_id?: number|null, country_id?: number|null }} options
     */
    async function setLocationFromCoords(lat, lng, options = {}) {
        state.latitude = lat;
        state.longitude = lng;
        placeMarker(lat, lng);

        if (options.address !== undefined) {
            state.address = options.address;
        }
        if (options.city_id !== undefined) {
            state.city_id = options.city_id;
        }
        if (options.country_id !== undefined) {
            state.country_id = options.country_id;
        }

        if (options.reverse && cfg.reverseUrl) {
            try {
                const { data } = await axios.get(cfg.reverseUrl, {
                    params: { lat, lng },
                });
                state.address = data.address_short || data.display_name || state.address;
                state.city_id = data.city_id ?? state.city_id;
                state.country_id = data.country_id ?? state.country_id;
            } catch {
                // Keep coords even if reverse geocode fails.
            }
        }

        syncLivewireLocation(root, state);
    }

    if (hasInitial) {
        placeMarker(initialLat, initialLng);
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
                    address: r.address_short || r.label || null,
                    city_id: r.city_id ?? null,
                    country_id: r.country_id ?? null,
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

    root._plmMap = map;
}

export function initAllPlaceLocationMaps() {
    document.querySelectorAll('[data-place-location-map]').forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }
        initPlaceLocationMap(root);
    });
}
