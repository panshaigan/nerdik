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
