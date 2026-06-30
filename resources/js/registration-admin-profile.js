/**
 * Admin registration profile: address (Nominatim), photo preview, bank mask, ID doc preview.
 */
import './cruLynkDialogs';

function initRegAddressRoot(root) {
    const input = root.querySelector('[data-reg-address]');
    const suggestions = root.querySelector('[data-reg-addr-suggestions]');
    const clearBtn = root.querySelector('[data-reg-address-clear]');
    const searchUrl = root.dataset.searchUrl;
    if (!input || !suggestions || !searchUrl) {
        return;
    }

    if (suggestions.parentElement !== document.body) {
        document.body.appendChild(suggestions);
    }
    suggestions.classList.add(
        'fixed',
        'z-[9999]',
        'max-h-52',
        'overflow-y-auto',
        'overflow-x-hidden',
        'rounded-xl',
        'border',
        'border-brand-border',
        'bg-white',
        'py-1',
        'shadow-2xl',
        'ring-1',
        'ring-black/10',
    );
    suggestions.classList.remove('absolute', 'left-0', 'right-0', 'top-full', 'z-[200]', 'mt-1.5');

    let suggestTimer = null;
    let suggestAbort = null;
    let suppressBlurHide = false;

    function positionSuggestions() {
        const rect = input.getBoundingClientRect();
        const gap = 6;
        const preferredMax = 208;
        const spaceBelow = window.innerHeight - rect.bottom - gap;
        const spaceAbove = rect.top - gap;

        suggestions.style.left = `${Math.max(8, rect.left)}px`;
        suggestions.style.width = `${rect.width}px`;
        suggestions.style.right = 'auto';

        if (spaceBelow < 140 && spaceAbove > spaceBelow) {
            const maxHeight = Math.min(preferredMax, Math.max(96, spaceAbove - 8));
            suggestions.style.top = 'auto';
            suggestions.style.bottom = `${window.innerHeight - rect.top + gap}px`;
            suggestions.style.maxHeight = `${maxHeight}px`;
        } else {
            const maxHeight = Math.min(preferredMax, Math.max(96, spaceBelow - 8));
            suggestions.style.bottom = 'auto';
            suggestions.style.top = `${rect.bottom + gap}px`;
            suggestions.style.maxHeight = `${maxHeight}px`;
        }
    }

    function showSuggestions() {
        positionSuggestions();
        suggestions.classList.remove('hidden');
    }

    function syncClearVisibility() {
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', input.value.trim() === '');
        }
    }

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
        showSuggestions();
    }

    function applySuggestion(item) {
        input.value = item.display_name;
        hideSuggestions();
        syncClearVisibility();
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
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
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
                    'block w-full border-b border-brand-border/80 px-3 py-2.5 text-left text-xs leading-relaxed text-brand-text transition hover:bg-brand-surface focus:bg-brand-surface focus:outline-none last:border-b-0';
                button.textContent = item.display_name;
                button.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    suppressBlurHide = true;
                });
                button.addEventListener('click', () => applySuggestion(item));
                suggestions.appendChild(button);
            });
            showSuggestions();
        } catch (err) {
            if (err?.name !== 'AbortError') {
                renderHint('Could not load suggestions right now.');
            }
        }
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            input.value = '';
            hideSuggestions();
            syncClearVisibility();
            input.focus();
        });
    }

    input.addEventListener('input', () => {
        syncClearVisibility();
        const query = input.value.trim();
        if (query.length < 2) {
            if (suggestTimer) clearTimeout(suggestTimer);
            hideSuggestions();
            return;
        }
        if (suggestTimer) clearTimeout(suggestTimer);
        suggestTimer = setTimeout(() => void fetchSuggestions(query), 320);
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
        }, 160);
    });

    suggestions.addEventListener('mousedown', (e) => {
        e.preventDefault();
        suppressBlurHide = true;
    });

    const repositionIfOpen = () => {
        if (!suggestions.classList.contains('hidden')) {
            positionSuggestions();
        }
    };
    window.addEventListener('scroll', repositionIfOpen, true);
    window.addEventListener('resize', repositionIfOpen);

    syncClearVisibility();
}

function initRegPhotoRoot(root) {
    const fileInput = root.querySelector('[data-reg-photo-input]');
    const preview = root.querySelector('[data-reg-photo-preview]');
    const current = root.querySelector('[data-reg-photo-current]');
    const empty = root.querySelector('[data-reg-photo-empty]');
    const filenameEl = root.querySelector('[data-reg-photo-filename]');
    if (!fileInput) {
        return;
    }

    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) {
            return;
        }
        if (filenameEl) {
            filenameEl.textContent = `Selected: ${file.name}`;
            filenameEl.hidden = false;
        }
        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result !== 'string' || !preview) {
                return;
            }
            preview.src = reader.result;
            preview.classList.remove('hidden');
            current?.classList.add('hidden');
            empty?.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    });
}

function initRegBankAccount(input) {
    const masked = input.dataset.bankMasked ?? '';
    if (!masked) {
        return;
    }
    let editing = false;
    input.addEventListener('focus', () => {
        if (editing) return;
        editing = true;
        if (input.value === masked) {
            input.value = '';
        }
    });
    input.addEventListener('blur', () => {
        editing = false;
        if (input.value.trim() === '') {
            input.value = masked;
        }
    });
    input.closest('form')?.addEventListener('submit', () => {
        if (input.value === masked) {
            input.value = '';
        }
    });
}

function initRegDocInput(input) {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            return;
        }
        const card = input.closest('[data-reg-id-doc-card]');
        const filenameEl = card?.querySelector('[data-reg-doc-filename]');
        if (filenameEl) {
            filenameEl.textContent = `Selected: ${file.name}`;
            filenameEl.hidden = false;
        }
        if (!file.type.startsWith('image/')) {
            return;
        }
        const preview = card?.querySelector('[data-reg-doc-preview]');
        if (!(preview instanceof HTMLImageElement)) {
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result === 'string') {
                preview.src = reader.result;
                preview.classList.remove('hidden');
            }
        };
        reader.readAsDataURL(file);
    });
}

const WEEKLY_DAY_LABELS = {
    mon: 'Mon',
    tue: 'Tue',
    wed: 'Wed',
    thu: 'Thu',
    fri: 'Fri',
    sat: 'Sat',
    sun: 'Sun',
};

function initWeeklyCalendar(root) {
    const cellOn =
        'flex aspect-square w-full min-w-[2.25rem] max-w-[2.75rem] items-center justify-center rounded-xl border text-sm font-bold transition duration-150 border-brand-primary bg-brand-primary text-white shadow-sm shadow-brand-primary/25';
    const cellOff =
        'flex aspect-square w-full min-w-[2.25rem] max-w-[2.75rem] items-center justify-center rounded-xl border text-sm font-bold transition duration-150 border-brand-border/80 bg-brand-surface/70 text-brand-text-secondary/50';

    function syncCell(checkbox) {
        const label = checkbox.closest('label');
        const visual = label?.querySelector('[data-reg-weekly-cell]');
        if (!(visual instanceof HTMLElement)) {
            return;
        }
        const on = checkbox.checked;
        visual.className = `${on ? cellOn : cellOff} peer-focus-visible:ring-2 peer-focus-visible:ring-brand-primary/40`;
        visual.textContent = on ? '✓' : '–';
    }

    function buildStatusText() {
        const parts = [];
        root.querySelectorAll('input[type="checkbox"][data-reg-day][data-reg-period]').forEach((input) => {
            if (!(input instanceof HTMLInputElement) || !input.checked) {
                return;
            }
            const day = input.dataset.regDay ?? '';
            const period = input.dataset.regPeriod ?? '';
            const dayLabel = WEEKLY_DAY_LABELS[day] ?? day;
            if (!dayLabel || !period) {
                return;
            }
            parts.push({ day, dayLabel, period });
        });

        const byDay = new Map();
        for (const item of parts) {
            const existing = byDay.get(item.day) ?? { label: item.dayLabel, morning: false, evening: false };
            if (item.period === 'morning') {
                existing.morning = true;
            }
            if (item.period === 'evening') {
                existing.evening = true;
            }
            byDay.set(item.day, existing);
        }

        const lines = [];
        for (const entry of byDay.values()) {
            const slotLabels = [];
            if (entry.morning) {
                slotLabels.push('Morning');
            }
            if (entry.evening) {
                slotLabels.push('Evening');
            }
            if (slotLabels.length > 0) {
                lines.push(`${entry.label}: ${slotLabels.join(', ')}`);
            }
        }

        return lines.length === 0 ? 'No time blocks selected yet.' : lines.join(' · ');
    }

    function syncAll() {
        root.querySelectorAll('input[type="checkbox"][data-reg-day]').forEach((input) => {
            if (input instanceof HTMLInputElement) {
                syncCell(input);
            }
        });
        const status = root.querySelector('[data-reg-weekly-status]');
        if (status) {
            status.textContent = buildStatusText();
        }
    }

    root.querySelectorAll('input[type="checkbox"][data-reg-day]').forEach((input) => {
        if (input instanceof HTMLInputElement) {
            input.addEventListener('change', syncAll);
        }
    });

    syncAll();
}

function initUnrestrictedVisaFields(select) {
    const block = document.querySelector('[data-reg-visa-expiry-field]');
    if (!block) {
        return;
    }
    function isYes(value) {
        return value.trim().localeCompare('yes', undefined, { sensitivity: 'accent' }) === 0;
    }
    function sync() {
        const show = !isYes(select.value);
        block.classList.toggle('hidden', !show);
        block.querySelectorAll('input, select, textarea').forEach((el) => {
            el.toggleAttribute('disabled', !show);
        });
    }
    select.addEventListener('change', sync);
    sync();
}

function initTransportVehicle(select) {
    const ownRaw = select.dataset.regOwnValue?.trim() ?? 'Own vehicle';
    const block = document.querySelector('[data-reg-vehicle-fields]');
    if (!block) {
        return;
    }
    function sync() {
        const own = select.value.trim().localeCompare(ownRaw, undefined, { sensitivity: 'accent' }) === 0;
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

function bootRegistrationAdminProfile() {
    document.querySelectorAll('[data-reg-addr-root]').forEach((root) => {
        if (root instanceof HTMLElement) {
            initRegAddressRoot(root);
        }
    });
    document.querySelectorAll('[data-reg-photo-root]').forEach((root) => {
        if (root instanceof HTMLElement) {
            initRegPhotoRoot(root);
        }
    });
    document.querySelectorAll('[data-reg-bank-account]').forEach((el) => {
        if (el instanceof HTMLInputElement) {
            initRegBankAccount(el);
        }
    });
    document.querySelectorAll('[data-reg-doc-input]').forEach((el) => {
        if (el instanceof HTMLInputElement) {
            initRegDocInput(el);
        }
    });
    const unrestrictedSelect = document.querySelector('[data-reg-unrestricted-work-rights]');
    if (unrestrictedSelect instanceof HTMLSelectElement) {
        initUnrestrictedVisaFields(unrestrictedSelect);
    }
    const modeSelect = document.querySelector('[data-reg-mode-transport]');
    if (modeSelect instanceof HTMLSelectElement) {
        initTransportVehicle(modeSelect);
    }
    document.querySelectorAll('[data-reg-weekly-calendar]').forEach((root) => {
        if (root instanceof HTMLElement) {
            initWeeklyCalendar(root);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootRegistrationAdminProfile);
} else {
    bootRegistrationAdminProfile();
}
