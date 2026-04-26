import axios from 'axios';
import L from './leaflet-setup.js';

function iconPlace(selected) {
    const BASE_ICON_SIZE = 18;
    const BASE_ICON_RADIUS = BASE_ICON_SIZE / 2;
    return L.divIcon({
        className: 'ep-marker',
        html: `<div style="width:${BASE_ICON_SIZE}px;height:${BASE_ICON_SIZE}px;border-radius:50%;background:${selected ? '#16a34a' : '#64748b'};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></div>`,
        iconSize: [BASE_ICON_SIZE, BASE_ICON_SIZE],
        iconAnchor: [BASE_ICON_RADIUS, BASE_ICON_RADIUS],
    });
}

function iconNew(index1Based) {
    const n = String(index1Based);

    return L.divIcon({
        className: 'ep-marker-new',
        html: `<div style="position:relative;width:22px;height:22px;"><div style="width:22px;height:22px;border-radius:50%;background:#ea580c;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></div><span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;line-height:1">${n}</span></div>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;

    return d.innerHTML;
}

function escapeAttr(s) {
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(s);
    }

    return String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function randomId() {
    return crypto.randomUUID?.() || `nv-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/**
 * Sync selected places + draft venues into the nearest Livewire component (event form).
 */
function syncLivewireEventPlacesRoot(root, placeIds, newPlacesPayload) {
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

    wire.set('place_ids', placeIds);
    wire.set('new_places', newPlacesPayload);
}

/**
 * Debounced Livewire sync to avoid re-renders on every map interaction (e.g. activity form).
 *
 * @param {HTMLElement} root
 * @param {number} debounceMs
 * @param {number[]} placeIds
 * @param {object[]} newPlacesPayload
 */
function scheduleLivewireEventPlacesSync(root, debounceMs, placeIds, newPlacesPayload) {
    if (!debounceMs || debounceMs <= 0) {
        syncLivewireEventPlacesRoot(root, placeIds, newPlacesPayload);

        return;
    }

    clearTimeout(root._epLwSyncTimer);
    root._epLwSyncTimer = setTimeout(() => {
        syncLivewireEventPlacesRoot(root, placeIds, newPlacesPayload);
    }, debounceMs);
}

/**
 * @param {HTMLElement} root
 */
export function initEventPlacesUnified(root) {
    if (root.dataset.epInitialized) {
        return;
    }

    const jsonEl = root.querySelector('script[type="application/json"][data-ep-config]');
    let cfg = {};
    try {
        cfg = jsonEl?.textContent ? JSON.parse(jsonEl.textContent) : {};
    } catch {
        cfg = {};
    }
    const places = Array.isArray(cfg.places) ? cfg.places : [];
    const singleSelect = cfg.singleSelect === true || cfg.singleSelect === 1 || cfg.singleSelect === '1';
    const maxNewVenuesParsed = Number.parseInt(String(cfg.maxNewVenues ?? ''), 10);
    const maxNewVenues = Number.isFinite(maxNewVenuesParsed) && maxNewVenuesParsed > 0 ? maxNewVenuesParsed : null;
    const hideSelectedChips = cfg.hideSelectedChips === true || cfg.hideSelectedChips === 1 || cfg.hideSelectedChips === '1';
    const openSuggestionsOnFocus = cfg.openSuggestionsOnFocus === true || cfg.openSuggestionsOnFocus === 1 || cfg.openSuggestionsOnFocus === '1';
    const emptyQuerySuggestions = String(cfg.emptyQuerySuggestions || 'none');
    const limitRemoteToViewport =
        cfg.limitRemoteToViewport === true
        || cfg.limitRemoteToViewport === 1
        || cfg.limitRemoteToViewport === '1';
    const disallowMixSelectedAndNew =
        cfg.disallowMixSelectedAndNew === true
        || cfg.disallowMixSelectedAndNew === 1
        || cfg.disallowMixSelectedAndNew === '1';
    const debounceLivewireMs = Number.parseInt(String(cfg.debounceLivewireMs ?? ''), 10);
    const effectiveDebounceLivewireMs = Number.isFinite(debounceLivewireMs) && debounceLivewireMs > 0 ? debounceLivewireMs : 0;
    const selectedIds = new Set((cfg.initialSelectedIds || []).map(Number));
    const initialList = Array.isArray(cfg.initialNewPlaces) ? cfg.initialNewPlaces : [];
    /** @type {{ id: string, lat: number, lng: number, name: string, address: string, city: string, country: string, cityId: string, countryId: string }[]} */
    let newVenues = initialList
        .filter((r) => r.lat != null && r.lng != null)
        .map((r) => ({
            id: randomId(),
            lat: Number(r.lat),
            lng: Number(r.lng),
            name: r.name || '',
            address: r.address || '',
            city: r.city || '',
            country: r.country || '',
            cityId: r.city_id != null && r.city_id !== '' ? String(r.city_id) : '',
            countryId: r.country_id != null && r.country_id !== '' ? String(r.country_id) : '',
        }));

    // Normalize any incoming state to obey configured constraints.
    if (singleSelect && selectedIds.size > 1) {
        const only = Array.from(selectedIds).slice(-1);
        selectedIds.clear();
        if (only[0] !== undefined) {
            selectedIds.add(Number(only[0]));
        }
    }
    if (maxNewVenues === 1 && newVenues.length > 1) {
        newVenues = newVenues.slice(-1);
    } else if (maxNewVenues !== null && newVenues.length > maxNewVenues) {
        newVenues = newVenues.slice(-maxNewVenues);
    }
    if (disallowMixSelectedAndNew && selectedIds.size > 0 && newVenues.length > 0) {
        // Prefer existing selected place over draft new place when both are present.
        newVenues = [];
    }

    const mapEl = root.querySelector('[data-ep-map]');
    const searchInput = root.querySelector('[data-ep-search]');
    const resultsEl = root.querySelector('[data-ep-results]');
    const chipsEl = root.querySelector('[data-ep-chips]');
    const hiddensEl = root.querySelector('[data-ep-place-ids]');
    const newVenuesWrap = root.querySelector('[data-ep-new-venues-wrap]');
    const newVenuesEl = root.querySelector('[data-ep-new-venues]');
    const headingEl = root.querySelector('[data-ep-new-heading]');

    if (!mapEl || !searchInput || !resultsEl || !chipsEl || !hiddensEl || !newVenuesEl) {
        return;
    }

    if (headingEl && cfg.strings?.newVenuesHeading) {
        headingEl.textContent = cfg.strings.newVenuesHeading;
    }

    function emitEventPlacesChange() {
        if (singleSelect && selectedIds.size > 1) {
            const only = Array.from(selectedIds).slice(-1);
            selectedIds.clear();
            if (only[0] !== undefined) {
                selectedIds.add(Number(only[0]));
            }
        }
        if (maxNewVenues === 1 && newVenues.length > 1) {
            newVenues = newVenues.slice(-1);
        } else if (maxNewVenues !== null && newVenues.length > maxNewVenues) {
            newVenues = newVenues.slice(-maxNewVenues);
        }
        // Cannot mix an existing selection with a new-venue draft. Prefer keeping the draft and
        // clearing the selection (otherwise we would drop the draft and leave the old chip visible).
        if (disallowMixSelectedAndNew && selectedIds.size > 0 && newVenues.length > 0) {
            selectedIds.clear();
            Object.keys(markersById).forEach((markerId) => refreshPlaceMarkerIcon(Number(markerId)));
            syncPlaceHiddensAndChips(true);
        }

        const placeIds = Array.from(selectedIds)
            .map((id) => Number(id))
            .sort((a, b) => a - b);
        const newPlacesPayload = newVenues.map((v) => ({
            name: v.name || '',
            address: v.address || '',
            city: v.city || '',
            country: v.country || '',
            city_id: v.cityId !== '' && v.cityId != null ? parseInt(v.cityId, 10) : null,
            country_id: v.countryId !== '' && v.countryId != null ? parseInt(v.countryId, 10) : null,
            latitude: v.lat,
            longitude: v.lng,
        }));
        scheduleLivewireEventPlacesSync(root, effectiveDebounceLivewireMs, placeIds, newPlacesPayload);
        root.dispatchEvent(new CustomEvent('ep:change', {
            bubbles: true,
            detail: {
                selectedIds: [...placeIds],
                newVenues: newVenues.map((v) => ({ ...v })),
            },
        }));
    }

    const INITIAL_CENTER = [52.1, 19.4];
    const INITIAL_ZOOM = 5;
    const FIT_BOUNDS_PADDING_RATIO = 0.18;
    const MIN_FOCUS_ZOOM = 14;
    const map = L.map(mapEl, { scrollWheelZoom: true }).setView(INITIAL_CENTER, INITIAL_ZOOM);
    map.doubleClickZoom.disable();
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const markersLayer = L.layerGroup().addTo(map);
    const newVenuesLayer = L.layerGroup().addTo(map);
    const markersById = {};
    /** @type {Map<string, L.Marker>} */
    const newMarkers = new Map();

    function refreshPlaceMarkerIcon(id) {
        const m = markersById[id];
        if (m) {
            m.setIcon(iconPlace(selectedIds.has(id)));
        }
    }

    function refreshNewMarkerIcons() {
        newVenues.forEach((v, i) => {
            const m = newMarkers.get(v.id);
            if (m) {
                m.setIcon(iconNew(i + 1));
            }
        });
    }

    function rebuildPlaceMarkers() {
        markersLayer.clearLayers();
        Object.keys(markersById).forEach((k) => delete markersById[k]);

        const withCoords = places.filter((p) => p.lat != null && p.lng != null);
        withCoords.forEach((p) => {
            const id = p.id;
            const m = L.marker([p.lat, p.lng], {
                icon: iconPlace(selectedIds.has(id)),
                title: p.label,
            });
            m.on('click', (e) => {
                if (e.originalEvent) {
                    L.DomEvent.stop(e.originalEvent);
                }
                if (selectedIds.has(id)) {
                    selectedIds.delete(id);
                } else {
                    if (singleSelect) {
                        selectedIds.clear();
                    }
                    if (disallowMixSelectedAndNew && newVenues.length > 0) {
                        newVenues.forEach((v) => {
                            const marker = newMarkers.get(v.id);
                            if (marker) {
                                newVenuesLayer.removeLayer(marker);
                                newMarkers.delete(v.id);
                            }
                        });
                        newVenues = [];
                    }
                    selectedIds.add(id);
                }
                Object.keys(markersById).forEach((markerId) => refreshPlaceMarkerIcon(Number(markerId)));
                if (disallowMixSelectedAndNew) {
                    rebuildNewVenueRows();
                }
                syncPlaceHiddensAndChips();
            });
            m.on('dblclick', (e) => {
                L.DomEvent.stopPropagation(e);
            });
            markersById[id] = m;
            m.addTo(markersLayer);
        });

        fitMapToCurrentData();
    }

    function syncPlaceHiddensAndChips(skipEmit = false) {
        hiddensEl.innerHTML = '';
        selectedIds.forEach((id) => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'place_ids[]';
            inp.value = String(id);
            hiddensEl.appendChild(inp);
        });

        chipsEl.innerHTML = '';
        const selectedLabels = [];
        selectedIds.forEach((id) => {
            const numericId = Number(id);
            const p = places.find((x) => Number(x.id) === numericId);
            const label = p ? p.label : `#${id}`;
            selectedLabels.push(label);
            if (!hideSelectedChips) {
                const chip = document.createElement('span');
                chip.className =
                    'inline-flex items-center gap-1 rounded-full border border-base-300 bg-base-200 px-3 py-1 text-xs text-base-content';
                chip.innerHTML = `${escapeHtml(label)} <button type="button" class="ml-1 opacity-60 hover:opacity-100" data-rm="${id}">×</button>`;
                chip.querySelector('button').addEventListener('click', () => {
                    selectedIds.delete(id);
                    refreshPlaceMarkerIcon(id);
                    syncPlaceHiddensAndChips();
                });
                chipsEl.appendChild(chip);
            }
        });
        if (hideSelectedChips) {
            chipsEl.classList.add('hidden');
        }

        if (newVenues.length > 0) {
            searchInput.value = newVenues[0].name || '';
        } else if (selectedLabels.length > 0) {
            searchInput.value = selectedLabels[0];
        } else {
            searchInput.value = '';
        }
        if (!skipEmit) {
            emitEventPlacesChange();
        }
    }

    async function reverseFillVenue(venueId, lat, lng) {
        const v = newVenues.find((x) => x.id === venueId);
        if (!v) {
            return;
        }
        try {
            const { data } = await axios.get(cfg.reverseUrl, { params: { lat, lng } });
            const cityDisp = data.city_display ?? data.city ?? '';
            const countryDisp = data.country_display ?? data.country ?? '';
            if (cityDisp) {
                v.city = cityDisp;
            }
            if (countryDisp) {
                v.country = countryDisp;
            }
            v.address = data.address_short || v.address || '';
            v.cityId = data.city_id != null && data.city_id !== '' ? String(data.city_id) : '';
            v.countryId = data.country_id != null && data.country_id !== '' ? String(data.country_id) : '';
            const row = newVenuesEl.querySelector(`[data-ep-nv-id="${escapeAttr(venueId)}"]`);
            if (row) {
                const c = row.querySelector('[data-ep-city]');
                const co = row.querySelector('[data-ep-country]');
                if (c) {
                    c.value = v.city;
                }
                if (co) {
                    co.value = v.country;
                }
                const hCid = row.querySelector('[data-ep-city-id]');
                const hCoid = row.querySelector('[data-ep-country-id]');
                if (hCid) {
                    hCid.value = v.cityId;
                }
                if (hCoid) {
                    hCoid.value = v.countryId;
                }
                const hLat = row.querySelector('[data-ep-lat]');
                const hLng = row.querySelector('[data-ep-lng]');
                const hAddress = row.querySelector('[data-ep-address]');
                if (hLat) {
                    hLat.value = String(lat);
                }
                if (hLng) {
                    hLng.value = String(lng);
                }
                if (hAddress) {
                    hAddress.value = v.address;
                }
            }
            emitEventPlacesChange();
        } catch {
            /* optional */
        }
    }

    function focusVenueNameInput(venueId) {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                const row = newVenuesEl.querySelector(`[data-ep-nv-id="${escapeAttr(venueId)}"]`);
                const inp = row?.querySelector('input[name*="[name]"]');
                if (inp) {
                    inp.focus();
                    inp.select();
                }
            });
        });
    }

    function bindMarkerDrag(marker, venueId) {
        marker.on('dragend', (ev) => {
            const ll = ev.target.getLatLng();
            const v = newVenues.find((x) => x.id === venueId);
            if (v) {
                v.lat = ll.lat;
                v.lng = ll.lng;
                const row = newVenuesEl.querySelector(`[data-ep-nv-id="${escapeAttr(venueId)}"]`);
                if (row) {
                    const hLat = row.querySelector('[data-ep-lat]');
                    const hLng = row.querySelector('[data-ep-lng]');
                    if (hLat) {
                        hLat.value = String(ll.lat);
                    }
                    if (hLng) {
                        hLng.value = String(ll.lng);
                    }
                }
                reverseFillVenue(venueId, ll.lat, ll.lng);
            }
        });
    }

    function addNewVenue(
        { lat, lng, name = '', address = '', city = '', country = '', cityId = '', countryId = '' },
        { skipReverse = false } = {},
    ) {
        if (disallowMixSelectedAndNew && selectedIds.size > 0) {
            // Switching to "new place" mode: unselect existing places first.
            selectedIds.clear();
            Object.keys(markersById).forEach((markerId) => refreshPlaceMarkerIcon(Number(markerId)));
            syncPlaceHiddensAndChips();
        }
        if (maxNewVenues === 1 && newVenues.length > 0) {
            newVenues.forEach((v) => {
                const marker = newMarkers.get(v.id);
                if (marker) {
                    newVenuesLayer.removeLayer(marker);
                    newMarkers.delete(v.id);
                }
            });
            newVenues = [];
        }

        const id = randomId();
        const venue = { id, lat, lng, name, address, city, country, cityId: String(cityId), countryId: String(countryId) };
        newVenues.push(venue);

        const idx = newVenues.length;
        const marker = L.marker([lat, lng], { icon: iconNew(idx), draggable: true }).addTo(newVenuesLayer);
        marker.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
        });
        marker.on('dblclick', (e) => {
            L.DomEvent.stopPropagation(e);
        });
        bindMarkerDrag(marker, id);
        newMarkers.set(id, marker);

        rebuildNewVenueRows();
        map.setView([lat, lng], Math.max(map.getZoom(), 14));
        if (!skipReverse) {
            reverseFillVenue(id, lat, lng);
        }
        focusVenueNameInput(id);
    }

    function removeNewVenue(id) {
        newVenues = newVenues.filter((v) => v.id !== id);
        const m = newMarkers.get(id);
        if (m) {
            newVenuesLayer.removeLayer(m);
            newMarkers.delete(id);
        }
        rebuildNewVenueRows();
    }

    function rebuildNewVenueRows() {
        newVenuesEl.innerHTML = '';
        newVenues.forEach((v, i) => {
            const row = document.createElement('div');
            row.className = 'space-y-2 rounded-md border border-base-300 bg-base-100 p-2';
            row.dataset.epNvId = v.id;

            const head = document.createElement('div');
            head.className = 'flex items-center justify-between gap-2';
            const lab = document.createElement('span');
            lab.className = 'text-xs font-medium text-base-content/70';
            lab.textContent = `${cfg.strings?.newVenueNumber || 'Venue'} ${i + 1}`;
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-ghost btn-xs';
            rm.textContent = cfg.strings?.removeVenue || 'Remove';
            rm.addEventListener('click', () => removeNewVenue(v.id));
            head.append(lab, rm);

            const inpName = document.createElement('input');
            inpName.type = 'text';
            inpName.name = `new_places[${i}][name]`;
            inpName.value = v.name;
            inpName.className = 'input input-bordered input-sm w-full border-base-300';
            inpName.placeholder = 'Name';
            inpName.addEventListener('input', () => {
                v.name = inpName.value;
                emitEventPlacesChange();
            });

            const hCity = document.createElement('input');
            hCity.type = 'hidden';
            hCity.name = `new_places[${i}][city]`;
            hCity.value = v.city;
            hCity.dataset.epCity = '1';

            const hCountry = document.createElement('input');
            hCountry.type = 'hidden';
            hCountry.name = `new_places[${i}][country]`;
            hCountry.value = v.country;
            hCountry.dataset.epCountry = '1';

            const hCityId = document.createElement('input');
            hCityId.type = 'hidden';
            hCityId.name = `new_places[${i}][city_id]`;
            hCityId.value = v.cityId;
            hCityId.dataset.epCityId = '1';

            const hCountryId = document.createElement('input');
            hCountryId.type = 'hidden';
            hCountryId.name = `new_places[${i}][country_id]`;
            hCountryId.value = v.countryId;
            hCountryId.dataset.epCountryId = '1';

            const hLat = document.createElement('input');
            hLat.type = 'hidden';
            hLat.name = `new_places[${i}][latitude]`;
            hLat.value = String(v.lat);
            hLat.dataset.epLat = '1';

            const hLng = document.createElement('input');
            hLng.type = 'hidden';
            hLng.name = `new_places[${i}][longitude]`;
            hLng.value = String(v.lng);
            hLng.dataset.epLng = '1';

            const hAddress = document.createElement('input');
            hAddress.type = 'hidden';
            hAddress.name = `new_places[${i}][address]`;
            hAddress.value = v.address || '';
            hAddress.dataset.epAddress = '1';

            row.append(head, inpName, hCity, hCountry, hCityId, hCountryId, hLat, hLng, hAddress);
            newVenuesEl.appendChild(row);
        });

        if (newVenuesWrap) {
            newVenuesWrap.classList.toggle('hidden', newVenues.length === 0);
        }
        refreshNewMarkerIcons();
        emitEventPlacesChange();
    }

    map.on('dblclick', (e) => {
        const { lat, lng } = e.latlng;
        addNewVenue({ lat, lng });
    });
    map.on('moveend zoomend', () => {
        const raw = searchInput.value.trim();
        if (resultsEl.classList.contains('hidden') || raw.length !== 0 || emptyQuerySuggestions !== 'saved_places_only') {
            return;
        }
        runSearch({ forceOpen: true });
    });

    let searchTimer;
    let activeResultIndex = -1;

    function resultButtons() {
        return Array.from(resultsEl.querySelectorAll('button[data-ep-result-item="1"]'));
    }

    function paintActiveResult() {
        const items = resultButtons();
        items.forEach((el, idx) => {
            const active = idx === activeResultIndex;
            el.classList.toggle('bg-base-200', active);
        });
        if (activeResultIndex >= 0 && items[activeResultIndex]) {
            items[activeResultIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(runSearch, 280);
    });
    searchInput.addEventListener('focus', () => {
        runSearch({ fromFocus: true });
    });
    searchInput.addEventListener('click', () => {
        runSearch({ fromFocus: true, forceOpen: true });
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) {
            resultsEl.classList.add('hidden');
            activeResultIndex = -1;
        }
    });

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const items = resultButtons();
            if (!resultsEl.classList.contains('hidden') && items.length > 0) {
                const idx = activeResultIndex >= 0 ? activeResultIndex : 0;
                items[idx]?.click();
            }
            return;
        }

        const items = resultButtons();
        if (resultsEl.classList.contains('hidden') || items.length === 0) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeResultIndex = (activeResultIndex + 1 + items.length) % items.length;
            paintActiveResult();
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeResultIndex = (activeResultIndex - 1 + items.length) % items.length;
            paintActiveResult();
            return;
        }
        if (e.key === 'Escape') {
            resultsEl.classList.add('hidden');
            activeResultIndex = -1;
        }
    });

    function localSavedPlacesForCurrentViewport(limit) {
        const bounds = typeof map.getBounds === 'function' ? map.getBounds() : null;
        if (!bounds || typeof bounds.contains !== 'function') {
            return [];
        }

        return places
            .filter((p) => p.lat != null && p.lng != null && bounds.contains([p.lat, p.lng]))
            .slice(0, limit);
    }

    function isInCurrentViewport(lat, lng) {
        const bounds = typeof map.getBounds === 'function' ? map.getBounds() : null;
        if (!bounds || typeof bounds.contains !== 'function') {
            return true;
        }

        return bounds.contains([lat, lng]);
    }

    async function runSearch({ fromFocus = false, forceOpen = false } = {}) {
        const raw = searchInput.value.trim();
        const qLower = raw.toLowerCase();
        resultsEl.innerHTML = '';
        activeResultIndex = -1;

        const canOpenEmpty = raw.length === 0 && (forceOpen || (fromFocus && openSuggestionsOnFocus));
        const bypassMinQueryLength = forceOpen;
        if (raw.length < 2 && !canOpenEmpty && !bypassMinQueryLength) {
            resultsEl.classList.add('hidden');

            return;
        }

        const LOCAL_SAVED_LIMIT = 10;
        const LOCAL_DRAFT_LIMIT = 8;
        const useFocusEmptySavedOnly = raw.length === 0 && emptyQuerySuggestions === 'saved_places_only' && canOpenEmpty;
        const localSaved = useFocusEmptySavedOnly
            ? (() => {
                const viewportSaved = localSavedPlacesForCurrentViewport(LOCAL_SAVED_LIMIT);
                return viewportSaved.length > 0 ? viewportSaved : places.slice(0, LOCAL_SAVED_LIMIT);
            })()
            : places.filter((p) => p.label.toLowerCase().includes(qLower)).slice(0, LOCAL_SAVED_LIMIT);
        const localDraft = useFocusEmptySavedOnly
            ? []
            : newVenues
            .filter((v) => {
                const n = v.name.trim();
                if (!n) {
                    return false;
                }
                const hay = `${n} ${v.city} ${v.country}`.toLowerCase();

                return hay.includes(qLower);
            })
            .slice(0, LOCAL_DRAFT_LIMIT)
            .map((v) => ({
                draftId: v.id,
                label: v.city.trim() ? `${v.name.trim()} (${v.city.trim()})` : v.name.trim(),
                lat: v.lat,
                lng: v.lng,
            }));
        let remote = [];
        if (!useFocusEmptySavedOnly && raw.length >= 2) {
            try {
                const { data } = await axios.get(cfg.searchUrl, { params: { q: raw } });
                remote = (data.results || []).filter((r) => {
                    if (!limitRemoteToViewport) {
                        return true;
                    }
                    if (r?.lat == null || r?.lon == null) {
                        return false;
                    }

                    return isInCurrentViewport(Number(r.lat), Number(r.lon));
                });
            } catch {
                remote = [];
            }
        }

        resultsEl.classList.remove('hidden');
        const frag = document.createDocumentFragment();

        function section(title) {
            const h = document.createElement('div');
            h.className = 'px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-base-content/50';
            h.textContent = title;
            frag.appendChild(h);
        }

        if (localSaved.length) {
            section(cfg.strings?.yourPlaces || 'Your places');
            localSaved.forEach((p) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.dataset.epResultItem = '1';
                b.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
                b.textContent = p.label;
                b.addEventListener('click', () => {
                    if (singleSelect) {
                        selectedIds.clear();
                    }
                    if (disallowMixSelectedAndNew && newVenues.length > 0) {
                        newVenues.forEach((v) => {
                            const marker = newMarkers.get(v.id);
                            if (marker) {
                                newVenuesLayer.removeLayer(marker);
                                newMarkers.delete(v.id);
                            }
                        });
                        newVenues = [];
                    }
                    if (selectedIds.has(p.id)) {
                        selectedIds.delete(p.id);
                    } else {
                        selectedIds.add(p.id);
                    }
                    Object.keys(markersById).forEach((markerId) => refreshPlaceMarkerIcon(Number(markerId)));
                    if (disallowMixSelectedAndNew) {
                        rebuildNewVenueRows();
                    }
                    if (p.lat != null && p.lng != null) {
                        map.setView([p.lat, p.lng], Math.max(map.getZoom(), MIN_FOCUS_ZOOM));
                    }
                    resultsEl.classList.add('hidden');
                    searchInput.value = '';
                    syncPlaceHiddensAndChips();
                });
                frag.appendChild(b);
            });
        }

        if (localDraft.length) {
            section(cfg.strings?.addedThisForm || 'Added on this form');
            localDraft.forEach((d) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.dataset.epResultItem = '1';
                b.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
                b.textContent = d.label;
                b.addEventListener('click', () => {
                    map.setView([d.lat, d.lng], Math.max(map.getZoom(), MIN_FOCUS_ZOOM));
                    resultsEl.classList.add('hidden');
                    searchInput.value = '';
                    focusVenueNameInput(d.draftId);
                });
                frag.appendChild(b);
            });
        }

        if (remote.length) {
            section(cfg.strings?.mapSearch || 'Map search');
            remote.forEach((r) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.dataset.epResultItem = '1';
                b.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
                b.textContent = r.label;
                b.addEventListener('click', () => {
                    const shortName = r.label.split(',').slice(0, 1).join('').trim();
                    addNewVenue({
                        lat: r.lat,
                        lng: r.lon,
                        name: shortName,
                        address: r.address_short || '',
                        city: r.city_display || r.city || '',
                        country: r.country_display || r.country || '',
                        cityId: r.city_id != null && r.city_id !== '' ? String(r.city_id) : '',
                        countryId: r.country_id != null && r.country_id !== '' ? String(r.country_id) : '',
                    });
                    resultsEl.classList.add('hidden');
                    searchInput.value = '';
                });
                frag.appendChild(b);
            });
        }

        if (!localSaved.length && !localDraft.length && !remote.length) {
            const empty = document.createElement('div');
            empty.className = 'px-3 py-2 text-sm text-base-content/60';
            empty.textContent = cfg.strings?.noResults || 'No results';
            frag.appendChild(empty);
        }

        resultsEl.appendChild(frag);
        paintActiveResult();
    }

    rebuildPlaceMarkers();
    selectedIds.forEach((id) => refreshPlaceMarkerIcon(id));
    syncPlaceHiddensAndChips();

    newVenues.forEach((v, i) => {
        const marker = L.marker([v.lat, v.lng], { icon: iconNew(i + 1), draggable: true }).addTo(newVenuesLayer);
        marker.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
        });
        marker.on('dblclick', (e) => {
            L.DomEvent.stopPropagation(e);
        });
        bindMarkerDrag(marker, v.id);
        newMarkers.set(v.id, marker);
    });
    rebuildNewVenueRows();

    function scheduleInvalidateSize() {
        requestAnimationFrame(() => map.invalidateSize());
        [50, 200, 600].forEach((ms) => {
            setTimeout(() => map.invalidateSize(), ms);
        });
    }

    function fitToLayers(layers) {
        if (layers.length === 0) {
            return false;
        }
        const group = L.featureGroup(layers);
        const bounds = group.getBounds();
        if (!bounds || !bounds.isValid()) {
            return false;
        }
        map.fitBounds(bounds.pad(FIT_BOUNDS_PADDING_RATIO));
        if (layers.length === 1) {
            map.setZoom(Math.max(map.getZoom(), MIN_FOCUS_ZOOM));
        }

        return true;
    }

    function fitMapToCurrentData() {
        // 1) If self-hosted selected venue exists, focus only selected marker(s).
        const selectedLayers = [];
        selectedIds.forEach((id) => {
            const m = markersById[id];
            if (m) {
                selectedLayers.push(m);
            }
        });
        if (fitToLayers(selectedLayers)) {
            return;
        }

        // 2) If no selected place, show all pins (saved + new).
        const allLayers = [];
        Object.values(markersById).forEach((m) => {
            if (m) {
                allLayers.push(m);
            }
        });
        newMarkers.forEach((m) => {
            if (m) {
                allLayers.push(m);
            }
        });
        if (fitToLayers(allLayers)) {
            return;
        }

        // 3) No pins at all: default to Poland.
        map.setView(INITIAL_CENTER, INITIAL_ZOOM);
    }

    function mapHasRenderableSize() {
        const rect = mapEl.getBoundingClientRect();
        return rect.width > 20 && rect.height > 20;
    }

    function scheduleInvalidateWhenVisible() {
        if (!mapHasRenderableSize()) {
            return;
        }
        scheduleInvalidateSize();
        // When map was initialized while hidden, invalidate alone can keep wrong/world view.
        [0, 120, 320].forEach((ms) => {
            setTimeout(() => {
                fitMapToCurrentData();
            }, ms);
        });
    }

    // Expose for tabbed forms that reveal the map after it was initialized hidden.
    root._epScheduleInvalidate = scheduleInvalidateSize;

    scheduleInvalidateSize();
    window.addEventListener('resize', () => map.invalidateSize());

    // Tabs use x-show (display: none/block); observers ensure map reflows when becoming visible.
    if (typeof ResizeObserver !== 'undefined') {
        const ro = new ResizeObserver(() => {
            scheduleInvalidateWhenVisible();
        });
        ro.observe(mapEl);
        if (root.parentElement) {
            ro.observe(root.parentElement);
        }
        root._epResizeObserver = ro;
    }

    if (typeof MutationObserver !== 'undefined') {
        const mo = new MutationObserver(() => {
            scheduleInvalidateWhenVisible();
        });
        let node = root;
        for (let i = 0; i < 4 && node; i += 1) {
            mo.observe(node, { attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
            node = node.parentElement;
        }
        root._epMutationObserver = mo;
    }

    // Extra guard for delayed tab transitions.
    [0, 120, 320, 700, 1200].forEach((ms) => {
        setTimeout(scheduleInvalidateWhenVisible, ms);
    });

    root.dataset.epInitialized = '1';
}
