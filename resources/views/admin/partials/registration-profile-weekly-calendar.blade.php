@php
    /** @var \App\Models\Employee $e */
    /** @var bool $canEditProfile */
    use App\Support\AdminWeeklyAvailability;

    $posted = old('availability');
    $schedule = is_array($posted)
        ? AdminWeeklyAvailability::dayScheduleFromPosted($posted)
        : AdminWeeklyAvailability::dayScheduleStateForEmployee(
            is_array($e->weekly_availability_json ?? null) ? $e->weekly_availability_json : null,
            $e->weekly_availability_summary ?? null
        );
    $dayKeys = AdminWeeklyAvailability::DAY_KEYS;
    $dayLabels = AdminWeeklyAvailability::FULL_DAY_LABELS;
    $timeInput = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2 text-sm text-brand-text';
@endphp
<div
    class="max-w-2xl rounded-2xl border border-brand-border bg-white p-4 shadow-sm ring-1 ring-black/[0.03] sm:p-5"
    @if ($canEditProfile) data-reg-day-schedule @endif
>
    <h4 class="text-base font-bold text-brand-text">Preferred weekly availability</h4>

    <div class="mt-4 space-y-3">
        @foreach ($dayKeys as $dKey)
            @php
                $day = $schedule[$dKey] ?? ['available' => false, 'periods' => []];
                $available = (bool) ($day['available'] ?? false);
                $periods = $day['periods'] ?? [];
                if ($canEditProfile && $periods === []) {
                    $periods = [['start' => '', 'end' => '']];
                }
            @endphp
            <div class="rounded-xl border border-brand-border bg-brand-surface/40 p-3 sm:p-4" data-reg-day="{{ $dKey }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-bold text-brand-text">{{ $dayLabels[$dKey] ?? ucfirst($dKey) }}</p>
                    @if ($canEditProfile)
                        <select
                            name="availability[{{ $dKey }}][status]"
                            data-reg-day-status
                            class="rounded-xl border border-brand-border bg-white px-3 py-2 text-sm text-brand-text"
                        >
                            <option value="available" @selected($available)>Available</option>
                            <option value="unavailable" @selected(! $available)>Not available</option>
                        </select>
                    @else
                        <span class="text-sm font-medium {{ $available ? 'text-brand-primary' : 'text-brand-text-secondary' }}">
                            {{ $available ? 'Available' : 'Not available' }}
                        </span>
                    @endif
                </div>

                <div class="{{ $available ? 'mt-3 space-y-2' : 'hidden' }}" data-reg-periods>
                    <div data-reg-period-list class="space-y-2">
                        @foreach ($periods as $index => $period)
                            @php
                                $start = (string) ($period['start'] ?? '');
                                $end = (string) ($period['end'] ?? '');
                                $overnight = AdminWeeklyAvailability::isOvernight($start, $end);
                            @endphp
                            <div data-reg-period-row>
                                @if ($canEditProfile)
                                    <div class="flex items-end gap-2">
                                        <label class="min-w-0 flex-1">
                                            <span class="mb-1 block text-xs text-brand-text-secondary">Start</span>
                                            <input
                                                type="time"
                                                step="60"
                                                data-reg-time="start"
                                                name="availability[{{ $dKey }}][periods][{{ $index }}][start]"
                                                value="{{ $start }}"
                                                class="{{ $timeInput }}"
                                            />
                                        </label>
                                        <label class="min-w-0 flex-1">
                                            <span class="mb-1 block text-xs text-brand-text-secondary">End</span>
                                            <input
                                                type="time"
                                                step="60"
                                                data-reg-time="end"
                                                name="availability[{{ $dKey }}][periods][{{ $index }}][end]"
                                                value="{{ $end }}"
                                                class="{{ $timeInput }}"
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            data-reg-remove-period
                                            class="mb-1 rounded-lg px-2 py-2 text-xs font-semibold text-red-600 {{ count($periods) > 1 ? '' : 'invisible' }}"
                                        >Remove</button>
                                    </div>
                                    <p class="mt-1 text-xs text-brand-primary {{ $overnight ? '' : 'hidden' }}" data-reg-overnight>Overnight — ends the next day.</p>
                                @else
                                    <p class="text-sm text-brand-text">
                                        {{ $start }}–{{ $end }}{{ $overnight ? ' (overnight)' : '' }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if ($canEditProfile)
                        <button type="button" data-reg-add-period class="text-sm font-semibold text-brand-primary">Add another period</button>
                    @endif
                </div>

                <p class="mt-2 text-sm text-brand-text-secondary {{ $available ? 'hidden' : '' }}" data-reg-unavailable-note>Not available this day.</p>
            </div>
        @endforeach
    </div>

    <template data-reg-period-template>
        <div data-reg-period-row>
            <div class="flex items-end gap-2">
                <label class="min-w-0 flex-1">
                    <span class="mb-1 block text-xs text-brand-text-secondary">Start</span>
                    <input type="time" step="60" data-reg-time="start" value="" class="{{ $timeInput }}" />
                </label>
                <label class="min-w-0 flex-1">
                    <span class="mb-1 block text-xs text-brand-text-secondary">End</span>
                    <input type="time" step="60" data-reg-time="end" value="" class="{{ $timeInput }}" />
                </label>
                <button type="button" data-reg-remove-period class="mb-1 rounded-lg px-2 py-2 text-xs font-semibold text-red-600">Remove</button>
            </div>
            <p class="mt-1 hidden text-xs text-brand-primary" data-reg-overnight>Overnight — ends the next day.</p>
        </div>
    </template>

    <p class="mt-4 text-sm leading-relaxed text-brand-text-secondary" data-reg-weekly-status>
        {{ AdminWeeklyAvailability::summaryTextFromDaySchedule($schedule) ?? 'No availability entered yet.' }}
    </p>
</div>
