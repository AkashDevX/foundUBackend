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

function initRegPhotoRemovals() {
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const removeBtn = target.closest('[data-reg-photo-remove]');
        if (!(removeBtn instanceof HTMLButtonElement)) {
            return;
        }
        event.preventDefault();
        const root = removeBtn.closest('[data-reg-photo-root]');
        if (!(root instanceof HTMLElement)) {
            return;
        }
        const fileInput = root.querySelector('[data-reg-photo-input]');
        const preview = root.querySelector('[data-reg-photo-preview]');
        const current = root.querySelector('[data-reg-photo-current]');
        const empty = root.querySelector('[data-reg-photo-empty]');
        const filenameEl = root.querySelector('[data-reg-photo-filename]');
        const removeFlag = root.querySelector('[data-reg-photo-remove-flag]');
        const replaceLabelText = root.querySelector('[data-reg-photo-replace-text]');

        if (removeFlag instanceof HTMLInputElement) {
            removeFlag.value = '1';
        }
        if (fileInput instanceof HTMLInputElement) {
            fileInput.value = '';
        }
        if (filenameEl instanceof HTMLElement) {
            filenameEl.textContent = 'Marked for removal — save the form to apply.';
            filenameEl.hidden = false;
        }
        preview?.classList.add('hidden');
        preview?.removeAttribute('src');
        current?.classList.add('hidden');
        empty?.classList.remove('hidden');
        removeBtn.classList.add('hidden');
        if (replaceLabelText instanceof HTMLElement) {
            replaceLabelText.textContent = 'Upload photo';
        }
    });
}

function initRegPhotoRoot(root) {
    const fileInput = root.querySelector('[data-reg-photo-input]');
    const preview = root.querySelector('[data-reg-photo-preview]');
    const current = root.querySelector('[data-reg-photo-current]');
    const empty = root.querySelector('[data-reg-photo-empty]');
    const filenameEl = root.querySelector('[data-reg-photo-filename]');
    const removeFlag = root.querySelector('[data-reg-photo-remove-flag]');
    const replaceLabelText = root.querySelector('[data-reg-photo-replace-text]');
    if (!fileInput) {
        return;
    }

    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) {
            return;
        }
        if (removeFlag instanceof HTMLInputElement) {
            removeFlag.value = '0';
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

function setDocStatus(root, uploaded) {
    const status = root?.querySelector('[data-reg-doc-status]');
    if (!(status instanceof HTMLElement)) {
        return;
    }
    if (uploaded) {
        status.className =
            'inline-flex items-center gap-1.5 rounded-full bg-emerald-600/15 px-2.5 py-1 text-xs font-bold text-emerald-800';
        status.innerHTML =
            '<svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> Uploaded';
    } else {
        status.className = 'text-xs font-medium text-brand-text-secondary';
        status.textContent = 'No file yet';
    }
}

function setDocCardRemoved(root, removed) {
    const card = root?.closest('[data-reg-id-doc-card]');
    if (!(card instanceof HTMLElement)) {
        return;
    }
    card.classList.toggle('border-emerald-200/90', !removed);
    card.classList.toggle('bg-emerald-50/40', !removed);
    card.classList.toggle('border-brand-border', removed);
    card.classList.toggle('bg-brand-surface/40', removed);
}

function clearDocPreview(root) {
    const wrap = root?.querySelector('[data-reg-doc-preview-wrap]');
    if (!(wrap instanceof HTMLElement)) {
        return;
    }
    wrap.innerHTML =
        '<p class="px-3 py-6 text-center text-xs text-brand-text-secondary" data-reg-doc-preview-empty>No file — save the form to apply removal.</p>';
}

function handleDocRemove(root) {
    const input = root.querySelector('[data-reg-doc-input]');
    const removeBtn = root.querySelector('[data-reg-doc-remove]');
    const removeFlag = root.querySelector('[data-reg-doc-remove-flag]');
    const filenameEl = root.querySelector('[data-reg-doc-filename]');
    const replaceLabel = root.querySelector('[data-reg-doc-replace]');

    if (removeFlag instanceof HTMLInputElement) {
        removeFlag.value = '1';
    }
    if (input instanceof HTMLInputElement) {
        input.value = '';
    }
    if (filenameEl instanceof HTMLElement) {
        filenameEl.textContent = 'Marked for removal — save the form to apply.';
        filenameEl.hidden = false;
    }
    clearDocPreview(root);
    setDocStatus(root, false);
    setDocCardRemoved(root, true);
    removeBtn?.classList.add('hidden');
    if (replaceLabel instanceof HTMLElement) {
        replaceLabel.textContent = 'Upload file';
    }
}

function initRegDocRoot(root) {
    const input = root.querySelector('[data-reg-doc-input]');
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            return;
        }
        const removeFlag = root.querySelector('[data-reg-doc-remove-flag]');
        const filenameEl = root.querySelector('[data-reg-doc-filename]');
        const removeBtn = root.querySelector('[data-reg-doc-remove]');
        const replaceLabel = root.querySelector('[data-reg-doc-replace]');

        if (removeFlag instanceof HTMLInputElement) {
            removeFlag.value = '0';
        }
        setDocCardRemoved(root, false);
        removeBtn?.classList.remove('hidden');
        if (replaceLabel instanceof HTMLElement) {
            replaceLabel.textContent = 'Replace file';
        }
        if (filenameEl instanceof HTMLElement) {
            filenameEl.textContent = `Selected: ${file.name}`;
            filenameEl.hidden = false;
        }
        if (!file.type.startsWith('image/')) {
            return;
        }
        const wrap = root.querySelector('[data-reg-doc-preview-wrap]');
        if (!(wrap instanceof HTMLElement)) {
            return;
        }
        wrap.innerHTML = '<img src="" alt="Selected file preview" class="max-h-72 w-full object-contain" data-reg-doc-preview />';
        const preview = root.querySelector('[data-reg-doc-preview]');
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

function initRegDocRemovals() {
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const removeBtn = target.closest('[data-reg-doc-remove]');
        if (!(removeBtn instanceof HTMLButtonElement)) {
            return;
        }
        event.preventDefault();
        const root = removeBtn.closest('[data-reg-doc-root]');
        if (!(root instanceof HTMLElement)) {
            return;
        }
        handleDocRemove(root);
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

const WEEKLY_DAY_LABELS = {
    mon: 'Mon',
    tue: 'Tue',
    wed: 'Wed',
    thu: 'Thu',
    fri: 'Fri',
    sat: 'Sat',
    sun: 'Sun',
};

function initDaySchedule(root) {
    const template = root.querySelector('[data-reg-period-template]');

    function reindex(dayEl) {
        const day = dayEl.dataset.regDay ?? '';
        const rows = dayEl.querySelectorAll('[data-reg-period-row]');
        rows.forEach((row, index) => {
            row.querySelectorAll('input[data-reg-time]').forEach((input) => {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }
                const field = input.dataset.regTime === 'end' ? 'end' : 'start';
                input.name = `availability[${day}][periods][${index}][${field}]`;
            });
            const remove = row.querySelector('[data-reg-remove-period]');
            if (remove instanceof HTMLElement) {
                remove.classList.toggle('invisible', rows.length < 2);
            }
        });
    }

    function hhmm(value) {
        const match = value.match(/^(\d{2}:\d{2})/);
        return match ? match[1] : '';
    }

    function syncDay(dayEl) {
        const status = dayEl.querySelector('[data-reg-day-status]');
        const periods = dayEl.querySelector('[data-reg-periods]');
        const note = dayEl.querySelector('[data-reg-unavailable-note]');
        const available = !(status instanceof HTMLSelectElement) || status.value === 'available';
        periods?.classList.toggle('hidden', !available);
        if (periods instanceof HTMLElement) {
            periods.classList.toggle('mt-3', available);
        }
        note?.classList.toggle('hidden', available);
        dayEl.querySelectorAll('[data-reg-period-row]').forEach((row) => {
            const start = row.querySelector('[data-reg-time="start"]');
            const end = row.querySelector('[data-reg-time="end"]');
            const hint = row.querySelector('[data-reg-overnight]');
            const startValue = hhmm(start instanceof HTMLInputElement ? start.value : '');
            const endValue = hhmm(end instanceof HTMLInputElement ? end.value : '');
            const overnight = startValue !== '' && endValue !== '' && endValue < startValue;
            hint?.classList.toggle('hidden', !overnight);
            row.querySelectorAll('input').forEach((input) => {
                if (input instanceof HTMLInputElement) {
                    input.toggleAttribute('disabled', !available);
                }
            });
        });
    }

    function buildStatusText() {
        const lines = [];
        root.querySelectorAll('[data-reg-day]').forEach((dayEl) => {
            if (!(dayEl instanceof HTMLElement)) {
                return;
            }
            const day = dayEl.dataset.regDay ?? '';
            const label = WEEKLY_DAY_LABELS[day] ?? day;
            const status = dayEl.querySelector('[data-reg-day-status]');
            const available = status instanceof HTMLSelectElement && status.value === 'available';
            if (!available) {
                lines.push(`${label}: Not available`);
                return;
            }
            const ranges = [];
            dayEl.querySelectorAll('[data-reg-period-row]').forEach((row) => {
                const start = row.querySelector('[data-reg-time="start"]');
                const end = row.querySelector('[data-reg-time="end"]');
                const startValue = hhmm(start instanceof HTMLInputElement ? start.value : '');
                const endValue = hhmm(end instanceof HTMLInputElement ? end.value : '');
                if (!/^\d{2}:\d{2}$/.test(startValue) || !/^\d{2}:\d{2}$/.test(endValue) || startValue === endValue) {
                    return;
                }
                ranges.push(endValue < startValue ? `${startValue}–${endValue} (overnight)` : `${startValue}–${endValue}`);
            });
            lines.push(ranges.length ? `${label}: ${ranges.join(', ')}` : `${label}: Available`);
        });
        return lines.length ? lines.join(' · ') : 'No availability entered yet.';
    }

    function syncAll() {
        root.querySelectorAll('[data-reg-day]').forEach((dayEl) => {
            if (dayEl instanceof HTMLElement) {
                syncDay(dayEl);
            }
        });
        const status = root.querySelector('[data-reg-weekly-status]');
        if (status) {
            status.textContent = buildStatusText();
        }
    }

    root.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const add = target.closest('[data-reg-add-period]');
        if (add && template instanceof HTMLTemplateElement) {
            const dayEl = add.closest('[data-reg-day]');
            const list = dayEl?.querySelector('[data-reg-period-list]');
            if (dayEl instanceof HTMLElement && list) {
                list.appendChild(template.content.cloneNode(true));
                reindex(dayEl);
                syncAll();
            }
            return;
        }
        const remove = target.closest('[data-reg-remove-period]');
        if (remove) {
            const dayEl = remove.closest('[data-reg-day]');
            const row = remove.closest('[data-reg-period-row]');
            if (dayEl instanceof HTMLElement && row && dayEl.querySelectorAll('[data-reg-period-row]').length > 1) {
                row.remove();
                reindex(dayEl);
                syncAll();
            }
        }
    });

    root.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        if (target.matches('[data-reg-day-status], input[data-reg-time]')) {
            syncAll();
        }
    });

    root.querySelectorAll('[data-reg-day]').forEach((dayEl) => {
        if (dayEl instanceof HTMLElement) {
            reindex(dayEl);
        }
    });
    syncAll();
}

function requiresVisaDocument(value) {
    const normalized = value.trim().toLowerCase();
    if (!normalized) {
        return false;
    }
    return !normalized.includes('citizen') && !normalized.includes('permanent resident');
}

function initVisaDocumentField(select) {
    const block = document.querySelector('[data-reg-visa-document-field]');
    if (!block) {
        return;
    }
    function sync() {
        const show = requiresVisaDocument(select.value);
        block.classList.toggle('hidden', !show);
        block.querySelectorAll('input, select, textarea, button').forEach((el) => {
            el.toggleAttribute('disabled', !show);
        });
    }
    select.addEventListener('change', sync);
    sync();
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

function isDriversLicenceType(value) {
    const normalized = value.trim().toLowerCase().replace(/[’`]/g, "'");
    return normalized.includes('driver') && normalized.includes('licen');
}

function initIdDocumentCard(card) {
    const select = card.querySelector('select[name^="id_document_type["]');
    const back = card.querySelector('[data-reg-id-doc-back]');
    const frontLabel = card.querySelector('[data-reg-id-doc-front-label]');
    if (!(select instanceof HTMLSelectElement) || !(back instanceof HTMLElement)) {
        return;
    }
    function sync() {
        const licence = isDriversLicenceType(select.value);
        const keepBack = licence || back.dataset.regIdDocBackHasFile === '1';
        back.classList.toggle('hidden', !keepBack);
        back.querySelectorAll('input, button').forEach((el) => {
            el.toggleAttribute('disabled', !keepBack);
        });
        if (frontLabel instanceof HTMLElement) {
            frontLabel.classList.toggle('hidden', !licence);
        }
    }
    select.addEventListener('change', sync);
    sync();
}

function initOtherDocumentType(card) {
    const select = card.querySelector('[data-reg-doc-type]');
    const block = card.querySelector('[data-reg-doc-type-other]');
    if (!(select instanceof HTMLSelectElement) || !(block instanceof HTMLElement)) {
        return;
    }
    function sync() {
        const show = select.value.trim().toLowerCase() === 'other';
        block.classList.toggle('hidden', !show);
        block.querySelectorAll('input, textarea').forEach((el) => {
            el.toggleAttribute('disabled', !show);
            if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) {
                el.required = show;
            }
        });
    }
    select.addEventListener('change', sync);
    sync();
}

function bootRegistrationAdminProfile() {
    initRegDocRemovals();
    initRegPhotoRemovals();
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
    document.querySelectorAll('[data-reg-doc-root]').forEach((root) => {
        if (root instanceof HTMLElement) {
            initRegDocRoot(root);
        }
    });
    document.querySelectorAll('[data-reg-id-doc-card]').forEach((card) => {
        if (card instanceof HTMLElement) {
            initOtherDocumentType(card);
            initIdDocumentCard(card);
        }
    });
    const unrestrictedSelect = document.querySelector('[data-reg-unrestricted-work-rights]');
    if (unrestrictedSelect instanceof HTMLSelectElement) {
        initUnrestrictedVisaFields(unrestrictedSelect);
    }
    const visaStatusSelect = document.querySelector('[data-reg-visa-status]');
    if (visaStatusSelect instanceof HTMLSelectElement) {
        initVisaDocumentField(visaStatusSelect);
    }
    const modeSelect = document.querySelector('[data-reg-mode-transport]');
    if (modeSelect instanceof HTMLSelectElement) {
        initTransportVehicle(modeSelect);
    }
    document.querySelectorAll('[data-reg-day-schedule]').forEach((root) => {
        if (root instanceof HTMLElement) {
            initDaySchedule(root);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootRegistrationAdminProfile);
} else {
    bootRegistrationAdminProfile();
}
