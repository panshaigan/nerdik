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

// Hosting mode toggles re-insert `[data-event-places-unified]` via morph. Two gotchas:
// 1) `commit` hook `succeed` runs before the queued morph (see Livewire effect + morph microtasks),
//    so booting there can run before the new map root exists.
// 2) Injected Livewire may run before deferred Vite bundles, so `livewire:init` can fire before
//    this module registers — register on morphed and retry until Livewire is available.
let livewireMapsMorphHookRegistered = false;

function registerLivewireMapsMorphHook() {
    if (livewireMapsMorphHookRegistered) {
        return true;
    }
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
        return false;
    }
    livewireMapsMorphHookRegistered = true;
    window.Livewire.hook('morphed', () => {
        requestAnimationFrame(() => {
            bootEventPlacesUnified();
            import('./maps/event-show-map.js')
                .then((m) => m.invalidateAllEventShowMaps())
                .catch(() => {});
        });
    });

    return true;
}

document.addEventListener('livewire:init', registerLivewireMapsMorphHook);
document.addEventListener('livewire:initialized', registerLivewireMapsMorphHook);
document.addEventListener('DOMContentLoaded', registerLivewireMapsMorphHook);
window.addEventListener('load', registerLivewireMapsMorphHook);
registerLivewireMapsMorphHook();
