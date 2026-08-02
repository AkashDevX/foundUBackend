/**
 * Global admin header search for applicants / employees.
 */
function normalizeApplicantSearch(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function applicantInitials(name) {
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    if (parts.length === 1 && parts[0]) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return '??';
}

function statusMeta(status) {
    const normalized = String(status || '').toLowerCase();

    if (normalized === 'pending') {
        return { label: 'Pending', classes: 'bg-amber-50 text-amber-900 border-amber-200' };
    }
    if (normalized === 'active') {
        return { label: 'Active', classes: 'bg-emerald-50 text-emerald-900 border-emerald-200' };
    }
    if (normalized === 'inactive') {
        return { label: 'Inactive', classes: 'bg-slate-100 text-slate-800 border-slate-300' };
    }
    if (normalized === 'declined' || normalized === 'rejected') {
        return { label: 'Declined', classes: 'bg-slate-100 text-slate-700 border-slate-200' };
    }

    if (normalized === '') {
        return null;
    }

    return {
        label: normalized.charAt(0).toUpperCase() + normalized.slice(1),
        classes: 'bg-brand-surface text-brand-text-secondary border-brand-border',
    };
}

function initApplicantSearchOverlay() {
    const overlay = document.querySelector('[data-admin-applicant-search-overlay]');
    const openButtons = document.querySelectorAll('[data-admin-applicant-search-open]');
    const closeButtons = document.querySelectorAll('[data-admin-applicant-search-close]');

    if (!overlay) {
        return { open: () => {}, close: () => {} };
    }

    const inlineRoot = document.querySelector('[data-admin-applicant-search][data-search-mode="inline"]');
    const overlayRoot = overlay.querySelector('[data-admin-applicant-search]');
    const isMac = /Mac|iPhone|iPad|iPod/.test(navigator.platform);

    document.querySelectorAll('[data-admin-applicant-search-shortcut]').forEach((badge) => {
        badge.textContent = isMac ? '⌘ K' : 'Ctrl K';
    });

    function focusTarget(root) {
        root?.querySelector('[data-admin-applicant-search-input]')?.focus();
    }

    function openOverlay() {
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        focusTarget(overlayRoot);
    }

    function closeOverlay() {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        overlayRoot?.querySelector('[data-admin-applicant-search-input]')?.blur();
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', openOverlay);
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeOverlay);
    });

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if (window.matchMedia('(min-width: 640px)').matches) {
                focusTarget(inlineRoot);
            } else {
                openOverlay();
            }
            return;
        }

        if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
            closeOverlay();
        }
    });

    return { open: openOverlay, close: closeOverlay };
}

function initAdminApplicantSearch(root, overlayApi) {
    const input = root.querySelector('[data-admin-applicant-search-input]');
    const suggestions = root.querySelector('[data-admin-applicant-search-suggestions]');
    const clearButton = root.querySelector('[data-admin-applicant-search-clear]');
    const loadingIndicator = root.querySelector('[data-admin-applicant-search-loading]');
    const shortcutBadge = root.querySelector('[data-admin-applicant-search-shortcut]');
    const searchUrl = root.dataset.searchUrl;
    const mode = root.dataset.searchMode || 'inline';

    if (!input || !suggestions || !searchUrl) {
        return;
    }

    suggestions.classList.add(
        'absolute',
        'right-0',
        'top-[calc(100%+0.5rem)]',
        'z-[80]',
        'w-[min(24rem,calc(100vw-1.5rem))]',
        'overflow-hidden',
        'rounded-2xl',
        'border',
        'border-brand-border',
        'bg-white',
        'shadow-2xl',
        'ring-1',
        'ring-black/[0.06]',
    );

    if (mode === 'overlay') {
        suggestions.classList.add('static', 'right-auto', 'top-auto', 'z-auto', 'mt-3', 'w-full', 'shadow-xl');
        suggestions.classList.remove('absolute', 'right-0', 'top-[calc(100%+0.5rem)]', 'z-[80]');
    }

    let debounceTimer = null;
    let activeIndex = -1;
    let latestResults = [];
    let requestId = 0;
    let suppressBlurHide = false;
    let isLoading = false;

    function setExpanded(expanded) {
        input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function hideSuggestions() {
        suggestions.innerHTML = '';
        suggestions.classList.add('hidden');
        activeIndex = -1;
        latestResults = [];
        setExpanded(false);
    }

    function showSuggestions() {
        suggestions.classList.remove('hidden');
        setExpanded(true);
    }

    function syncClearVisibility() {
        const hasValue = input.value.trim() !== '';
        if (clearButton) {
            clearButton.classList.toggle('hidden', !hasValue);
        }
        if (shortcutBadge) {
            shortcutBadge.classList.toggle('hidden', hasValue);
        }
    }

    function setLoading(loading) {
        isLoading = loading;
        if (loadingIndicator) {
            loadingIndicator.classList.toggle('hidden', !loading);
        }
        if (clearButton && loading) {
            clearButton.classList.add('hidden');
        }
        if (shortcutBadge && loading) {
            shortcutBadge.classList.add('hidden');
        }
    }

    function renderPanelHeader(title, subtitle) {
        return `
            <div class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-3">
                <p class="text-sm font-bold text-brand-text">${escapeHtml(title)}</p>
                <p class="mt-0.5 text-xs leading-relaxed text-brand-text-secondary">${escapeHtml(subtitle)}</p>
            </div>
        `;
    }

    function renderPanelFooter() {
        return `
            <div class="border-t border-brand-border bg-brand-surface/60 px-4 py-2.5 text-[11px] text-brand-text-secondary">
                <span class="font-semibold text-brand-text">↑↓</span> choose
                <span class="mx-1.5 text-brand-border" aria-hidden="true">·</span>
                <span class="font-semibold text-brand-text">Enter</span> open profile
                <span class="mx-1.5 text-brand-border" aria-hidden="true">·</span>
                <span class="font-semibold text-brand-text">Esc</span> close
            </div>
        `;
    }

    function renderHint(message, title = 'Find an applicant') {
        suggestions.innerHTML =
            renderPanelHeader(title, 'Search by name, email, employee code, phone, or public ID.') +
            `<div class="px-4 py-5 text-sm leading-relaxed text-brand-text-secondary">${escapeHtml(message)}</div>` +
            renderPanelFooter();
        showSuggestions();
    }

    function navigateTo(result) {
        if (!result?.url) {
            return;
        }

        if (mode === 'overlay') {
            overlayApi.close();
        }

        window.location.href = result.url;
    }

    function renderResultRow(result, index) {
        const status = statusMeta(result.status);
        const name = result.name || result.label || 'Applicant';
        const email = result.email && result.email !== name ? result.email : '';
        const code = result.employee_code ? `Code ${result.employee_code}` : '';
        const publicId = result.public_id ? `ID ${result.public_id}` : '';
        const metaLine = [code, publicId].filter(Boolean).join(' · ');
        const isActive = index === activeIndex;

        return `
            <button
                type="button"
                data-index="${index}"
                class="group flex w-full items-center gap-3 px-4 py-3 text-left transition ${isActive ? 'bg-brand-primary/[0.06]' : 'hover:bg-brand-surface'}"
            >
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-sm font-bold text-brand-primary">
                    ${escapeHtml(applicantInitials(name))}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="truncate text-sm font-semibold text-brand-text">${escapeHtml(name)}</span>
                        ${
                            status
                                ? `<span class="inline-flex shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${status.classes}">${escapeHtml(status.label)}</span>`
                                : ''
                        }
                    </span>
                    ${email ? `<span class="mt-0.5 block truncate text-xs text-brand-text-secondary">${escapeHtml(email)}</span>` : ''}
                    ${metaLine ? `<span class="mt-1 block truncate text-[11px] text-brand-text-secondary/80">${escapeHtml(metaLine)}</span>` : ''}
                </span>
                <span class="shrink-0 text-xs font-semibold text-brand-primary opacity-0 transition group-hover:opacity-100 ${isActive ? 'opacity-100' : ''}">
                    Open →
                </span>
            </button>
        `;
    }

    function bindResultButtons() {
        suggestions.querySelectorAll('button[data-index]').forEach((button) => {
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                suppressBlurHide = true;
            });
            button.addEventListener('click', () => {
                const index = Number(button.dataset.index);
                navigateTo(latestResults[index]);
            });
        });
    }

    function renderResults(results) {
        latestResults = results;
        suggestions.innerHTML = renderPanelHeader(
            results.length === 1 ? '1 match found' : `${results.length} matches found`,
            'Select someone to open their registration profile.',
        );

        const list = document.createElement('div');
        list.className = 'max-h-72 overflow-y-auto divide-y divide-brand-border/70';

        if (results.length === 0) {
            list.innerHTML =
                '<div class="px-4 py-8 text-center"><p class="text-sm font-semibold text-brand-text">No matches</p><p class="mt-1 text-xs text-brand-text-secondary">Try a different name, email, or employee ID.</p></div>';
        } else {
            list.innerHTML = results.map((result, index) => renderResultRow(result, index)).join('');
        }

        suggestions.appendChild(list);
        suggestions.insertAdjacentHTML('beforeend', renderPanelFooter());
        bindResultButtons();
        showSuggestions();
    }

    function highlightActive() {
        suggestions.querySelectorAll('button[data-index]').forEach((button, index) => {
            const isActive = index === activeIndex;
            button.classList.toggle('bg-brand-primary/[0.06]', isActive);
            button.querySelector('span:last-child')?.classList.toggle('opacity-100', isActive);
            if (isActive) {
                button.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    async function fetchResults(query) {
        const normalized = normalizeApplicantSearch(query);
        if (normalized.length < 2) {
            setLoading(false);
            hideSuggestions();
            return;
        }

        const currentRequest = ++requestId;
        setLoading(true);

        try {
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Search failed');
            }

            const payload = await response.json();
            if (currentRequest !== requestId) {
                return;
            }

            renderResults(Array.isArray(payload.results) ? payload.results : []);
        } catch {
            if (currentRequest === requestId) {
                renderHint('Could not search applicants right now. Check your connection and try again.', 'Search unavailable');
            }
        } finally {
            if (currentRequest === requestId) {
                setLoading(false);
                syncClearVisibility();
            }
        }
    }

    function scheduleSearch() {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            fetchResults(input.value);
        }, 220);
    }

    function showFocusHint() {
        const normalized = normalizeApplicantSearch(input.value);
        if (normalized.length >= 2) {
            scheduleSearch();
            return;
        }

        renderHint('Type at least 2 characters to start searching.', 'Quick find');
    }

    function clearSearch() {
        input.value = '';
        syncClearVisibility();
        hideSuggestions();
        input.focus();
    }

    input.addEventListener('input', () => {
        activeIndex = -1;
        syncClearVisibility();

        if (normalizeApplicantSearch(input.value).length < 2) {
            window.clearTimeout(debounceTimer);
            setLoading(false);
            hideSuggestions();
            return;
        }

        scheduleSearch();
    });

    input.addEventListener('focus', showFocusHint);

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (suppressBlurHide) {
                suppressBlurHide = false;
                return;
            }
            hideSuggestions();
        }, 160);
    });

    input.addEventListener('keydown', (event) => {
        const buttons = suggestions.querySelectorAll('button[data-index]');

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (buttons.length === 0) {
                showFocusHint();
                return;
            }
            activeIndex = Math.min(activeIndex + 1, buttons.length - 1);
            highlightActive();
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            highlightActive();
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            if (isLoading) {
                return;
            }
            if (activeIndex >= 0 && latestResults[activeIndex]) {
                navigateTo(latestResults[activeIndex]);
                return;
            }
            if (latestResults.length === 1) {
                navigateTo(latestResults[0]);
                return;
            }
            if (latestResults.length > 1) {
                activeIndex = 0;
                highlightActive();
                return;
            }
            if (normalizeApplicantSearch(input.value).length >= 2) {
                fetchResults(input.value);
            }
            return;
        }

        if (event.key === 'Escape') {
            if (mode === 'overlay') {
                overlayApi.close();
            } else {
                hideSuggestions();
                input.blur();
            }
        }
    });

    if (clearButton) {
        clearButton.addEventListener('click', clearSearch);
    }

    syncClearVisibility();
}

document.addEventListener('DOMContentLoaded', () => {
    const overlayApi = initApplicantSearchOverlay();

    document.querySelectorAll('[data-admin-applicant-search]').forEach((root) => {
        initAdminApplicantSearch(root, overlayApi);
    });
});
