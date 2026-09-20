import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const POLL_MS = 20_000;
const DEFAULT_LAT = -27.4698;
const DEFAULT_LNG = 153.0251;

function employeePinIcon(hasIdle, color) {
    const fill = color || (hasIdle ? '#d97706' : '#059669');
    return L.divIcon({
        className: 'tc-punch-pin-outer',
        html: `<div class="tc-punch-pin-inner" style="background:${fill}" aria-hidden="true"></div>`,
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

function pathLabelIcon(label, kind) {
    return L.divIcon({
        className: 'tc-punch-pin-outer',
        html: `<div class="${kind === 'start' ? 'tc-path-start' : 'tc-path-end'}">${label}</div>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });
}

function arrowIcon(bearingDeg) {
    return L.divIcon({
        className: 'tc-footpath-arrow-outer',
        html: `<div class="tc-footpath-arrow" style="transform:rotate(${bearingDeg}deg)" aria-hidden="true">➤</div>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });
}

function createMap(el) {
    const map = L.map(el, {
        zoomControl: true,
        attributionControl: true,
    }).setView([DEFAULT_LAT, DEFAULT_LNG], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const layerGroup = L.layerGroup().addTo(map);
    return { map, layerGroup };
}

function readJsonScript(root, selector, fallback) {
    const el = root.querySelector(selector);
    if (!el) return fallback;
    try {
        return JSON.parse(el.textContent || '');
    } catch {
        return fallback;
    }
}

function formatTime(iso, withSeconds = false) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit',
        ...(withSeconds ? { second: '2-digit' } : {}),
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function formatDistance(meters) {
    const m = Number(meters) || 0;
    if (m < 1) return '0 m';
    if (m < 1000) return `${Math.round(m)} m`;
    return `${(m / 1000).toFixed(2)} km`;
}

function formatMinutes(minutes) {
    const mins = Math.max(0, Math.round(Number(minutes) || 0));
    if (mins <= 0) return '0 min';
    if (mins < 60) return `${mins} min`;
    const h = Math.floor(mins / 60);
    const rem = mins % 60;
    return rem > 0 ? `${h}h ${rem}m` : `${h}h`;
}

function bearingDegrees(from, to) {
    const φ1 = (from.lat * Math.PI) / 180;
    const φ2 = (to.lat * Math.PI) / 180;
    const Δλ = ((to.lng - from.lng) * Math.PI) / 180;
    const y = Math.sin(Δλ) * Math.cos(φ2);
    const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ);
    return ((Math.atan2(y, x) * 180) / Math.PI + 360) % 360;
}

function haversineM(a, b) {
    const R = 6371000;
    const φ1 = (a.lat * Math.PI) / 180;
    const φ2 = (b.lat * Math.PI) / 180;
    const Δφ = ((b.lat - a.lat) * Math.PI) / 180;
    const Δλ = ((b.lng - a.lng) * Math.PI) / 180;
    const s =
        Math.sin(Δφ / 2) ** 2 +
        Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ / 2) ** 2;
    return 2 * R * Math.atan2(Math.sqrt(s), Math.sqrt(1 - s));
}

function computeStatsLocally(trailPoints, idleAlerts) {
    const pts = (trailPoints || [])
        .filter((p) => p.latitude != null && p.longitude != null)
        .map((p) => ({
            lat: Number(p.latitude),
            lng: Number(p.longitude),
            at: p.recorded_at || null,
        }));

    let distance = 0;
    const segments = [];
    for (let i = 1; i < pts.length; i += 1) {
        const step = haversineM(pts[i - 1], pts[i]);
        if (step >= 2) distance += step;
        if (step >= 5) {
            segments.push({
                mid_lat: (pts[i - 1].lat + pts[i].lat) / 2,
                mid_lng: (pts[i - 1].lng + pts[i].lng) / 2,
                bearing_degrees: bearingDegrees(pts[i - 1], pts[i]),
                distance_meters: Math.round(step * 10) / 10,
                from_at: pts[i - 1].at,
                to_at: pts[i].at,
            });
        }
    }

    let waitingMinutes = 0;
    let isCurrentlyWaiting = false;
    if (pts.length >= 2) {
        const window = pts.slice(-Math.max(3, Math.ceil(pts.length / 3)));
        const center = {
            lat: window.reduce((s, p) => s + p.lat, 0) / window.length,
            lng: window.reduce((s, p) => s + p.lng, 0) / window.length,
        };
        const maxDisp = Math.max(...window.map((p) => haversineM(center, p)));
        if (maxDisp <= 25 && window[0].at && window[window.length - 1].at) {
            waitingMinutes = Math.max(
                0,
                Math.round(
                    (new Date(window[window.length - 1].at) - new Date(window[0].at)) / 60000,
                ),
            );
            isCurrentlyWaiting = waitingMinutes > 0;
        }
    }

    let longestWait = waitingMinutes;
    (idleAlerts || []).forEach((a) => {
        longestWait = Math.max(longestWait, Number(a.idle_minutes) || 0);
    });

    let durationMinutes = 0;
    if (pts.length >= 2 && pts[0].at && pts[pts.length - 1].at) {
        durationMinutes = Math.max(
            0,
            Math.round((new Date(pts[pts.length - 1].at) - new Date(pts[0].at)) / 60000),
        );
    }

    return {
        sample_count: pts.length,
        distance_meters: Math.round(distance * 10) / 10,
        distance_label: formatDistance(distance),
        waiting_minutes: waitingMinutes,
        waiting_label: formatMinutes(waitingMinutes),
        longest_wait_minutes: longestWait,
        is_currently_waiting: isCurrentlyWaiting,
        duration_minutes: durationMinutes,
        segments,
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-location-tracking]');
    if (!root) return;

    const mode = root.getAttribute('data-mode');
    if (mode === 'site') {
        initSiteMode(root);
        return;
    }
    if (mode === 'detail') {
        initDetailMode(root);
    }
});

function initSiteMode(root) {
    const mapEl = document.getElementById('location-tracking-site-map');
    if (!mapEl) return;

    const liveStatus = root.querySelector('[data-live-status]');
    const mapMeta = root.querySelector('[data-map-meta]');
    const roster = root.querySelector('[data-site-roster]');
    const liveUrl = root.getAttribute('data-live-url');

    const mapBundle = createMap(mapEl);
    let site = readJsonScript(root, '[data-initial-site]', {}) || {};
    let employees = readJsonScript(root, '[data-initial-site-employees]', []) || [];

    const palette = ['#0f766e', '#2563eb', '#c2410c', '#7c3aed', '#be123c', '#0891b2', '#ca8a04', '#15803d'];

    const renderRoster = (list) => {
        if (!roster) return;
        if (!list.length) {
            roster.innerHTML =
                '<p class="px-4 py-8 text-sm text-brand-text-secondary">No one is clocked in at this site right now.</p>';
            return;
        }
        roster.innerHTML = list
            .map((person) => {
                const stats = person.movement_stats || {};
                const waiting =
                    person.has_idle_alert || stats.is_currently_waiting
                        ? '<span class="font-semibold text-amber-700"> · waiting</span>'
                        : '';
                const clocked = person.clocked_in_at
                    ? ` · in since ${formatTime(person.clocked_in_at)}`
                    : '';
                return `<a href="${escapeHtml(person.detail_url || '#')}" class="block border-b border-brand-border/80 px-4 py-3.5 transition hover:bg-brand-surface/50" data-site-person="${escapeHtml(person.employee_public_id || '')}">
                    <div class="flex items-start gap-3">
                        <span class="mt-1 inline-block size-3 shrink-0 rounded-full ring-2 ring-white shadow" style="background:${escapeHtml(person.color || '#0f766e')}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-brand-text">${escapeHtml(person.employee_name || 'Employee')}</p>
                            <p class="mt-0.5 text-xs text-brand-text-secondary">${escapeHtml(person.shift_label || person.shift_name || 'On shift')}${clocked}</p>
                            <p class="mt-1 text-xs text-brand-text-secondary">${escapeHtml(stats.distance_label || '0 m')} walked${waiting}</p>
                        </div>
                    </div>
                </a>`;
            })
            .join('');
    };

    const renderSiteMap = () => {
        mapBundle.layerGroup.clearLayers();
        const bounds = [];

        if (site.latitude != null && site.longitude != null) {
            const siteLatLng = L.latLng(Number(site.latitude), Number(site.longitude));
            L.marker(siteLatLng, { icon: sitePinIcon })
                .bindTooltip(escapeHtml(site.name || 'Work site'), { direction: 'top', opacity: 0.95 })
                .addTo(mapBundle.layerGroup);
            L.circle(siteLatLng, {
                radius: Number(site.allowed_radius_meters) || 300,
                color: '#2563eb',
                fillColor: '#3b82f6',
                fillOpacity: 0.1,
                weight: 1.5,
            }).addTo(mapBundle.layerGroup);
            bounds.push(siteLatLng);
        }

        employees.forEach((person, index) => {
            const color = person.color || palette[index % palette.length];
            const stats = person.movement_stats || computeStatsLocally(person.trail || [], person.idle_alerts || []);
            const trail = person.trail || [];
            const latLngs = [];

            trail.forEach((pt) => {
                if (pt.latitude == null || pt.longitude == null) return;
                const ll = L.latLng(Number(pt.latitude), Number(pt.longitude));
                latLngs.push(ll);
                bounds.push(ll);
            });

            if (latLngs.length >= 2) {
                L.polyline(latLngs, {
                    color,
                    weight: 4,
                    opacity: 0.8,
                    lineJoin: 'round',
                    lineCap: 'round',
                })
                    .bindTooltip(
                        `<strong>${escapeHtml(person.employee_name || 'Employee')}</strong><br/>Footpath · ${escapeHtml(stats.distance_label || '0 m')}`,
                        { sticky: true, className: 'tc-employee-hover', opacity: 0.95 },
                    )
                    .addTo(mapBundle.layerGroup);
            }

            const lat = person.latitude ?? (latLngs.length ? latLngs[latLngs.length - 1].lat : null);
            const lng = person.longitude ?? (latLngs.length ? latLngs[latLngs.length - 1].lng : null);
            if (lat == null || lng == null) return;

            const pin = L.latLng(Number(lat), Number(lng));
            bounds.push(pin);
            const waitNote = stats.is_currently_waiting || person.has_idle_alert
                ? `Waiting ~ ${stats.waiting_label || formatMinutes(stats.waiting_minutes || 0)}`
                : `Longest wait ${formatMinutes(stats.longest_wait_minutes || 0)}`;

            L.marker(pin, {
                icon: employeePinIcon(Boolean(person.has_idle_alert), color),
            })
                .bindTooltip(
                    `<div><strong>${escapeHtml(person.employee_name || 'Employee')}</strong></div>
                     <div>Distance: <strong>${escapeHtml(stats.distance_label || '0 m')}</strong></div>
                     <div>${escapeHtml(waitNote)}</div>
                     <div>${escapeHtml(person.shift_label || person.shift_name || 'On shift')}</div>`,
                    { sticky: true, direction: 'top', opacity: 0.96, className: 'tc-employee-hover' },
                )
                .addTo(mapBundle.layerGroup);
        });

        if (bounds.length) {
            mapBundle.map.fitBounds(L.latLngBounds(bounds).pad(0.28));
        }
        requestAnimationFrame(() => {
            mapBundle.map.invalidateSize();
            setTimeout(() => mapBundle.map.invalidateSize(), 200);
        });

        if (mapMeta) {
            mapMeta.textContent = `${employees.length} on site · coloured paths show each person's movement · hover a pin for details`;
        }
    };

    const applySiteData = (list) => {
        employees = (list || []).map((row, index) => ({
            ...row,
            color: row.color || palette[index % palette.length],
            trail: row.trail || [],
            idle_alerts: row.idle_alerts || [],
            movement_stats:
                row.movement_stats || computeStatsLocally(row.trail || [], row.idle_alerts || []),
        }));
        site = {
            ...site,
            staff_count: employees.length,
            idle_count: employees.filter((e) => e.has_idle_alert).length,
        };
        renderRoster(employees);
        renderSiteMap();
        if (liveStatus) {
            liveStatus.textContent = `Everyone on shift here — last refresh ${formatTime(new Date().toISOString(), true)}`;
        }
    };

    applySiteData(employees);

    const pollLive = async () => {
        if (!liveUrl) return;
        try {
            const res = await fetch(liveUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            applySiteData(data.positions || []);
        } catch {
            /* next poll */
        }
    };

    setInterval(pollLive, POLL_MS);
}

function initDetailMode(root) {
    const mapEl = document.getElementById('location-tracking-map');
    if (!mapEl) {
        return;
    }

    const liveStatus = root.querySelector('[data-live-status]');
    const mapMeta = root.querySelector('[data-map-meta]');
    const liveUrl = root.getAttribute('data-live-url');
    const trailUrl = root.getAttribute('data-trail-url');
    const statsRoot = root.querySelector('[data-movement-stats]');

    const mapBundle = createMap(mapEl);

    let position = readJsonScript(root, '[data-initial-position]', null);
    let trailPoints = readJsonScript(root, '[data-initial-trail]', []);
    let idleAlerts = readJsonScript(root, '[data-initial-idle-alerts]', []);
    let movementStats =
        readJsonScript(root, '[data-initial-movement-stats]', null) ||
        computeStatsLocally(trailPoints, idleAlerts);

    const employeeHoverHtml = (p, stats) => {
        const waitNote = stats.is_currently_waiting
            ? `Waiting here ~ ${stats.waiting_label}`
            : `Longest wait ${formatMinutes(stats.longest_wait_minutes)}`;
        return `
            <div><strong>${escapeHtml(p.employee_name || 'Employee')}</strong></div>
            <div>Distance travelled: <strong>${escapeHtml(stats.distance_label || '0 m')}</strong></div>
            <div>${escapeHtml(waitNote)}</div>
            <div>Tracking window: ${escapeHtml(formatMinutes(stats.duration_minutes || 0))}</div>
            <div>${stats.sample_count || 0} footpath samples</div>
        `;
    };

    const updateStatsCards = (stats) => {
        if (!statsRoot || !stats) return;
        const distanceEl = statsRoot.querySelector('[data-stat-distance]');
        const samplesEl = statsRoot.querySelector('[data-stat-samples]');
        const waitingEl = statsRoot.querySelector('[data-stat-waiting]');
        const waitingNoteEl = statsRoot.querySelector('[data-stat-waiting-note]');
        const durationEl = statsRoot.querySelector('[data-stat-duration]');
        if (distanceEl) distanceEl.textContent = stats.distance_label || formatDistance(stats.distance_meters);
        if (samplesEl) samplesEl.textContent = `${stats.sample_count || 0} GPS samples on footpath`;
        if (waitingEl) waitingEl.textContent = stats.waiting_label || formatMinutes(stats.waiting_minutes);
        if (waitingNoteEl) {
            waitingNoteEl.textContent = stats.is_currently_waiting
                ? 'Currently low movement'
                : `Longest wait ${formatMinutes(stats.longest_wait_minutes)}`;
        }
        if (durationEl) durationEl.textContent = formatMinutes(stats.duration_minutes);
    };

    const renderCombinedMap = () => {
        mapBundle.layerGroup.clearLayers();
        const bounds = [];
        const p = position || {};
        const stats = movementStats || computeStatsLocally(trailPoints, idleAlerts);

        if (p.site_latitude != null && p.site_longitude != null) {
            const siteLatLng = L.latLng(Number(p.site_latitude), Number(p.site_longitude));
            L.marker(siteLatLng, { icon: sitePinIcon })
                .bindTooltip(escapeHtml(p.work_location_name || 'Work site'), {
                    direction: 'top',
                    opacity: 0.95,
                })
                .addTo(mapBundle.layerGroup);
            L.circle(siteLatLng, {
                radius: Number(p.allowed_radius_meters) || 300,
                color: '#2563eb',
                fillColor: '#3b82f6',
                fillOpacity: 0.08,
                weight: 1,
            }).addTo(mapBundle.layerGroup);
            bounds.push(siteLatLng);
        }

        const latLngs = [];
        (trailPoints || []).forEach((pt, index) => {
            if (pt.latitude == null || pt.longitude == null) return;
            const ll = L.latLng(Number(pt.latitude), Number(pt.longitude));
            latLngs.push(ll);
            bounds.push(ll);
            L.circleMarker(ll, {
                radius: index === 0 || index === (trailPoints.length - 1) ? 5 : 3.5,
                color: pt.within_geofence ? '#0f766e' : '#d97706',
                fillColor: pt.within_geofence ? '#14b8a6' : '#f59e0b',
                fillOpacity: 0.9,
                weight: 1,
            })
                .bindTooltip(
                    `Footpath point · ${formatTime(pt.recorded_at)}${
                        pt.distance_from_site_meters != null
                            ? `<br/>${Math.round(Number(pt.distance_from_site_meters))} m from site`
                            : ''
                    }`,
                    { sticky: true, opacity: 0.95, className: 'tc-employee-hover' },
                )
                .addTo(mapBundle.layerGroup);
        });

        if (latLngs.length >= 2) {
            const footpath = L.polyline(latLngs, {
                color: '#0f766e',
                weight: 5,
                opacity: 0.88,
                lineJoin: 'round',
                lineCap: 'round',
            }).addTo(mapBundle.layerGroup);

            footpath.bindTooltip(
                `<strong>Footpath</strong><br/>Distance: ${escapeHtml(stats.distance_label || '0 m')}<br/>${stats.sample_count || 0} samples · ${escapeHtml(formatMinutes(stats.duration_minutes || 0))} tracked`,
                { sticky: true, opacity: 0.95, className: 'tc-employee-hover' },
            );

            L.marker(latLngs[0], { icon: pathLabelIcon('A', 'start'), interactive: false }).addTo(
                mapBundle.layerGroup,
            );
            L.marker(latLngs[latLngs.length - 1], {
                icon: pathLabelIcon('B', 'end'),
                interactive: false,
            }).addTo(mapBundle.layerGroup);

            const segments =
                Array.isArray(stats.segments) && stats.segments.length
                    ? stats.segments
                    : computeStatsLocally(trailPoints, idleAlerts).segments;

            const step = Math.max(1, Math.ceil(segments.length / 18));
            segments.forEach((seg, idx) => {
                if (idx % step !== 0) return;
                if (seg.mid_lat == null || seg.mid_lng == null) return;
                const mid = L.latLng(Number(seg.mid_lat), Number(seg.mid_lng));
                L.marker(mid, {
                    icon: arrowIcon(Number(seg.bearing_degrees) || 0),
                    interactive: true,
                    keyboard: false,
                })
                    .bindTooltip(
                        `Travelled ${formatDistance(seg.distance_meters)} · ${formatTime(seg.from_at)} → ${formatTime(seg.to_at)}`,
                        { sticky: true, opacity: 0.95, className: 'tc-employee-hover' },
                    )
                    .addTo(mapBundle.layerGroup);
            });
        }

        (idleAlerts || []).forEach((alert) => {
            if (alert.center_latitude == null || alert.center_longitude == null) return;
            const ll = L.latLng(Number(alert.center_latitude), Number(alert.center_longitude));
            bounds.push(ll);
            L.circle(ll, {
                radius: Math.max(Number(alert.max_displacement_meters) || 20, 15),
                color: '#b45309',
                fillColor: '#f59e0b',
                fillOpacity: 0.22,
                weight: 2,
            })
                .bindTooltip(`Waited ~${alert.idle_minutes} min here`, {
                    sticky: true,
                    opacity: 0.95,
                    className: 'tc-employee-hover',
                })
                .addTo(mapBundle.layerGroup);
        });

        if (p.latitude != null && p.longitude != null) {
            const latLng = L.latLng(Number(p.latitude), Number(p.longitude));
            const marker = L.marker(latLng, {
                icon: employeePinIcon(Boolean(p.has_idle_alert) || Boolean(stats.is_currently_waiting)),
            }).addTo(mapBundle.layerGroup);

            marker.bindTooltip(employeeHoverHtml(p, stats), {
                sticky: true,
                direction: 'top',
                opacity: 0.96,
                className: 'tc-employee-hover',
            });

            bounds.push(latLng);
        }

        if (bounds.length) {
            mapBundle.map.fitBounds(L.latLngBounds(bounds).pad(0.3));
        } else {
            mapBundle.map.setView([DEFAULT_LAT, DEFAULT_LNG], 12);
        }

        requestAnimationFrame(() => {
            mapBundle.map.invalidateSize();
            setTimeout(() => mapBundle.map.invalidateSize(), 200);
        });

        updateStatsCards(stats);

        if (mapMeta) {
            const parts = [];
            if (stats.distance_meters > 0) {
                parts.push(`Footpath ${stats.distance_label}`);
            }
            if (stats.is_currently_waiting) {
                parts.push(`Waiting ${stats.waiting_label}`);
            } else if (stats.longest_wait_minutes > 0) {
                parts.push(`Longest wait ${formatMinutes(stats.longest_wait_minutes)}`);
            }
            if (p.recorded_at) {
                parts.push(
                    p.position_source === 'clock_in'
                        ? 'Clock-in pin (awaiting live pings)'
                        : `Updated ${formatTime(p.recorded_at, true)}`,
                );
            }
            mapMeta.textContent =
                parts.length > 0
                    ? parts.join(' · ')
                    : 'Waiting for mid-shift GPS samples to draw the footpath…';
        }
    };

    const applyPosition = (p) => {
        if (!p) return;
        position = { ...(position || {}), ...p };
        renderCombinedMap();

        if (liveStatus && position.status !== 'ended') {
            liveStatus.textContent = `Footpath + live pin — last refresh ${formatTime(new Date().toISOString(), true)}`;
        }
    };

    renderCombinedMap();

    const refreshTrail = async () => {
        if (!trailUrl || !position?.employee_public_id || !position?.clock_in_entry_id) return;
        try {
            const url = `${trailUrl}?employee=${encodeURIComponent(position.employee_public_id)}&clock_in_entry_id=${encodeURIComponent(position.clock_in_entry_id)}`;
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            trailPoints = data.trail || [];
            idleAlerts = data.idle_alerts || [];
            movementStats =
                data.movement_stats || computeStatsLocally(trailPoints, idleAlerts);
            if (data.site_latitude != null) {
                position = {
                    ...position,
                    site_latitude: data.site_latitude,
                    site_longitude: data.site_longitude,
                    allowed_radius_meters: data.allowed_radius_meters ?? position.allowed_radius_meters,
                };
            }
            renderCombinedMap();
        } catch {
            /* next poll */
        }
    };

    const pollLive = async () => {
        if (!liveUrl || (position && position.status === 'ended')) return;
        try {
            const res = await fetch(liveUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            const next = Array.isArray(data.positions) ? data.positions[0] : null;
            if (next) {
                applyPosition(next);
                void refreshTrail();
            } else if (position && position.status !== 'ended') {
                if (liveStatus) {
                    liveStatus.textContent = 'Employee is no longer clocked in.';
                }
            }
        } catch {
            /* next poll */
        }
    };

    void pollLive();
    setInterval(pollLive, POLL_MS);
}
