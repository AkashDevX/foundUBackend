@php
    /** @var \App\Models\Employee $employee */
    /** @var string $eventFilter all|clock_in|clock_out */
    use App\Models\TimeClockEntry;
    use App\Support\AdminTimeClockDisplay;
    use App\Support\DisplayTimezone;

    $eventFilter = $eventFilter ?? 'all';
    $allEntries = $employee->timeClockEntries ?? collect();
    $entries = match ($eventFilter) {
        TimeClockEntry::EVENT_CLOCK_IN => $allEntries->where('event_type', TimeClockEntry::EVENT_CLOCK_IN)->values(),
        TimeClockEntry::EVENT_CLOCK_OUT => $allEntries->where('event_type', TimeClockEntry::EVENT_CLOCK_OUT)->values(),
        default => $allEntries,
    };
    $lastEntry = $allEntries->first();
    $isClockedIn = $lastEntry instanceof TimeClockEntry && $lastEntry->event_type === TimeClockEntry::EVENT_CLOCK_IN;
    $tz = DisplayTimezone::name();
    $tzLabel = DisplayTimezone::label();

    $filterLink = static function (string $filter) use ($employee): string {
        return route('admin.employees.time-clock', array_filter([
            'employee' => $employee->public_id,
            'event' => $filter !== 'all' ? $filter : null,
        ]));
    };
    $filterLabel = match ($eventFilter) {
        TimeClockEntry::EVENT_CLOCK_IN => 'Clock in only',
        TimeClockEntry::EVENT_CLOCK_OUT => 'Clock out only',
        default => 'All punches',
    };
    $sessionSummary = AdminTimeClockDisplay::summarizeWorkSessions($allEntries);
    $hoursByEntryId = $sessionSummary['hours_by_entry_id'];
    $sessionByClockInId = collect($hoursByEntryId)->keyBy(static fn (array $session) => $session['clock_in_id']);
    $totalHoursLabel = AdminTimeClockDisplay::formatDuration($sessionSummary['total_seconds']);
@endphp

<div class="space-y-5">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-brand-border bg-gradient-to-br from-white to-brand-surface/80 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Current status</p>
            <p class="mt-2 flex items-center gap-2 text-sm font-bold {{ $isClockedIn ? 'text-emerald-700' : 'text-brand-text-secondary' }}">
                <span class="inline-flex size-2.5 rounded-full {{ $isClockedIn ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                {{ $isClockedIn ? 'Clocked in' : 'Not clocked in' }}
            </p>
            @if ($isClockedIn && $lastEntry?->clocked_at)
                <p class="mt-1 text-xs text-brand-text-secondary">
                    Since {{ DisplayTimezone::formatDateTime($lastEntry->clocked_at) }}
                </p>
            @endif
        </div>
        <div class="rounded-xl border border-brand-border bg-gradient-to-br from-white to-brand-surface/80 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Total hours worked</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-brand-text">{{ $totalHoursLabel }}</p>
            <p class="mt-1 text-xs text-brand-text-secondary">
                {{ $sessionSummary['completed_sessions'] }} completed session{{ $sessionSummary['completed_sessions'] === 1 ? '' : 's' }}
                @if ($isClockedIn)
                    · includes open shift
                @endif
            </p>
        </div>
        <div class="rounded-xl border border-brand-border bg-gradient-to-br from-white to-brand-surface/80 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Showing records</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-brand-text">{{ $entries->count() }}</p>
            <p class="mt-1 text-xs text-brand-text-secondary">{{ $filterLabel }} · {{ $allEntries->count() }} total loaded</p>
        </div>
        <div class="rounded-xl border border-brand-border bg-gradient-to-br from-white to-brand-surface/80 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Assigned site</p>
            <p class="mt-2 text-sm font-semibold text-brand-text">{{ $employee->workLocation?->name ?? '—' }}</p>
            @if ($employee->workLocation?->latitude !== null && $employee->workLocation?->longitude !== null)
                <p class="mt-1 font-mono text-[11px] text-brand-text-secondary">
                    {{ AdminTimeClockDisplay::formatCoords((float) $employee->workLocation->latitude, (float) $employee->workLocation->longitude) }}
                </p>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-border bg-white px-4 py-3 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Filter by event</p>
        <div class="inline-flex flex-wrap rounded-xl border border-brand-border bg-brand-surface/60 p-1 shadow-inner">
            <a
                href="{{ $filterLink('all') }}"
                class="rounded-lg px-4 py-2 text-xs font-bold transition {{ $eventFilter === 'all' ? 'bg-white text-brand-primary shadow-sm ring-1 ring-brand-border/80' : 'text-brand-text-secondary hover:text-brand-text' }}"
            >
                All
            </a>
            <a
                href="{{ $filterLink('clock_in') }}"
                class="rounded-lg px-4 py-2 text-xs font-bold transition {{ $eventFilter === 'clock_in' ? 'bg-white text-emerald-700 shadow-sm ring-1 ring-emerald-200' : 'text-brand-text-secondary hover:text-brand-text' }}"
            >
                Clock in
            </a>
            <a
                href="{{ $filterLink('clock_out') }}"
                class="rounded-lg px-4 py-2 text-xs font-bold transition {{ $eventFilter === 'clock_out' ? 'bg-white text-slate-800 shadow-sm ring-1 ring-slate-200' : 'text-brand-text-secondary hover:text-brand-text' }}"
            >
                Clock out
            </a>
        </div>
    </div>

    @if ($allEntries->isEmpty())
        <div class="rounded-2xl border border-dashed border-brand-border bg-brand-surface/50 px-6 py-10 text-center">
            <p class="text-sm font-semibold text-brand-text">No clock in/out records yet</p>
            <p class="mt-2 text-xs leading-relaxed text-brand-text-secondary">
                When this employee uses the mobile app time clock at their assigned work site, punches will appear here.
            </p>
        </div>
    @elseif ($entries->isEmpty())
        <div class="rounded-2xl border border-dashed border-amber-200 bg-amber-50/50 px-6 py-10 text-center">
            <p class="text-sm font-semibold text-brand-text">No records match this filter</p>
            <p class="mt-2 text-xs text-brand-text-secondary">Try <a href="{{ $filterLink('all') }}" class="font-semibold text-brand-link hover:underline">All</a> to see every punch.</p>
        </div>
    @else
        <p class="text-xs text-brand-text-secondary">Times shown in {{ $tzLabel }} ({{ str_replace('_', ' ', $tz) }}).</p>
        <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">When</th>
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">Event</th>
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">Hours worked</th>
                            <th class="hidden whitespace-nowrap px-4 py-3 md:table-cell sm:px-5">Work location</th>
                            <th class="hidden whitespace-nowrap px-4 py-3 lg:table-cell sm:px-5">Department</th>
                            <th class="hidden whitespace-nowrap px-4 py-3 lg:table-cell sm:px-5">Shift</th>
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">Distance</th>
                            <th class="whitespace-nowrap px-4 py-3 sm:px-5">Geofence</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border/80">
                        @foreach ($entries as $entry)
                            <tr class="transition hover:bg-brand-surface/40">
                                <td class="whitespace-nowrap px-4 py-3.5 tabular-nums text-brand-text sm:px-5">
                                    {{ DisplayTimezone::formatDate($entry->clocked_at) }}
                                    <span class="block text-xs text-brand-text-secondary">{{ DisplayTimezone::format($entry->clocked_at, 'g:i A') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1 {{ AdminTimeClockDisplay::eventBadgeClasses($entry->event_type) }}">
                                        {{ AdminTimeClockDisplay::eventLabel($entry->event_type) }}
                                    </span>
                                </td>
                                @php
                                    $sessionHours = $hoursByEntryId[$entry->id]
                                        ?? ($entry->event_type === TimeClockEntry::EVENT_CLOCK_IN
                                            ? ($sessionByClockInId->get($entry->id) ?? null)
                                            : null);
                                    $showSessionHours = $sessionHours !== null
                                        && (
                                            $entry->event_type === TimeClockEntry::EVENT_CLOCK_OUT
                                            || $sessionHours['is_open']
                                            || $eventFilter === TimeClockEntry::EVENT_CLOCK_IN
                                        );
                                @endphp
                                <td class="whitespace-nowrap px-4 py-3.5 tabular-nums text-brand-text sm:px-5">
                                    @if ($showSessionHours)
                                        <span class="font-semibold">{{ $sessionHours['label'] }}</span>
                                        <span class="block text-[10px] text-brand-text-secondary">{{ $sessionHours['range_label'] }}</span>
                                        @if ($sessionHours['is_open'])
                                            <span class="block text-[10px] font-medium text-emerald-700">In progress</span>
                                        @endif
                                    @else
                                        <span class="text-brand-text-secondary">—</span>
                                    @endif
                                </td>
                                <td class="hidden max-w-[12rem] px-4 py-3.5 text-brand-text-secondary md:table-cell sm:px-5">
                                    <span class="block truncate font-medium text-brand-text">{{ $entry->workLocation?->name ?? '—' }}</span>
                                    <span class="font-mono text-[10px]">{{ AdminTimeClockDisplay::formatCoords($entry->expected_latitude, $entry->expected_longitude) }}</span>
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3.5 text-brand-text-secondary lg:table-cell sm:px-5">{{ $entry->department?->name ?? '—' }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3.5 text-brand-text-secondary lg:table-cell sm:px-5">{{ $entry->shift?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 tabular-nums text-brand-text sm:px-5">
                                    {{ AdminTimeClockDisplay::formatDistance($entry->distance_from_site_meters) }}
                                    <span class="block text-[10px] text-brand-text-secondary">≤ {{ $entry->allowed_radius_meters }} m</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                                    @if ($entry->within_geofence)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">OK</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">Outside</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
