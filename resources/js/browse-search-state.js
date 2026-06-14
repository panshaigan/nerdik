const STORAGE_KEY = 'nerdik.browse.search';

const EPHEMERAL_PRESETS = new Set(['my_events', 'my_activities']);

const IGNORED_QUERY_KEYS = new Set(['page', 'preset']);

function isSearchPage() {
    return window.location.pathname === '/search';
}

function isEphemeralSearchVisit() {
    const preset = new URLSearchParams(window.location.search).get('preset');

    return preset !== null && EPHEMERAL_PRESETS.has(preset);
}

function hasSearchQueryString() {
    const params = new URLSearchParams(window.location.search);

    for (const key of params.keys()) {
        if (!IGNORED_QUERY_KEYS.has(key)) {
            return true;
        }
    }

    return false;
}

function syncBrowseSearchState() {
    if (!isSearchPage()) {
        return;
    }

    if (isEphemeralSearchVisit()) {
        localStorage.removeItem(STORAGE_KEY);

        return;
    }

    const query = window.location.search;
    if (query && hasSearchQueryString()) {
        localStorage.setItem(STORAGE_KEY, query);

        return;
    }

    localStorage.removeItem(STORAGE_KEY);
}

function restoreBrowseSearchStateIfNeeded() {
    if (!isSearchPage() || window.location.search) {
        return;
    }

    const cached = localStorage.getItem(STORAGE_KEY);
    if (!cached || cached === '?' || cached === '') {
        return;
    }

    const target = '/search' + (cached.startsWith('?') ? cached : '?' + cached);

    if (typeof window.Livewire?.navigate === 'function') {
        window.Livewire.navigate(target);
    } else {
        window.location.replace(target);
    }
}

export function clearBrowseSearchState() {
    localStorage.removeItem(STORAGE_KEY);
}

export function initBrowseSearchState() {
    window.__nerdikClearBrowseSearchState = clearBrowseSearchState;

    syncBrowseSearchState();
    restoreBrowseSearchStateIfNeeded();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBrowseSearchState);
} else {
    initBrowseSearchState();
}

document.addEventListener('livewire:navigated', () => {
    syncBrowseSearchState();
    restoreBrowseSearchStateIfNeeded();
});
