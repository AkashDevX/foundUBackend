import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * @typedef {Object} PunchMapPayload
 * @property {string} event_label
 * @property {string} time_label
 * @property {string} date_label
 * @property {boolean} within_geofence
 * @property {string} geofence_label
 * @property {number} device_latitude
 * @property {number} device_longitude
 * @property {number|null} expected_latitude
 * @property {number|null} expected_longitude
 * @property {string} distance_label
 * @property {number} allowed_radius_meters
 * @property {string} device_coords_label
 * @property {string} expected_coords_label
 */

function punchPinIcon(tone) {
    const color = tone === 'within' ? '#059669' : '#d97706';

    return L.divIcon({
        className: 'tc-punch-pin-outer',
        html: `<div class="tc-punch-pin-inner" style="background:${color}" aria-hidden="true"></div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 28],
    });
}

const sitePinIcon = L.divIcon({
    className: 'tc-punch-pin-outer',
    html: '<div class="tc-site-pin-inner" aria-hidden="true"></div>',
    iconSize: [28, 28],
    iconAnchor: [14, 28],
});

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('time-clock-punch-map-modal');
    const mapCanvas = document.getElementById('time-clock-punch-map-canvas');
    const closeButton = document.querySelector('[data-time-clock-punch-map-close]');
    const titleEl = document.querySelector('[data-time-clock-punch-map-title]');
    const subtitleEl = document.querySelector('[data-time-clock-punch-map-subtitle]');
    const geofenceBadgeEl = document.querySelector('[data-time-clock-punch-map-geofence-badge]');
    const distanceEl = document.querySelector('[data-time-clock-punch-map-distance]');
    const deviceCoordsEl = document.querySelector('[data-time-clock-punch-map-device-coords]');
    const expectedCoordsEl = document.querySelector('[data-time-clock-punch-map-expected-coords]');

    if (!modal || !mapCanvas) {
        return;
    }

    const defaultLat = Number.parseFloat(modal.dataset.defaultLat ?? '-27.4698');
    const defaultLng = Number.parseFloat(modal.dataset.defaultLng ?? '153.0251');
    const defaultZoom = Number.parseInt(modal.dataset.defaultZoom ?? '14', 10);

    /** @type {L.Map|null} */
    let map = null;
    /** @type {L.LayerGroup|null} */
    let layerGroup = null;

    const ensureMap = () => {
        if (map) {
            return map;
        }

        map = L.map(mapCanvas, {
            zoomControl: true,
            attributionControl: true,
        }).setView([defaultLat, defaultLng], defaultZoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        layerGroup = L.layerGroup().addTo(map);

        return map;
    };

    const clearLayers = () => {
        layerGroup?.clearLayers();
    };

    /**
     * @param {PunchMapPayload} payload
     */
    const renderMap = (payload) => {
        const activeMap = ensureMap();
        clearLayers();

        const deviceLatLng = [payload.device_latitude, payload.device_longitude];
        const layers = [];

        L.marker(deviceLatLng, {
            icon: punchPinIcon(payload.within_geofence ? 'within' : 'outside'),
        })
            .bindPopup(`<strong>${payload.event_label}</strong><br>${payload.time_label}<br>${payload.device_coords_label}`)
            .addTo(layerGroup);

        layers.push(L.latLng(deviceLatLng[0], deviceLatLng[1]));

        if (payload.expected_latitude !== null && payload.expected_longitude !== null) {
            const expectedLatLng = [payload.expected_latitude, payload.expected_longitude];

            L.marker(expectedLatLng, { icon: sitePinIcon })
                .bindPopup(`<strong>Expected site</strong><br>${payload.expected_coords_label}`)
                .addTo(layerGroup);

            L.circle(expectedLatLng, {
                radius: payload.allowed_radius_meters,
                color: '#003d7a',
                fillColor: '#0052a2',
                fillOpacity: 0.12,
                weight: 2,
            }).addTo(layerGroup);

            layers.push(L.latLng(expectedLatLng[0], expectedLatLng[1]));
        }

        if (layers.length === 1) {
            activeMap.setView(deviceLatLng, 17);
        } else {
            activeMap.fitBounds(L.latLngBounds(layers), { padding: [36, 36], maxZoom: 18 });
        }

        window.setTimeout(() => {
            activeMap.invalidateSize();
        }, 50);
    };

    /**
     * @param {PunchMapPayload} payload
     */
    const openModal = (payload) => {
        if (titleEl) {
            titleEl.textContent = `${payload.event_label} location`;
        }
        if (subtitleEl) {
            subtitleEl.textContent = `${payload.date_label} at ${payload.time_label}`;
        }
        if (geofenceBadgeEl) {
            geofenceBadgeEl.textContent = payload.geofence_label;
            geofenceBadgeEl.className = payload.within_geofence
                ? 'inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200'
                : 'inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200';
        }
        if (distanceEl) {
            distanceEl.textContent = `${payload.distance_label} from site · allowed radius ${payload.allowed_radius_meters} m`;
        }
        if (deviceCoordsEl) {
            deviceCoordsEl.textContent = payload.device_coords_label;
        }
        if (expectedCoordsEl) {
            expectedCoordsEl.textContent = payload.expected_coords_label;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');

        renderMap(payload);
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        const fullscreenModal = document.getElementById('timesheet-fullscreen-modal');
        if (!fullscreenModal) {
            document.body.classList.remove('overflow-hidden');
        }
    };

    document.querySelectorAll('[data-time-clock-punch-map]').forEach((button) => {
        button.addEventListener('click', () => {
            const raw = button.getAttribute('data-punch');
            if (!raw) {
                return;
            }

            try {
                const payload = JSON.parse(raw);
                openModal(payload);
            } catch {
                // Ignore malformed payload.
            }
        });
    });

    closeButton?.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('flex')) {
            closeModal();
        }
    });
});
