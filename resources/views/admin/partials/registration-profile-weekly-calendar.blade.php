@php
    /** @var array<string, array{morning: bool, evening: bool}> $weeklyGrid */
    /** @var bool $canEditProfile */
    use App\Support\AdminWeeklyAvailability;

    $dayKeys = AdminWeeklyAvailability::DAY_KEYS;
    $shortLabels = AdminWeeklyAvailability::SHORT_DAY_LABELS;
    $morningRange = AdminWeeklyAvailability::mobileMorningRangeLabel();
    $eveningRange = AdminWeeklyAvailability::mobileEveningRangeLabel();

    $cellBase = 'flex aspect-square w-full min-w-[2.25rem] max-w-[2.75rem] items-center justify-center rounded-xl border text-sm font-bold transition duration-150';
    $cellOff = 'border-brand-border/80 bg-brand-surface/70 text-brand-text-secondary/50';
    $cellOn = 'border-brand-primary bg-brand-primary text-white shadow-sm shadow-brand-primary/25';
    $cellReadOn = 'border-brand-primary/40 bg-brand-primary/[0.12] text-brand-primary';
@endphp
<div
    class="max-w-2xl rounded-2xl border border-brand-border bg-white p-4 shadow-sm ring-1 ring-black/[0.03] sm:p-5"
    @if ($canEditProfile) data-reg-weekly-calendar @endif
>
    <p class="text-xs font-semibold uppercase tracking-wide text-brand-text-secondary">Weekly schedule</p>
    <h4 class="mt-1 text-base font-bold text-brand-text">Tap days &amp; time blocks</h4>
    <p class="mt-1 text-sm leading-relaxed text-brand-text-secondary">
        @if ($canEditProfile)
            Same layout as the mobile app: choose morning or evening (or both) for each day the employee can work.
        @else
            Availability saved when the employee registered in the app.
        @endif
    </p>

    <div class="mt-5 overflow-x-auto">
        <div class="min-w-[20rem]">
            <div class="grid grid-cols-[minmax(0,1fr)_repeat(7,minmax(2.25rem,1fr))] items-end gap-x-1.5 gap-y-1 sm:gap-x-2">
                <div></div>
                @foreach ($dayKeys as $dKey)
                    <p class="pb-1 text-center text-xs font-bold text-brand-primary">{{ $shortLabels[$dKey] ?? strtoupper($dKey) }}</p>
                @endforeach

                <div class="flex min-w-0 items-center gap-2 py-2 pr-1">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary" aria-hidden="true">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2M5.64 5.64l1.41 1.41m10.9 10.9 1.41 1.41M3 12h2m14 0h2M5.64 18.36l1.41-1.41m10.9-10.9 1.41-1.41" />
                            <circle cx="12" cy="12" r="4" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-brand-text">Morning</p>
                        <p class="text-[11px] leading-tight text-brand-text-secondary">{{ $morningRange }}</p>
                    </div>
                </div>
                @foreach ($dayKeys as $dKey)
                    @php
                        $morningOn = $canEditProfile
                            ? old("availability.$dKey.morning", ($weeklyGrid[$dKey]['morning'] ?? false) ? '1' : '0') === '1'
                            : (bool) ($weeklyGrid[$dKey]['morning'] ?? false);
                    @endphp
                    @if ($canEditProfile)
                        <label class="flex justify-center">
                            <input type="hidden" name="availability[{{ $dKey }}][morning]" value="0" />
                            <input
                                type="checkbox"
                                name="availability[{{ $dKey }}][morning]"
                                value="1"
                                class="peer sr-only"
                                data-reg-period="morning"
                                data-reg-day="{{ $dKey }}"
                                @checked($morningOn)
                            />
                            <span
                                class="{{ $cellBase }} {{ $morningOn ? $cellOn : $cellOff }} peer-focus-visible:ring-2 peer-focus-visible:ring-brand-primary/40"
                                data-reg-weekly-cell
                            >{{ $morningOn ? '✓' : '–' }}</span>
                        </label>
                    @else
                        <div class="flex justify-center">
                            <span class="{{ $cellBase }} {{ $morningOn ? $cellReadOn : $cellOff }}" aria-label="{{ $shortLabels[$dKey] }} morning {{ $morningOn ? 'selected' : 'not selected' }}">
                                {{ $morningOn ? '✓' : '–' }}
                            </span>
                        </div>
                    @endif
                @endforeach

                <div class="flex min-w-0 items-center gap-2 py-2 pr-1">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary" aria-hidden="true">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-brand-text">Evening</p>
                        <p class="text-[11px] leading-tight text-brand-text-secondary">{{ $eveningRange }}</p>
                    </div>
                </div>
                @foreach ($dayKeys as $dKey)
                    @php
                        $eveningOn = $canEditProfile
                            ? old("availability.$dKey.evening", ($weeklyGrid[$dKey]['evening'] ?? false) ? '1' : '0') === '1'
                            : (bool) ($weeklyGrid[$dKey]['evening'] ?? false);
                    @endphp
                    @if ($canEditProfile)
                        <label class="flex justify-center">
                            <input type="hidden" name="availability[{{ $dKey }}][evening]" value="0" />
                            <input
                                type="checkbox"
                                name="availability[{{ $dKey }}][evening]"
                                value="1"
                                class="peer sr-only"
                                data-reg-period="evening"
                                data-reg-day="{{ $dKey }}"
                                @checked($eveningOn)
                            />
                            <span
                                class="{{ $cellBase }} {{ $eveningOn ? $cellOn : $cellOff }} peer-focus-visible:ring-2 peer-focus-visible:ring-brand-primary/40"
                                data-reg-weekly-cell
                            >{{ $eveningOn ? '✓' : '–' }}</span>
                        </label>
                    @else
                        <div class="flex justify-center">
                            <span class="{{ $cellBase }} {{ $eveningOn ? $cellReadOn : $cellOff }}" aria-label="{{ $shortLabels[$dKey] }} evening {{ $eveningOn ? 'selected' : 'not selected' }}">
                                {{ $eveningOn ? '✓' : '–' }}
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <p class="mt-4 text-center text-sm text-brand-text-secondary" data-reg-weekly-status>
        {{ AdminWeeklyAvailability::summaryTextFromMobileGrid(
            collect($dayKeys)->mapWithKeys(function (string $dKey) use ($weeklyGrid, $canEditProfile) {
                $morning = $canEditProfile
                    ? old("availability.$dKey.morning", ($weeklyGrid[$dKey]['morning'] ?? false) ? '1' : '0') === '1'
                    : (bool) ($weeklyGrid[$dKey]['morning'] ?? false);
                $evening = $canEditProfile
                    ? old("availability.$dKey.evening", ($weeklyGrid[$dKey]['evening'] ?? false) ? '1' : '0') === '1'
                    : (bool) ($weeklyGrid[$dKey]['evening'] ?? false);

                return [$dKey => ['morning' => $morning, 'evening' => $evening]];
            })->all()
        ) ?? 'No time blocks selected yet.' }}
    </p>
</div>
