import L from './leaflet-setup.js';
import { addThemedBasemapToMap } from './themed-basemap.js';
import './browse-events-map.css';

const DEBOUNCE_MS = 320;
const DEFAULT_CENTER = [52.0, 19.0];
const DEFAULT_ZOOM = 5;
const BBOX_ZOOM = 6;

/**
 * Push bbox hidden field values into the wrapping Livewire component (browse events).
 */
function bboxValuesFromRoot(root) {
    const minLatEl = document.getElementById('bbox_min_lat');
    const maxLatEl = document.getElementById('bbox_max_lat');
    const minLngEl = document.getElementById('bbox_min_lng');
    const maxLngEl = document.getElementById('bbox_max_lng');

    if (minLatEl && maxLatEl && minLngEl && maxLngEl) {
        return {
            min_lat: minLatEl.value,
            max_lat: maxLatEl.value,
            min_lng: minLngEl.value,
            max_lng: maxLngEl.value,
        };
    }

    if (!root?.dataset) {
        return null;
    }

    return {
        min_lat: root.dataset.bboxMinLat ?? '',
        max_lat: root.dataset.bboxMaxLat ?? '',
        min_lng: root.dataset.bboxMinLng ?? '',
        max_lng: root.dataset.bboxMaxLng ?? '',
    };
}

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

    const values = bboxValuesFromRoot(root);
    if (!values) {
        return;
    }

    const toNull = (v) => (v === '' || v === undefined || v === null ? null : v);

    setProp('min_lat', toNull(values.min_lat));
    setProp('max_lat', toNull(values.max_lat));
    setProp('min_lng', toNull(values.min_lng));
    setProp('max_lng', toNull(values.max_lng));
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

function parseBBoxValues(minLatRaw, maxLatRaw, minLngRaw, maxLngRaw) {
    const hasBbox =
        minLatRaw !== '' &&
        maxLatRaw !== '' &&
        minLngRaw !== '' &&
        maxLngRaw !== '' &&
        minLatRaw !== undefined &&
        maxLatRaw !== undefined &&
        minLngRaw !== undefined &&
        maxLngRaw !== undefined;
    if (!hasBbox) {
        return null;
    }

    const swLat = parseFloat(String(minLatRaw));
    const neLat = parseFloat(String(maxLatRaw));
    const swLng = parseFloat(String(minLngRaw));
    const neLng = parseFloat(String(maxLngRaw));
    if (Number.isNaN(swLat) || Number.isNaN(neLat) || Number.isNaN(swLng) || Number.isNaN(neLng)) {
        return null;
    }

    return {
        south: Math.min(swLat, neLat),
        north: Math.max(swLat, neLat),
        west: Math.min(swLng, neLng),
        east: Math.max(swLng, neLng),
    };
}

function readBBoxFromInputs(root) {
    const values = bboxValuesFromRoot(root);
    if (!values) {
        return null;
    }

    return parseBBoxValues(values.min_lat, values.max_lat, values.min_lng, values.max_lng);
}

function applyBoundsToInputs(bounds, root) {
    const south = bounds.getSouth();
    const north = bounds.getNorth();
    const west = bounds.getWest();
    const east = bounds.getEast();
    if (!Number.isFinite(south) || !Number.isFinite(north) || !Number.isFinite(west) || !Number.isFinite(east)) {
        return;
    }

    const minLat = south.toFixed(5);
    const maxLat = north.toFixed(5);
    const minLng = west.toFixed(5);
    const maxLng = east.toFixed(5);

    const mMinLat = document.getElementById('bbox_min_lat');
    const mMaxLat = document.getElementById('bbox_max_lat');
    const mMinLng = document.getElementById('bbox_min_lng');
    const mMaxLng = document.getElementById('bbox_max_lng');
    if (mMinLat && mMaxLat && mMinLng && mMaxLng) {
        mMinLat.value = minLat;
        mMaxLat.value = maxLat;
        mMinLng.value = minLng;
        mMaxLng.value = maxLng;
    }

    if (root?.dataset) {
        root.dataset.bboxMinLat = minLat;
        root.dataset.bboxMaxLat = maxLat;
        root.dataset.bboxMinLng = minLng;
        root.dataset.bboxMaxLng = maxLng;
    }

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

    if (root?.dataset) {
        root.dataset.bboxMinLat = '';
        root.dataset.bboxMaxLat = '';
        root.dataset.bboxMinLng = '';
        root.dataset.bboxMaxLng = '';
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

function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Minimal escaping for use inside an HTML attribute (e.g. href).
 */
function escapeHtmlAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function listingPinDivIcon(kind) {
    const variant = kind === 'activity' ? 'activity' : 'event';

    const pinSvg = `<svg class="browse-map-listing-pin__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 36" width="28" height="36" aria-hidden="true"><path fill="currentColor" d="M14 0C8.48 0 4 4.35 4 9.72c0 7.28 10 18.28 10 26.28 0-8 10-19 10-26.28C24 4.35 19.52 0 14 0Zm0 13.2a4.2 4.2 0 1 1 0-8.4 4.2 4.2 0 0 1 0 8.4Z"/></svg>`;

    return L.divIcon({
        className: `browse-map-listing-pin-root browse-map-listing-pin-root--${variant}`,
        html: `<div class="browse-map-listing-pin browse-map-listing-pin--${variant}" role="presentation">${pinSvg}</div>`,
        iconSize: [28, 36],
        iconAnchor: [14, 36],
        popupAnchor: [0, -32],
    });
}

/**
 * @param {Record<string, unknown>} props
 * @param {string} detailsLabel
 */
function buildListingPopupHtml(props, detailsLabel) {
    const title = escapeHtml(props.name ? String(props.name) : '');
    const urlRaw = props.url ? String(props.url).trim() : '';
    const label = escapeHtml(detailsLabel);

    const actions =
        urlRaw !== ''
            ? `<a href="${escapeHtmlAttr(urlRaw)}" class="browse-map-popup-details">${label}</a>`
            : `<span class="browse-map-popup-details browse-map-popup-details--muted">${label}</span>`;

    return `<div class="browse-map-popup-inner"><div class="browse-map-popup-title">${title}</div><div class="browse-map-popup-actions">${actions}</div></div>`;
}

function countrySummaryDivIcon(iso, count) {
    const code = escapeHtml(iso || '?');
    const n = escapeHtml(String(count));

    return L.divIcon({
        className: 'browse-map-country-icon',
        html: `<div class="browse-map-country-inner"><span class="browse-map-country-code">${code}</span><span class="browse-map-country-count">${n}</span></div>`,
        iconSize: [52, 44],
        iconAnchor: [26, 22],
    });
}

function renderFeatures(map, layer, data, root) {
    layer.clearLayers();
    if (!data || !Array.isArray(data.features)) {
        return;
    }
    const listingsTpl = root?.dataset?.mapCountryListings || ':count listings';
    const popupDetailsLabel = root?.dataset?.mapPopupDetails || 'Details';
    for (const f of data.features) {
        if (!f.geometry || f.geometry.type !== 'Point' || !Array.isArray(f.geometry.coordinates)) {
            continue;
        }
        const [lng, lat] = f.geometry.coordinates;
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            continue;
        }
        const props = f.properties || {};
        if (props.countrySummary) {
            const iso = props.country_iso ? String(props.country_iso) : '';
            const cname = props.country_name ? String(props.country_name) : iso;
            const n = Number(props.count) || 0;
            const m = L.marker([lat, lng], { icon: countrySummaryDivIcon(iso, n) });
            m.on('click', () => {
                map.setView([lat, lng], Math.min(map.getZoom() + 2, 18), { animate: true });
            });
            m.bindPopup(
                `<div class="text-sm font-medium">${escapeHtml(cname)}</div><div class="text-xs opacity-80">${escapeHtml(listingsTpl.replace(':count', String(n)))}</div>`,
            );
            layer.addLayer(m);
            continue;
        }
        if (props.cluster) {
            const n = Number(props.count) || 0;
            const m = L.marker([lat, lng], { icon: clusterDivIcon(n) });
            m.on('click', () => {
                map.setView([lat, lng], Math.min(map.getZoom() + 2, 18), { animate: true });
            });
            m.bindPopup(`<strong>${n}</strong>`);
            layer.addLayer(m);
        } else {
            const kind = props.kind ? String(props.kind) : 'event';
            const popupHtml = buildListingPopupHtml(props, popupDetailsLabel);
            L.marker([lat, lng], { icon: listingPinDivIcon(kind) })
                .addTo(layer)
                .bindPopup(popupHtml, {
                    className: 'browse-map-listing-popup',
                    maxWidth: 280,
                    autoPanPaddingTopLeft: [16, 16],
                    autoPanPaddingBottomRight: [16, 16],
                });
        }
    }
}

function appendMapViewportToSearchParams(u, map) {
    const b = map.getBounds();
    const south = b.getSouth();
    const north = b.getNorth();
    const west = b.getWest();
    const east = b.getEast();
    if (!Number.isFinite(south) || !Number.isFinite(north) || !Number.isFinite(west) || !Number.isFinite(east)) {
        return;
    }
    u.searchParams.set('min_lat', south.toFixed(5));
    u.searchParams.set('max_lat', north.toFixed(5));
    u.searchParams.set('min_lng', west.toFixed(5));
    u.searchParams.set('max_lng', east.toFixed(5));
}

async function loadMapFeatures(map, layer, root) {
    const baseUrl = root.dataset.mapFeaturesUrl;
    if (!baseUrl) {
        return;
    }
    const u = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams(window.location.search);
    for (const [k, v] of params.entries()) {
        if (k === 'zoom' || k === 'min_lat' || k === 'max_lat' || k === 'min_lng' || k === 'max_lng') {
            continue;
        }
        u.searchParams.set(k, v);
    }
    appendMapViewportToSearchParams(u, map);
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
        renderFeatures(map, layer, json, root);
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
        const bbox = readBBoxFromInputs(root);
        const center = bbox ? [(bbox.south + bbox.north) / 2, (bbox.west + bbox.east) / 2] : DEFAULT_CENTER;
        const zoom = bbox ? BBOX_ZOOM : DEFAULT_ZOOM;

        const map = L.map(root, { scrollWheelZoom: true }).setView(center, zoom);

        invalidateMapSize(map);

        addThemedBasemapToMap(map);

        const markersLayer = L.layerGroup().addTo(map);

        if (bbox) {
            // No padding: bbox values come from prior `getBounds()` sync. Padding would
            // shrink the fit area and force an extra zoom-out on every hide/show cycle.
            map.fitBounds(
                [
                    [bbox.south, bbox.west],
                    [bbox.north, bbox.east],
                ],
                { padding: [0, 0], animate: false },
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

        map.whenReady(() => {
            applyBoundsToInputs(map.getBounds(), root);
            loadMapFeatures(map, markersLayer, root);
        });

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
