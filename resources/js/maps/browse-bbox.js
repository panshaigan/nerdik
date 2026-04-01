import L from './leaflet-setup.js';

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
        minLatEl.value = south.toFixed(5);
        maxLatEl.value = north.toFixed(5);
        minLngEl.value = west.toFixed(5);
        maxLngEl.value = east.toFixed(5);
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
        minLatEl.value = '';
        maxLatEl.value = '';
        minLngEl.value = '';
        maxLngEl.value = '';
    });
}
