@php
    $idPrefix = is_string($idPrefix ?? null) && $idPrefix !== '' ? $idPrefix : 'shift';
    $fieldInputClass = is_string($fieldInputClass ?? null)
        ? $fieldInputClass
        : (is_string($in ?? null) ? $in : 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20');
    $fieldLabelClass = is_string($fieldLabelClass ?? null)
        ? $fieldLabelClass
        : (is_string($lbl ?? null) ? $lbl : 'text-xs font-semibold uppercase tracking-wide text-brand-label');
    // Avoid clashing with weekly-schedule `$row` arrays when this partial is nested there.
    $fieldRowClass = is_string($fieldRowClass ?? null)
        ? $fieldRowClass
        : (is_string($row ?? null) ? $row : 'space-y-1.5');
    $shiftDaysMap = is_array($shiftDaysMap ?? null) ? $shiftDaysMap : [
        'mon' => 'Mon',
        'tue' => 'Tue',
        'wed' => 'Wed',
        'thu' => 'Thu',
        'fri' => 'Fri',
        'sat' => 'Sat',
        'sun' => 'Sun',
    ];
    $shiftDayLabel = is_string($shiftDayLabel ?? null)
        ? $shiftDayLabel
        : 'relative flex min-h-[2.75rem] cursor-pointer select-none items-center justify-center overflow-hidden rounded-xl border border-brand-border bg-white px-3 py-2 text-center text-xs font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/40 hover:bg-brand-surface/60 [&:has(input:checked)]:border-brand-primary [&:has(input:checked)]:bg-brand-primary [&:has(input:checked)]:text-white [&:has(input:checked)]:shadow-md [&:has(input:checked)]:shadow-brand-primary/25';
    $selectedDays = is_array($selectedDays ?? null) ? $selectedDays : [];
    $hideName = (bool) ($hideName ?? false);
    $nameValue = is_scalar($nameValue ?? null) ? (string) $nameValue : '';
    $startValue = is_scalar($startValue ?? null) ? (string) $startValue : '';
    $endValue = is_scalar($endValue ?? null) ? (string) $endValue : '';
    $notesValue = is_scalar($notesValue ?? null) ? (string) $notesValue : '';
    $breaks = is_array($breaks ?? null) ? $breaks : [['label' => '', 'minutes' => '', 'paid' => false]];
    $breaksFieldId = is_string($breaksFieldId ?? null) && $breaksFieldId !== ''
        ? $breaksFieldId
        : ($idPrefix.'-breaks');
@endphp

@unless ($hideName)
<div class="{{ $fieldRowClass }}">
    <label for="{{ $idPrefix }}-name" class="{{ $fieldLabelClass }}">Name</label>
    <input id="{{ $idPrefix }}-name" name="shift_name" required maxlength="160" value="{{ $nameValue }}" class="{{ $fieldInputClass }}" placeholder="e.g. Morning" />
</div>
@endunless
<div class="{{ $fieldRowClass }}">
    <span class="{{ $fieldLabelClass }}">Hours</span>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label for="{{ $idPrefix }}-start" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Start</label>
            <input id="{{ $idPrefix }}-start" name="shift_start_time" type="time" required value="{{ $startValue }}" class="{{ $fieldInputClass }}" />
        </div>
        <div>
            <label for="{{ $idPrefix }}-end" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">End</label>
            <input id="{{ $idPrefix }}-end" name="shift_end_time" type="time" required value="{{ $endValue }}" class="{{ $fieldInputClass }}" />
        </div>
    </div>
</div>
<div class="{{ $fieldRowClass }}">
    <span class="{{ $fieldLabelClass }}">Days</span>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach ($shiftDaysMap as $dayKey => $dayLabel)
            <label class="{{ $shiftDayLabel }}">
                <span class="relative z-0">{{ $dayLabel }}</span>
                <input type="checkbox" name="shift_days[]" value="{{ $dayKey }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" @checked(in_array($dayKey, $selectedDays, true)) />
            </label>
        @endforeach
    </div>
</div>
<div class="{{ $fieldRowClass }}">
    <div class="sm:col-span-1">
        @include('admin.partials.shift-breaks-fields', [
            'breaks' => $breaks,
            'lbl' => $fieldLabelClass,
            'in' => $fieldInputClass,
            'fieldId' => $breaksFieldId,
        ])
    </div>
</div>
<div class="{{ $fieldRowClass }}">
    <label for="{{ $idPrefix }}-notes" class="{{ $fieldLabelClass }}">Notes</label>
    <textarea id="{{ $idPrefix }}-notes" name="shift_notes" rows="2" maxlength="2000" class="{{ $fieldInputClass }} min-h-[4.5rem] resize-y" placeholder="Optional roster notes">{{ $notesValue }}</textarea>
</div>
