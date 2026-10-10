document.addEventListener('DOMContentLoaded', () => {
    const filterToggle = document.querySelector('[data-timesheet-filter-toggle]');
    const filterPanel = document.querySelector('[data-timesheet-filter-panel]');
    const modal = document.getElementById('timesheet-fullscreen-modal');
    const modalPanel = document.querySelector('[data-timesheet-modal-panel]');
    const modalScroll = document.querySelector('[data-timesheet-modal-scroll]');

    if (filterToggle && filterPanel) {
        filterToggle.addEventListener('click', () => {
            const isHidden = filterPanel.classList.contains('hidden');
            filterPanel.classList.toggle('hidden', !isHidden);
            filterToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });
    }

    if (modal) {
        document.body.classList.add('overflow-hidden');

        const closeUrl = modal.getAttribute('data-close-url');
        const navigateToClose = () => {
            if (closeUrl) {
                window.location.href = closeUrl;
            }
        };

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                navigateToClose();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                navigateToClose();
            }
        });

        modalPanel?.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    if (modalScroll) {
        modalScroll.addEventListener(
            'wheel',
            (event) => {
                if (!event.shiftKey || modalScroll.scrollWidth <= modalScroll.clientWidth) {
                    return;
                }

                event.preventDefault();
                modalScroll.scrollLeft += event.deltaY;
            },
            { passive: false },
        );
    }

    initShiftDurationTips();
    initOlderWeekLoader();
});

function initOlderWeekLoader() {
    const wrap = document.querySelector('[data-time-clock-load-more-wrap]');
    const button = document.querySelector('[data-time-clock-load-more]');
    const status = document.querySelector('[data-time-clock-load-more-status]');
    const tbody = document.querySelector('[data-time-clock-week-rows]');

    if (!(button instanceof HTMLButtonElement) || !(tbody instanceof HTMLElement) || !(wrap instanceof HTMLElement)) {
        return;
    }

    let loading = false;

    const setStatus = (message, isError = false) => {
        if (!(status instanceof HTMLElement)) {
            return;
        }

        if (!message) {
            status.classList.add('hidden');
            status.textContent = '';
            return;
        }

        status.textContent = message;
        status.classList.remove('hidden');
        status.classList.toggle('text-red-700', isError);
        status.classList.toggle('text-brand-text-secondary', !isError);
    };

    const syncOldest = (oldest) => {
        button.setAttribute('data-before', oldest);
        const pageUrl = new URL(window.location.href);
        pageUrl.searchParams.set('before', oldest);
        window.history.replaceState({}, '', pageUrl);

        tbody.querySelectorAll('a[href]').forEach((link) => {
            if (!(link instanceof HTMLAnchorElement)) {
                return;
            }

            const linkUrl = new URL(link.href, window.location.origin);
            linkUrl.searchParams.set('before', oldest);
            link.href = linkUrl.toString();
        });
    };

    const loadNext = async () => {
        if (loading) {
            return null;
        }

        const before = button.getAttribute('data-before');
        const endpoint = button.getAttribute('data-url');
        if (!before || !endpoint) {
            return null;
        }

        loading = true;
        button.disabled = true;
        setStatus('Loading older weeks…');

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('before', before);
            const week = new URL(window.location.href).searchParams.get('week');
            if (week) {
                url.searchParams.set('week', week);
            }

            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Could not load older weeks.');
            }

            const data = await response.json();
            if (typeof data.html === 'string' && data.html.trim() !== '') {
                tbody.insertAdjacentHTML('beforeend', data.html);
            }

            const oldest = typeof data.oldest_week === 'string' ? data.oldest_week : before;
            syncOldest(oldest);

            if (!data.has_more) {
                wrap.classList.add('hidden');
            } else {
                wrap.classList.remove('hidden');
                button.disabled = false;
            }

            setStatus('');

            return data;
        } catch {
            button.disabled = false;
            setStatus('Could not load older weeks. Try again.', true);

            return null;
        } finally {
            loading = false;
        }
    };

    button.addEventListener('click', () => {
        loadNext();
    });

    const requested = new URL(window.location.href).searchParams.get('before');
    const rendered = button.getAttribute('data-before');
    if (requested && rendered && requested < rendered && !wrap.classList.contains('hidden')) {
        const restore = async () => {
            let guard = 0;
            let oldest = rendered;

            while (requested < oldest && guard < 30 && !wrap.classList.contains('hidden')) {
                const data = await loadNext();
                guard += 1;
                if (!data || typeof data.oldest_week !== 'string' || data.oldest_week >= oldest) {
                    break;
                }

                oldest = data.oldest_week;
                if (!data.has_more) {
                    break;
                }
            }
        };

        restore();
    }
}

function initShiftDurationTips() {
    /** @type {HTMLDivElement | null} */
    let tipEl = null;
    /** @type {HTMLElement | null} */
    let activeTrigger = null;

    const ensureTip = () => {
        if (tipEl) {
            return tipEl;
        }

        tipEl = document.createElement('div');
        tipEl.setAttribute('role', 'tooltip');
        tipEl.className =
            'pointer-events-none fixed z-[80] hidden w-64 rounded-lg border border-brand-border bg-white p-2.5 text-left shadow-lg ring-1 ring-black/[0.04]';
        document.body.appendChild(tipEl);

        return tipEl;
    };

    const hideTip = () => {
        if (!tipEl) {
            return;
        }
        tipEl.classList.add('hidden');
        tipEl.innerHTML = '';
        activeTrigger = null;
    };

    const showTip = (trigger) => {
        let lines = [];
        try {
            lines = JSON.parse(trigger.getAttribute('data-tip-lines') || '[]');
        } catch {
            lines = [];
        }

        if (!Array.isArray(lines) || lines.length === 0) {
            return;
        }

        const el = ensureTip();
        const rows = lines
            .map((line, index) => {
                const isLast = index === lines.length - 1;
                const rowClass = isLast
                    ? 'border-t border-brand-border/70 pt-1 font-semibold text-brand-text'
                    : 'text-brand-text-secondary';

                return `<div class="flex items-start justify-between gap-3 text-[11px] leading-tight ${rowClass}">
                    <span>${escapeHtml(String(line.label ?? ''))}</span>
                    <span class="tabular-nums text-brand-text">${escapeHtml(String(line.value ?? ''))}</span>
                </div>`;
            })
            .join('');

        el.innerHTML = `<p class="mb-1.5 text-[10px] font-bold uppercase tracking-wide text-brand-label">How shift duration is calculated</p>
            <div class="space-y-1">${rows}</div>`;
        el.classList.remove('hidden');
        activeTrigger = trigger;

        const rect = trigger.getBoundingClientRect();
        const tipRect = el.getBoundingClientRect();
        let left = rect.left + rect.width / 2 - tipRect.width / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));

        let top = rect.top - tipRect.height - 8;
        if (top < 8) {
            top = rect.bottom + 8;
        }

        el.style.left = `${Math.round(left)}px`;
        el.style.top = `${Math.round(top)}px`;
    };

    document.addEventListener('pointerover', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-shift-duration-tip]')
            : null;
        if (!(trigger instanceof HTMLElement)) {
            return;
        }
        showTip(trigger);
    });

    document.addEventListener('pointerout', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-shift-duration-tip]')
            : null;
        if (!(trigger instanceof HTMLElement) || trigger !== activeTrigger) {
            return;
        }

        const next = event.relatedTarget;
        if (next instanceof Node && trigger.contains(next)) {
            return;
        }

        hideTip();
    });

    document.addEventListener('focusin', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-shift-duration-tip]')
            : null;
        if (trigger instanceof HTMLElement) {
            showTip(trigger);
        }
    });

    document.addEventListener('focusout', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-shift-duration-tip]')
            : null;
        if (trigger instanceof HTMLElement && trigger === activeTrigger) {
            hideTip();
        }
    });

    window.addEventListener('scroll', hideTip, true);
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}