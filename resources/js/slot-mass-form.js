/**
 * Slot mass-create / edit form (venue, room, activity chips, name suggestions).
 *
 * @param {HTMLFormElement} form
 */
export function initSlotMassForm(form) {
    if (!form || form.dataset.slotMassFormInitialized) {
        return;
    }
    form.dataset.slotMassFormInitialized = '1';

    const massForm = form;

    massForm.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') {
            return;
        }
        const t = e.target;
        if (t.tagName === 'TEXTAREA') {
            return;
        }
        if (t.tagName === 'BUTTON') {
            return;
        }
        if (
            t.tagName === 'INPUT' &&
            (t.type === 'checkbox' || t.type === 'radio' || t.type === 'submit' || t.type === 'button')
        ) {
            return;
        }
        if (t.hasAttribute('data-ts-input')) {
            return;
        }
        if (t.hasAttribute('data-slot-base-name-input') || t.hasAttribute('data-slot-name-input')) {
            return;
        }
        if (t.hasAttribute('data-slot-activity-add')) {
            return;
        }
        if (t.hasAttribute('data-slot-room-input')) {
            return;
        }
        if (t.tagName === 'SELECT' && t.multiple) {
            return;
        }
        if (t.tagName === 'INPUT' || t.tagName === 'SELECT') {
            e.preventDefault();
        }
    });

    const configScript = massForm.querySelector('script[data-slot-mass-config]');
    let config = {};
    try {
        config = JSON.parse(configScript?.textContent || '{}');
    } catch {
        config = {};
    }

    const oldVenue = config.oldVenuePlaceId ?? null;
    const isEdit = Boolean(config.isEdit);
    const eventDatetimeBounds = config.eventDatetimeBounds && typeof config.eventDatetimeBounds === 'object'
        ? config.eventDatetimeBounds
        : {};

    const baseNameSuggestionsJson = massForm.querySelector('[data-slot-base-name-suggestions-json]');
    const nameSuggestionsJson = massForm.querySelector('[data-slot-name-suggestions-json]');

    if (isEdit) {
        const nameInput = massForm.querySelector('[data-slot-name-input]');
        const namePopup = massForm.querySelector('[data-slot-name-popup]');
        if (nameInput && namePopup && nameSuggestionsJson) {
            wireNameSuggestions(nameInput, namePopup, nameSuggestionsJson.textContent || '[]', massForm);
        }
    } else {
        const nameInput = massForm.querySelector('[data-slot-base-name-input]');
        const namePopup = massForm.querySelector('[data-slot-base-name-popup]');
        if (nameInput && namePopup && baseNameSuggestionsJson) {
            wireNameSuggestions(nameInput, namePopup, baseNameSuggestionsJson.textContent || '[]', massForm);
        }
    }

    const activityRoot = massForm.querySelector('[data-slot-activity-types-root]');
    if (activityRoot) {
        const addSelect = activityRoot.querySelector('[data-slot-activity-add]');
        const chips = activityRoot.querySelector('[data-slot-activity-chips]');
        const hiddenWrap = activityRoot.querySelector('[data-slot-activity-hidden]');
        const initial = Array.isArray(config.initialActivityTypes)
            ? config.initialActivityTypes.map((x) => Number.parseInt(String(x), 10)).filter((x) => Number.isInteger(x) && x > 0)
            : [];
        const activityTypeLabels = config.activityTypeLabels && typeof config.activityTypeLabels === 'object'
            ? config.activityTypeLabels
            : {};
        const allowedActivityTypeIds = new Set(
            Array.isArray(config.allowedActivityTypeIds)
                ? config.allowedActivityTypeIds
                      .map((x) => Number.parseInt(String(x), 10))
                      .filter((x) => Number.isInteger(x) && x > 0)
                : [],
        );

        const selected = new Set(initial);

        function renderChips() {
            chips.innerHTML = '';
            hiddenWrap.innerHTML = '';
            [...selected].sort((a, b) => a - b).forEach((typeId) => {
                const chip = document.createElement('span');
                chip.className = 'badge badge-outline gap-1';
                const label = document.createElement('span');
                label.className = 'text-sm';
                label.textContent = activityTypeLabels[String(typeId)] || `#${typeId}`;
                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'btn btn-ghost btn-xs px-0';
                rm.setAttribute('aria-label', 'remove');
                rm.textContent = '×';
                rm.addEventListener('click', () => {
                    selected.delete(typeId);
                    renderChips();
                });
                chip.appendChild(label);
                chip.appendChild(rm);
                chips.appendChild(chip);

                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'activity_types[]';
                inp.value = String(typeId);
                hiddenWrap.appendChild(inp);
            });
        }

        addSelect?.addEventListener('change', () => {
            const v = Number.parseInt(addSelect.value, 10);
            if (!Number.isInteger(v) || v <= 0) {
                return;
            }
            if (allowedActivityTypeIds.size > 0 && !allowedActivityTypeIds.has(v)) {
                return;
            }
            selected.add(v);
            addSelect.value = '';
            renderChips();
        });

        renderChips();
    }

    const eventVenuesJson = massForm.querySelector('[data-slot-mass-event-venues]');
    const eventRoomsJson = massForm.querySelector('[data-slot-mass-rooms]');
    const roomsLockedJson = massForm.querySelector('[data-slot-mass-rooms-locked]');
    let eventVenuesById = {};
    let roomsByEventAndVenue = {};
    let roomsLockedByVenue = {};
    try {
        eventVenuesById = JSON.parse(eventVenuesJson?.textContent || '{}');
    } catch {
        eventVenuesById = {};
    }
    try {
        roomsByEventAndVenue = JSON.parse(eventRoomsJson?.textContent || '{}');
    } catch {
        roomsByEventAndVenue = {};
    }
    try {
        roomsLockedByVenue = JSON.parse(roomsLockedJson?.textContent || '{}');
    } catch {
        roomsLockedByVenue = {};
    }

    const eventSelect = massForm.querySelector('[data-slot-mass-event-select]');
    const venueSelect = massForm.querySelector('[data-slot-venue-select]');
    const roomInput = massForm.querySelector('[data-slot-room-input]');
    const roomPopup = massForm.querySelector('[data-slot-room-popup]');
    const startsAtInput = massForm.querySelector('input[name="starts_at"]');
    const endsAtInput = massForm.querySelector('input[name="ends_at"]');

    const noneLabel = config.strings?.none ?? '—';

    /** @type {{ id: number, name: string }[]} */
    let roomList = [];

    function getRoomsList(eid, vid) {
        if (!vid) {
            return [];
        }
        const vidStr = String(vid);
        const eidStr = eid ? String(eid) : '';
        const byEv = eidStr ? roomsByEventAndVenue[eidStr] || {} : {};
        if (eidStr && byEv[vidStr]) {
            return byEv[vidStr];
        }
        if (!eidStr && roomsLockedByVenue[vidStr]) {
            return roomsLockedByVenue[vidStr];
        }

        return [];
    }

    function getCurrentVenueId() {
        if (venueSelect) {
            const v = venueSelect.value;
            if (v) {
                return v;
            }
            const hid = massForm.querySelector('[data-slot-venue-hidden]');
            if (hid?.value) {
                return hid.value;
            }

            return '';
        }
        const hiddenVenue = massForm.querySelector('[data-slot-venue-id]');

        return hiddenVenue?.value || '';
    }

    function refreshRoomList() {
        const eid = eventSelect ? String(eventSelect.value) : '';
        const vid = getCurrentVenueId();
        roomList = getRoomsList(eid, vid);
    }

    function fillVenueOptionsForEvent(eventId) {
        if (!venueSelect) {
            return;
        }
        massForm.querySelector('[data-slot-venue-hidden]')?.remove();
        const list = eventVenuesById[eventId] || [];
        venueSelect.innerHTML = '';
        if (list.length === 0) {
            const opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = noneLabel;
            venueSelect.appendChild(opt0);
        }
        list.forEach((v) => {
            const o = document.createElement('option');
            o.value = String(v.id);
            o.textContent = `${v.name} (${v.type})`;
            if (oldVenue != null && String(oldVenue) === String(v.id)) {
                o.selected = true;
            }
            venueSelect.appendChild(o);
        });
        if (list.length === 1) {
            venueSelect.value = String(list[0].id);
            venueSelect.removeAttribute('name');
            venueSelect.disabled = true;
            const hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = 'venue_place_id';
            hid.value = String(list[0].id);
            hid.setAttribute('data-slot-venue-hidden', '');
            venueSelect.parentNode.insertBefore(hid, venueSelect);
        } else {
            venueSelect.name = 'venue_place_id';
            venueSelect.disabled = false;
            if (list.length > 0) {
                const ids = new Set(list.map((v) => String(v.id)));
                const preferred =
                    oldVenue != null && ids.has(String(oldVenue)) ? String(oldVenue) : String(list[0].id);
                venueSelect.value = preferred;
            }
        }
        refreshRoomList();
    }

    function hideRoomPopupIfAny() {
        if (roomPopup) {
            roomPopup.classList.add('hidden');
            roomPopup.innerHTML = '';
        }
    }

    function parseLocalDateTime(value) {
        if (!value) {
            return null;
        }
        const parsed = new Date(value);

        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatLocalDateTime(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    function getCurrentEventId() {
        if (!eventSelect) {
            const hiddenEventInput = massForm.querySelector('input[name="event_id"]');

            return hiddenEventInput?.value || '';
        }

        return String(eventSelect.value || '');
    }

    function getCurrentEventBounds() {
        const eventId = getCurrentEventId();
        if (!eventId) {
            return null;
        }

        return eventDatetimeBounds[eventId] || eventDatetimeBounds[Number.parseInt(eventId, 10)] || null;
    }

    function applyEventDatetimeLimits() {
        const bounds = getCurrentEventBounds();
        const eventStart = bounds?.starts_at || '';
        const eventEnd = bounds?.ends_at || '';
        if (startsAtInput) {
            startsAtInput.min = eventStart;
            startsAtInput.max = eventEnd;
        }
        if (endsAtInput) {
            endsAtInput.min = eventStart;
            endsAtInput.max = eventEnd;
        }
    }

    function defaultStartsAtIfEmpty() {
        if (!startsAtInput || startsAtInput.value) {
            return;
        }
        const bounds = getCurrentEventBounds();
        if (bounds?.starts_at) {
            startsAtInput.value = bounds.starts_at;
        }
    }

    function defaultEndsAtIfEmpty() {
        if (!endsAtInput || endsAtInput.value) {
            return;
        }

        const bounds = getCurrentEventBounds();
        const baseDate = parseLocalDateTime(startsAtInput?.value || '') || parseLocalDateTime(bounds?.starts_at || '');
        if (!baseDate) {
            return;
        }

        const defaultEnd = new Date(baseDate.getTime() + (4 * 60 * 60 * 1000));
        let finalEnd = defaultEnd;
        const eventEndDate = parseLocalDateTime(bounds?.ends_at || '');
        if (eventEndDate && finalEnd > eventEndDate) {
            finalEnd = eventEndDate;
        }
        endsAtInput.value = formatLocalDateTime(finalEnd);
    }

    venueSelect?.addEventListener('change', () => {
        if (roomInput) {
            roomInput.value = '';
        }
        refreshRoomList();
        hideRoomPopupIfAny();
    });

    eventSelect?.addEventListener('change', () => {
        if (roomInput) {
            roomInput.value = '';
        }
        fillVenueOptionsForEvent(String(eventSelect.value));
        applyEventDatetimeLimits();
        hideRoomPopupIfAny();
    });

    if (eventSelect && venueSelect) {
        fillVenueOptionsForEvent(String(eventSelect.value));
    } else if (venueSelect && venueSelect.value) {
        refreshRoomList();
    } else {
        const hiddenVenue = massForm.querySelector('[data-slot-venue-id]');
        if (hiddenVenue?.value) {
            refreshRoomList();
        }
    }

    if (roomInput && roomPopup) {
        wireRoomAutocomplete(roomInput, roomPopup, massForm, () => roomList);
    }

    applyEventDatetimeLimits();
    startsAtInput?.addEventListener('focus', defaultStartsAtIfEmpty);
    startsAtInput?.addEventListener('click', defaultStartsAtIfEmpty);
    endsAtInput?.addEventListener('focus', defaultEndsAtIfEmpty);
    endsAtInput?.addEventListener('click', defaultEndsAtIfEmpty);
}

/**
 * Suggest rooms that are children of the selected venue; unknown text is submitted as new_room_name.
 *
 * @param {HTMLInputElement} roomInput
 * @param {HTMLElement} roomPopup
 * @param {HTMLFormElement} massForm
 * @param {() => { id: number, name: string }[]} getRooms
 */
function wireRoomAutocomplete(roomInput, roomPopup, massForm, getRooms) {
    /** @type {{ id: number, name: string }[]} */
    const MAX_RESULTS = 8;
    const POPUP_MARGIN_PX = 8;
    const POPUP_OFFSET_Y_PX = 4;
    let shown = [];
    let active = -1;

    /**
     * Popups on document.body render behind open <dialog> (top layer). Keep the list inside the dialog.
     */
    function ensurePopupInDialogLayer() {
        const dialog = massForm.closest('dialog');
        if (dialog) {
            if (roomPopup.parentElement !== dialog) {
                dialog.appendChild(roomPopup);
            }

            return;
        }
        if (roomPopup.parentElement !== document.body) {
            document.body.appendChild(roomPopup);
        }
    }

    function isEventOnRoomField(e) {
        if (e.target === roomInput || roomInput.contains(e.target)) {
            return true;
        }
        const labelWrap = roomInput.closest('label');
        if (labelWrap && e.target instanceof Node && labelWrap.contains(e.target)) {
            return true;
        }
        const roomBlock = massForm.querySelector('[data-slot-room-block]');
        if (roomBlock && e.target instanceof Node && roomBlock.contains(e.target)) {
            return true;
        }

        return false;
    }

    function positionRoomPopup() {
        if (roomPopup.classList.contains('hidden')) {
            return;
        }
        const r = roomInput.getBoundingClientRect();
        const vw = window.innerWidth;
        let left = r.left;
        let width = r.width;
        if (left + width > vw - POPUP_MARGIN_PX) {
            left = Math.max(POPUP_MARGIN_PX, vw - POPUP_MARGIN_PX - width);
        }
        left = Math.max(POPUP_MARGIN_PX, left);
        roomPopup.style.left = `${left}px`;
        roomPopup.style.top = `${r.bottom + POPUP_OFFSET_Y_PX}px`;
        roomPopup.style.width = `${width}px`;
    }

    function closeRoomPopup() {
        roomPopup.classList.add('hidden');
        roomPopup.innerHTML = '';
        active = -1;
        roomInput.setAttribute('aria-expanded', 'false');
    }

    function openRoomPopup() {
        if (shown.length === 0) {
            closeRoomPopup();

            return;
        }
        ensurePopupInDialogLayer();
        roomPopup.classList.remove('hidden');
        roomInput.setAttribute('aria-expanded', 'true');
        positionRoomPopup();
        requestAnimationFrame(() => positionRoomPopup());
    }

    function applyActive() {
        [...roomPopup.querySelectorAll('[data-suggestion-idx]')].forEach((el, idx) => {
            const isActive = idx === active;
            el.classList.toggle('bg-base-200', isActive);
            el.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function choose(name) {
        roomInput.value = name;
        closeRoomPopup();
    }

    function render(items) {
        shown = items.slice(0, MAX_RESULTS);
        roomPopup.innerHTML = '';
        active = -1;

        if (shown.length === 0) {
            closeRoomPopup();

            return;
        }

        const frag = document.createDocumentFragment();
        shown.forEach((r, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'block w-full cursor-pointer px-3 py-2 text-left text-sm hover:bg-base-200';
            btn.textContent = r.name;
            btn.dataset.suggestionIdx = String(idx);
            btn.setAttribute('role', 'option');
            btn.setAttribute('aria-selected', 'false');
            btn.addEventListener('mousedown', (e) => e.preventDefault());
            btn.addEventListener('click', () => choose(r.name));
            frag.appendChild(btn);
        });
        roomPopup.appendChild(frag);
        openRoomPopup();
    }

    function updateFromInput() {
        const q = roomInput.value.trim().toLowerCase();
        const rooms = getRooms();
        let items;
        if (q.length < 1) {
            items = rooms.slice(0, MAX_RESULTS);
        } else {
            items = rooms.filter(
                (r) => r.name.toLowerCase().includes(q) && r.name.toLowerCase() !== q
            );
        }
        render(items);
    }

    const reposition = () => positionRoomPopup();
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);

    roomInput.addEventListener('input', updateFromInput);
    roomInput.addEventListener('focus', updateFromInput);
    roomInput.addEventListener('keydown', (e) => {
        if (roomPopup.classList.contains('hidden') || shown.length === 0) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            active = (active + 1) % shown.length;
            applyActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            active = active <= 0 ? shown.length - 1 : active - 1;
            applyActive();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (active >= 0 && active < shown.length) {
                choose(shown[active].name);
            }
        } else if (e.key === 'Escape') {
            closeRoomPopup();
        }
    });

    document.addEventListener('click', (e) => {
        if (roomPopup.classList.contains('hidden')) {
            return;
        }
        if (isEventOnRoomField(e)) {
            return;
        }
        if (roomPopup.contains(e.target)) {
            return;
        }
        closeRoomPopup();
    });
}

/**
 * @param {HTMLInputElement} nameInput
 * @param {HTMLElement} namePopup
 * @param {string} suggestionsJson
 * @param {HTMLFormElement} massForm
 */
function wireNameSuggestions(nameInput, namePopup, suggestionsJson, massForm) {
    const MAX_SUGGESTIONS = 8;
    let suggestions = [];
    try {
        suggestions = JSON.parse(suggestionsJson || '[]');
    } catch {
        suggestions = [];
    }
    let shown = [];
    let active = -1;

    function closeNamePopup() {
        namePopup.classList.add('hidden');
        namePopup.innerHTML = '';
        active = -1;
        nameInput.setAttribute('aria-expanded', 'false');
    }

    function openNamePopup() {
        if (shown.length === 0) {
            closeNamePopup();
            return;
        }
        namePopup.classList.remove('hidden');
        nameInput.setAttribute('aria-expanded', 'true');
    }

    function applyActive() {
        [...namePopup.querySelectorAll('[data-suggestion-idx]')].forEach((el, idx) => {
            const isActive = idx === active;
            el.classList.toggle('bg-base-200', isActive);
            el.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function choose(value) {
        nameInput.value = value;
        closeNamePopup();
    }

    function render(items) {
        shown = items.slice(0, MAX_SUGGESTIONS);
        namePopup.innerHTML = '';
        active = -1;

        if (shown.length === 0) {
            closeNamePopup();
            return;
        }

        const frag = document.createDocumentFragment();
        shown.forEach((name, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'block w-full cursor-pointer px-3 py-2 text-left text-sm hover:bg-base-200';
            btn.textContent = name;
            btn.dataset.suggestionIdx = String(idx);
            btn.setAttribute('role', 'option');
            btn.setAttribute('aria-selected', 'false');
            btn.addEventListener('mousedown', (e) => e.preventDefault());
            btn.addEventListener('click', () => choose(name));
            frag.appendChild(btn);
        });
        namePopup.appendChild(frag);
        openNamePopup();
    }

    function updateFromInput() {
        const q = nameInput.value.trim().toLowerCase();
        if (q.length < 1) {
            closeNamePopup();
            return;
        }

        const items = suggestions.filter((s) => s.toLowerCase().includes(q) && s.toLowerCase() !== q);
        render(items);
    }

    nameInput.addEventListener('input', updateFromInput);
    nameInput.addEventListener('focus', updateFromInput);
    nameInput.addEventListener('keydown', (e) => {
        if (namePopup.classList.contains('hidden') || shown.length === 0) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            active = (active + 1) % shown.length;
            applyActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            active = active <= 0 ? shown.length - 1 : active - 1;
            applyActive();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (active >= 0 && active < shown.length) {
                choose(shown[active]);
            }
        } else if (e.key === 'Escape') {
            closeNamePopup();
        }
    });

    const boundary = massForm?.closest('.modal-box') || massForm;
    boundary?.addEventListener('click', (e) => {
        if (!namePopup.contains(e.target) && e.target !== nameInput) {
            closeNamePopup();
        }
    });
}
