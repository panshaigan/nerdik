import L from './leaflet-setup.js';
import './browse-events-map.css';

const DEBOUNCE_MS = 320;
const DEFAULT_CENTER = [52.0, 19.0];
const DEFAULT_ZOOM = 5;
const BBOX_ZOOM = 6;

/**
 * Push bbox hidden field values into the wrapping Livewire component (browse events).
 */
function syncLivewireBrowseMap(root) {
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
    if (!wire) {
        return;
    }

    const setProp =
        typeof wire.set === 'function'
            ? (k, v) => wire.set(k, v)
            : typeof wire.$set === 'function'
              ? (k, v) => wire.$set(k, v)
              : null;
    if (!setProp) {
        return;
    }

    const minLatEl = document.getElementById('bbox_min_lat');
    const maxLatEl = document.getElementById('bbox_max_lat');
    const minLngEl = document.getElementById('bbox_min_lng');
    const maxLngEl = document.getElementById('bbox_max_lng');
    if (!minLatEl || !maxLatEl || !minLngEl || !maxLngEl) {
        return;
    }

    const toNull = (v) => (v === '' || v === undefined || v === null ? null : v);

    setProp('min_lat', toNull(minLatEl.value));
    setProp('max_lat', toNull(maxLatEl.value));
    setProp('min_lng', toNull(minLngEl.value));
    setProp('max_lng', toNull(maxLngEl.value));
}

function invalidateMapSize(map) {
    if (!map) {
        return;
    }
    requestAnimationFrame(() => {
        map.invalidateSize({ animate: false });
        requestAnimationFrame(() => map.invalidateSize({ animate: false }));
    });
}

function containerIsMeasurable(root) {
    return root.offsetWidth >= 2 && root.offsetHeight >= 2;
}

function readBBoxFromInputs() {
    const minLatEl = document.getElementById('bbox_min_lat');
    const maxLatEl = document.getElementById('bbox_max_lat');
    const minLngEl = document.getElementById('bbox_min_lng');
    const maxLngEl = document.getElementById('bbox_max_lng');
    if (!minLatEl || !maxLatEl || !minLngEl || !maxLngEl) {
        return null;
    }
    const swLat = parseFloat(minLatEl.value);
    const neLat = parseFloat(maxLatEl.value);
    const swLng = parseFloat(minLngEl.value);
    const neLng = parseFloat(maxLngEl.value);
    const hasBbox =
        minLatEl.value !== '' &&
        maxLatEl.value !== '' &&
        minLngEl.value !== '' &&
        maxLngEl.value !== '' &&
        !Number.isNaN(swLat) &&
        !Number.isNaN(neLat) &&
        !Number.isNaN(swLng) &&
        !Number.isNaN(neLng);
    if (!hasBbox) {
        return null;
    }
    const south = Math.min(swLat, neLat);
    const north = Math.max(swLat, neLat);
    const west = Math.min(swLng, neLng);
    const east = Math.max(swLng, neLng);

    return { south, north, west, east };
}

function applyBoundsToInputs(bounds, root) {
    const mMinLat = document.getElementById('bbox_min_lat');
    const mMaxLat = document.getElementById('bbox_max_lat');
    const mMinLng = document.getElementById('bbox_min_lng');
    const mMaxLng = document.getElementById('bbox_max_lng');
    if (!mMinLat || !mMaxLat || !mMinLng || !mMaxLng) {
        return;
    }
    const south = bounds.getSouth();
    const north = bounds.getNorth();
    const west = bounds.getWest();
    const east = bounds.getEast();
    if (!Number.isFinite(south) || !Number.isFinite(north) || !Number.isFinite(west) || !Number.isFinite(east)) {
        return;
    }
    mMinLat.value = south.toFixed(5);
    mMaxLat.value = north.toFixed(5);
    mMinLng.value = west.toFixed(5);
    mMaxLng.value = east.toFixed(5);
    syncLivewireBrowseMap(root);
}

function clearInputsAndSync(root) {
    const mMinLat = document.getElementById('bbox_min_lat');
    const mMaxLat = document.getElementById('bbox_max_lat');
    const mMinLng = document.getElementById('bbox_min_lng');
    const mMaxLng = document.getElementById('bbox_max_lng');
    if (mMinLat) {
        mMinLat.value = '';
    }
    if (mMaxLat) {
        mMaxLat.value = '';
    }
    if (mMinLng) {
        mMinLng.value = '';
    }
    if (mMaxLng) {
        mMaxLng.value = '';
    }
    syncLivewireBrowseMap(root);
}

function clusterDivIcon(count) {
    return L.divIcon({
        className: 'browse-map-cluster-icon',
        html: `<span class="browse-map-cluster-count">${count}</span>`,
        iconSize: [36, 36],
        iconAnchor: [18, 18],
    });
}

function renderFeatures(map, layer, data) {
    layer.clearLayers();
    if (!data || !Array.isArray(data.features)) {
        return;
    }
    for (const f of data.features) {
        if (!f.geometry || f.geometry.type !== 'Point' || !Array.isArray(f.geometry.coordinates)) {
            continue;
        }
        const [lng, lat] = f.geometry.coordinates;
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            continue;
        }
        const props = f.properties || {};
        if (props.cluster) {
            const n = Number(props.count) || 0;
            const m = L.marker([lat, lng], { icon: clusterDivIcon(n) });
            m.on('click', () => {
                map.setView([lat, lng], Math.min(map.getZoom() + 2, 18), { animate: true });
            });
            m.bindPopup(`<strong>${n}</strong>`);
            layer.addLayer(m);
        } else {
            const name = props.name ? String(props.name) : '';
            const url = props.url ? String(props.url) : '';
            const kind = props.kind ? String(props.kind) : '';
            const body =
                url !== ''
                    ? `<a href="${url.replace(/"/g, '&quot;')}" class="link link-primary">${name.replace(/</g, '')}</a>`
                    : name.replace(/</g, '');
            L.marker([lat, lng]).addTo(layer).bindPopup(`<div class="text-sm">${body}</div><div class="text-xs opacity-70">${kind}</div>`);
        }
    }
}

async function loadMapFeatures(map, layer, root) {
    const baseUrl = root.dataset.mapFeaturesUrl;
    if (!baseUrl) {
        return;
    }
    const u = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams(window.location.search);
    for (const [k, v] of params.entries()) {
        if (k === 'zoom') {
            continue;
        }
        u.searchParams.set(k, v);
    }
    u.searchParams.set('zoom', String(map.getZoom()));
    try {
        const res = await fetch(u.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || json.type !== 'FeatureCollection') {
            layer.clearLayers();

            return;
        }
        renderFeatures(map, layer, json);
    } catch {
        /* ignore */
    }
}

function attachClearToolbar(map, root, markersLayer) {
    const Toolbar = L.Control.extend({
        options: { position: 'topright' },

        onAdd() {
            const wrap = L.DomUtil.create('div', 'leaflet-bar leaflet-control browse-events-map-toolbar');
            const clearBtn = L.DomUtil.create('a', 'browse-events-map-clear-btn', wrap);
            clearBtn.href = '#';
            clearBtn.title = root.dataset.strClear || 'Clear area';
            clearBtn.setAttribute('aria-label', clearBtn.title);
            clearBtn.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';

            L.DomEvent.on(clearBtn, 'click', L.DomEvent.stopPropagation);
            L.DomEvent.on(clearBtn, 'click', L.DomEvent.preventDefault);
            L.DomEvent.on(clearBtn, 'click', () => {
                clearInputsAndSync(root);
                markersLayer.clearLayers();
                map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            });

            return wrap;
        },
    });

    map.addControl(new Toolbar());
}

function startBrowseEventsMap(root) {
    if (root.dataset.leafletBrowseMapInit === '1') {
        return;
    }
    root.dataset.leafletBrowseMapInit = '1';

    try {
        const bbox = readBBoxFromInputs();
        const center = bbox ? [(bbox.south + bbox.north) / 2, (bbox.west + bbox.east) / 2] : DEFAULT_CENTER;
        const zoom = bbox ? BBOX_ZOOM : DEFAULT_ZOOM;

        const map = L.map(root, { scrollWheelZoom: true }).setView(center, zoom);

        invalidateMapSize(map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        const markersLayer = L.layerGroup().addTo(map);

        if (bbox) {
            map.fitBounds(
                [
                    [bbox.south, bbox.west],
                    [bbox.north, bbox.east],
                ],
                { padding: [24, 24] },
            );
            invalidateMapSize(map);
        }

        attachClearToolbar(map, root, markersLayer);

        let debounceTimer = null;
        const scheduleBoundsSync = () => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(() => {
                debounceTimer = null;
                applyBoundsToInputs(map.getBounds(), root);
            }, DEBOUNCE_MS);
        };

        map.on('moveend', scheduleBoundsSync);
        map.on('zoomend', scheduleBoundsSync);

        const ro = new ResizeObserver(() => {
            if (containerIsMeasurable(root)) {
                map.invalidateSize({ animate: false });
            }
        });
        ro.observe(root);

        invalidateMapSize(map);

        const runFeatures = () => loadMapFeatures(map, markersLayer, root);
        runFeatures();

        root._leafletBrowseMap = map;
        root._browseMarkersLayer = markersLayer;
    } catch (err) {
        delete root.dataset.leafletBrowseMapInit;
        console.error('[browse-events-map]', err);
    }
}

function tryStartBrowseEventsMap(root) {
    if (root.dataset.leafletBrowseMapInit === '1') {
        return true;
    }
    if (!containerIsMeasurable(root)) {
        return false;
    }
    startBrowseEventsMap(root);
    return true;
}

let browseMapMorphHookRegistered = false;

function registerBrowseMapMorphRefetch() {
    if (browseMapMorphHookRegistered) {
        return true;
    }
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
        return false;
    }
    browseMapMorphHookRegistered = true;
    window.Livewire.hook('morphed', () => {
        requestAnimationFrame(() => {
            const r = document.querySelector('[data-browse-events-map]');
            if (!r || r.dataset.leafletBrowseMapInit !== '1') {
                return;
            }
            const m = r._leafletBrowseMap;
            const lyr = r._browseMarkersLayer;
            if (m && lyr) {
                loadMapFeatures(m, lyr, r);
            }
        });
    });

    return true;
}

document.addEventListener('livewire:init', registerBrowseMapMorphRefetch);
document.addEventListener('livewire:initialized', registerBrowseMapMorphRefetch);
registerBrowseMapMorphRefetch();

/**
 * Entry: wait until the map container has a real size.
 */
export function initBrowseEventsMap() {
    const root = document.querySelector('[data-browse-events-map]');
    if (!root) {
        return;
    }

    if (root.dataset.leafletBrowseMapInit === '1') {
        return;
    }

    registerBrowseMapMorphRefetch();

    const kickStart = () => {
        let attempts = 0;
        const kick = () => {
            if (tryStartBrowseEventsMap(root) || attempts++ > 120) {
                return;
            }
            requestAnimationFrame(kick);
        };
        kick();
    };

    if (!tryStartBrowseEventsMap(root)) {
        requestAnimationFrame(() => {
            if (!tryStartBrowseEventsMap(root)) {
                requestAnimationFrame(() => tryStartBrowseEventsMap(root));
            }
        });
    }

    if (root.dataset.browseEventsMapVisibleListener !== '1') {
        root.dataset.browseEventsMapVisibleListener = '1';
        window.addEventListener('browse-events-map:visible', kickStart);
    }
}
