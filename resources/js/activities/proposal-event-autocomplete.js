function getLivewire(root) {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.find !== 'function') {
        return null;
    }
    const host = root.closest('[wire\\:id]');
    const wireId = host?.getAttribute('wire:id');
    if (!wireId) {
        return null;
    }

    return window.Livewire.find(wireId);
}

function callLivewireMethod(wire, method, ...args) {
    if (!wire) {
        return Promise.resolve([]);
    }
    if (typeof wire.call === 'function') {
        return Promise.resolve(wire.call(method, ...args));
    }
    if (typeof wire.$call === 'function') {
        return Promise.resolve(wire.$call(method, ...args));
    }

    return Promise.resolve([]);
}

function parseConfig(root) {
    const cfgEl = root.querySelector('script[type="application/json"][data-proposal-event-config]');
    if (!cfgEl) {
        return {};
    }
    try {
        return JSON.parse(cfgEl.textContent || '{}');
    } catch {
        return {};
    }
}

function closePopup(input, popup) {
    popup.classList.add('hidden');
    popup.innerHTML = '';
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
}

function openPopup(input, popup) {
    popup.classList.remove('hidden');
    input.setAttribute('aria-expanded', 'true');
}

function applySelectionStyles(popup, activeIndex) {
    [...popup.querySelectorAll('[data-proposal-event-idx]')].forEach((el, idx) => {
        const isActive = idx === activeIndex;
        el.classList.toggle('bg-base-200', isActive);
        el.setAttribute('aria-selected', isActive ? 'true' : 'false');
        if (isActive) {
            el.scrollIntoView({ block: 'nearest' });
        }
    });
}

function setSelectedEvent(root, item, clearSearch = false) {
    const input = root.querySelector('[data-proposal-event-input]');
    const hidden = root.querySelector('[data-proposal-event-id]');
    const popup = root.querySelector('[data-proposal-event-popup]');
    if (!input || !hidden || !popup) {
        return;
    }

    const idValue = item?.id ? String(item.id) : '';
    const labelValue = clearSearch ? '' : String(item?.label || '');
    root.dataset.proposalEventSelecting = '1';
    hidden.value = idValue;
    hidden.dispatchEvent(new Event('input', { bubbles: true }));
    hidden.dispatchEvent(new Event('change', { bubbles: true }));

    input.value = labelValue;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    delete root.dataset.proposalEventSelecting;
    closePopup(input, popup);
}

function renderSuggestionItems(root, items, state) {
    const input = root.querySelector('[data-proposal-event-input]');
    const popup = root.querySelector('[data-proposal-event-popup]');
    if (!input || !popup) {
        return;
    }
    popup.innerHTML = '';
    state.shown = items;
    state.active = -1;

    if (items.length === 0) {
        const cfg = parseConfig(root);
        const empty = document.createElement('div');
        empty.className = 'px-3 py-2 text-sm text-base-content/60';
        empty.textContent = cfg.noResultsLabel || 'No results';
        popup.appendChild(empty);
        openPopup(input, popup);

        return;
    }

    const cfg = parseConfig(root);
    const frag = document.createDocumentFragment();
    const noneBtn = document.createElement('button');
    noneBtn.type = 'button';
    noneBtn.className = 'block w-full border-b border-base-300/70 px-3 py-2 text-left text-sm text-base-content/80 hover:bg-base-200';
    noneBtn.textContent = cfg.noneLabel || '—';
    noneBtn.addEventListener('mousedown', (e) => e.preventDefault());
    noneBtn.addEventListener('click', () => setSelectedEvent(root, null, true));
    frag.appendChild(noneBtn);

    items.forEach((item, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
        btn.textContent = item.label;
        btn.dataset.proposalEventIdx = String(idx);
        btn.id = `proposal-event-opt-${idx}`;
        btn.setAttribute('role', 'option');
        btn.setAttribute('aria-selected', 'false');
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', () => setSelectedEvent(root, item));
        frag.appendChild(btn);
    });
    popup.appendChild(frag);
    openPopup(input, popup);
}

function normalizeItems(raw) {
    if (!Array.isArray(raw)) {
        return [];
    }

    return raw
        .map((item) => ({
            id: Number(item?.id),
            label: String(item?.label || ''),
        }))
        .filter((item) => Number.isInteger(item.id) && item.id > 0 && item.label !== '');
}

function debounce(fn, waitMs) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), waitMs);
    };
}

const stateByRoot = new WeakMap();
let proposalEventAutocompleteBound = false;

function getState(root) {
    if (!stateByRoot.has(root)) {
        stateByRoot.set(root, { active: -1, shown: [], lastQuery: '', reqSeq: 0 });
    }

    return stateByRoot.get(root);
}

function initRoot(root) {
    if (!root) {
        return;
    }
    getState(root);
}

async function fetchSuggestions(root, query) {
    const wire = getLivewire(root);
    const state = getState(root);
    const seq = ++state.reqSeq;
    const result = await callLivewireMethod(wire, 'searchProposalEvents', query);
    if (seq !== state.reqSeq) {
        return [];
    }

    return normalizeItems(result);
}

function bindDelegation() {
    if (proposalEventAutocompleteBound) {
        return;
    }
    proposalEventAutocompleteBound = true;

    const debouncedFetchByRoot = new WeakMap();

    document.addEventListener('focusin', async (event) => {
        const input = event.target.closest?.('[data-proposal-event-input]');
        if (!input) {
            return;
        }
        const root = input.closest('[data-proposal-event-autocomplete]');
        if (!root) {
            return;
        }
        initRoot(root);
        const state = getState(root);
        state.lastQuery = '';
        const cfg = parseConfig(root);
        const live = await fetchSuggestions(root, '');
        const initial = live.length > 0 ? live : normalizeItems(cfg.initialSuggestions || []);
        renderSuggestionItems(root, initial, state);
    });

    document.addEventListener('input', (event) => {
        const input = event.target.closest?.('[data-proposal-event-input]');
        if (!input) {
            return;
        }
        const root = input.closest('[data-proposal-event-autocomplete]');
        if (!root) {
            return;
        }
        initRoot(root);
        const state = getState(root);
        if (root.dataset.proposalEventSelecting === '1') {
            return;
        }
        const hidden = root.querySelector('[data-proposal-event-id]');
        if (hidden?.value) {
            hidden.value = '';
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }

        let run = debouncedFetchByRoot.get(root);
        if (!run) {
            run = debounce(async (query) => {
                const items = await fetchSuggestions(root, query);
                renderSuggestionItems(root, items, state);
            }, 220);
            debouncedFetchByRoot.set(root, run);
        }

        const query = input.value.trim();
        state.lastQuery = query;
        run(query);
    });

    document.addEventListener('keydown', (event) => {
        const input = event.target.closest?.('[data-proposal-event-input]');
        if (!input) {
            return;
        }
        const root = input.closest('[data-proposal-event-autocomplete]');
        const popup = root?.querySelector('[data-proposal-event-popup]');
        if (!root || !popup || popup.classList.contains('hidden')) {
            return;
        }
        const state = getState(root);
        if (state.shown.length === 0) {
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            state.active = (state.active + 1) % state.shown.length;
            applySelectionStyles(popup, state.active);
            input.setAttribute('aria-activedescendant', `proposal-event-opt-${state.active}`);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            state.active = state.active <= 0 ? state.shown.length - 1 : state.active - 1;
            applySelectionStyles(popup, state.active);
            input.setAttribute('aria-activedescendant', `proposal-event-opt-${state.active}`);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const pick = state.active >= 0 ? state.active : 0;
            setSelectedEvent(root, state.shown[pick]);
        } else if (event.key === 'Escape') {
            closePopup(input, popup);
        }
    });

    document.addEventListener('click', (event) => {
        const root = event.target.closest?.('[data-proposal-event-autocomplete]');
        if (!root) {
            document.querySelectorAll('[data-proposal-event-autocomplete]').forEach((candidate) => {
                const input = candidate.querySelector('[data-proposal-event-input]');
                const popup = candidate.querySelector('[data-proposal-event-popup]');
                if (input && popup) {
                    closePopup(input, popup);
                }
            });
            return;
        }
        const inInput = event.target.closest('[data-proposal-event-input]');
        const inPopup = event.target.closest('[data-proposal-event-popup]');
        if (inInput || inPopup) {
            return;
        }
        const input = root.querySelector('[data-proposal-event-input]');
        const popup = root.querySelector('[data-proposal-event-popup]');
        if (input && popup) {
            closePopup(input, popup);
        }
    });
}

export function bootProposalEventAutocomplete() {
    bindDelegation();
    document.querySelectorAll('[data-proposal-event-autocomplete]').forEach((root) => initRoot(root));
}
