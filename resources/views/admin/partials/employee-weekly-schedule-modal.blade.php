@php
    use App\Models\EmployeeScheduleShift;
@endphp

<div
    id="schedule-shift-modal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-primary-dark/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="schedule-shift-modal-title"
>
    <div id="schedule-shift-panel" class="w-full max-w-md overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
        <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p id="schedule-modal-mode" class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">New entry</p>
                    <h2 id="schedule-shift-modal-title" class="mt-1 text-lg font-bold text-brand-text">Add to schedule</h2>
                </div>
                <button type="button" id="schedule-shift-modal-close" class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Close">
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mt-3 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm shadow-sm">
                <p id="schedule-modal-employee" class="font-semibold text-brand-text">—</p>
                <p id="schedule-modal-date-label" class="mt-0.5 text-xs text-brand-text-secondary">—</p>
                <span id="schedule-modal-status" class="mt-1.5 hidden items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset"></span>
            </div>
            <p id="schedule-suggestion-banner" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                Suggested from work assignment — save to add to this week.
            </p>
        </header>

        <div id="schedule-shift-details" class="hidden px-5 py-5">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div id="schedule-detail-time-row">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Time</dt>
                    <dd id="schedule-detail-time" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                </div>
                <div id="schedule-detail-duration-row">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Duration</dt>
                    <dd id="schedule-detail-duration" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                </div>
                <div id="schedule-detail-position-row" class="sm:col-span-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Position</dt>
                    <dd id="schedule-detail-position" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                </div>
                <div id="schedule-detail-shift-row" class="sm:col-span-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Shift</dt>
                    <dd id="schedule-detail-shift" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                </div>
                <div id="schedule-detail-meta-row" class="sm:col-span-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Department & location</dt>
                    <dd id="schedule-detail-meta" class="mt-1 text-sm font-medium text-brand-text">—</dd>
                </div>
                <div id="schedule-detail-notes-row" class="hidden sm:col-span-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Notes</dt>
                    <dd id="schedule-detail-notes" class="mt-1 text-sm font-medium text-brand-text whitespace-pre-wrap">—</dd>
                </div>
            </dl>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-brand-border pt-4">
                <button type="button" id="schedule-detail-delete" class="text-sm font-semibold text-red-600 hover:text-red-700">
                    Delete
                </button>
                <div class="ml-auto flex items-center gap-2">
                    <div id="schedule-detail-actions" class="relative">
                        <button type="button" id="schedule-detail-menu-toggle" class="hidden rounded-xl border border-brand-border bg-white p-2.5 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Shift actions" aria-haspopup="true" aria-expanded="false">
                            <svg class="size-5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
                        </button>
                        <div id="schedule-detail-menu" class="absolute bottom-full right-0 z-10 mb-2 hidden w-56 overflow-hidden rounded-xl border border-brand-border bg-white py-1 shadow-lg ring-1 ring-black/[0.06]">
                            <button type="button" data-status-action="{{ \App\Models\EmployeeScheduleShift::STATUS_SICK_CALL_OUT }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-brand-text hover:bg-brand-surface">
                                <span class="size-2 shrink-0 rounded-full bg-amber-400"></span>
                                Mark as Sick Call Out
                            </button>
                            <button type="button" data-status-action="{{ \App\Models\EmployeeScheduleShift::STATUS_NO_SHOW }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-brand-text hover:bg-brand-surface">
                                <span class="size-2 shrink-0 rounded-full bg-red-500"></span>
                                Mark as No Show
                            </button>
                            <button type="button" id="schedule-clear-status" data-status-action="" class="hidden w-full items-center gap-2 border-t border-brand-border px-3 py-2 text-left text-sm font-medium text-brand-text-secondary hover:bg-brand-surface">
                                Clear status
                            </button>
                        </div>
                    </div>
                    <button type="button" id="schedule-detail-edit" class="rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text shadow-sm hover:bg-brand-surface">
                        Edit shift
                    </button>
                </div>
            </div>
        </div>

        <form id="schedule-shift-form" method="post" action="{{ route('admin.employees.weekly-schedule.shifts.store') }}" class="px-5 py-5">
            @csrf
            @foreach ($redirectQuery as $key => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                @endif
            @endforeach
            <input type="hidden" name="employee_public_id" id="schedule-employee-hidden">
            <input type="hidden" name="scheduled_date" id="schedule-date-hidden">
            <input type="hidden" name="entry_type" id="schedule-entry-type-hidden" value="{{ EmployeeScheduleShift::TYPE_SHIFT }}">
            <input type="hidden" name="start_time" id="schedule-start-hidden">
            <input type="hidden" name="end_time" id="schedule-end-hidden">
            <input type="hidden" name="time_off_request_id" id="schedule-time-off-request-id">

            <div id="schedule-shift-fields" class="space-y-4">
                <div class="block">
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Shift</span>
                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                id="schedule-open-edit-shift"
                                class="hidden text-[11px] font-semibold text-brand-primary hover:text-brand-primary-dark hover:underline"
                            >
                                Edit shift details
                            </button>
                            <button
                                type="button"
                                id="schedule-open-create-shift"
                                class="text-[11px] font-semibold text-brand-primary hover:text-brand-primary-dark hover:underline"
                            >
                                + New shift
                            </button>
                        </div>
                    </div>
                    <select name="shift_id" id="schedule-shift-template" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                        <option value="">Select a shift…</option>
                        @foreach ($shiftTemplates as $shift)
                            <option
                                value="{{ $shift->id }}"
                                data-start="{{ $shift->start_time instanceof \Carbon\CarbonInterface ? $shift->start_time->format('H:i') : '09:00' }}"
                                data-end="{{ $shift->end_time instanceof \Carbon\CarbonInterface ? $shift->end_time->format('H:i') : '17:00' }}"
                            >
                                {{ $shift->name }} · {{ $shift->start_time instanceof \Carbon\CarbonInterface ? $shift->start_time->format('g:i A') : '—' }} – {{ $shift->end_time instanceof \Carbon\CarbonInterface ? $shift->end_time->format('g:i A') : '—' }}
                            </option>
                        @endforeach
                    </select>
                    <!-- <p class="mt-1.5 text-[11px] text-brand-text-secondary">Not in the list? Create a shift here</p> -->
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Work location</span>
                    <select name="work_location_id" id="schedule-work-location" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                        <option value="">Select a location…</option>
                        @foreach ($workLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </label>

                <p id="schedule-shift-preview" class="hidden rounded-xl border border-brand-border bg-brand-surface/60 px-3 py-2.5 text-xs text-brand-text-secondary"></p>
            </div>

            <div id="schedule-timeoff-fields" class="hidden space-y-3">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Leave type</span>
                    <select name="leave_type_id" id="schedule-leave-type" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                        <option value="">No leave type (unpaid day off)</option>
                    </select>
                </label>

                <div id="schedule-leave-balance" class="hidden rounded-xl border border-brand-border bg-brand-surface/50 px-3 py-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span id="schedule-leave-balance-name" class="truncate text-xs font-semibold text-brand-text"></span>
                        <span id="schedule-leave-balance-paid" class="shrink-0 text-[10px] font-bold uppercase tracking-wide"></span>
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Allocated</p>
                            <p id="schedule-leave-allocated" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-text">—</p>
                        </div>
                        <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Used</p>
                            <p id="schedule-leave-used" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-text">—</p>
                        </div>
                        <div class="rounded-lg bg-white px-1.5 py-1.5 ring-1 ring-brand-border">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-brand-label">Remaining</p>
                            <p id="schedule-leave-remaining" class="mt-0.5 font-mono text-sm font-bold tabular-nums text-brand-primary">—</p>
                        </div>
                    </div>
                </div>

                <label class="block" id="schedule-leave-hours-wrap">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Leave hours</span>
                    <input type="number" step="0.25" min="0.25" max="24" name="leave_hours" id="schedule-leave-hours" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20" placeholder="e.g. 7.6">
                    <span id="schedule-leave-hours-hint" class="mt-1 block text-[11px] text-brand-text-secondary"></span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Reason / notes (optional)</span>
                    <input type="text" name="notes" id="schedule-notes" maxlength="500" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20" placeholder="e.g. Family reasons">
                </label>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-brand-border pt-4">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" id="schedule-shift-delete" class="hidden text-sm font-semibold text-red-600 hover:text-red-700">
                        Delete
                    </button>
                    <button type="button" id="schedule-convert-to-shift" class="hidden text-sm font-semibold text-brand-primary hover:text-brand-primary-dark">
                        Add shift instead
                    </button>
                </div>
                <div class="ml-auto flex gap-2">
                    <button type="button" id="schedule-shift-cancel" class="rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary shadow-sm hover:bg-brand-surface">
                        Cancel
                    </button>
                    <button type="submit" id="schedule-shift-submit" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-primary-dark">
                        Save
                    </button>
                </div>
            </div>
        </form>

        <form
            id="schedule-shift-delete-form"
            method="post"
            action="#"
            class="hidden"
            data-confirm="This entry will be removed from the weekly schedule."
            data-confirm-title="Remove from schedule?"
            data-confirm-confirm="Remove"
            data-confirm-cancel="Keep it"
            data-confirm-danger="1"
        >
            @csrf
            @method('DELETE')
            @foreach ($redirectQuery as $key => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                @endif
            @endforeach
        </form>

        <form id="schedule-shift-status-form" method="post" action="#" class="hidden">
            @csrf
            @foreach ($redirectQuery as $key => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                @endif
            @endforeach
            <input type="hidden" name="status" id="schedule-status-value">
            <input type="hidden" name="notes" id="schedule-status-notes" value="">
        </form>
    </div>
</div>

<div
    id="schedule-create-shift-modal"
    class="fixed inset-0 z-[60] hidden items-center justify-center bg-brand-primary-dark/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="schedule-create-shift-title"
>
    <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
        <header class="shrink-0 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Organization shift</p>
                    <h2 id="schedule-create-shift-title" class="mt-1 text-lg font-bold text-brand-text">Create new shift</h2>
                    <p id="schedule-create-shift-subtitle" class="mt-1 text-xs text-brand-text-secondary">Saved to organization setup, then selected for this employee and date.</p>
                </div>
                <button type="button" id="schedule-create-shift-close" class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Close">
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </header>
        <form id="schedule-create-shift-form" class="flex min-h-0 flex-1 flex-col" action="{{ route('admin.workforce.shifts.store') }}" method="post">
            @csrf
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
                <p id="schedule-create-shift-error" class="hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800"></p>
                @include('admin.partials.shift-create-fields', [
                    'idPrefix' => 'schedule-new-shift',
                    'breaksFieldId' => 'schedule-new-shift-breaks',
                ])
            </div>
            <div class="shrink-0 flex justify-end gap-2 border-t border-brand-border px-5 py-4">
                <button type="button" id="schedule-create-shift-cancel" class="rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary shadow-sm hover:bg-brand-surface">
                    Cancel
                </button>
                <button type="submit" id="schedule-create-shift-submit" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-primary-dark">
                    Create and select
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const TYPE_SHIFT = @json(EmployeeScheduleShift::TYPE_SHIFT);
        const TYPE_TIME_OFF = @json(EmployeeScheduleShift::TYPE_TIME_OFF);

        const modal = document.getElementById('schedule-shift-modal');
        const form = document.getElementById('schedule-shift-form');
        const deleteForm = document.getElementById('schedule-shift-delete-form');
        const deleteButton = document.getElementById('schedule-shift-delete');
        const convertToShiftButton = document.getElementById('schedule-convert-to-shift');
        const submitButton = document.getElementById('schedule-shift-submit');
        const entryTypeHidden = document.getElementById('schedule-entry-type-hidden');
        const shiftFields = document.getElementById('schedule-shift-fields');
        const timeOffFields = document.getElementById('schedule-timeoff-fields');
        const shiftTemplateEl = document.getElementById('schedule-shift-template');
        const workLocationEl = document.getElementById('schedule-work-location');
        const openCreateShiftButton = document.getElementById('schedule-open-create-shift');
        const openEditShiftButton = document.getElementById('schedule-open-edit-shift');
        const createShiftModal = document.getElementById('schedule-create-shift-modal');
        const createShiftForm = document.getElementById('schedule-create-shift-form');
        const createShiftError = document.getElementById('schedule-create-shift-error');
        const createShiftSubmit = document.getElementById('schedule-create-shift-submit');
        const createShiftTitle = document.getElementById('schedule-create-shift-title');
        const createShiftSubtitle = document.getElementById('schedule-create-shift-subtitle');
        const createShiftStoreUrl = @json(route('admin.workforce.shifts.store'));
        const createShiftUpdateUrlTemplate = @json(route('admin.workforce.shifts.update', ['shift' => '__ID__']));
        const shiftCatalog = @json($shiftCatalog ?? []);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        /** @type {'create'|'edit'} */
        let orgShiftModalMode = 'create';
        let editingShiftId = '';
        const shiftCatalogById = Object.create(null);
        (Array.isArray(shiftCatalog) ? shiftCatalog : []).forEach((entry) => {
            if (entry && entry.id != null) {
                shiftCatalogById[String(entry.id)] = entry;
            }
        });
        const notesEl = document.getElementById('schedule-notes');
        const leaveTypeEl = document.getElementById('schedule-leave-type');
        const leaveHoursEl = document.getElementById('schedule-leave-hours');
        const leaveHoursWrap = document.getElementById('schedule-leave-hours-wrap');
        const leaveHoursHint = document.getElementById('schedule-leave-hours-hint');
        const leaveBalancePanel = document.getElementById('schedule-leave-balance');
        const leaveBalanceName = document.getElementById('schedule-leave-balance-name');
        const leaveBalancePaid = document.getElementById('schedule-leave-balance-paid');
        const leaveAllocatedEl = document.getElementById('schedule-leave-allocated');
        const leaveUsedEl = document.getElementById('schedule-leave-used');
        const leaveRemainingEl = document.getElementById('schedule-leave-remaining');
        const leaveBalances = @json($leaveBalances ?? []);
        const employeeHidden = document.getElementById('schedule-employee-hidden');
        const dateHidden = document.getElementById('schedule-date-hidden');
        const startHidden = document.getElementById('schedule-start-hidden');
        const endHidden = document.getElementById('schedule-end-hidden');
        const timeOffRequestIdInput = document.getElementById('schedule-time-off-request-id');
        const previewEl = document.getElementById('schedule-shift-preview');

        const modeEl = document.getElementById('schedule-modal-mode');
        const titleEl = document.getElementById('schedule-shift-modal-title');
        const employeeLabelEl = document.getElementById('schedule-modal-employee');
        const dateLabelEl = document.getElementById('schedule-modal-date-label');
        const suggestionBanner = document.getElementById('schedule-suggestion-banner');
        const detailsPanel = document.getElementById('schedule-shift-details');
        const detailDeleteButton = document.getElementById('schedule-detail-delete');
        const detailEditButton = document.getElementById('schedule-detail-edit');
        const menuToggle = document.getElementById('schedule-detail-menu-toggle');
        const detailMenu = document.getElementById('schedule-detail-menu');
        const clearStatusButton = document.getElementById('schedule-clear-status');
        const statusForm = document.getElementById('schedule-shift-status-form');
        const statusValueInput = document.getElementById('schedule-status-value');
        const statusNotesInput = document.getElementById('schedule-status-notes');
        const statusBadge = document.getElementById('schedule-modal-status');
        const STATUS_LABELS = { {{ \App\Models\EmployeeScheduleShift::STATUS_SICK_CALL_OUT }}: 'Sick call out', {{ \App\Models\EmployeeScheduleShift::STATUS_NO_SHOW }}: 'No show' };
        const detailTimeRow = document.getElementById('schedule-detail-time-row');
        const detailDurationRow = document.getElementById('schedule-detail-duration-row');
        const detailPositionRow = document.getElementById('schedule-detail-position-row');
        const detailShiftRow = document.getElementById('schedule-detail-shift-row');
        const detailMetaRow = document.getElementById('schedule-detail-meta-row');
        const detailNotesRow = document.getElementById('schedule-detail-notes-row');
        const detailTimeEl = document.getElementById('schedule-detail-time');
        const detailDurationEl = document.getElementById('schedule-detail-duration');
        const detailPositionEl = document.getElementById('schedule-detail-position');
        const detailShiftEl = document.getElementById('schedule-detail-shift');
        const detailMetaEl = document.getElementById('schedule-detail-meta');
        const detailNotesEl = document.getElementById('schedule-detail-notes');

        const storeUrl = @json(route('admin.employees.weekly-schedule.shifts.store'));
        const updateUrlTemplate = @json(route('admin.employees.weekly-schedule.shifts.update', ['scheduleShift' => '__ID__']));
        const destroyUrlTemplate = @json(route('admin.employees.weekly-schedule.shifts.destroy', ['scheduleShift' => '__ID__']));
        const statusUrlTemplate = @json(route('admin.employees.weekly-schedule.shifts.status', ['scheduleShift' => '__ID__']));

        if (!modal || !form) return;

        /** @type {Record<string, string>|null} */
        let currentPayload = null;
        let openedFromDetails = false;

        function formatDateLabel(isoDate) {
            if (!isoDate) return '—';
            const date = new Date(isoDate + 'T12:00:00');
            if (Number.isNaN(date.getTime())) return isoDate;
            return date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        }

        function setDetailText(el, value) {
            if (el) {
                el.textContent = value && String(value).trim() !== '' ? value : '—';
            }
        }

        function toggleDetailRow(row, visible) {
            if (row) {
                row.classList.toggle('hidden', !visible);
            }
        }

        function showDetailsView() {
            if (detailsPanel) detailsPanel.classList.remove('hidden');
            form.classList.add('hidden');
        }

        function showFormView() {
            if (detailsPanel) detailsPanel.classList.add('hidden');
            form.classList.remove('hidden');
        }

        function closeDetailMenu() {
            detailMenu?.classList.add('hidden');
            menuToggle?.setAttribute('aria-expanded', 'false');
        }

        function fmtHours(value) {
            if (value === null || value === undefined || value === '') return '—';
            const n = Number(value);
            if (Number.isNaN(n)) return '—';
            return (Math.round(n * 100) / 100).toString();
        }

        function populateLeaveTypeOptions(employeePublicId, selectedId) {
            if (!leaveTypeEl) return;
            const list = leaveBalances[employeePublicId] || [];
            leaveTypeEl.innerHTML = '<option value="">No leave type (unpaid day off)</option>';
            list.forEach((item) => {
                const opt = document.createElement('option');
                opt.value = String(item.id);
                opt.textContent = item.name + (item.is_paid ? '' : ' (Unpaid)');
                opt.dataset.allocated = item.allocated === null ? '' : String(item.allocated);
                opt.dataset.used = item.used === null ? '' : String(item.used);
                opt.dataset.remaining = item.remaining === null ? '' : String(item.remaining);
                opt.dataset.isPaid = item.is_paid ? '1' : '0';
                leaveTypeEl.appendChild(opt);
            });
            leaveTypeEl.value = selectedId ? String(selectedId) : '';
            if (selectedId && leaveTypeEl.value !== String(selectedId)) {
                leaveTypeEl.value = '';
            }
        }

        function updateLeaveBalanceUi() {
            if (!leaveTypeEl) return;
            const option = leaveTypeEl.selectedOptions[0];
            const hasType = Boolean(leaveTypeEl.value);
            const isTimeOff = entryTypeHidden.value === TYPE_TIME_OFF;

            if (leaveHoursWrap) leaveHoursWrap.classList.toggle('hidden', !hasType);
            if (leaveHoursEl) leaveHoursEl.required = hasType && isTimeOff;

            if (!hasType || !option) {
                leaveBalancePanel?.classList.add('hidden');
                if (leaveHoursHint) leaveHoursHint.textContent = '';
                return;
            }

            leaveBalancePanel?.classList.remove('hidden');
            const isPaid = option.dataset.isPaid === '1';
            if (leaveBalanceName) leaveBalanceName.textContent = option.textContent;
            if (leaveBalancePaid) {
                leaveBalancePaid.textContent = isPaid ? 'Paid' : 'Unpaid';
                leaveBalancePaid.classList.toggle('text-emerald-600', isPaid);
                leaveBalancePaid.classList.toggle('text-slate-500', !isPaid);
            }

            const allocated = option.dataset.allocated;
            const remaining = option.dataset.remaining;
            if (leaveAllocatedEl) leaveAllocatedEl.textContent = allocated === '' ? '—' : fmtHours(allocated) + 'h';
            if (leaveUsedEl) leaveUsedEl.textContent = fmtHours(option.dataset.used) + 'h';
            if (leaveRemainingEl) leaveRemainingEl.textContent = remaining === '' ? '—' : fmtHours(remaining) + 'h';

            if (leaveHoursHint) {
                leaveHoursHint.textContent = remaining === ''
                    ? 'No balance set for this leave type.'
                    : fmtHours(remaining) + 'h remaining before this day off.';
            }
        }

        const STATUS_NO_SHOW = @json(EmployeeScheduleShift::STATUS_NO_SHOW);

        function updateStatusUi(status) {
            const label = STATUS_LABELS[status] || '';

            if (statusBadge) {
                statusBadge.textContent = label;
                statusBadge.classList.toggle('hidden', label === '');
                statusBadge.classList.toggle('inline-flex', label !== '');
                const isNoShow = status === STATUS_NO_SHOW;
                statusBadge.classList.toggle('bg-red-100', isNoShow);
                statusBadge.classList.toggle('text-red-700', isNoShow);
                statusBadge.classList.toggle('ring-red-200', isNoShow);
                statusBadge.classList.toggle('bg-amber-100', label !== '' && !isNoShow);
                statusBadge.classList.toggle('text-amber-800', label !== '' && !isNoShow);
                statusBadge.classList.toggle('ring-amber-200', label !== '' && !isNoShow);
            }

            if (clearStatusButton) {
                clearStatusButton.classList.toggle('hidden', label === '');
                clearStatusButton.classList.toggle('flex', label !== '');
            }
        }

        function hideDetailActions() {
            menuToggle?.classList.add('hidden');
            closeDetailMenu();
            updateStatusUi('');
        }

        function populateSharedHeader(payload, isSuggestion) {
            employeeLabelEl.textContent = payload.employeeName || 'Employee';
            dateLabelEl.textContent = payload.dayLabel || formatDateLabel(payload.scheduledDate);
            suggestionBanner.classList.toggle('hidden', !isSuggestion);
        }

        function openDetails(payload) {
            currentPayload = payload;
            openedFromDetails = false;

            const entryType = payload.entryType || TYPE_SHIFT;
            const isTimeOff = entryType === TYPE_TIME_OFF;

            populateSharedHeader(payload, false);

            modeEl.textContent = isTimeOff ? 'Day off' : 'Scheduled shift';
            titleEl.textContent = isTimeOff ? 'Day off details' : 'Shift details';

            setDetailText(detailTimeEl, isTimeOff ? 'All day' : payload.timeRange);
            setDetailText(detailDurationEl, isTimeOff ? 'Day off' : payload.durationLabel);
            setDetailText(detailPositionEl, payload.blockTitle);
            setDetailText(detailShiftEl, payload.blockSubtitle);
            setDetailText(detailMetaEl, payload.blockMeta);

            toggleDetailRow(detailTimeRow, true);
            toggleDetailRow(detailDurationRow, true);
            toggleDetailRow(detailPositionRow, !isTimeOff && Boolean(payload.blockTitle));
            toggleDetailRow(detailShiftRow, !isTimeOff && Boolean(payload.blockSubtitle));
            toggleDetailRow(detailMetaRow, !isTimeOff && Boolean(payload.blockMeta));

            const shiftRowLabel = detailShiftRow?.querySelector('dt');
            if (shiftRowLabel) shiftRowLabel.textContent = 'Shift';

            if (isTimeOff && payload.leaveTypeName) {
                setDetailText(detailDurationEl, payload.leaveHours ? fmtHours(payload.leaveHours) + 'h leave' : 'Day off');
                setDetailText(detailShiftEl, payload.leaveTypeName);
                toggleDetailRow(detailShiftRow, true);
                if (shiftRowLabel) shiftRowLabel.textContent = 'Leave type';
            }

            const notes = (payload.notes || '').trim();
            const reasonText = isTimeOff
                ? (notes || payload.blockSubtitle || '')
                : notes;
            const showNotes = isTimeOff || reasonText !== '';
            setDetailText(detailNotesEl, reasonText);
            if (detailNotesRow) {
                detailNotesRow.classList.toggle('hidden', !showNotes);
                const notesLabel = detailNotesRow.querySelector('dt');
                if (notesLabel) {
                    notesLabel.textContent = isTimeOff
                        ? 'Reason'
                        : (payload.status ? 'Comment' : 'Notes');
                }
            }

            deleteForm.action = destroyUrlTemplate.replace('__ID__', payload.shiftId);

            const canMark = !isTimeOff && Boolean(payload.shiftId);
            closeDetailMenu();
            if (menuToggle) {
                menuToggle.classList.toggle('hidden', !canMark);
            }
            if (canMark) {
                statusForm.action = statusUrlTemplate.replace('__ID__', payload.shiftId);
                updateStatusUi(payload.status || '');
            } else {
                updateStatusUi('');
            }

            if (detailEditButton) {
                detailEditButton.textContent = isTimeOff ? 'Edit day off' : 'Edit shift';
            }

            showDetailsView();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            detailEditButton?.focus();
        }

        function syncTimesFromShift() {
            const option = shiftTemplateEl.selectedOptions[0];
            if (!option || !option.dataset.start) {
                startHidden.value = '';
                endHidden.value = '';
                previewEl.classList.add('hidden');
                return;
            }
            startHidden.value = option.dataset.start;
            endHidden.value = option.dataset.end;
            previewEl.textContent = 'Scheduled: ' + option.textContent.trim();
            previewEl.classList.remove('hidden');
        }

        function applyEntryType(type) {
            entryTypeHidden.value = type;
            const isTimeOff = type === TYPE_TIME_OFF;

            shiftFields.classList.toggle('hidden', isTimeOff);
            timeOffFields.classList.toggle('hidden', !isTimeOff);

            shiftTemplateEl.required = !isTimeOff;
            workLocationEl.required = !isTimeOff;
            shiftTemplateEl.disabled = isTimeOff;
            workLocationEl.disabled = isTimeOff;

            if (leaveTypeEl) leaveTypeEl.disabled = !isTimeOff;
            if (leaveHoursEl) leaveHoursEl.disabled = !isTimeOff;

            if (isTimeOff) {
                openEditShiftButton?.classList.add('hidden');
            } else {
                updateEditShiftButtonVisibility();
            }

            const isEdit = entryTypeHidden.dataset.edit === '1';
            modeEl.textContent = isEdit ? 'Editing' : (isTimeOff ? 'Day off' : 'New shift');
            titleEl.textContent = isTimeOff
                ? (isEdit ? 'Edit day off' : 'Add day off')
                : (isEdit ? 'Edit shift' : 'Add shift');
            submitButton.textContent = isTimeOff ? 'Save day off' : 'Save shift';
        }

        function switchToShiftMode(defaultShiftId, defaultLocationId) {
            entryTypeHidden.dataset.edit = '0';
            if (timeOffRequestIdInput) timeOffRequestIdInput.value = '';
            applyEntryType(TYPE_SHIFT);
            form.action = storeUrl;
            deleteButton.classList.add('hidden');
            convertToShiftButton.classList.add('hidden');
            shiftTemplateEl.value = defaultShiftId ? String(defaultShiftId) : '';
            workLocationEl.value = defaultLocationId ? String(defaultLocationId) : '';
            notesEl.value = '';
            if (leaveTypeEl) leaveTypeEl.value = '';
            updateLeaveBalanceUi();
            syncTimesFromShift();
            updateEditShiftButtonVisibility();
            (shiftTemplateEl.value ? workLocationEl : shiftTemplateEl).focus();
        }

        function openEditForm(payload, fromDetails) {
            currentPayload = payload;
            openedFromDetails = Boolean(fromDetails);

            if (timeOffRequestIdInput) timeOffRequestIdInput.value = '';

            const isEdit = Boolean(payload.shiftId);
            const isSuggestion = payload.isSuggestion === '1' || payload.isSuggestion === true;
            const entryType = isSuggestion ? TYPE_SHIFT : (payload.entryType || TYPE_SHIFT);

            entryTypeHidden.dataset.edit = isEdit ? '1' : '0';
            hideDetailActions();
            populateSharedHeader(payload, isSuggestion);

            employeeHidden.value = payload.employeePublicId || '';
            dateHidden.value = payload.scheduledDate || '';

            applyEntryType(entryType);

            if (isSuggestion) {
                modeEl.textContent = 'Suggested shift';
            }

            shiftTemplateEl.value = payload.shiftTemplateId ? String(payload.shiftTemplateId) : '';
            workLocationEl.value = payload.workLocationId ? String(payload.workLocationId) : '';
            notesEl.value = payload.notes || '';
            populateLeaveTypeOptions(payload.employeePublicId, payload.leaveTypeId);
            if (leaveHoursEl) leaveHoursEl.value = payload.leaveHours || '';
            updateLeaveBalanceUi();
            if (convertToShiftButton) {
                convertToShiftButton.dataset.defaultShiftId = payload.shiftTemplateId || '';
                convertToShiftButton.dataset.defaultLocationId = payload.workLocationId || '';
            }
            syncTimesFromShift();
            updateEditShiftButtonVisibility();

            if (isSuggestion) {
                submitButton.textContent = 'Save to roster';
            }

            form.action = isEdit ? updateUrlTemplate.replace('__ID__', payload.shiftId) : storeUrl;
            deleteButton.classList.toggle('hidden', !isEdit);
            convertToShiftButton.classList.toggle('hidden', !isEdit || entryType !== TYPE_TIME_OFF);
            if (isEdit) {
                deleteForm.action = destroyUrlTemplate.replace('__ID__', payload.shiftId);
            }

            showFormView();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            if (entryType === TYPE_TIME_OFF) {
                notesEl.focus();
            } else {
                (shiftTemplateEl.value ? workLocationEl : shiftTemplateEl).focus();
            }
        }

        function openModal(payload) {
            const isSuggestion = payload.isSuggestion === '1' || payload.isSuggestion === true;
            const hasExistingShift = Boolean(payload.shiftId) && !isSuggestion;

            if (hasExistingShift) {
                openDetails(payload);
                return;
            }

            openEditForm(payload, false);
        }

        function closeModal() {
            closeCreateShiftModal();
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            closeDetailMenu();
            currentPayload = null;
            openedFromDetails = false;
        }

        function handleCancel() {
            if (openedFromDetails && currentPayload) {
                openDetails(currentPayload);
                return;
            }

            closeModal();
        }

        function createShiftModalIsOpen() {
            return Boolean(createShiftModal && !createShiftModal.classList.contains('hidden'));
        }

        function weekdayKeyFromIso(isoDate) {
            if (!isoDate) return null;
            const date = new Date(isoDate + 'T12:00:00');
            if (Number.isNaN(date.getTime())) return null;
            return ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][date.getDay()] || null;
        }

        function resetCreateShiftBreaks(breaks) {
            const list = createShiftForm?.querySelector('[data-shift-breaks-list]');
            const template = createShiftForm?.querySelector('[data-shift-breaks-template]');
            if (!list || !(template instanceof HTMLTemplateElement)) return;

            list.innerHTML = '';
            const rows = Array.isArray(breaks) && breaks.length > 0
                ? breaks
                : [{ label: '', minutes: '', paid: false }];

            rows.forEach((breakRow) => {
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('[data-shift-breaks-row]');
                if (!row) return;
                const labelInput = row.querySelector('input[name*="[label]"]');
                const minutesInput = row.querySelector('input[name*="[minutes]"]');
                const paidSelect = row.querySelector('select[name*="[paid]"]');
                if (labelInput instanceof HTMLInputElement) labelInput.value = breakRow.label || '';
                if (minutesInput instanceof HTMLInputElement) {
                    minutesInput.value = breakRow.minutes != null && breakRow.minutes !== ''
                        ? String(breakRow.minutes)
                        : '';
                }
                if (paidSelect instanceof HTMLSelectElement) {
                    paidSelect.value = breakRow.paid ? '1' : '0';
                }
                list.appendChild(fragment);
            });

            list.querySelectorAll('[data-shift-breaks-row]').forEach((row, index) => {
                row.querySelectorAll('input, select').forEach((field) => {
                    const name = field.getAttribute('name');
                    if (!name) return;
                    field.setAttribute(
                        'name',
                        name.replace(/shift_breaks\[(?:\d+|__INDEX__)]/, 'shift_breaks[' + index + ']')
                    );
                });
            });
        }

        function setCreateShiftError(message) {
            if (!createShiftError) return;
            const text = String(message || '').trim();
            createShiftError.textContent = text;
            createShiftError.classList.toggle('hidden', text === '');
        }

        function updateEditShiftButtonVisibility() {
            const hasTemplate = Boolean(shiftTemplateEl?.value);
            openEditShiftButton?.classList.toggle('hidden', !hasTemplate);
        }

        function setOrgShiftModalMode(mode, shiftId) {
            orgShiftModalMode = mode;
            editingShiftId = shiftId ? String(shiftId) : '';
            const isEdit = mode === 'edit';
            if (createShiftTitle) {
                createShiftTitle.textContent = isEdit ? 'Edit shift details' : 'Create new shift';
            }
            if (createShiftSubtitle) {
                createShiftSubtitle.textContent = isEdit
                    ? 'Updates the organization shift template used by this schedule entry.'
                    : 'Saved to organization setup, then selected for this employee and date.';
            }
            if (createShiftSubmit) {
                createShiftSubmit.textContent = isEdit ? 'Save shift changes' : 'Create and select';
            }
            if (createShiftForm) {
                createShiftForm.action = isEdit
                    ? createShiftUpdateUrlTemplate.replace('__ID__', editingShiftId)
                    : createShiftStoreUrl;
            }
        }

        function fillOrgShiftForm(entry) {
            if (!createShiftForm) return;
            const nameInput = document.getElementById('schedule-new-shift-name');
            const startInput = document.getElementById('schedule-new-shift-start');
            const endInput = document.getElementById('schedule-new-shift-end');
            const notesInput = document.getElementById('schedule-new-shift-notes');
            if (nameInput instanceof HTMLInputElement) nameInput.value = entry?.name || '';
            if (startInput instanceof HTMLInputElement) startInput.value = entry?.start_time || '';
            if (endInput instanceof HTMLInputElement) endInput.value = entry?.end_time || '';
            if (notesInput instanceof HTMLTextAreaElement || notesInput instanceof HTMLInputElement) {
                notesInput.value = entry?.notes || '';
            }

            const selectedDays = Array.isArray(entry?.shift_days) ? entry.shift_days.map(String) : [];
            createShiftForm.querySelectorAll('input[name="shift_days[]"]').forEach((input) => {
                if (input instanceof HTMLInputElement) {
                    input.checked = selectedDays.includes(input.value);
                }
            });

            resetCreateShiftBreaks(entry?.breaks || []);
        }

        function openCreateShiftModal() {
            if (!createShiftModal || !createShiftForm) return;
            setOrgShiftModalMode('create', '');
            createShiftForm.reset();
            resetCreateShiftBreaks([]);
            setCreateShiftError('');
            const dayKey = weekdayKeyFromIso(dateHidden?.value || currentPayload?.scheduledDate || '');
            createShiftForm.querySelectorAll('input[name="shift_days[]"]').forEach((input) => {
                if (input instanceof HTMLInputElement) {
                    input.checked = Boolean(dayKey) && input.value === dayKey;
                }
            });
            createShiftModal.classList.remove('hidden');
            createShiftModal.classList.add('flex');
            document.getElementById('schedule-new-shift-name')?.focus();
        }

        function openEditShiftModal(templateId) {
            if (!createShiftModal || !createShiftForm) return;
            const id = String(templateId || shiftTemplateEl?.value || currentPayload?.shiftTemplateId || '');
            const entry = shiftCatalogById[id];
            if (!id || !entry) {
                window.CruLynkDialog?.toastError?.('Select a shift first, or create a new one.');
                return;
            }

            setOrgShiftModalMode('edit', id);
            setCreateShiftError('');
            fillOrgShiftForm(entry);
            createShiftModal.classList.remove('hidden');
            createShiftModal.classList.add('flex');
            document.getElementById('schedule-new-shift-name')?.focus();
        }

        function closeCreateShiftModal() {
            if (!createShiftModal) return;
            createShiftModal.classList.add('hidden');
            createShiftModal.classList.remove('flex');
            setOrgShiftModalMode('create', '');
        }

        function selectCreatedShift(payload) {
            if (!shiftTemplateEl || !payload || !payload.id) return;
            const id = String(payload.id);
            let option = Array.from(shiftTemplateEl.options).find((opt) => opt.value === id) || null;
            if (!option) {
                option = document.createElement('option');
                option.value = id;
                shiftTemplateEl.appendChild(option);
            }
            option.dataset.start = payload.start_time || '';
            option.dataset.end = payload.end_time || '';
            option.textContent = payload.option_label || payload.name || 'Shift';
            shiftTemplateEl.value = id;
            syncTimesFromShift();
            updateEditShiftButtonVisibility();
            workLocationEl?.focus();
        }

        function captureOrgShiftFormMeta() {
            if (!createShiftForm) {
                return { shift_days: [], breaks: [], notes: '' };
            }
            const shift_days = Array.from(createShiftForm.querySelectorAll('input[name="shift_days[]"]:checked'))
                .map((input) => input.value)
                .filter(Boolean);
            const breaks = [];
            createShiftForm.querySelectorAll('[data-shift-breaks-row]').forEach((row) => {
                const label = row.querySelector('input[name*="[label]"]')?.value || '';
                const minutes = row.querySelector('input[name*="[minutes]"]')?.value || '';
                const paid = row.querySelector('select[name*="[paid]"]')?.value === '1';
                if (label === '' && (minutes === '' || Number(minutes) <= 0)) {
                    return;
                }
                breaks.push({
                    label,
                    minutes: minutes === '' ? '' : Number(minutes),
                    paid,
                });
            });
            const notesInput = document.getElementById('schedule-new-shift-notes');
            return {
                shift_days,
                breaks,
                notes: notesInput instanceof HTMLTextAreaElement || notesInput instanceof HTMLInputElement
                    ? notesInput.value
                    : '',
            };
        }

        function upsertCatalogEntry(payload) {
            if (!payload?.id) return;
            const id = String(payload.id);
            const existing = shiftCatalogById[id] || {};
            shiftCatalogById[id] = {
                ...existing,
                ...payload,
                id: Number(payload.id),
                shift_days: Array.isArray(payload.shift_days) ? payload.shift_days : (existing.shift_days || []),
                breaks: Array.isArray(payload.breaks) ? payload.breaks : (existing.breaks || []),
                notes: payload.notes != null ? payload.notes : (existing.notes || ''),
                employee_count: payload.employee_count != null
                    ? Number(payload.employee_count)
                    : (existing.employee_count || 0),
                schedule_count: payload.schedule_count != null
                    ? Number(payload.schedule_count)
                    : (existing.schedule_count || 0),
            };
        }

        function sharedShiftWarningText(entry) {
            const name = entry?.name || 'this shift';
            const id = entry?.id != null ? String(entry.id) : editingShiftId || '—';
            const employees = Number(entry?.employee_count || 0);
            const schedules = Number(entry?.schedule_count || 0);
            const usageParts = [];
            if (employees > 0) {
                usageParts.push(employees === 1 ? '1 employee' : employees + ' employees');
            }
            if (schedules > 0) {
                usageParts.push(schedules === 1 ? '1 schedule entry' : schedules + ' schedule entries');
            }
            const usage = usageParts.length > 0
                ? ' Currently used by ' + usageParts.join(' and ') + '.'
                : '';

            return 'Editing shift ID ' + id + ' ("' + name + '") updates the shared template for every employee who has this shift — not only the person on this screen.'
                + usage
                + ' Continue only if you intend that change.';
        }

        async function confirmSharedShiftEdit(entry) {
            const dialog = window.CruLynkDialog;
            if (dialog && typeof dialog.confirm === 'function') {
                return dialog.confirm({
                    title: 'Shared shift change',
                    text: sharedShiftWarningText(entry),
                    confirmText: 'Update shared shift',
                    cancelText: 'Go back',
                    icon: 'warning',
                    danger: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                });
            }
            return window.confirm(sharedShiftWarningText(entry));
        }

        async function submitCreateShift(event) {
            event.preventDefault();
            if (!createShiftForm) return;

            setCreateShiftError('');
            const isEdit = orgShiftModalMode === 'edit' && editingShiftId !== '';
            if (isEdit) {
                const entry = shiftCatalogById[editingShiftId] || { id: editingShiftId, name: document.getElementById('schedule-new-shift-name')?.value || 'this shift' };
                const confirmed = await confirmSharedShiftEdit(entry);
                if (!confirmed) {
                    return;
                }
            }

            if (createShiftSubmit) {
                createShiftSubmit.disabled = true;
            }

            try {
                const url = isEdit
                    ? createShiftUpdateUrlTemplate.replace('__ID__', editingShiftId)
                    : createShiftStoreUrl;
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: new FormData(createShiftForm),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data.message || (isEdit ? 'Could not update the shift.' : 'Could not create the shift.'));
                    setCreateShiftError(firstError);
                    window.CruLynkDialog?.toastError?.(firstError);
                    return;
                }

                upsertCatalogEntry({ ...data, ...captureOrgShiftFormMeta() });
                selectCreatedShift(data);
                closeCreateShiftModal();

                if (isEdit) {
                    window.CruLynkDialog?.toastSuccess?.(
                        data.times_changed
                            ? 'Shared shift updated. Schedule times were synced where this shift is used.'
                            : 'Shared shift details updated.'
                    );
                    window.location.reload();
                    return;
                }

                window.CruLynkDialog?.toastSuccess?.('Shift created and selected for this employee.');
            } catch (error) {
                const fallback = isEdit
                    ? 'Could not update the shift. Please try again.'
                    : 'Could not create the shift. Please try again.';
                setCreateShiftError(fallback);
                window.CruLynkDialog?.toastError?.(fallback);
            } finally {
                if (createShiftSubmit) {
                    createShiftSubmit.disabled = false;
                }
            }
        }

        function payloadFromTrigger(el) {
            return {
                shiftId: el.dataset.shiftId || '',
                employeePublicId: el.dataset.employeePublicId || '',
                employeeName: el.dataset.employeeName || '',
                dayLabel: el.dataset.dayLabel || '',
                scheduledDate: el.dataset.scheduledDate || '',
                entryType: el.dataset.entryType || TYPE_SHIFT,
                shiftTemplateId: el.dataset.shiftTemplateId || '',
                workLocationId: el.dataset.workLocationId || '',
                notes: el.dataset.notes || '',
                isSuggestion: el.dataset.isSuggestion || '0',
                timeRange: el.dataset.timeRange || '',
                durationLabel: el.dataset.durationLabel || '',
                blockTitle: el.dataset.blockTitle || '',
                blockSubtitle: el.dataset.blockSubtitle || '',
                blockMeta: el.dataset.blockMeta || '',
                status: el.dataset.status || '',
                leaveTypeId: el.dataset.leaveTypeId || '',
                leaveHours: el.dataset.leaveHours || '',
                leaveTypeName: el.dataset.leaveTypeName || '',
            };
        }

        document.querySelectorAll('[data-schedule-open]').forEach((el) => {
            el.addEventListener('click', () => openModal(payloadFromTrigger(el)));
        });

        shiftTemplateEl.addEventListener('change', () => {
            syncTimesFromShift();
            updateEditShiftButtonVisibility();
        });
        leaveTypeEl?.addEventListener('change', updateLeaveBalanceUi);

        form.addEventListener('submit', (event) => {
            if (entryTypeHidden.value === TYPE_TIME_OFF) {
                if (leaveTypeEl && leaveTypeEl.value && (!leaveHoursEl.value || Number(leaveHoursEl.value) <= 0)) {
                    event.preventDefault();
                    leaveHoursEl.focus();
                }
                return;
            }
            syncTimesFromShift();
            if (!shiftTemplateEl.value || !workLocationEl.value || !startHidden.value || !endHidden.value) {
                event.preventDefault();
                if (!shiftTemplateEl.value) shiftTemplateEl.focus();
                else workLocationEl.focus();
            }
        });

        document.getElementById('schedule-shift-modal-close')?.addEventListener('click', closeModal);
        document.getElementById('schedule-shift-cancel')?.addEventListener('click', handleCancel);

        menuToggle?.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = detailMenu.classList.contains('hidden');
            detailMenu.classList.toggle('hidden', !willOpen);
            menuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        detailMenu?.querySelectorAll('[data-status-action]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!currentPayload || !currentPayload.shiftId) return;

                const status = button.dataset.statusAction || '';
                const isClearing = status === '';
                let notes = '';

                if (!isClearing) {
                    const statusLabel = STATUS_LABELS[status] || 'status';
                    const dialog = window.CruLynkDialog;
                    const existingNotes = (currentPayload.notes || '').trim();

                    if (dialog && typeof dialog.promptNote === 'function') {
                        const note = await dialog.promptNote({
                            title: 'Mark as ' + statusLabel + '?',
                            text: (currentPayload.employeeName || 'Employee') + ' · ' + (currentPayload.dayLabel || formatDateLabel(currentPayload.scheduledDate)),
                            inputLabel: 'Comment (optional)',
                            inputPlaceholder: 'e.g. Worked 2 hours before calling in sick',
                            inputValue: existingNotes,
                            confirmText: 'Mark ' + statusLabel.toLowerCase(),
                            cancelText: 'Cancel',
                            danger: status === STATUS_NO_SHOW,
                        });

                        if (note === null) {
                            closeDetailMenu();
                            return;
                        }

                        notes = note;
                    } else {
                        const fallback = window.prompt(
                            'Comment (optional) for ' + statusLabel.toLowerCase() + ':',
                            existingNotes
                        );
                        if (fallback === null) {
                            closeDetailMenu();
                            return;
                        }
                        notes = fallback.trim();
                    }
                }

                closeDetailMenu();
                statusForm.action = statusUrlTemplate.replace('__ID__', currentPayload.shiftId);
                statusValueInput.value = status;
                if (statusNotesInput) {
                    statusNotesInput.value = notes;
                }
                statusForm.requestSubmit();
            });
        });

        document.addEventListener('click', (event) => {
            if (!detailMenu || detailMenu.classList.contains('hidden')) return;
            if (detailMenu.contains(event.target) || menuToggle?.contains(event.target)) return;
            closeDetailMenu();
        });
        detailEditButton?.addEventListener('click', () => {
            if (currentPayload) {
                openEditForm(currentPayload, true);
            }
        });
        detailDeleteButton?.addEventListener('click', () => deleteForm.requestSubmit());
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (createShiftModalIsOpen()) {
                closeCreateShiftModal();
                return;
            }
            if (modal.classList.contains('hidden')) return;
            if (detailMenu && !detailMenu.classList.contains('hidden')) {
                closeDetailMenu();
                return;
            }
            closeModal();
        });
        deleteButton.addEventListener('click', () => deleteForm.requestSubmit());
        convertToShiftButton?.addEventListener('click', () => {
            switchToShiftMode(
                convertToShiftButton.dataset.defaultShiftId,
                convertToShiftButton.dataset.defaultLocationId
            );
        });
        openCreateShiftButton?.addEventListener('click', openCreateShiftModal);
        openEditShiftButton?.addEventListener('click', () => openEditShiftModal(shiftTemplateEl?.value || ''));
        createShiftForm?.addEventListener('submit', submitCreateShift);
        document.getElementById('schedule-create-shift-close')?.addEventListener('click', closeCreateShiftModal);
        document.getElementById('schedule-create-shift-cancel')?.addEventListener('click', closeCreateShiftModal);
        createShiftModal?.addEventListener('click', (e) => {
            if (e.target === createShiftModal) closeCreateShiftModal();
        });

    })();
</script>
