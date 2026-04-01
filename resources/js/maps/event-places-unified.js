import axios from 'axios';
import L from './leaflet-setup.js';

function iconPlace(selected) {
    return L.divIcon({
        className: 'ep-marker',
        html: `<div style="width:18px;height:18px;border-radius:50%;background:${selected ? '#16a34a' : '#64748b'};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></div>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
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
    const selectedIds = new Set((cfg.initialSelectedIds || []).map(Number));
    const initialList = Array.isArray(cfg.initialNewPlaces) ? cfg.initialNewPlaces : [];
    /** @type {{ id: string, lat: number, lng: number, name: string, city: string, country: string, cityId: string, countryId: string }[]} */
    let newVenues = initialList
        .filter((r) => r.lat != null && r.lng != null)
        .map((r) => ({
            id: randomId(),
            lat: Number(r.lat),
            lng: Number(r.lng),
            name: r.name || '',
            city: r.city || '',
            country: r.country || '',
            cityId: r.city_id != null && r.city_id !== '' ? String(r.city_id) : '',
            countryId: r.country_id != null && r.country_id !== '' ? String(r.country_id) : '',
        }));

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

    const map = L.map(mapEl, { scrollWheelZoom: true }).setView([52.1, 19.4], 5);
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
                    selectedIds.add(id);
                }
                refreshPlaceMarkerIcon(id);
                syncPlaceHiddensAndChips();
            });
            m.on('dblclick', (e) => {
                L.DomEvent.stopPropagation(e);
            });
            markersById[id] = m;
            m.addTo(markersLayer);
        });

        if (withCoords.length > 0) {
            const group = L.featureGroup(Object.values(markersById));
            map.fitBounds(group.getBounds().pad(0.18));
        }
    }

    function syncPlaceHiddensAndChips() {
        hiddensEl.innerHTML = '';
        selectedIds.forEach((id) => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'place_ids[]';
            inp.value = String(id);
            hiddensEl.appendChild(inp);
        });

        chipsEl.innerHTML = '';
        selectedIds.forEach((id) => {
            const p = places.find((x) => x.id === id);
            const label = p ? p.label : `#${id}`;
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
        });
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
                if (hLat) {
                    hLat.value = String(lat);
                }
                if (hLng) {
                    hLng.value = String(lng);
                }
            }
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
        { lat, lng, name = '', city = '', country = '', cityId = '', countryId = '' },
        { skipReverse = false } = {},
    ) {
        const id = randomId();
        const venue = { id, lat, lng, name, city, country, cityId: String(cityId), countryId: String(countryId) };
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
            });

            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-1 gap-2 sm:grid-cols-2';

            const inpCity = document.createElement('input');
            inpCity.type = 'text';
            inpCity.name = `new_places[${i}][city]`;
            inpCity.value = v.city;
            inpCity.dataset.epCity = '1';
            inpCity.className = 'input input-bordered input-sm w-full border-base-300';
            inpCity.placeholder = 'City';
            inpCity.addEventListener('input', () => {
                v.city = inpCity.value;
                v.cityId = '';
                const hc = row.querySelector('[data-ep-city-id]');
                if (hc) {
                    hc.value = '';
                }
            });

            const inpCountry = document.createElement('input');
            inpCountry.type = 'text';
            inpCountry.name = `new_places[${i}][country]`;
            inpCountry.value = v.country;
            inpCountry.dataset.epCountry = '1';
            inpCountry.className = 'input input-bordered input-sm w-full border-base-300';
            inpCountry.placeholder = 'Country';
            inpCountry.addEventListener('input', () => {
                v.country = inpCountry.value;
                v.countryId = '';
                const hco = row.querySelector('[data-ep-country-id]');
                if (hco) {
                    hco.value = '';
                }
            });

            grid.append(inpCity, inpCountry);

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

            row.append(head, inpName, grid, hCityId, hCountryId, hLat, hLng);
            newVenuesEl.appendChild(row);
        });

        if (newVenuesWrap) {
            newVenuesWrap.classList.toggle('hidden', newVenues.length === 0);
        }
        refreshNewMarkerIcons();
    }

    map.on('dblclick', (e) => {
        const { lat, lng } = e.latlng;
        addNewVenue({ lat, lng });
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
        if (searchInput.value.trim().length >= 2) {
            runSearch();
        }
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) {
            resultsEl.classList.add('hidden');
            activeResultIndex = -1;
        }
    });

    searchInput.addEventListener('keydown', (e) => {
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
        if (e.key === 'Enter' && activeResultIndex >= 0) {
            e.preventDefault();
            items[activeResultIndex]?.click();
            return;
        }
        if (e.key === 'Escape') {
            resultsEl.classList.add('hidden');
            activeResultIndex = -1;
        }
    });

    async function runSearch() {
        const raw = searchInput.value.trim();
        const qLower = raw.toLowerCase();
        resultsEl.innerHTML = '';
        activeResultIndex = -1;

        if (raw.length < 2) {
            resultsEl.classList.add('hidden');

            return;
        }

        const localSaved = places.filter((p) => p.label.toLowerCase().includes(qLower)).slice(0, 10);
        const localDraft = newVenues
            .filter((v) => {
                const n = v.name.trim();
                if (!n) {
                    return false;
                }
                const hay = `${n} ${v.city} ${v.country}`.toLowerCase();

                return hay.includes(qLower);
            })
            .slice(0, 8)
            .map((v) => ({
                draftId: v.id,
                label: v.city.trim() ? `${v.name.trim()} (${v.city.trim()})` : v.name.trim(),
                lat: v.lat,
                lng: v.lng,
            }));
        let remote = [];
        try {
            const { data } = await axios.get(cfg.searchUrl, { params: { q: raw } });
            remote = data.results || [];
        } catch {
            remote = [];
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
                    if (selectedIds.has(p.id)) {
                        selectedIds.delete(p.id);
                    } else {
                        selectedIds.add(p.id);
                    }
                    refreshPlaceMarkerIcon(p.id);
                    if (p.lat != null && p.lng != null) {
                        map.setView([p.lat, p.lng], Math.max(map.getZoom(), 14));
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
                    map.setView([d.lat, d.lng], Math.max(map.getZoom(), 14));
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

    scheduleInvalidateSize();
    window.addEventListener('resize', () => map.invalidateSize());

    root.dataset.epInitialized = '1';
}
