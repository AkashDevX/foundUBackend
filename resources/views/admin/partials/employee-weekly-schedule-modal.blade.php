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
                <button type="button" id="schedule-shift-modal-close" class="rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Close">
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mt-3 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm shadow-sm">
                <p id="schedule-modal-employee" class="font-semibold text-brand-text">—</p>
                <p id="schedule-modal-date-label" class="mt-0.5 text-xs text-brand-text-secondary">—</p>
            </div>
            <p id="schedule-suggestion-banner" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                Suggested from work assignment — save to add to this week.
            </p>
        </header>

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

            <div id="schedule-shift-fields" class="space-y-4">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Shift</span>
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
                </label>

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
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-brand-text-secondary">
                    Marks the full day as <strong class="text-brand-text">time off</strong> on the roster (no shift hours).
                </p>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Reason (optional)</span>
                    <input type="text" name="notes" id="schedule-notes" maxlength="500" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20" placeholder="e.g. Annual leave, sick day">
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
        const notesEl = document.getElementById('schedule-notes');
        const employeeHidden = document.getElementById('schedule-employee-hidden');
        const dateHidden = document.getElementById('schedule-date-hidden');
        const startHidden = document.getElementById('schedule-start-hidden');
        const endHidden = document.getElementById('schedule-end-hidden');
        const previewEl = document.getElementById('schedule-shift-preview');

        const modeEl = document.getElementById('schedule-modal-mode');
        const titleEl = document.getElementById('schedule-shift-modal-title');
        const employeeLabelEl = document.getElementById('schedule-modal-employee');
        const dateLabelEl = document.getElementById('schedule-modal-date-label');
        const suggestionBanner = document.getElementById('schedule-suggestion-banner');

        const storeUrl = @json(route('admin.employees.weekly-schedule.shifts.store'));
        const updateUrlTemplate = @json(route('admin.employees.weekly-schedule.shifts.update', ['scheduleShift' => '__ID__']));
        const destroyUrlTemplate = @json(route('admin.employees.weekly-schedule.shifts.destroy', ['scheduleShift' => '__ID__']));

        if (!modal || !form) return;

        function formatDateLabel(isoDate) {
            if (!isoDate) return '—';
            const date = new Date(isoDate + 'T12:00:00');
            if (Number.isNaN(date.getTime())) return isoDate;
            return date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
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

            const isEdit = entryTypeHidden.dataset.edit === '1';
            modeEl.textContent = isEdit ? 'Editing' : (isTimeOff ? 'Day off' : 'New shift');
            titleEl.textContent = isTimeOff
                ? (isEdit ? 'Edit day off' : 'Add day off')
                : (isEdit ? 'Edit shift' : 'Add shift');
            submitButton.textContent = isTimeOff ? 'Save day off' : 'Save shift';
        }

        function switchToShiftMode(defaultShiftId, defaultLocationId) {
            entryTypeHidden.dataset.edit = '0';
            applyEntryType(TYPE_SHIFT);
            form.action = storeUrl;
            deleteButton.classList.add('hidden');
            convertToShiftButton.classList.add('hidden');
            shiftTemplateEl.value = defaultShiftId ? String(defaultShiftId) : '';
            workLocationEl.value = defaultLocationId ? String(defaultLocationId) : '';
            notesEl.value = '';
            syncTimesFromShift();
            (shiftTemplateEl.value ? workLocationEl : shiftTemplateEl).focus();
        }

        function openModal(payload) {
            const isEdit = Boolean(payload.shiftId);
            const isSuggestion = payload.isSuggestion === '1' || payload.isSuggestion === true;
            const entryType = isSuggestion ? TYPE_SHIFT : (payload.entryType || TYPE_SHIFT);

            entryTypeHidden.dataset.edit = isEdit ? '1' : '0';
            employeeLabelEl.textContent = payload.employeeName || 'Employee';
            dateLabelEl.textContent = payload.dayLabel || formatDateLabel(payload.scheduledDate);
            suggestionBanner.classList.toggle('hidden', !isSuggestion);

            employeeHidden.value = payload.employeePublicId || '';
            dateHidden.value = payload.scheduledDate || '';

            applyEntryType(entryType);

            if (isSuggestion) {
                modeEl.textContent = 'Suggested shift';
            }

            shiftTemplateEl.value = payload.shiftTemplateId ? String(payload.shiftTemplateId) : '';
            workLocationEl.value = payload.workLocationId ? String(payload.workLocationId) : '';
            notesEl.value = payload.notes || '';
            if (convertToShiftButton) {
                convertToShiftButton.dataset.defaultShiftId = payload.shiftTemplateId || '';
                convertToShiftButton.dataset.defaultLocationId = payload.workLocationId || '';
            }
            syncTimesFromShift();

            if (isSuggestion) {
                submitButton.textContent = 'Save to roster';
            }

            form.action = isEdit ? updateUrlTemplate.replace('__ID__', payload.shiftId) : storeUrl;
            deleteButton.classList.toggle('hidden', !isEdit);
            convertToShiftButton.classList.toggle('hidden', !isEdit || entryType !== TYPE_TIME_OFF);
            if (isEdit) {
                deleteForm.action = destroyUrlTemplate.replace('__ID__', payload.shiftId);
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            if (entryType === TYPE_TIME_OFF) {
                notesEl.focus();
            } else {
                (shiftTemplateEl.value ? workLocationEl : shiftTemplateEl).focus();
            }
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
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
            };
        }

        document.querySelectorAll('[data-schedule-open]').forEach((el) => {
            el.addEventListener('click', () => openModal(payloadFromTrigger(el)));
        });

        document.getElementById('schedule-create-shift')?.addEventListener('click', () => {
            openModal({
                shiftId: '',
                employeePublicId: '',
                employeeName: 'Pick an employee from the grid',
                dayLabel: '',
                scheduledDate: @json($redirectQuery['week'] ?? ''),
                entryType: TYPE_SHIFT,
                shiftTemplateId: '',
                workLocationId: '',
                notes: '',
                isSuggestion: '0',
            });
        });

        shiftTemplateEl.addEventListener('change', syncTimesFromShift);

        form.addEventListener('submit', (event) => {
            if (entryTypeHidden.value === TYPE_TIME_OFF) {
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
        document.getElementById('schedule-shift-cancel')?.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
        deleteButton.addEventListener('click', () => deleteForm.requestSubmit());
        convertToShiftButton?.addEventListener('click', () => {
            switchToShiftMode(
                convertToShiftButton.dataset.defaultShiftId,
                convertToShiftButton.dataset.defaultLocationId
            );
        });
    })();
</script>
