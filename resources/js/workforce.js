import './cruLynkDialogs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/** ~Brisbane; used for random fallbacks and jitter (matches config `workforce.default_map_*` defaults). */
const BRISBANE_LAT = -27.4698;
const BRISBANE_LNG = 153.0251;
/** ~+/-4.4 km from centre — keeps random pins in greater Brisbane. */
const BRISBANE_JITTER_DEG = 0.04;

function randomPinNearBrisbane() {
    return {
        lat: BRISBANE_LAT + (Math.random() * 2 * BRISBANE_JITTER_DEG - BRISBANE_JITTER_DEG),
        lng: BRISBANE_LNG + (Math.random() * 2 * BRISBANE_JITTER_DEG - BRISBANE_JITTER_DEG),
    };
}

/**
 * @returns {Promise<{ lat: number, lng: number, source: 'gps' | 'random' }>}
 */
function resolveUserGpsOrRandomBrisbane() {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
        return Promise.resolve({ ...randomPinNearBrisbane(), source: 'random' });
    }
    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                resolve({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    source: 'gps',
                });
            },
            () => {
                resolve({ ...randomPinNearBrisbane(), source: 'random' });
            },
            { enableHighAccuracy: false, timeout: 15000, maximumAge: 60_000 },
        );
    });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * @param {HTMLElement} root
 */
function initWorkLocationRoot(root) {
    const mapEl = root.querySelector('[data-wf-map]');
    const tabManual = root.querySelector('[data-wf-tab="manual"]');
    const tabMap = root.querySelector('[data-wf-tab="map"]');
    const panelManual = root.querySelector('[data-wf-panel="manual"]');
    const panelMap = root.querySelector('[data-wf-panel="map"]');
    const inputLat = root.querySelector('input[type="hidden"][data-wf-lat]');
    const inputLng = root.querySelector('input[type="hidden"][data-wf-lng]');
    const displayLat = root.querySelector('[data-wf-lat-display]');
    const displayLng = root.querySelector('[data-wf-lng-display]');
    const inputAddress = root.querySelector('[data-wf-address]');
    const addressSuggestions = root.querySelector('[data-wf-address-suggestions]');
    const geocodeStatus = root.querySelector('[data-wf-geocode-status]');
    const reverseUrl = root.dataset.reverseUrl;
    const searchUrl = root.dataset.searchUrl;
    const form = root.closest('form');
    const clearAddressBtn = root.querySelector('[data-wf-clear-address]');

    const mapOnly = root.dataset.mapOnly === 'true';
    const lazyMap = root.dataset.lazyMap === 'true';

    if (!mapEl || !inputLat || !inputLng || !reverseUrl) {
        return;
    }

    const mapWrap = mapEl.closest('[data-wf-map-wrap]');
    const mapLoader = mapWrap?.querySelector('[data-wf-map-loader]') ?? null;
    const mapLoaderText = mapWrap?.querySelector('[data-wf-map-loader-text]') ?? null;

    if (!mapOnly) {
        if (!tabManual || !tabMap || !panelManual || !panelMap || !displayLat || !displayLng) {
            return;
        }
    } else if (!displayLat || !displayLng) {
        return;
    }

    const defaultZoom = Number.parseInt(root.dataset.defaultZoom ?? '12', 10);
    const autoLocateZoom = 14;

    let map = null;
    let marker = null;
    let mapInited = false;
    let mapInitPromise = null;
    let hasPin = false;
    let suggestTimer = null;
    let suggestAbort = null;
    let suppressBlurHide = false;
    let applyingSuggestion = false;

    const pinIcon = L.divIcon({
        className: 'wf-leaflet-pin-outer',
        html: '<div class="wf-leaflet-pin-inner" aria-hidden="true"></div>',
        iconSize: [28, 28],
        iconAnchor: [14, 28],
    });

    function setMapSurfaceLoading(visible, text) {
        if (!mapLoader) {
            return;
        }
        if (mapLoaderText && text) {
            mapLoaderText.textContent = text;
        }
        if (visible) {
            mapLoader.classList.remove('hidden');
            mapLoader.classList.add('flex', 'flex-col', 'pointer-events-auto');
            mapLoader.setAttribute('aria-busy', 'true');
        } else {
            mapLoader.classList.add('hidden');
            mapLoader.classList.remove('flex', 'flex-col', 'pointer-events-auto');
            mapLoader.removeAttribute('aria-busy');
        }
    }

    function setStatus(msg, kind) {
        if (!geocodeStatus) return;
        geocodeStatus.classList.remove('wf-is-geocoding');
        geocodeStatus.textContent = msg;
        geocodeStatus.classList.remove('text-brand-text-secondary', 'text-emerald-700', 'text-amber-800', 'text-red-700');
        if (kind === 'ok') geocodeStatus.classList.add('text-emerald-700');
        else if (kind === 'warn') geocodeStatus.classList.add('text-amber-800');
        else if (kind === 'err') geocodeStatus.classList.add('text-red-700');
        else geocodeStatus.classList.add('text-brand-text-secondary');
    }

    function hideSuggestions() {
        if (!addressSuggestions) return;
        addressSuggestions.innerHTML = '';
        addressSuggestions.classList.add('hidden');
    }

    function syncAddressClearVisibility() {
        if (!clearAddressBtn || !inputAddress) return;
        clearAddressBtn.classList.toggle('hidden', inputAddress.value.trim() === '');
    }

    function clearAddressFromControl() {
        if (!inputAddress) return;
        applyingSuggestion = true;
        inputAddress.value = '';
        hideSuggestions();
        hasPin = false;
        inputLat.value = '';
        inputLng.value = '';
        displayLat.value = '';
        displayLng.value = '';
        syncAddressClearVisibility();
        setStatus('Address cleared. Search again or click the map to set a pin.', 'warn');
        if (marker && map) {
            const r = randomPinNearBrisbane();
            marker.setLatLng([r.lat, r.lng]);
            map.panTo([r.lat, r.lng]);
        }
        setTimeout(() => {
            applyingSuggestion = false;
        }, 0);
    }

    function renderHintSuggestion(message, { busy = false } = {}) {
        if (!addressSuggestions) return;
        addressSuggestions.innerHTML = '';
        const row = document.createElement('div');
        row.className = `px-3 py-2 text-xs text-brand-text-secondary${busy ? ' wf-addr-suggest-hint--busy' : ''}`;
        row.textContent = message;
        addressSuggestions.appendChild(row);
        addressSuggestions.classList.remove('hidden');
    }

    function applySuggestion(item) {
        if (!inputAddress) return;
        applyingSuggestion = true;
        inputAddress.value = item.display_name;
        hideSuggestions();
        if (typeof item.lat === 'number' && typeof item.lng === 'number') {
            inputLat.value = item.lat.toFixed(7);
            inputLng.value = item.lng.toFixed(7);
            displayLat.value = inputLat.value;
            displayLng.value = inputLng.value;
            hasPin = true;
            if (marker && map) {
                marker.setLatLng([item.lat, item.lng]);
                map.setView([item.lat, item.lng], Math.max(map.getZoom(), 16));
            } else {
                scheduleEnsureMap();
            }
            setStatus('Address selected. Pin placed — drag the map if you need to fine-tune.', 'ok');
        }
        syncAddressClearVisibility();
        setTimeout(() => {
            applyingSuggestion = false;
        }, 0);
    }

    function renderSuggestions(items) {
        if (!addressSuggestions) return;
        addressSuggestions.innerHTML = '';
        if (!items.length) {
            hideSuggestions();
            return;
        }

        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className =
                'block w-full border-b border-brand-border px-3 py-2.5 text-left text-xs leading-relaxed text-brand-text transition hover:bg-brand-surface focus:bg-brand-surface focus:outline-none last:border-b-0';
            button.textContent = item.display_name;
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                suppressBlurHide = true;
            });
            button.addEventListener('click', () => applySuggestion(item));
            addressSuggestions.appendChild(button);
        });

        addressSuggestions.classList.remove('hidden');
    }

    async function fetchAddressSuggestions(query) {
        if (!searchUrl) return;
        suggestAbort?.abort();
        suggestAbort = new AbortController();
        renderHintSuggestion('Searching OpenStreetMap...', { busy: true });

        try {
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', query);
            const res = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: suggestAbort.signal,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok || !Array.isArray(data.suggestions)) {
                if (res.status === 429) {
                    renderHintSuggestion('Too many lookups just now. Please pause 2-3 seconds and continue typing.');
                } else {
                    renderHintSuggestion(data.message || 'Could not load suggestions right now.');
                }
                return;
            }
            if (data.suggestions.length === 0) {
                renderHintSuggestion('No matching address found.');
                return;
            }
            renderSuggestions(data.suggestions);
        } catch (err) {
            if (err?.name !== 'AbortError') {
                renderHintSuggestion('Could not load suggestions right now.');
            }
        }
    }

    function syncInputsFromMarker() {
        if (!marker || !hasPin) return;
        const ll = marker.getLatLng();
        const la = ll.lat.toFixed(7);
        const ln = ll.lng.toFixed(7);
        inputLat.value = la;
        inputLng.value = ln;
        displayLat.value = la;
        displayLng.value = ln;
    }

    async function reverseGeocode(lat, lng) {
        if (geocodeStatus) {
            geocodeStatus.classList.add('wf-is-geocoding');
            geocodeStatus.textContent = 'Looking up address…';
            geocodeStatus.classList.remove('text-emerald-700', 'text-amber-800', 'text-red-700');
            geocodeStatus.classList.add('text-brand-text-secondary');
        }
        try {
            const res = await fetch(reverseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ lat, lng }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                setStatus(data.message ?? 'Could not resolve address. You can type it below.', 'warn');
                return;
            }
            if (inputAddress && typeof data.display_name === 'string') {
                inputAddress.value = data.display_name;
            }
            setStatus('Address filled from OpenStreetMap. Edit the text if needed.', 'ok');
        } catch {
            setStatus('Network error. Type the address below if needed.', 'err');
        } finally {
            geocodeStatus?.classList.remove('wf-is-geocoding');
            syncAddressClearVisibility();
        }
    }

    function ensureMap() {
        if (mapInited) {
            return Promise.resolve();
        }
        if (mapInitPromise) {
            return mapInitPromise;
        }

                mapInitPromise = (async () => {
            try {
                if (mapLoader) {
                    setMapSurfaceLoading(true, 'Preparing map…');
                }

                const readPinnedCoords = () => {
                    const la = parseFloat(inputLat.value);
                    const ln = parseFloat(inputLng.value);
                    return !Number.isNaN(la) && !Number.isNaN(ln) ? { lat: la, lng: ln } : null;
                };

                let pinned = readPinnedCoords();
                let viewLat;
                let viewLng;
                let viewZoom;
                let fromDevice = null;

                if (pinned) {
                    setMapSurfaceLoading(true, 'Loading map & tiles…');
                    viewLat = pinned.lat;
                    viewLng = pinned.lng;
                    viewZoom = defaultZoom;
                } else {
                    setMapSurfaceLoading(true, 'Finding your location…');
                    setStatus('Detecting your location…', 'neutral');
                    fromDevice = await resolveUserGpsOrRandomBrisbane();
                    pinned = readPinnedCoords();
                    setMapSurfaceLoading(true, 'Loading map & tiles…');
                    if (pinned) {
                        viewLat = pinned.lat;
                        viewLng = pinned.lng;
                        viewZoom = defaultZoom;
                        fromDevice = null;
                    } else {
                        viewLat = fromDevice.lat;
                        viewLng = fromDevice.lng;
                        viewZoom = autoLocateZoom;
                    }
                }

                map = L.map(mapEl, { scrollWheelZoom: true }).setView([viewLat, viewLng], viewZoom);
                const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution:
                        '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a> contributors',
                });
                tileLayer.addTo(map);

                await new Promise((resolve) => {
                    let settled = false;
                    const fin = () => {
                        if (settled) return;
                        settled = true;
                        resolve();
                    };
                    map.whenReady(() => {
                        tileLayer.once('load', fin);
                        setTimeout(fin, 5000);
                    });
                });

                hasPin = true;
                marker = L.marker([viewLat, viewLng], {
                    draggable: true,
                    icon: pinIcon,
                }).addTo(map);
                syncInputsFromMarker();

                setMapSurfaceLoading(false);

                if (pinned && !fromDevice) {
                    setStatus('Pin loaded. Drag to adjust or edit the address above.', 'ok');
                } else if (fromDevice) {
                    if (fromDevice.source === 'gps') {
                        setStatus('Placed from your current location. Drag to fine-tune.', 'ok');
                    } else {
                        setStatus(
                            'Could not detect your location. Placed a random pin near Brisbane — move it to your site.',
                            'warn',
                        );
                    }
                    void reverseGeocode(viewLat, viewLng);
                }

                marker.on('dragend', () => {
                    hasPin = true;
                    syncInputsFromMarker();
                    const ll = marker.getLatLng();
                    void reverseGeocode(ll.lat, ll.lng);
                });

                map.on('click', (e) => {
                    hasPin = true;
                    marker.setLatLng(e.latlng);
                    syncInputsFromMarker();
                    void reverseGeocode(e.latlng.lat, e.latlng.lng);
                });

                setTimeout(() => map?.invalidateSize(), 50);
                mapInited = true;
            } catch (e) {
                setMapSurfaceLoading(false);
                setStatus('Could not start the map. Please reload and try again.', 'err');
                throw e;
            } finally {
                mapInitPromise = null;
            }
        })();
        return mapInitPromise;
    }

    function scheduleEnsureMap() {
        void ensureMap().then(() => {
            setTimeout(() => {
                map?.invalidateSize();
                if (marker && map) {
                    map.panTo(marker.getLatLng());
                }
            }, 120);
        });
    }

    function activateTab(which) {
        if (mapOnly || !tabManual || !tabMap || !panelManual || !panelMap) return;
        const manual = which === 'manual';
        tabManual.setAttribute('aria-selected', manual ? 'true' : 'false');
        tabMap.setAttribute('aria-selected', manual ? 'false' : 'true');
        tabManual.classList.toggle('wf-tab-active', manual);
        tabMap.classList.toggle('wf-tab-active', !manual);
        panelManual.classList.toggle('hidden', !manual);
        panelMap.classList.toggle('hidden', manual);
        if (!manual) {
            scheduleEnsureMap();
        }
    }

    if (!mapOnly) {
        tabManual?.addEventListener('click', () => activateTab('manual'));
        tabMap?.addEventListener('click', () => activateTab('map'));
    }

    root.querySelector('[data-wf-clear-pin]')?.addEventListener('click', () => {
        hasPin = false;
        inputLat.value = '';
        inputLng.value = '';
        displayLat.value = '';
        displayLng.value = '';
        setStatus('Pin cleared. Click the map to place a new one.', 'warn');
        if (marker && map) {
            const r = randomPinNearBrisbane();
            marker.setLatLng([r.lat, r.lng]);
            map.panTo([r.lat, r.lng]);
        }
    });

    clearAddressBtn?.addEventListener('mousedown', (event) => {
        event.preventDefault();
        suppressBlurHide = true;
    });
    clearAddressBtn?.addEventListener('click', () => {
        clearAddressFromControl();
        suppressBlurHide = false;
        inputAddress?.focus();
    });

    if (inputAddress) {
        inputAddress.addEventListener('input', () => {
            const query = inputAddress.value.trim();
            if (!applyingSuggestion) {
                if (!mapOnly) {
                    hasPin = false;
                    inputLat.value = '';
                    inputLng.value = '';
                    displayLat.value = '';
                    displayLng.value = '';
                } else if (query === '') {
                    hasPin = false;
                    inputLat.value = '';
                    inputLng.value = '';
                    displayLat.value = '';
                    displayLng.value = '';
                }
            }
            if (query.length < 2) {
                if (suggestTimer) clearTimeout(suggestTimer);
                hideSuggestions();
                syncAddressClearVisibility();
                return;
            }
            if (suggestTimer) clearTimeout(suggestTimer);
            suggestTimer = setTimeout(() => {
                void fetchAddressSuggestions(query);
            }, 320);
            syncAddressClearVisibility();
        });

        inputAddress.addEventListener('focus', () => {
            const query = inputAddress.value.trim();
            if (query.length >= 2) {
                void fetchAddressSuggestions(query);
            }
        });

        inputAddress.addEventListener('blur', () => {
            setTimeout(() => {
                if (suppressBlurHide) {
                    suppressBlurHide = false;
                    return;
                }
                hideSuggestions();
            }, 140);
        });

        syncAddressClearVisibility();
    }

    form?.addEventListener('submit', (event) => {
        hideSuggestions();
        const usingMap = mapOnly || tabMap?.getAttribute('aria-selected') === 'true';
        if (usingMap && !hasPin) {
            event.preventDefault();
            setStatus('Place a pin on the map before saving.', 'warn');
            window.CruLynkDialog?.toastWarning('Place a pin on the map before saving.');
            scheduleEnsureMap();
        }
    });

    const hostDetails = root.closest('details');
    if (lazyMap && hostDetails) {
        hostDetails.addEventListener('toggle', () => {
            if (hostDetails.open) {
                scheduleEnsureMap();
            }
        });
    } else if (mapOnly) {
        if (mapLoader) {
            setMapSurfaceLoading(true, 'Preparing map…');
        }
        setTimeout(() => {
            void scheduleEnsureMap();
        }, 80);
    } else {
        activateTab('manual');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-wf-loc-root]').forEach((root) => {
        if (root instanceof HTMLElement) {
            initWorkLocationRoot(root);
        }
    });

    initShiftBreaksRepeaters(document);
});

/**
 * @param {ParentNode} root
 */
function initShiftBreaksRepeaters(root) {
    root.querySelectorAll('[data-shift-breaks-repeater]').forEach((repeater) => {
        if (!(repeater instanceof HTMLElement) || repeater.dataset.bound === '1') {
            return;
        }
        repeater.dataset.bound = '1';

        const list = repeater.querySelector('[data-shift-breaks-list]');
        const template = repeater.querySelector('[data-shift-breaks-template]');
        const addBtn = repeater.querySelector('[data-shift-breaks-add]');
        const maxBreaks = Math.max(1, Number(repeater.dataset.maxBreaks || 8));

        if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
            return;
        }

        const reindex = () => {
            list.querySelectorAll('[data-shift-breaks-row]').forEach((row, index) => {
                row.querySelectorAll('input, select').forEach((field) => {
                    const name = field.getAttribute('name');
                    if (!name) return;
                    field.setAttribute(
                        'name',
                        name.replace(/shift_breaks\[\d+]/, `shift_breaks[${index}]`),
                    );
                });
            });
            if (addBtn instanceof HTMLButtonElement) {
                addBtn.disabled = list.querySelectorAll('[data-shift-breaks-row]').length >= maxBreaks;
            }
        };

        addBtn?.addEventListener('click', () => {
            if (list.querySelectorAll('[data-shift-breaks-row]').length >= maxBreaks) {
                return;
            }
            const fragment = template.content.cloneNode(true);
            list.appendChild(fragment);
            reindex();
        });

        list.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const removeBtn = target.closest('[data-shift-breaks-remove]');
            if (!removeBtn) return;
            const row = removeBtn.closest('[data-shift-breaks-row]');
            if (!row) return;

            const rows = list.querySelectorAll('[data-shift-breaks-row]');
            if (rows.length <= 1) {
                row.querySelectorAll('input').forEach((input) => {
                    if (input instanceof HTMLInputElement) {
                        input.value = '';
                    }
                });
                const select = row.querySelector('select');
                if (select instanceof HTMLSelectElement) {
                    select.value = '0';
                }
                return;
            }

            row.remove();
            reindex();
        });

        reindex();
    });
}
