import L from './leaflet-setup.js';

/**
 * Push bbox hidden field values into the wrapping Livewire component (browse events).
 */
function syncLivewireBrowseBbox(root) {
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

    const minLatEl = document.getElementById('bbox_min_lat');
    const maxLatEl = document.getElementById('bbox_max_lat');
    const minLngEl = document.getElementById('bbox_min_lng');
    const maxLngEl = document.getElementById('bbox_max_lng');
    if (!minLatEl || !maxLatEl || !minLngEl || !maxLngEl) {
        return;
    }

    const toNull = (v) => (v === '' || v === undefined || v === null ? null : v);

    wire.set('min_lat', toNull(minLatEl.value));
    wire.set('max_lat', toNull(maxLatEl.value));
    wire.set('min_lng', toNull(minLngEl.value));
    wire.set('max_lng', toNull(maxLngEl.value));
}

export async function initBrowseBboxMap() {
    const root = document.querySelector('[data-browse-bbox-map]');
    if (!root) {
        return;
    }

    if (root.dataset.leafletBboxInit) {
        return;
    }
    root.dataset.leafletBboxInit = '1';

    window.L = L;
    await import('leaflet-draw/dist/leaflet.draw.css');
    await import('leaflet-draw/dist/leaflet.draw.js');

    const minLatEl = document.getElementById('bbox_min_lat');
    const maxLatEl = document.getElementById('bbox_max_lat');
    const minLngEl = document.getElementById('bbox_min_lng');
    const maxLngEl = document.getElementById('bbox_max_lng');

    if (!minLatEl || !maxLatEl || !minLngEl || !maxLngEl) {
        return;
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

    const center = hasBbox
        ? [(swLat + neLat) / 2, (swLng + neLng) / 2]
        : [52.0, 19.0];
    const zoom = hasBbox ? 6 : 5;

    const map = L.map(root).setView(center, zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    if (hasBbox) {
        const south = Math.min(swLat, neLat);
        const north = Math.max(swLat, neLat);
        const west = Math.min(swLng, neLng);
        const east = Math.max(swLng, neLng);
        const rect = L.rectangle(
            [
                [south, west],
                [north, east],
            ],
            { color: '#6366f1', weight: 2 },
        );
        drawnItems.addLayer(rect);
        map.fitBounds(rect.getBounds(), { padding: [24, 24] });
    }

    const drawControl = new L.Control.Draw({
        position: 'topright',
        draw: {
            polyline: false,
            polygon: false,
            circle: false,
            marker: false,
            circlemarker: false,
            rectangle: {
                shapeOptions: {
                    color: '#6366f1',
                    weight: 2,
                },
            },
        },
        edit: {
            featureGroup: drawnItems,
        },
    });
    map.addControl(drawControl);

    function applyBounds(layer) {
        const b = layer.getBounds();
        const south = b.getSouth();
        const north = b.getNorth();
        const west = b.getWest();
        const east = b.getEast();
        const mMinLat = document.getElementById('bbox_min_lat');
        const mMaxLat = document.getElementById('bbox_max_lat');
        const mMinLng = document.getElementById('bbox_min_lng');
        const mMaxLng = document.getElementById('bbox_max_lng');
        if (!mMinLat || !mMaxLat || !mMinLng || !mMaxLng) {
            return;
        }
        mMinLat.value = south.toFixed(5);
        mMaxLat.value = north.toFixed(5);
        mMinLng.value = west.toFixed(5);
        mMaxLng.value = east.toFixed(5);
        syncLivewireBrowseBbox(root);
    }

    map.on(L.Draw.Event.CREATED, (e) => {
        drawnItems.clearLayers();
        const layer = e.layer;
        drawnItems.addLayer(layer);
        applyBounds(layer);
    });

    map.on(L.Draw.Event.EDITED, (e) => {
        e.layers.eachLayer((layer) => applyBounds(layer));
    });

    map.on(L.Draw.Event.DELETED, () => {
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
        syncLivewireBrowseBbox(root);
    });
}
