const STORAGE_KEY = 'nerdik.browse.search';

function isSearchPage() {
    return window.location.pathname === '/search';
}

function hasSearchQueryString() {
    const params = new URLSearchParams(window.location.search);

    for (const key of params.keys()) {
        if (key !== 'page') {
            return true;
        }
    }

    return false;
}

function syncBrowseSearchState() {
    if (!isSearchPage()) {
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
