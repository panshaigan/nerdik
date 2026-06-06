import L from './leaflet-setup.js';

const DEFAULT_OSM_ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

/**
 * @typedef {{ osmAttribution?: string }} ThemedBasemapOptions
 */

/**
 * Adds the standard OpenStreetMap basemap.
 *
 * @param {import('leaflet').Map} map
 * @param {ThemedBasemapOptions} [options]
 */
export function addThemedBasemapToMap(map, options = {}) {
    const attribution = options.osmAttribution ?? DEFAULT_OSM_ATTRIBUTION;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution,
    }).addTo(map);
}
