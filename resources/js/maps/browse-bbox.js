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

/**
 * Native click-drag rectangle (Leaflet.draw’s rectangle tool is unreliable on Leaflet 1.9+).
 */
function attachBrowseBboxDraw(map, drawnItems, root, rectStyle) {
    let drawMode = false;
    let dragging = false;
    let startLatLng = null;
    let previewLayer = null;
    let drawButton = null;

    function setDrawMode(on) {
        drawMode = on;
        const el = map.getContainer();
        if (el) {
            el.style.cursor = on ? 'crosshair' : '';
        }
        if (drawButton) {
            drawButton.classList.toggle('browse-bbox-draw-armed', on);
            drawButton.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
    }

    function clearInputsAndSync() {
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
    }

    function applyBounds(layer) {
        const b = layer.getBounds();
        const south = b.getSouth();
        const north = b.getNorth();
        const west = b.getWest();
        const east = b.getEast();
        if (
            !Number.isFinite(south) ||
            !Number.isFinite(north) ||
            !Number.isFinite(west) ||
            !Number.isFinite(east)
        ) {
            return;
        }
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

    function nativeToLatLng(ev) {
        const e =
            ev.touches && ev.touches[0]
                ? ev.touches[0]
                : ev.changedTouches && ev.changedTouches[0]
                  ? ev.changedTouches[0]
                  : ev;
        return map.mouseEventToLatLng(e);
    }

    function finishDrag() {
        dragging = false;
        startLatLng = null;
        map.dragging.enable();
        map.doubleClickZoom.enable();
        setDrawMode(false);
    }

    function onMove(ev) {
        if (!dragging || !previewLayer || !startLatLng) {
            return;
        }
        ev.preventDefault();
        const latlng = nativeToLatLng(ev);
        previewLayer.setBounds(L.latLngBounds(startLatLng, latlng));
    }

    function onUp(ev) {
        if (!dragging) {
            return;
        }
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.removeEventListener('touchmove', onMove, { capture: true });
        document.removeEventListener('touchend', onUp);
        document.removeEventListener('touchcancel', onUp);

        const latlng = nativeToLatLng(ev);
        if (previewLayer && startLatLng) {
            previewLayer.setBounds(L.latLngBounds(startLatLng, latlng));
            const b = previewLayer.getBounds();
            const h = Math.abs(b.getNorth() - b.getSouth());
            const w = Math.abs(b.getEast() - b.getWest());
            if (h < 1e-6 && w < 1e-6) {
                drawnItems.removeLayer(previewLayer);
                previewLayer = null;
            } else {
                applyBounds(previewLayer);
            }
        }

        previewLayer = null;
        finishDrag();
    }

    function onDown(e) {
        if (!drawMode || dragging) {
            return;
        }
        const t = e.originalEvent?.target;
        if (t && typeof t.closest === 'function' && t.closest('.leaflet-control')) {
            return;
        }
        if (e.originalEvent?.button != null && e.originalEvent.button !== 0) {
            return;
        }
        if (e.originalEvent?.touches && e.originalEvent.touches.length > 1) {
            return;
        }

        const latlng =
            e.latlng != null
                ? e.latlng
                : e.originalEvent?.touches?.[0]
                  ? map.mouseEventToLatLng(e.originalEvent.touches[0])
                  : null;
        if (!latlng) {
            return;
        }

        const ev = e.originalEvent;
        if (ev) {
            L.DomEvent.preventDefault(ev);
            L.DomEvent.stopPropagation(ev);
        }

        dragging = true;
        startLatLng = latlng;
        map.dragging.disable();
        map.doubleClickZoom.disable();

        drawnItems.clearLayers();
        previewLayer = L.rectangle(L.latLngBounds(startLatLng, startLatLng), rectStyle);
        drawnItems.addLayer(previewLayer);

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        document.addEventListener('touchmove', onMove, { capture: true, passive: false });
        document.addEventListener('touchend', onUp);
        document.addEventListener('touchcancel', onUp);
    }

    map.on('mousedown', onDown);
    map.on('touchstart', onDown);

    const DrawToolbar = L.Control.extend({
        options: { position: 'topright' },

        onAdd() {
            const wrap = L.DomUtil.create('div', 'leaflet-bar leaflet-control browse-bbox-toolbar');
            drawButton = L.DomUtil.create('a', 'browse-bbox-draw-btn', wrap);
            drawButton.href = '#';
            drawButton.title = 'Draw rectangle to filter results';
            drawButton.setAttribute('aria-label', drawButton.title);
            drawButton.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="1"/></svg>';

            const clearBtn = L.DomUtil.create('a', 'browse-bbox-clear-btn', wrap);
            clearBtn.href = '#';
            clearBtn.title = 'Clear map area filter';
            clearBtn.setAttribute('aria-label', clearBtn.title);
            clearBtn.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';

            L.DomEvent.on(drawButton, 'click', L.DomEvent.stopPropagation);
            L.DomEvent.on(drawButton, 'click', L.DomEvent.preventDefault);
            L.DomEvent.on(drawButton, 'click', () => setDrawMode(!drawMode));

            L.DomEvent.on(clearBtn, 'click', L.DomEvent.stopPropagation);
            L.DomEvent.on(clearBtn, 'click', L.DomEvent.preventDefault);
            L.DomEvent.on(clearBtn, 'click', () => {
                drawnItems.clearLayers();
                previewLayer = null;
                setDrawMode(false);
                clearInputsAndSync();
            });

            return wrap;
        },
    });

    map.addControl(new DrawToolbar());

    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape' && drawMode && !dragging) {
            setDrawMode(false);
        }
    });
}

function startBrowseBboxMap(root) {
    if (root.dataset.leafletBboxInit === '1') {
        return;
    }
    root.dataset.leafletBboxInit = '1';

    try {
        const minLatEl = document.getElementById('bbox_min_lat');
        const maxLatEl = document.getElementById('bbox_max_lat');
        const minLngEl = document.getElementById('bbox_min_lng');
        const maxLngEl = document.getElementById('bbox_max_lng');

        if (!minLatEl || !maxLatEl || !minLngEl || !maxLngEl) {
            delete root.dataset.leafletBboxInit;
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

        const DEFAULT_CENTER = [52.0, 19.0];
        const BBOX_ZOOM = 6;
        const DEFAULT_ZOOM = 5;
        const center = hasBbox ? [(swLat + neLat) / 2, (swLng + neLng) / 2] : DEFAULT_CENTER;
        const zoom = hasBbox ? BBOX_ZOOM : DEFAULT_ZOOM;

        const map = L.map(root, { scrollWheelZoom: true }).setView(center, zoom);

        invalidateMapSize(map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const rectStyle = {
            color: '#6366f1',
            weight: 3,
            opacity: 0.9,
            fill: true,
            fillColor: '#6366f1',
            fillOpacity: 0.2,
        };

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
                rectStyle,
            );
            drawnItems.addLayer(rect);
            map.fitBounds(rect.getBounds(), { padding: [24, 24] });
            invalidateMapSize(map);
        }

        attachBrowseBboxDraw(map, drawnItems, root, rectStyle);

        const details = root.closest('details');
        if (details) {
            details.addEventListener('toggle', () => {
                if (details.open) {
                    invalidateMapSize(map);
                }
            });
        }

        const ro = new ResizeObserver(() => {
            if (containerIsMeasurable(root)) {
                map.invalidateSize({ animate: false });
            }
        });
        ro.observe(root);

        invalidateMapSize(map);
    } catch (err) {
        delete root.dataset.leafletBboxInit;
        console.error('[browse-bbox]', err);
    }
}

function tryStartBrowseBboxMap(root) {
    if (root.dataset.leafletBboxInit === '1') {
        return true;
    }
    if (!containerIsMeasurable(root)) {
        return false;
    }
    startBrowseBboxMap(root);
    return true;
}

/**
 * Entry: wait until the map container has a real size (open <details> or visible layout).
 */
export function initBrowseBboxMap() {
    const root = document.querySelector('[data-browse-bbox-map]');
    if (!root) {
        return;
    }

    if (root.dataset.leafletBboxInit === '1') {
        return;
    }

    const details = root.closest('details');

    if (details && !details.open) {
        if (root.dataset.browseBboxDeferListener === '1') {
            return;
        }
        root.dataset.browseBboxDeferListener = '1';
        details.addEventListener('toggle', () => {
            if (!details.open) {
                return;
            }
            let attempts = 0;
            const kick = () => {
                if (tryStartBrowseBboxMap(root) || attempts++ > 120) {
                    return;
                }
                requestAnimationFrame(kick);
            };
            kick();
        });
        return;
    }

    const kickStart = () => {
        let attempts = 0;
        const kick = () => {
            if (tryStartBrowseBboxMap(root) || attempts++ > 120) {
                return;
            }
            requestAnimationFrame(kick);
        };
        kick();
    };

    if (!tryStartBrowseBboxMap(root)) {
        requestAnimationFrame(() => {
            if (!tryStartBrowseBboxMap(root)) {
                requestAnimationFrame(() => tryStartBrowseBboxMap(root));
            }
        });
    }

    if (root.dataset.browseBboxPanelOpenListener !== '1') {
        root.dataset.browseBboxPanelOpenListener = '1';
        window.addEventListener('browse-bbox:panel-open', kickStart);
    }
}
