document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('time-clock-row-modal');
    const form = document.querySelector('[data-time-clock-row-form]');
    const closeButtons = document.querySelectorAll('[data-time-clock-row-close], [data-time-clock-row-action="close"]');
    const actionButtons = document.querySelectorAll('[data-time-clock-row-action]');
    const clockInInput = document.querySelector('[data-time-clock-row-clock-in]');
    const clockOutInput = document.querySelector('[data-time-clock-row-clock-out]');
    const commentInput = document.querySelector('[data-time-clock-row-comment]');
    const clockOutCommentEl = document.querySelector('[data-time-clock-row-clock-out-comment]');
    const clockOutCommentEmptyEl = document.querySelector('[data-time-clock-row-clock-out-comment-empty]');

    if (!modal || !form) {
        return;
    }

    const urls = {
        save: modal.getAttribute('data-update-url') || '',
        approve: modal.getAttribute('data-approve-url') || '',
        reject: modal.getAttribute('data-reject-url') || '',
    };

    /** @type {'edit'|'approve'|'reject'|'view'} */
    let currentMode = 'view';

    const fields = {
        title: document.querySelector('[data-time-clock-row-title]'),
        kicker: document.querySelector('[data-time-clock-row-kicker]'),
        employee: document.querySelector('[data-time-clock-row-employee]'),
        statusBadge: document.querySelector('[data-time-clock-row-status-badge]'),
        date: document.querySelector('[data-time-clock-row-date]'),
        position: document.querySelector('[data-time-clock-row-position]'),
        location: document.querySelector('[data-time-clock-row-location]'),
        scheduled: document.querySelector('[data-time-clock-row-scheduled]'),
        actual: document.querySelector('[data-time-clock-row-actual]'),
        employeeInput: document.querySelector('[data-time-clock-row-employee-input]'),
        workDateInput: document.querySelector('[data-time-clock-row-work-date-input]'),
        clockInId: document.querySelector('[data-time-clock-row-clock-in-id]'),
        clockOutId: document.querySelector('[data-time-clock-row-clock-out-id]'),
    };

    const statusBadgeClasses = {
        pending: 'inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200',
        approved: 'inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200',
        rejected: 'inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-800 ring-1 ring-red-200',
    };

    const setText = (element, value) => {
        if (element) {
            element.textContent = value || '—';
        }
    };

    const setInputValue = (input, value) => {
        if (input) {
            input.value = value || '';
        }
    };

    const setHiddenValue = (input, value) => {
        if (input) {
            input.value = value === null || value === undefined ? '' : String(value);
        }
    };

    const updateFooter = () => {
        actionButtons.forEach((button) => {
            const action = button.getAttribute('data-time-clock-row-action');
            let visible = false;

            if (action === 'close') {
                visible = true;
            } else if (action === 'save') {
                visible = currentMode === 'edit';
            } else if (action === 'approve') {
                visible = currentMode === 'approve';
            } else if (action === 'reject') {
                visible = currentMode === 'reject';
            }

            button.classList.toggle('hidden', !visible);
        });
    };

    /**
     * @param {Record<string, any>} row
     * @param {'edit'|'approve'|'reject'|'view'} mode
     */
    const openModal = (row, mode) => {
        currentMode = mode;

        const kickerLabels = {
            edit: 'Edit timesheet',
            approve: 'Approve timesheet',
            reject: 'Reject timesheet',
            view: 'Timesheet record',
        };

        setText(fields.kicker, kickerLabels[mode] || 'Timesheet record');
        setText(fields.title, row.date_label || '—');
        setText(fields.employee, row.employee_name || '—');
        setText(fields.date, row.date_label || '—');
        setText(fields.position, row.position || '—');
        setText(fields.location, row.location || '—');
        setText(fields.scheduled, row.scheduled_time || '—');
        setText(fields.actual, row.actual_time || '—');

        if (fields.statusBadge) {
            fields.statusBadge.textContent = row.status_label || '—';
            fields.statusBadge.className = statusBadgeClasses[row.status] || statusBadgeClasses.pending;
        }

        setHiddenValue(fields.employeeInput, row.employee_public_id);
        setHiddenValue(fields.workDateInput, row.work_date);
        setHiddenValue(fields.clockInId, row.clock_in_entry_id);
        setHiddenValue(fields.clockOutId, row.clock_out_entry_id);
        setInputValue(clockInInput, row.clock_in_at);
        setInputValue(clockOutInput, row.is_open ? '' : row.clock_out_at);
        setInputValue(commentInput, row.review_notes || '');

        const clockOutComment = typeof row.clock_out_comment === 'string' ? row.clock_out_comment.trim() : '';
        if (clockOutCommentEl) {
            if (clockOutComment !== '') {
                clockOutCommentEl.textContent = clockOutComment;
                clockOutCommentEl.classList.remove('hidden');
            } else {
                clockOutCommentEl.textContent = '';
                clockOutCommentEl.classList.add('hidden');
            }
        }
        if (clockOutCommentEmptyEl) {
            clockOutCommentEmptyEl.classList.toggle('hidden', clockOutComment !== '');
        }

        const editable = mode === 'edit' || mode === 'approve' || mode === 'reject';

        if (clockInInput) {
            if (editable && row.clock_in_entry_id) {
                clockInInput.removeAttribute('disabled');
            } else {
                clockInInput.setAttribute('disabled', 'disabled');
            }
        }

        if (clockOutInput) {
            if (editable && row.clock_out_entry_id && !row.is_open) {
                clockOutInput.removeAttribute('disabled');
            } else {
                clockOutInput.setAttribute('disabled', 'disabled');
            }
        }

        if (commentInput) {
            if (editable) {
                commentInput.removeAttribute('disabled');
            } else {
                commentInput.setAttribute('disabled', 'disabled');
            }
        }

        updateFooter();

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        const fullscreenModal = document.getElementById('timesheet-fullscreen-modal');
        if (!fullscreenModal) {
            document.body.classList.remove('overflow-hidden');
        }
    };

  /**
     * @param {HTMLElement} row
     * @returns {Record<string, any>|null}
     */
    const parseRowData = (row) => {
        const raw = row.getAttribute('data-row');
        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch {
            return null;
        }
    };

    document.querySelectorAll('[data-timesheet-row]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            if (event.target.closest('[data-timesheet-row-ignore]')) {
                return;
            }

            const data = parseRowData(row);
            if (!data) {
                return;
            }

            const mode = data.can_review ? 'edit' : 'view';
            openModal(data, mode);
        });
    });

    const closeRowMenus = () => {
        document.querySelectorAll('[data-timesheet-row-menu][open]').forEach((menu) => {
            menu.removeAttribute('open');
            resetRowMenuPanel(menu);
        });
    };

    const resetRowMenuPanel = (details) => {
        const panel = details.querySelector('[data-timesheet-row-menu-panel]');
        if (!panel) {
            return;
        }

        panel.style.position = '';
        panel.style.top = '';
        panel.style.left = '';
        panel.style.right = '';
        panel.style.bottom = '';
        panel.style.zIndex = '';
        panel.style.marginTop = '';
    };

    const positionRowMenuPanel = (details) => {
        const trigger = details.querySelector('[data-timesheet-row-menu-open]');
        const panel = details.querySelector('[data-timesheet-row-menu-panel]');
        if (!trigger || !panel) {
            return;
        }

        panel.style.position = 'fixed';
        panel.style.zIndex = '100';
        panel.style.marginTop = '0';

        const triggerRect = trigger.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const viewportPadding = 8;
        const gap = 4;

        let top = triggerRect.bottom + gap;
        if (top + panelRect.height > window.innerHeight - viewportPadding
            && triggerRect.top - panelRect.height - gap >= viewportPadding) {
            top = triggerRect.top - panelRect.height - gap;
        }

        let left = triggerRect.right - panelRect.width;
        left = Math.max(viewportPadding, Math.min(left, window.innerWidth - panelRect.width - viewportPadding));
        top = Math.max(viewportPadding, Math.min(top, window.innerHeight - panelRect.height - viewportPadding));

        panel.style.top = `${top}px`;
        panel.style.left = `${left}px`;
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
    };

    document.querySelectorAll('[data-timesheet-row-menu]').forEach((details) => {
        const panel = details.querySelector('[data-timesheet-row-menu-panel]');
        panel?.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        details.addEventListener('toggle', () => {
            if (!details.open) {
                resetRowMenuPanel(details);
                return;
            }

            document.querySelectorAll('[data-timesheet-row-menu][open]').forEach((openMenu) => {
                if (openMenu !== details) {
                    openMenu.removeAttribute('open');
                    resetRowMenuPanel(openMenu);
                }
            });

            window.requestAnimationFrame(() => {
                positionRowMenuPanel(details);
            });
        });
    });

    document.querySelectorAll('[data-timesheet-row-menu-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    });

    document.querySelectorAll('[data-timesheet-row-action]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();

            const row = button.closest('[data-timesheet-row]');
            const data = row ? parseRowData(row) : null;
            if (!data) {
                return;
            }

            const action = button.getAttribute('data-timesheet-row-action');
            const menu = button.closest('[data-timesheet-row-menu]');
            menu?.removeAttribute('open');
            if (menu) {
                resetRowMenuPanel(menu);
            }

            if (action === 'edit') {
                openModal(data, 'edit');
            } else if (action === 'approve') {
                openModal(data, 'approve');
            } else if (action === 'reject') {
                openModal(data, 'reject');
            }
        });
    });

    document.addEventListener('click', () => {
        closeRowMenus();
    });

    document.querySelector('[data-timesheet-modal-scroll]')?.addEventListener('scroll', closeRowMenus, { passive: true });
    document.querySelectorAll('[data-timesheet-table-scroll]').forEach((element) => {
        element.addEventListener('scroll', closeRowMenus, { passive: true });
    });
    window.addEventListener('resize', closeRowMenus);

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

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

    form.addEventListener('submit', (event) => {
        const submitter = event.submitter;
        const action = submitter?.getAttribute('data-time-clock-row-action');

        if (action === 'save') {
            form.action = urls.save;
            return;
        }

        if (action === 'approve') {
            form.action = urls.approve;
            return;
        }

        if (action === 'reject') {
            form.action = urls.reject;
            return;
        }

        event.preventDefault();
    });
});
