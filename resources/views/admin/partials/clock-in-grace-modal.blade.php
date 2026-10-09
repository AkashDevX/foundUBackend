@php
    /** @var array{ready: bool, settings: array{grace_minutes: int, outside_policy: string, persisted: bool, ready: bool}, pending: \Illuminate\Support\Collection} $clockInGrace */
    $graceMinutes = old('grace_minutes', $clockInGrace['settings']['grace_minutes']);
    $gracePolicy = old('outside_policy', $clockInGrace['settings']['outside_policy']);
    $graceExample = \App\Support\ClockInGrace::exampleLabels((int) $graceMinutes);
    $openClockInGrace = $errors->has('grace_minutes')
        || $errors->has('outside_policy')
        || $errors->has('action')
        || $errors->has('admin_note');
    $approvalSection = (string) request()->query('approval', old('approval_section', 'clock_in_exceptions'));
    if (! in_array($approvalSection, ['clock_in_exceptions', 'early_clock_outs'], true)) {
        $approvalSection = 'clock_in_exceptions';
    }
    $approvalCopy = [
        'clock_in_exceptions' => [
            'title' => 'Clock-in waiting for approval',
            'subtitle' => 'Allow a clock-in that is outside the grace window, or dismiss it.',
        ],
        'early_clock_outs' => [
            'title' => 'Early clock-out waiting for approval',
            'subtitle' => 'They want to leave before the shift ends. Allow clock-out so they can go.',
        ],
    ];
@endphp

<div
    id="clock-in-grace-modal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-primary-dark/50 p-4"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="clock-in-grace-title"
>
    <div class="flex max-h-[min(40rem,calc(100vh-2rem))] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
        <header class="border-b border-brand-border bg-gradient-to-br from-amber-50 via-white to-white px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800">Requires action</p>
                    <h2 id="clock-in-grace-title" class="mt-1 text-lg font-bold text-brand-text">{{ $approvalCopy[$approvalSection]['title'] }}</h2>
                    <p id="clock-in-grace-subtitle" class="mt-1 text-xs leading-relaxed text-brand-text-secondary">
                        {{ $approvalCopy[$approvalSection]['subtitle'] }}
                    </p>
                </div>
                <button
                    type="button"
                    id="clock-in-grace-close"
                    class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text"
                    aria-label="Close"
                >
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            @php
                $earlyClockOuts = $clockInGrace['early_clock_outs'] ?? collect();
            @endphp
            <div data-approval-panel="clock_in_exceptions" class="{{ $approvalSection === 'clock_in_exceptions' ? '' : 'hidden' }}">
            @if (! $clockInGrace['ready'])
                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Clock-in approval is not available for this organization yet. Apply the latest update, then refresh this page.
                </p>
            @elseif ($clockInGrace['pending']->isEmpty())
                <p class="rounded-xl border border-brand-border bg-brand-surface/60 px-4 py-6 text-center text-sm text-brand-text-secondary">
                    No clock-ins are waiting for approval.
                </p>
            @else
                <ul class="space-y-3">
                    @foreach ($clockInGrace['pending'] as $row)
                        @php
                            $employee = $row->employee;
                            $name = $employee?->full_legal_name ?: ($employee?->email ?: 'Employee');
                        @endphp
                        <li class="rounded-xl border border-brand-border bg-white p-3 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold text-brand-text">{{ $name }}</p>
                                    <p class="mt-0.5 text-xs text-brand-text-secondary">
                                        Shift starts {{ \App\Support\DisplayTimezone::formatDateTime($row->shift_starts_at) }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-brand-text-secondary">
                                        Tried to clock in {{ \App\Support\DisplayTimezone::formatDateTime($row->attempted_at) }}
                                        — {{ $row->kind === 'early' ? 'too early' : 'too late' }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <form method="post" action="{{ route('admin.employees.time-clock.grace.clear', $row->id) }}" class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                                    @csrf
                                    <input type="hidden" name="approval_section" value="clock_in_exceptions">
                                    <input type="hidden" name="action" value="allow">
                                    <input
                                        type="text"
                                        name="admin_note"
                                        maxlength="2000"
                                        placeholder="Note (optional)"
                                        class="w-full flex-1 rounded-lg border border-brand-border px-3 py-2 text-sm text-brand-text"
                                    >
                                    <button type="submit" class="shrink-0 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Allow clock-in</button>
                                </form>
                                <form method="post" action="{{ route('admin.employees.time-clock.grace.clear', $row->id) }}">
                                    @csrf
                                    <input type="hidden" name="approval_section" value="clock_in_exceptions">
                                    <input type="hidden" name="action" value="dismiss">
                                    <button type="submit" class="rounded-lg border border-brand-border px-3 py-2 text-xs font-semibold text-brand-text-secondary hover:bg-brand-surface">Dismiss</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @php
                $graceSettingsOpen = $errors->has('grace_minutes') || $errors->has('outside_policy');
            @endphp
            <div class="mt-5 border-t border-brand-border pt-4">
                <button
                    type="button"
                    id="clock-in-grace-settings-toggle"
                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-brand-border bg-brand-surface/60 px-3.5 py-2.5 text-left text-sm font-semibold text-brand-text hover:bg-brand-surface"
                    aria-expanded="{{ $graceSettingsOpen ? 'true' : 'false' }}"
                    aria-controls="clock-in-grace-settings"
                >
                    <span>Grace period settings</span>
                    <svg class="size-4 shrink-0 text-brand-text-secondary transition-transform {{ $graceSettingsOpen ? 'rotate-180' : '' }}" data-grace-settings-chevron fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
            <form method="post" action="{{ route('admin.employees.time-clock.grace.update') }}" id="clock-in-grace-settings" class="{{ $graceSettingsOpen ? '' : 'hidden' }} mt-3 space-y-3">
                @csrf
                <input type="hidden" name="approval_section" value="clock_in_exceptions">
                <!-- <p class="text-xs leading-relaxed text-brand-text-secondary">
                    Staff can clock in this many minutes before or after their shift starts.
                    For example, a {{ $graceExample['start'] }} shift can be clocked in from {{ $graceExample['earliest'] }} to {{ $graceExample['latest'] }}.
                </p> -->
                <div class="grid gap-3 sm:grid-cols-[8rem_minmax(0,1fr)]">
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Minutes</span>
                        <input
                            type="number"
                            name="grace_minutes"
                            min="0"
                            max="180"
                            value="{{ $graceMinutes }}"
                            @disabled(! $clockInGrace['ready'])
                            class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                        >
                    </label>
                    <fieldset class="space-y-2">
                        <legend class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-brand-label">If they are earlier or later</legend>
                        <label class="flex items-start gap-2 text-sm text-brand-text">
                            <input type="radio" name="outside_policy" value="exception" class="mt-1" @checked($gracePolicy === 'exception') @disabled(! $clockInGrace['ready'])>
                            <span>Ask you to approve it first</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-brand-text">
                            <input type="radio" name="outside_policy" value="prevent" class="mt-1" @checked($gracePolicy === 'prevent') @disabled(! $clockInGrace['ready'])>
                            <span>Don’t let them clock in</span>
                        </label>
                    </fieldset>
                </div>
                @error('grace_minutes')
                    <p class="text-xs text-red-700">{{ $message }}</p>
                @enderror
                @error('outside_policy')
                    <p class="text-xs text-red-700">{{ $message }}</p>
                @enderror
                <button
                    type="submit"
                    @disabled(! $clockInGrace['ready'])
                    class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary-dark disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Save
                </button>
            </form>
            </div>
            </div>

            <div data-approval-panel="early_clock_outs" class="{{ $approvalSection === 'early_clock_outs' ? '' : 'hidden' }}">
                @if ($earlyClockOuts->isEmpty())
                    <p class="rounded-xl border border-brand-border bg-brand-surface/60 px-4 py-6 text-center text-sm text-brand-text-secondary">
                        No early clock-outs are waiting for approval.
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($earlyClockOuts as $row)
                            @php
                                $employee = $row->employee;
                                $name = $employee?->full_legal_name ?: ($employee?->email ?: 'Employee');
                            @endphp
                            <li class="rounded-xl border border-brand-border bg-white p-3 shadow-sm">
                                <p class="font-semibold text-brand-text">{{ $name }}</p>
                                <p class="mt-0.5 text-xs text-brand-text-secondary">
                                    Shift ends {{ \App\Support\DisplayTimezone::formatDateTime($row->shift_ends_at) }}
                                </p>
                                <p class="mt-0.5 text-xs text-brand-text-secondary">
                                    Tried to clock out {{ \App\Support\DisplayTimezone::formatDateTime($row->attempted_at) }}
                                </p>
                                <p class="mt-2 rounded-lg bg-brand-surface px-3 py-2 text-sm text-brand-text">{{ $row->employee_note }}</p>
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <form method="post" action="{{ route('admin.employees.time-clock.early-clock-out.clear', $row->id) }}" class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                                        @csrf
                                        <input type="hidden" name="approval_section" value="early_clock_outs">
                                        <input type="hidden" name="action" value="allow">
                                        <input
                                            type="text"
                                            name="admin_note"
                                            maxlength="2000"
                                            placeholder="Note (optional)"
                                            class="w-full flex-1 rounded-lg border border-brand-border px-3 py-2 text-sm text-brand-text"
                                        >
                                        <button type="submit" class="shrink-0 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Allow clock-out</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.employees.time-clock.early-clock-out.clear', $row->id) }}">
                                        @csrf
                                        <input type="hidden" name="approval_section" value="early_clock_outs">
                                        <input type="hidden" name="action" value="dismiss">
                                        <button type="submit" class="rounded-lg border border-brand-border px-3 py-2 text-xs font-semibold text-brand-text-secondary hover:bg-brand-surface">Dismiss</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('clock-in-grace-modal');
            if (!modal) return;

            const approvalCopy = @json($approvalCopy);
            const titleEl = document.getElementById('clock-in-grace-title');
            const subtitleEl = document.getElementById('clock-in-grace-subtitle');

            function showSection(section) {
                const key = Object.prototype.hasOwnProperty.call(approvalCopy, section)
                    ? section
                    : 'clock_in_exceptions';
                modal.querySelectorAll('[data-approval-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.getAttribute('data-approval-panel') !== key);
                });
                if (titleEl) titleEl.textContent = approvalCopy[key].title;
                if (subtitleEl) subtitleEl.textContent = approvalCopy[key].subtitle;
            }

            function openModal(section) {
                showSection(section);
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.setAttribute('aria-hidden', 'true');
            }

            document.querySelectorAll('[data-open-clock-in-grace]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    openModal(button.getAttribute('data-open-clock-in-grace'));
                });
            });

            const settingsToggle = document.getElementById('clock-in-grace-settings-toggle');
            const settingsPanel = document.getElementById('clock-in-grace-settings');
            const settingsChevron = settingsToggle?.querySelector('[data-grace-settings-chevron]');
            settingsToggle?.addEventListener('click', () => {
                if (!settingsPanel) return;
                const open = settingsPanel.classList.toggle('hidden') === false;
                settingsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                settingsChevron?.classList.toggle('rotate-180', open);
            });

            document.getElementById('clock-in-grace-close')?.addEventListener('click', closeModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
            });

            const url = new URL(window.location.href);
            if (url.searchParams.has('open_clock_in') || url.searchParams.has('approval')) {
                url.searchParams.delete('open_clock_in');
                url.searchParams.delete('approval');
                const query = url.searchParams.toString();
                window.history.replaceState(window.history.state, '', url.pathname + (query ? '?' + query : '') + url.hash);
            }

            if (@json($openClockInGrace)) openModal(@json($approvalSection));
        })();
    </script>
@endpush
