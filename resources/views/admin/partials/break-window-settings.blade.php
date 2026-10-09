@php
    $breakRules = \App\Support\BreakWindowSettings::current($company->tenant_connection);
    $breakEnabled = (bool) old('break_rule_enabled', $breakRules['enabled']);
    $startHours = old('start_hours', \App\Support\BreakWindow::minutesToHoursInput((int) $breakRules['start_minutes']));
    $endHours = old('end_hours', \App\Support\BreakWindow::minutesToHoursInput((int) $breakRules['end_minutes']));
    $requiredAfterHours = old('required_after_hours', \App\Support\BreakWindow::minutesToHoursInput((int) $breakRules['required_after_minutes']));
    $reminderLead = old('reminder_lead_minutes', (int) $breakRules['reminder_lead_minutes']);
    $breakExample = \App\Support\BreakWindow::exampleSentence(
        \App\Support\BreakWindow::hoursToMinutes((float) $startHours),
        \App\Support\BreakWindow::hoursToMinutes((float) $endHours),
    );
    $breakRulesOpen = request()->query('break_rules') === '1'
        || $errors->has('start_hours')
        || $errors->has('end_hours')
        || $errors->has('required_after_hours')
        || $errors->has('reminder_lead_minutes')
        || $errors->has('break_rule_enabled');
@endphp

<details class="mb-4 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]" @if($breakRulesOpen) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-gradient-to-br from-amber-50 via-white to-white px-4 py-4 sm:px-6 [&::-webkit-details-marker]:hidden">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.14em] text-amber-800">Break rules</p>
            <p class="mt-1 text-sm text-brand-text-secondary">When employees may take their meal break</p>
        </div>
        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-900">
            {{ $breakEnabled ? 'On' : 'Off' }}
        </span>
    </summary>

    <form method="post" action="{{ route('admin.employees.time-clock.break-window.update') }}" class="space-y-4 border-t border-brand-border px-4 py-4 sm:px-6">
        @csrf
        @if (! $breakRules['ready'])
            <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Break rules are not available for this organization yet. Apply the latest update, then refresh this page.
            </p>
        @endif

        <p class="text-sm leading-relaxed text-brand-text-secondary">
            A meal break is required when a shift runs longer than the threshold below. Australian workplace rules commonly require that unpaid meal break once someone works more than 5 hours. This rule keeps the break inside a set part of the shift, so staff take it then and not at the start or the end.
            On a 6:00 AM shift with the hours below, the dashboard says: <span class="font-semibold text-brand-text">{{ $breakExample }}</span>
        </p>

        <label class="flex items-start gap-2 text-sm text-brand-text">
            <input type="hidden" name="break_rule_enabled" value="0">
            <input
                type="checkbox"
                name="break_rule_enabled"
                value="1"
                class="mt-1"
                @checked($breakEnabled)
                @disabled(! $breakRules['ready'])
            >
            <span>Only allow Break In during this window</span>
        </label>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Window opens (hours into the shift)</span>
                <input
                    type="number"
                    name="start_hours"
                    min="1"
                    max="12"
                    step="0.5"
                    value="{{ $startHours }}"
                    @disabled(! $breakRules['ready'])
                    class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                >
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Window closes (hours into the shift)</span>
                <input
                    type="number"
                    name="end_hours"
                    min="1"
                    max="16"
                    step="0.5"
                    value="{{ $endHours }}"
                    @disabled(! $breakRules['ready'])
                    class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                >
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Required when shift is longer than (hours)</span>
                <input
                    type="number"
                    name="required_after_hours"
                    min="0"
                    max="16"
                    step="0.5"
                    value="{{ $requiredAfterHours }}"
                    @disabled(! $breakRules['ready'])
                    class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                >
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Remind this many minutes before</span>
                <input
                    type="number"
                    name="reminder_lead_minutes"
                    min="0"
                    max="120"
                    step="1"
                    value="{{ $reminderLead }}"
                    @disabled(! $breakRules['ready'])
                    class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                >
            </label>
        </div>

        @foreach (['start_hours', 'end_hours', 'required_after_hours', 'reminder_lead_minutes', 'break_rule_enabled'] as $field)
            @error($field)
                <p class="text-xs text-red-700">{{ $message }}</p>
            @enderror
        @endforeach

        <button
            type="submit"
            @disabled(! $breakRules['ready'])
            class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary-dark disabled:cursor-not-allowed disabled:opacity-50"
        >
            Save break rules
        </button>
    </form>
</details>
