@php
    $breakRules = \App\Support\BreakWindowSettings::current($company->tenant_connection);
    $breakEnabled = (bool) old('break_rule_enabled', $breakRules['enabled']);
    $startHours = old('start_hours', \App\Support\BreakWindow::minutesToHoursInput((int) $breakRules['start_minutes']));
    $endHours = old('end_hours', \App\Support\BreakWindow::minutesToHoursInput((int) $breakRules['end_minutes']));
    $requiredAfterHours = old('required_after_hours', \App\Support\BreakWindow::minutesToHoursInput((int) $breakRules['required_after_minutes']));
    $reminderLead = old('reminder_lead_minutes', (int) $breakRules['reminder_lead_minutes']);
    $breakTimesValid = (float) $endHours > (float) $startHours;
    $breakRulesOpen = request()->query('break_rules') === '1'
        || $errors->has('start_hours')
        || $errors->has('end_hours')
        || $errors->has('required_after_hours')
        || $errors->has('reminder_lead_minutes')
        || $errors->has('break_rule_enabled');
    $inputClass = 'min-w-0 w-full flex-1 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm tabular-nums text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20 disabled:cursor-not-allowed disabled:opacity-50';
@endphp

<details class="mb-4 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]" @if($breakRulesOpen) open @endif data-meal-break>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 bg-gradient-to-br from-amber-50 via-white to-white px-4 py-4 sm:px-6 [&::-webkit-details-marker]:hidden">
        <div class="min-w-0">
            <p class="text-sm font-bold uppercase tracking-[0.14em] text-amber-800">Meal breaks</p>
            <p class="mt-1 text-sm text-brand-text-secondary">Between these hours of the shift</p>
        </div>
        <span
            data-meal-break-status
            class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $breakEnabled ? 'bg-amber-100 text-amber-900' : 'bg-brand-surface text-brand-text-secondary' }}"
        >
            {{ $breakEnabled ? 'On' : 'Off' }}
        </span>
    </summary>

    <form method="post" action="{{ route('admin.employees.time-clock.break-window.update') }}" class="space-y-5 border-t border-brand-border px-4 py-5 sm:px-6" data-meal-break-form>
        @csrf
        @if (! $breakRules['ready'])
            <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Meal break settings are not ready yet. Refresh this page. If this message stays, contact support.
            </p>
        @endif

        <label class="flex items-center gap-3">
            <input type="hidden" name="break_rule_enabled" value="0">
            <input
                type="checkbox"
                name="break_rule_enabled"
                value="1"
                class="size-4 shrink-0 rounded border-brand-border text-brand-primary focus:ring-brand-primary/30"
                data-meal-break-enabled
                @checked($breakEnabled)
                @disabled(! $breakRules['ready'])
            >
            <span class="text-sm font-semibold text-brand-text">Only between these hours</span>
        </label>
        @error('break_rule_enabled')
            <p class="-mt-3 text-xs text-red-700">{{ $message }}</p>
        @enderror

        <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="flex min-w-0 flex-col">
                <span class="mb-2 text-sm font-semibold leading-5 text-brand-text">From hour</span>
                <div class="flex items-center gap-2">
                    <input
                        type="number"
                        name="start_hours"
                        min="1"
                        max="12"
                        step="0.5"
                        value="{{ $startHours }}"
                        @disabled(! $breakRules['ready'])
                        class="{{ $inputClass }}"
                    >
                    <span class="shrink-0 whitespace-nowrap text-xs font-medium text-brand-text-secondary" style="width:3.25rem">hours</span>
                </div>
                @error('start_hours')
                    <span class="mt-1 text-xs text-red-700">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex min-w-0 flex-col">
                <span class="mb-2 text-sm font-semibold leading-5 text-brand-text">Until hour</span>
                <div class="flex items-center gap-2">
                    <input
                        type="number"
                        name="end_hours"
                        min="1"
                        max="16"
                        step="0.5"
                        value="{{ $endHours }}"
                        @disabled(! $breakRules['ready'])
                        class="{{ $inputClass }}"
                    >
                    <span class="shrink-0 whitespace-nowrap text-xs font-medium text-brand-text-secondary" style="width:3.25rem">hours</span>
                </div>
                @error('end_hours')
                    <span class="mt-1 text-xs text-red-700">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex min-w-0 flex-col">
                <span class="mb-2 text-sm font-semibold leading-5 text-brand-text">Shifts longer than</span>
                <div class="flex items-center gap-2">
                    <input
                        type="number"
                        name="required_after_hours"
                        min="0"
                        max="16"
                        step="0.5"
                        value="{{ $requiredAfterHours }}"
                        @disabled(! $breakRules['ready'])
                        class="{{ $inputClass }}"
                    >
                    <span class="shrink-0 whitespace-nowrap text-xs font-medium text-brand-text-secondary" style="width:3.25rem">hours</span>
                </div>
                @error('required_after_hours')
                    <span class="mt-1 text-xs text-red-700">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex min-w-0 flex-col">
                <span class="mb-2 text-sm font-semibold leading-5 text-brand-text">Remind before</span>
                <div class="flex items-center gap-2">
                    <input
                        type="number"
                        name="reminder_lead_minutes"
                        min="0"
                        max="120"
                        step="1"
                        value="{{ $reminderLead }}"
                        @disabled(! $breakRules['ready'])
                        class="{{ $inputClass }}"
                    >
                    <span class="shrink-0 whitespace-nowrap text-xs font-medium text-brand-text-secondary" style="width:3.25rem">minutes</span>
                </div>
                @error('reminder_lead_minutes')
                    <span class="mt-1 text-xs text-red-700">{{ $message }}</span>
                @enderror
            </label>
        </div>

        <p data-meal-break-warn @class(['text-xs text-red-700', 'hidden' => $breakTimesValid])>
            Until hour has to be later than from hour.
        </p>

        <button
            type="submit"
            @disabled(! $breakRules['ready'])
            class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary-dark disabled:cursor-not-allowed disabled:opacity-50"
        >
            Save
        </button>
    </form>
</details>

<script>
    (function () {
        const root = document.querySelector('[data-meal-break]');
        const form = root ? root.querySelector('[data-meal-break-form]') : null;
        if (!root || !form) return;

        const startInput = form.querySelector('[name="start_hours"]');
        const endInput = form.querySelector('[name="end_hours"]');
        const enabledInput = form.querySelector('[data-meal-break-enabled]');
        const status = root.querySelector('[data-meal-break-status]');
        const warn = form.querySelector('[data-meal-break-warn]');

        function refresh() {
            const on = !!(enabledInput && enabledInput.checked);
            const start = Number(startInput && startInput.value);
            const end = Number(endInput && endInput.value);
            const valid = Number.isFinite(start) && Number.isFinite(end) && end > start;

            if (status) {
                status.textContent = on ? 'On' : 'Off';
                status.classList.toggle('bg-amber-100', on);
                status.classList.toggle('text-amber-900', on);
                status.classList.toggle('bg-brand-surface', !on);
                status.classList.toggle('text-brand-text-secondary', !on);
            }
            if (warn) warn.classList.toggle('hidden', valid);
        }

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
    })();
</script>
