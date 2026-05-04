/**
 * OpenStreetMap (Nominatim) address suggestions for admin registration profile (no map).
 */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * @param {HTMLElement} root
 */
function initRegAddressRoot(root) {
    const input = root.querySelector('[data-reg-address]');
    const suggestions = root.querySelector('[data-reg-addr-suggestions]');
    const searchUrl = root.dataset.searchUrl;
    if (!input || !suggestions || !searchUrl) {
        return;
    }

    let suggestTimer = null;
    let suggestAbort = null;
    let suppressBlurHide = false;

    function hideSuggestions() {
        suggestions.innerHTML = '';
        suggestions.classList.add('hidden');
    }

    function renderHint(message, busy = false) {
        suggestions.innerHTML = '';
        const row = document.createElement('div');
        row.className = `px-3 py-2 text-xs text-brand-text-secondary${busy ? ' opacity-80' : ''}`;
        row.textContent = message;
        suggestions.appendChild(row);
        suggestions.classList.remove('hidden');
    }

    function applySuggestion(item) {
        input.value = item.display_name;
        hideSuggestions();
    }

    async function fetchSuggestions(query) {
        suggestAbort?.abort();
        suggestAbort = new AbortController();
        renderHint('Searching OpenStreetMap…', true);
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
                renderHint(data.message || 'Could not load suggestions.');
                return;
            }
            if (data.suggestions.length === 0) {
                renderHint('No matching address found.');
                return;
            }
            suggestions.innerHTML = '';
            data.suggestions.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className =
                    'block w-full border-b border-brand-border px-3 py-2.5 text-left text-xs leading-relaxed text-brand-text transition hover:bg-brand-surface focus:bg-brand-surface focus:outline-none last:border-b-0';
                button.textContent = item.display_name;
                button.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    suppressBlurHide = true;
                });
                button.addEventListener('click', () => applySuggestion(item));
                suggestions.appendChild(button);
            });
            suggestions.classList.remove('hidden');
        } catch (err) {
            if (err?.name !== 'AbortError') {
                renderHint('Could not load suggestions right now.');
            }
        }
    }

    input.addEventListener('input', () => {
        const query = input.value.trim();
        if (query.length < 2) {
            if (suggestTimer) clearTimeout(suggestTimer);
            hideSuggestions();
            return;
        }
        if (suggestTimer) clearTimeout(suggestTimer);
        suggestTimer = setTimeout(() => {
            void fetchSuggestions(query);
        }, 320);
    });

    input.addEventListener('focus', () => {
        const query = input.value.trim();
        if (query.length >= 2) {
            void fetchSuggestions(query);
        }
    });

    input.addEventListener('blur', () => {
        setTimeout(() => {
            if (suppressBlurHide) {
                suppressBlurHide = false;
                return;
            }
            hideSuggestions();
        }, 140);
    });
}

/**
 * Show vehicle fields only when mode of transport matches "Own vehicle" (case-insensitive).
 */
function initTransportVehicle(select) {
    const ownRaw = select.dataset.regOwnValue?.trim() ?? 'Own vehicle';
    const block = document.querySelector('[data-reg-vehicle-fields]');
    if (!block) {
        return;
    }

    function sync() {
        const v = select.value.trim();
        const own = v.localeCompare(ownRaw, undefined, { sensitivity: 'accent' }) === 0;
        block.classList.toggle('hidden', !own);
        block.querySelectorAll('input, select, textarea').forEach((el) => {
            if (el instanceof HTMLInputElement && el.type === 'hidden') {
                return;
            }
            el.toggleAttribute('disabled', !own);
        });
    }

    select.addEventListener('change', sync);
    sync();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-reg-addr-root]').forEach((root) => {
        if (root instanceof HTMLElement) {
            initRegAddressRoot(root);
        }
    });

    const modeSelect = document.querySelector('[data-reg-mode-transport]');
    if (modeSelect instanceof HTMLSelectElement) {
        initTransportVehicle(modeSelect);
    }
});
