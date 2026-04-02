function bootEventPlacesUnified() {
    document.querySelectorAll('[data-event-places-unified]').forEach((root) => {
        if (root.dataset.epLoadStarted) {
            return;
        }
        root.dataset.epLoadStarted = '1';
        import('./maps/event-places-unified.js').then((m) => m.initEventPlacesUnified(root));
    });
}

function bootMaps() {
    bootEventPlacesUnified();
    document.querySelectorAll('[data-event-show-map-root]').forEach((root) => {
        if (root.dataset.eventShowMapLoadStarted) return;
        root.dataset.eventShowMapLoadStarted = '1';
        import('./maps/event-show-map.js').then((m) => m.initEventShowMap(root));
    });
    if (document.querySelector('[data-browse-bbox-map]')) {
        import('./maps/browse-bbox.js').then((m) => m.initBrowseBboxMap().catch(() => {}));
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootMaps);
} else {
    bootMaps();
}

window.addEventListener('load', () => {
    bootEventPlacesUnified();
});

document.addEventListener('livewire:navigated', bootMaps);
