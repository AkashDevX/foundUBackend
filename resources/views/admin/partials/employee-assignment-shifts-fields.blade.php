@php
    /** @var \App\Models\Employee $employee */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Shift> $shifts */
    $shiftTimes = $shiftTimes ?? static function (?\App\Models\Shift $s): string {
        if ($s === null) {
            return '—';
        }
        $st = $s->start_time instanceof \Carbon\CarbonInterface ? $s->start_time->format('g:i A') : '—';
        $en = $s->end_time instanceof \Carbon\CarbonInterface ? $s->end_time->format('g:i A') : '—';

        return $st.'–'.$en;
    };
    $shiftDays = $shiftDays ?? static function (?\App\Models\Shift $s): string {
        if ($s === null || ! is_array($s->shift_days) || $s->shift_days === []) {
            return 'All days';
        }
        $map = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];

        return collect($s->shift_days)->map(fn ($d) => $map[$d] ?? null)->filter()->join(', ');
    };

    $employee->loadMissing(['assignmentShifts.shiftTemplate', 'assignedShift']);

    $existingRows = collect(old('assignment_shifts'))
        ->map(static fn (array $row): array => [
            'shift_id' => (string) ($row['shift_id'] ?? ''),
        ])
        ->filter(static fn (array $row): bool => $row['shift_id'] !== '')
        ->values();

    if ($existingRows->isEmpty()) {
        if ($employee->assignmentShifts->isNotEmpty()) {
            $existingRows = $employee->assignmentShifts->map(static fn ($row): array => [
                'shift_id' => (string) $row->shift_id,
            ]);
        } elseif ($employee->shift_id) {
            $existingRows = collect([[
                'shift_id' => (string) $employee->shift_id,
            ]]);
        }
    }

    if ($existingRows->isEmpty()) {
        $existingRows = collect([['shift_id' => '']]);
    }

    $selectClass = $selectClass ?? 'mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25';
    $rowGridClass = $rowGridClass ?? 'grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end';
@endphp

<div class="{{ $wrapperClass ?? 'lg:col-span-4' }}" data-assignment-shifts-root>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Shifts</label>
    </div>

    <div class="mt-2 space-y-3" data-assignment-shifts-list>
        @foreach ($existingRows as $index => $row)
            <div class="{{ $rowGridClass }} rounded-xl border border-brand-border bg-brand-surface/30 p-3" data-assignment-shift-row>
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Shift</label>
                        <button
                            type="button"
                            data-assignment-create-shift
                            class="text-[11px] font-semibold text-brand-primary hover:text-brand-primary-dark hover:underline"
                        >
                            + New shift
                        </button>
                    </div>
                    <select name="assignment_shifts[{{ $index }}][shift_id]" data-assignment-shift-select class="{{ $selectClass }}">
                        <option value="">— Select shift —</option>
                        @foreach ($shifts as $sh)
                            <option value="{{ $sh->id }}" @selected((string) $row['shift_id'] === (string) $sh->id)>{{ $sh->name }} ({{ $shiftTimes($sh) }}, {{ $shiftDays($sh) }})</option>
                        @endforeach
                    </select>
                    <!-- <p class="mt-1.5 text-[11px] text-brand-text-secondary">Not in the list? Create a shift here.</p> -->
                </div>
                <div class="flex justify-end sm:pb-0.5">
                    <button type="button" data-assignment-shift-remove class="rounded-lg border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text-secondary shadow-sm hover:bg-red-50 hover:text-red-700">
                        Remove
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" data-assignment-shifts-add class="mt-3 inline-flex items-center rounded-xl border border-dashed border-brand-border px-4 py-2 text-xs font-semibold text-brand-primary hover:border-brand-primary/40 hover:bg-brand-primary/[0.04]">
        + Add shift
    </button>

    <template data-assignment-shifts-template>
        <div class="{{ $rowGridClass }} rounded-xl border border-brand-border bg-brand-surface/30 p-3" data-assignment-shift-row>
            <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Shift</label>
                    <button
                        type="button"
                        data-assignment-create-shift
                        class="text-[11px] font-semibold text-brand-primary hover:text-brand-primary-dark hover:underline"
                    >
                        + New shift
                    </button>
                </div>
                <select data-field="shift_id" data-assignment-shift-select class="{{ $selectClass }}">
                    <option value="">— Select shift —</option>
                    @foreach ($shifts as $sh)
                        <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $shiftTimes($sh) }}, {{ $shiftDays($sh) }})</option>
                    @endforeach
                </select>
                <!-- <p class="mt-1.5 text-[11px] text-brand-text-secondary">Not in the list? Create a shift here.</p> -->
            </div>
            <div class="flex justify-end sm:pb-0.5">
                <button type="button" data-assignment-shift-remove class="rounded-lg border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text-secondary shadow-sm hover:bg-red-50 hover:text-red-700">
                    Remove
                </button>
            </div>
        </div>
    </template>
</div>

@once
    @push('scripts')
        @vite(['resources/js/shift-breaks.js'])
        <div
            id="assignment-create-shift-modal"
            class="fixed inset-0 z-[60] hidden items-center justify-center bg-brand-primary-dark/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="assignment-create-shift-title"
        >
            <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
                <header class="shrink-0 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Organization shift</p>
                            <h2 id="assignment-create-shift-title" class="mt-1 text-lg font-bold text-brand-text">Create new shift</h2>
                            <p class="mt-1 text-xs text-brand-text-secondary">Saved to organization setup, then selected for this assignment row.</p>
                        </div>
                        <button type="button" id="assignment-create-shift-close" class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Close">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </header>
                <form id="assignment-create-shift-form" class="flex min-h-0 flex-1 flex-col" action="{{ route('admin.workforce.shifts.store') }}" method="post">
                    @csrf
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
                        <p id="assignment-create-shift-error" class="hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800"></p>
                        @include('admin.partials.shift-create-fields', [
                            'idPrefix' => 'assignment-new-shift',
                            'breaksFieldId' => 'assignment-new-shift-breaks',
                        ])
                    </div>
                    <div class="shrink-0 flex justify-end gap-2 border-t border-brand-border px-5 py-4">
                        <button type="button" id="assignment-create-shift-cancel" class="rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary shadow-sm hover:bg-brand-surface">
                            Cancel
                        </button>
                        <button type="submit" id="assignment-create-shift-submit" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-primary-dark">
                            Create and select
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            (function () {
                const DAY_LABELS = { mon: 'Mon', tue: 'Tue', wed: 'Wed', thu: 'Thu', fri: 'Fri', sat: 'Sat', sun: 'Sun' };
                const createShiftStoreUrl = @json(route('admin.workforce.shifts.store'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const createShiftModal = document.getElementById('assignment-create-shift-modal');
                const createShiftForm = document.getElementById('assignment-create-shift-form');
                const createShiftError = document.getElementById('assignment-create-shift-error');
                const createShiftSubmit = document.getElementById('assignment-create-shift-submit');

                /** @type {HTMLSelectElement|null} */
                let activeSelect = null;

                function reindexRows(list) {
                    list.querySelectorAll('[data-assignment-shift-row]').forEach((row, index) => {
                        const shiftSelect = row.querySelector('[data-assignment-shift-select], [name*="[shift_id]"], [data-field="shift_id"]');
                        if (shiftSelect) {
                            shiftSelect.name = 'assignment_shifts[' + index + '][shift_id]';
                            shiftSelect.setAttribute('data-assignment-shift-select', '');
                            shiftSelect.removeAttribute('data-field');
                        }
                    });
                }

                function formatTimeLabel(hhmm) {
                    if (!hhmm || typeof hhmm !== 'string') return '—';
                    const parts = hhmm.split(':');
                    const hours = Number(parts[0]);
                    const minutes = Number(parts[1] || 0);
                    if (Number.isNaN(hours) || Number.isNaN(minutes)) return hhmm;
                    const suffix = hours >= 12 ? 'PM' : 'AM';
                    const hour12 = ((hours + 11) % 12) + 1;
                    return hour12 + ':' + String(minutes).padStart(2, '0') + ' ' + suffix;
                }

                function daysLabelFromForm() {
                    if (!createShiftForm) return 'All days';
                    const checked = Array.from(createShiftForm.querySelectorAll('input[name="shift_days[]"]:checked'))
                        .map((input) => DAY_LABELS[input.value] || input.value)
                        .filter(Boolean);
                    return checked.length === 0 ? 'All days' : checked.join(', ');
                }

                function assignmentOptionLabel(payload) {
                    const name = payload.name || 'Shift';
                    const times = formatTimeLabel(payload.start_time) + '–' + formatTimeLabel(payload.end_time);
                    return name + ' (' + times + ', ' + daysLabelFromForm() + ')';
                }

                function resetCreateShiftBreaks() {
                    const list = createShiftForm?.querySelector('[data-shift-breaks-list]');
                    if (!list) return;
                    list.querySelectorAll('[data-shift-breaks-row]').forEach((row, index) => {
                        if (index === 0) {
                            row.querySelectorAll('input').forEach((input) => {
                                if (input instanceof HTMLInputElement) input.value = '';
                            });
                            const select = row.querySelector('select');
                            if (select instanceof HTMLSelectElement) select.value = '0';
                            return;
                        }
                        row.remove();
                    });
                }

                function setCreateShiftError(message) {
                    if (!createShiftError) return;
                    const text = String(message || '').trim();
                    createShiftError.textContent = text;
                    createShiftError.classList.toggle('hidden', text === '');
                }

                function createShiftModalIsOpen() {
                    return Boolean(createShiftModal && !createShiftModal.classList.contains('hidden'));
                }

                function openCreateShiftModal(selectEl) {
                    if (!createShiftModal || !createShiftForm) return;
                    activeSelect = selectEl instanceof HTMLSelectElement ? selectEl : null;
                    createShiftForm.reset();
                    resetCreateShiftBreaks();
                    setCreateShiftError('');
                    createShiftModal.classList.remove('hidden');
                    createShiftModal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                    document.getElementById('assignment-new-shift-name')?.focus();
                }

                function closeCreateShiftModal() {
                    if (!createShiftModal) return;
                    createShiftModal.classList.add('hidden');
                    createShiftModal.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                    activeSelect = null;
                    setCreateShiftError('');
                }

                function upsertOption(selectEl, payload, label) {
                    if (!(selectEl instanceof HTMLSelectElement) || !payload?.id) return;
                    const id = String(payload.id);
                    let option = Array.from(selectEl.options).find((opt) => opt.value === id) || null;
                    if (!option) {
                        option = document.createElement('option');
                        option.value = id;
                        selectEl.appendChild(option);
                    }
                    option.textContent = label;
                }

                function injectCreatedShift(payload) {
                    if (!payload?.id) return;
                    const label = assignmentOptionLabel(payload);
                    const id = String(payload.id);

                    document.querySelectorAll('[data-assignment-shift-select]').forEach((selectEl) => {
                        upsertOption(selectEl, payload, label);
                    });

                    document.querySelectorAll('[data-assignment-shifts-template]').forEach((template) => {
                        if (!(template instanceof HTMLTemplateElement)) return;
                        template.content.querySelectorAll('[data-assignment-shift-select]').forEach((selectEl) => {
                            upsertOption(selectEl, payload, label);
                        });
                    });

                    if (activeSelect instanceof HTMLSelectElement) {
                        upsertOption(activeSelect, payload, label);
                        activeSelect.value = id;
                        activeSelect.focus();
                    }
                }

                async function submitCreateShift(event) {
                    event.preventDefault();
                    if (!createShiftForm) return;

                    setCreateShiftError('');
                    if (createShiftSubmit) createShiftSubmit.disabled = true;

                    try {
                        const response = await fetch(createShiftStoreUrl, {
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
                                : (data.message || 'Could not create the shift.');
                            setCreateShiftError(firstError);
                            window.CruLynkDialog?.toastError?.(firstError);
                            return;
                        }

                        injectCreatedShift(data);
                        closeCreateShiftModal();
                        window.CruLynkDialog?.toastSuccess?.('Shift created and selected for this assignment.');
                    } catch (error) {
                        setCreateShiftError('Could not create the shift. Please try again.');
                        window.CruLynkDialog?.toastError?.('Could not create the shift. Please try again.');
                    } finally {
                        if (createShiftSubmit) createShiftSubmit.disabled = false;
                    }
                }

                function initRoot(root) {
                    const list = root.querySelector('[data-assignment-shifts-list]');
                    const template = root.querySelector('[data-assignment-shifts-template]');
                    const addButton = root.querySelector('[data-assignment-shifts-add]');
                    if (!list || !template || !addButton || root.dataset.assignmentShiftsReady === '1') {
                        return;
                    }
                    root.dataset.assignmentShiftsReady = '1';

                    addButton.addEventListener('click', () => {
                        const clone = template.content.cloneNode(true);
                        list.appendChild(clone);
                        reindexRows(list);
                        const lastRow = list.querySelector('[data-assignment-shift-row]:last-child');
                        lastRow?.querySelector('[data-assignment-shift-select]')?.focus();
                    });

                    list.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof Element)) return;

                        const createButton = target.closest('[data-assignment-create-shift]');
                        if (createButton) {
                            const row = createButton.closest('[data-assignment-shift-row]');
                            const selectEl = row?.querySelector('[data-assignment-shift-select]');
                            openCreateShiftModal(selectEl);
                            return;
                        }

                        const removeButton = target.closest('[data-assignment-shift-remove]');
                        if (!removeButton) {
                            return;
                        }
                        const rows = list.querySelectorAll('[data-assignment-shift-row]');
                        if (rows.length <= 1) {
                            const row = removeButton.closest('[data-assignment-shift-row]');
                            row?.querySelectorAll('select, input').forEach((field) => {
                                if (field.tagName === 'SELECT') {
                                    field.selectedIndex = 0;
                                } else {
                                    field.value = '';
                                }
                            });
                            return;
                        }
                        removeButton.closest('[data-assignment-shift-row]')?.remove();
                        reindexRows(list);
                    });
                }

                document.querySelectorAll('[data-assignment-shifts-root]').forEach(initRoot);

                createShiftForm?.addEventListener('submit', submitCreateShift);
                document.getElementById('assignment-create-shift-close')?.addEventListener('click', closeCreateShiftModal);
                document.getElementById('assignment-create-shift-cancel')?.addEventListener('click', closeCreateShiftModal);
                createShiftModal?.addEventListener('click', (event) => {
                    if (event.target === createShiftModal) closeCreateShiftModal();
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && createShiftModalIsOpen()) {
                        closeCreateShiftModal();
                    }
                });
            })();
        </script>
    @endpush
@endonce
