@php
    /** @var list<array{label?: string, minutes?: int|string, paid?: bool|string|int}> $breaks */
    $breaks = is_array($breaks ?? null) ? array_values($breaks) : [];
    if ($breaks === []) {
        $breaks = [['label' => '', 'minutes' => '', 'paid' => false]];
    }
    $fieldId = $fieldId ?? 'shift-breaks';
@endphp

<div
    class="space-y-2"
    data-shift-breaks-repeater
    data-max-breaks="8"
>
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <p class="{{ $lbl ?? 'mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary' }}">Breaks</p>
            <p class="text-[11px] text-brand-text-secondary">Add each break and mark it paid or unpaid.</p>
        </div>
        <button
            type="button"
            class="rounded-lg border border-brand-border bg-white px-2.5 py-1.5 text-[11px] font-bold text-brand-primary shadow-sm transition hover:bg-brand-surface"
            data-shift-breaks-add
        >
            + Add break
        </button>
    </div>

    <div class="space-y-2" data-shift-breaks-list>
        @foreach ($breaks as $index => $break)
            @php
                $isPaid = filter_var($break['paid'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || ($break['paid'] ?? null) === 1
                    || ($break['paid'] ?? null) === '1';
            @endphp
            <div class="grid gap-2 rounded-xl border border-brand-border/80 bg-white p-2.5 shadow-sm sm:grid-cols-[minmax(0,1fr)_5.5rem_7rem_2rem]" data-shift-breaks-row>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Label</label>
                    <input
                        type="text"
                        name="shift_breaks[{{ $index }}][label]"
                        value="{{ $break['label'] ?? '' }}"
                        maxlength="80"
                        class="{{ $in }}"
                        placeholder="e.g. Lunch"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Minutes</label>
                    <input
                        type="number"
                        name="shift_breaks[{{ $index }}][minutes]"
                        value="{{ $break['minutes'] ?? '' }}"
                        min="1"
                        max="480"
                        class="{{ $in }}"
                        placeholder="30"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Type</label>
                    <select name="shift_breaks[{{ $index }}][paid]" class="{{ $in }}">
                        <option value="0" @selected(! $isPaid)>Unpaid</option>
                        <option value="1" @selected($isPaid)>Paid</option>
                    </select>
                </div>
                <div class="flex items-end justify-end sm:justify-center">
                    <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-brand-border text-brand-text-secondary transition hover:border-red-200 hover:bg-red-50 hover:text-red-700"
                        data-shift-breaks-remove
                        aria-label="Remove break"
                        title="Remove break"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    <template data-shift-breaks-template>
        <div class="grid gap-2 rounded-xl border border-brand-border/80 bg-white p-2.5 shadow-sm sm:grid-cols-[minmax(0,1fr)_5.5rem_7rem_2rem]" data-shift-breaks-row>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Label</label>
                <input type="text" name="shift_breaks[__INDEX__][label]" value="" maxlength="80" class="{{ $in }}" placeholder="e.g. Lunch" />
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Minutes</label>
                <input type="number" name="shift_breaks[__INDEX__][minutes]" value="" min="1" max="480" class="{{ $in }}" placeholder="30" />
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Type</label>
                <select name="shift_breaks[__INDEX__][paid]" class="{{ $in }}">
                    <option value="0" selected>Unpaid</option>
                    <option value="1">Paid</option>
                </select>
            </div>
            <div class="flex items-end justify-end sm:justify-center">
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-brand-border text-brand-text-secondary transition hover:border-red-200 hover:bg-red-50 hover:text-red-700"
                    data-shift-breaks-remove
                    aria-label="Remove break"
                    title="Remove break"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
    </template>
</div>
