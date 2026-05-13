import L from './leaflet-setup.js';

/** Dispatched from `applyTheme()` in theme-script when the app light/dark class changes. */
export const THEME_APPLIED_EVENT = 'nerdik:theme-applied';

export function isAppDarkMode() {
    return document.documentElement.classList.contains('dark');
}

const DEFAULT_OSM_ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

/**
 * @typedef {{ osmAttribution?: string }} ThemedBasemapOptions
 */

/**
 * Adds an OSM basemap in light theme and Stadia.AlidadeSmoothDark in dark theme, and keeps them in sync when the theme toggles.
 *
 * @param {import('leaflet').Map} map
 * @param {ThemedBasemapOptions} [options]
 */
export function addThemedBasemapToMap(map, options = {}) {
    const attribution = options.osmAttribution ?? DEFAULT_OSM_ATTRIBUTION;
    const light = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution,
    });
    const dark = L.tileLayer.provider('Stadia.AlidadeSmoothDark', {
        maxZoom: 19,
    });

    let active = isAppDarkMode() ? dark : light;
    active.addTo(map);

    const sync = () => {
        const want = isAppDarkMode() ? dark : light;
        if (want === active) {
            return;
        }
        map.removeLayer(active);
        want.addTo(map);
        active = want;
    };

    window.addEventListener(THEME_APPLIED_EVENT, sync);
    map.on('unload', () => {
        window.removeEventListener(THEME_APPLIED_EVENT, sync);
    });
}
