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
            'unpaid_break_minutes' => (string) ($row['unpaid_break_minutes'] ?? ''),
        ])
        ->filter(static fn (array $row): bool => $row['shift_id'] !== '' || $row['unpaid_break_minutes'] !== '')
        ->values();

    if ($existingRows->isEmpty()) {
        if ($employee->assignmentShifts->isNotEmpty()) {
            $existingRows = $employee->assignmentShifts->map(static fn ($row): array => [
                'shift_id' => (string) $row->shift_id,
                'unpaid_break_minutes' => (string) $row->unpaid_break_minutes,
            ]);
        } elseif ($employee->shift_id) {
            $existingRows = collect([[
                'shift_id' => (string) $employee->shift_id,
                'unpaid_break_minutes' => '0',
            ]]);
        }
    }

    if ($existingRows->isEmpty()) {
        $existingRows = collect([['shift_id' => '', 'unpaid_break_minutes' => '']]);
    }

    $selectClass = $selectClass ?? 'mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25';
    $inputClass = $inputClass ?? $selectClass;
    $rowGridClass = $rowGridClass ?? 'grid gap-3 sm:grid-cols-[minmax(0,1fr)_140px_auto] sm:items-end';
@endphp

<div class="{{ $wrapperClass ?? 'lg:col-span-4' }}" data-assignment-shifts-root>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Shifts</label>
    </div>

    <div class="mt-2 space-y-3" data-assignment-shifts-list>
        @foreach ($existingRows as $index => $row)
            <div class="{{ $rowGridClass }} rounded-xl border border-brand-border bg-brand-surface/30 p-3" data-assignment-shift-row>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Shift</label>
                    <select name="assignment_shifts[{{ $index }}][shift_id]" class="{{ $selectClass }}">
                        <option value="">— Select shift —</option>
                        @foreach ($shifts as $sh)
                            <option value="{{ $sh->id }}" @selected((string) $row['shift_id'] === (string) $sh->id)>{{ $sh->name }} ({{ $shiftTimes($sh) }}, {{ $shiftDays($sh) }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Unpaid break (min, optional)</label>
                    <input
                        type="number"
                        name="assignment_shifts[{{ $index }}][unpaid_break_minutes]"
                        min="0"
                        max="480"
                        step="1"
                        value="{{ $row['unpaid_break_minutes'] }}"
                        class="{{ $inputClass }}"
                        placeholder="e.g. 30"
                    />
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
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Shift</label>
                <select data-field="shift_id" class="{{ $selectClass }}">
                    <option value="">— Select shift —</option>
                    @foreach ($shifts as $sh)
                        <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $shiftTimes($sh) }}, {{ $shiftDays($sh) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Unpaid break (min, optional)</label>
                <input
                    type="number"
                    data-field="unpaid_break_minutes"
                    min="0"
                    max="480"
                    step="1"
                    class="{{ $inputClass }}"
                    placeholder="e.g. 30"
                />
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
        <script>
            (function () {
                function reindexRows(list) {
                    list.querySelectorAll('[data-assignment-shift-row]').forEach((row, index) => {
                        const shiftSelect = row.querySelector('[name*="[shift_id]"], [data-field="shift_id"]');
                        const breakInput = row.querySelector('[name*="[unpaid_break_minutes]"], [data-field="unpaid_break_minutes"]');
                        if (shiftSelect) {
                            shiftSelect.name = 'assignment_shifts[' + index + '][shift_id]';
                            shiftSelect.removeAttribute('data-field');
                        }
                        if (breakInput) {
                            breakInput.name = 'assignment_shifts[' + index + '][unpaid_break_minutes]';
                            breakInput.removeAttribute('data-field');
                        }
                    });
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
                        lastRow?.querySelector('select')?.focus();
                    });

                    list.addEventListener('click', (event) => {
                        const removeButton = event.target.closest('[data-assignment-shift-remove]');
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
            })();
        </script>
    @endpush
@endonce
