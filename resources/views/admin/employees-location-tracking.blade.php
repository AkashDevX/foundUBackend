@extends('layouts.admin')

@section('title', 'Employees — Location tracking')

@section('heading', 'Location tracking')

@section('subheading')
    {{ $company->name }}
@endsection

@push('scripts')
    @vite(['resources/js/admin-location-tracking.js'])
@endpush

@section('content')
    @php
        use App\Support\DisplayTimezone;
        /** @var string $mode */
        /** @var list<array<string, mixed>> $clockedInRows */
        /** @var list<array<string, mixed>> $siteCards */
        /** @var array<string, mixed>|null $selected */
        /** @var array<string, mixed>|null $site */
        /** @var list<array<string, mixed>> $siteEmployees */
        $siteCards = $siteCards ?? [];
        $siteEmployees = $siteEmployees ?? [];
    @endphp

    @if ($mode === 'list')
        <div class="space-y-6">
            @if (count($siteCards) > 0)
                <section>
                    <div class="mb-3 flex items-end justify-between gap-3 px-1">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-[0.14em] text-brand-primary">Sites with staff on shift</p>
                            <p class="mt-1 text-sm text-brand-text-secondary">Open a work location to see everyone there and their movements together.</p>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($siteCards as $card)
                            <a
                                href="{{ $card['url'] }}"
                                class="group relative overflow-hidden rounded-2xl border border-brand-border bg-white p-5 shadow-sm ring-1 ring-black/[0.02] transition hover:-translate-y-0.5 hover:border-brand-primary/40 hover:shadow-md"
                            >
                                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-primary via-teal-500 to-sky-500 opacity-80"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-semibold text-brand-text group-hover:text-brand-primary">{{ $card['name'] }}</p>
                                        @if (!empty($card['address']))
                                            <p class="mt-1 line-clamp-2 text-xs text-brand-text-secondary">{{ $card['address'] }}</p>
                                        @endif
                                    </div>
                                    <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-600/15">
                                        {{ $card['staff_count'] }} on site
                                    </span>
                                </div>
                                <p class="mt-4 text-xs text-brand-text-secondary">
                                    {{ implode(' · ', $card['names']) }}{{ $card['staff_count'] > count($card['names']) ? '…' : '' }}
                                </p>
                                <div class="mt-4 flex items-center justify-between text-xs font-semibold">
                                    <span class="text-brand-primary">View site map</span>
                                    @if (($card['idle_count'] ?? 0) > 0)
                                        <span class="text-amber-700">{{ $card['idle_count'] }} low movement</span>
                                    @else
                                        <span class="text-brand-text-secondary">All moving normally</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
                <div class="flex flex-col gap-2 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-[0.14em] text-brand-primary">In progress</p>
                        <p class="mt-1 text-sm text-brand-text-secondary">Click a work location for the whole site, or Live map for one person.</p>
                    </div>
                    <p class="text-xs font-semibold text-brand-text-secondary">
                        {{ count($clockedInRows) }} on shift
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-brand-border text-left">
                        <thead class="bg-brand-surface/60">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-brand-label sm:px-6">Employee</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-brand-label sm:px-6">Status</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-brand-label sm:px-6">Work location</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-brand-label sm:px-6">Shift</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10px] font-semibold uppercase tracking-wide text-brand-label sm:px-6">Clocked in</th>
                                <th class="px-4 py-3 sm:px-6"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            @forelse ($clockedInRows as $row)
                                <tr class="transition hover:bg-brand-surface/40">
                                    <td class="px-4 py-3.5 sm:px-6">
                                        <a href="{{ $row['detail_url'] }}" class="block">
                                            <p class="text-sm font-semibold text-brand-text">{{ $row['employee_name'] }}</p>
                                            @if (!empty($row['has_idle_alert']))
                                                <p class="mt-0.5 text-xs font-semibold text-amber-700">Little movement detected</p>
                                            @endif
                                        </a>
                                    </td>
                                    <td class="px-4 py-3.5 sm:px-6">
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-600/15">
                                            {{ !empty($row['is_on_break']) ? 'On break' : 'In progress' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-sm text-brand-text sm:px-6">
                                        @if (!empty($row['site_url']))
                                            <a href="{{ $row['site_url'] }}" class="group/site block">
                                                <p class="font-medium text-brand-primary underline-offset-2 group-hover/site:underline">{{ $row['work_location_name'] ?: '—' }}</p>
                                                @if (!empty($row['work_location_address']))
                                                    <p class="mt-0.5 max-w-[16rem] truncate text-xs text-brand-text-secondary">{{ $row['work_location_address'] }}</p>
                                                @endif
                                                <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-brand-primary/80">Open site map →</p>
                                            </a>
                                        @else
                                            <p class="font-medium">{{ $row['work_location_name'] ?: '—' }}</p>
                                            @if (!empty($row['work_location_address']))
                                                <p class="mt-0.5 max-w-[16rem] truncate text-xs text-brand-text-secondary">{{ $row['work_location_address'] }}</p>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-sm text-brand-text sm:px-6">
                                        @if (!empty($row['shift_name']))
                                            <p class="font-medium">{{ $row['shift_name'] }}</p>
                                        @endif
                                        <p class="{{ !empty($row['shift_name']) ? 'mt-0.5 text-xs text-brand-text-secondary' : '' }}">
                                            {{ $row['shift_label'] ?: '—' }}
                                        </p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-sm tabular-nums text-brand-text sm:px-6">
                                        @if (!empty($row['clocked_in_at']))
                                            {{ DisplayTimezone::format(\Illuminate\Support\Carbon::parse($row['clocked_in_at']), 'g:i A') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right sm:px-6">
                                        <a
                                            href="{{ $row['detail_url'] }}"
                                            class="inline-flex items-center gap-1 rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface"
                                        >
                                            Live map
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-brand-text-secondary sm:px-6">
                                        No employees are clocked in right now.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @elseif ($mode === 'site')
        @php $site = $site ?? []; @endphp
        <div
            class="space-y-5"
            data-location-tracking
            data-mode="site"
            data-live-url="{{ $livePollUrl }}"
        >
            <script type="application/json" data-initial-site>{!! json_encode($site, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!}</script>
            <script type="application/json" data-initial-site-employees>{!! json_encode($siteEmployees, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!}</script>

            <div class="flex flex-wrap items-center gap-3">
                <a
                    href="{{ $listUrl }}"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                    Back to in-progress list
                </a>
            </div>

            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
                <div class="relative overflow-hidden border-b border-brand-border px-4 py-6 sm:px-6">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(15,118,110,0.14),_transparent_55%),radial-gradient(ellipse_at_bottom_left,_rgba(37,99,235,0.10),_transparent_50%)]"></div>
                    <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-bold uppercase tracking-[0.14em] text-brand-primary">Site live map</p>
                            <h2 class="mt-1 truncate text-2xl font-semibold tracking-tight text-brand-text">{{ $site['name'] ?? 'Work location' }}</h2>
                            @if (!empty($site['address']))
                                <p class="mt-1 max-w-2xl text-sm text-brand-text-secondary">{{ $site['address'] }}</p>
                            @endif
                            <p class="mt-2 text-sm text-brand-text-secondary" data-live-status>Everyone on shift here — map refreshes every 20 seconds.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/15">
                                {{ (int) ($site['staff_count'] ?? 0) }} in progress
                            </span>
                            @if (((int) ($site['idle_count'] ?? 0)) > 0)
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">
                                    {{ (int) $site['idle_count'] }} low movement
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid lg:grid-cols-[300px_minmax(0,1fr)]">
                    <aside class="max-h-[36rem] overflow-y-auto border-b border-brand-border lg:border-b-0 lg:border-r" data-site-roster>
                        @forelse ($siteEmployees as $person)
                            <a
                                href="{{ $person['detail_url'] }}"
                                class="block border-b border-brand-border/80 px-4 py-3.5 transition hover:bg-brand-surface/50"
                                data-site-person="{{ $person['employee_public_id'] }}"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 inline-block size-3 shrink-0 rounded-full ring-2 ring-white shadow" style="background: {{ $person['color'] }}"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-brand-text">{{ $person['employee_name'] }}</p>
                                        <p class="mt-0.5 text-xs text-brand-text-secondary">
                                            {{ $person['shift_label'] ?: ($person['shift_name'] ?: 'On shift') }}
                                            @if (!empty($person['clocked_in_at']))
                                                · in since {{ DisplayTimezone::format(\Illuminate\Support\Carbon::parse($person['clocked_in_at']), 'g:i A') }}
                                            @endif
                                        </p>
                                        <p class="mt-1 text-xs text-brand-text-secondary">
                                            {{ $person['movement_stats']['distance_label'] ?? '0 m' }} walked
                                            @if (!empty($person['has_idle_alert']) || !empty($person['movement_stats']['is_currently_waiting']))
                                                <span class="font-semibold text-amber-700"> · waiting</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <p class="px-4 py-8 text-sm text-brand-text-secondary">No one is clocked in at this site right now.</p>
                        @endforelse
                    </aside>

                    <div>
                        <div id="location-tracking-site-map" class="h-[36rem] w-full bg-brand-surface" role="img" aria-label="Site staff locations and movements"></div>
                        <div class="border-t border-brand-border px-4 py-3 text-xs text-brand-text-secondary sm:px-6" data-map-meta>
                            Coloured pins and paths show each person. Hover a pin for distance and wait time.
                        </div>
                    </div>
                </div>
            </section>
        </div>
    @else
        @php
            $selected = $selected ?? [];
        @endphp
        <div
            class="space-y-6"
            data-location-tracking
            data-mode="detail"
            data-live-url="{{ $livePollUrl }}"
            data-trail-url="{{ $trailUrl ?? '' }}"
        >
            <script type="application/json" data-initial-position>{!! json_encode($selected, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!}</script>
            <script type="application/json" data-initial-trail>{!! json_encode($trail, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!}</script>
            <script type="application/json" data-initial-idle-alerts>{!! json_encode($idleAlerts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!}</script>
            <script type="application/json" data-initial-movement-stats>{!! json_encode($movementStats ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) !!}</script>

            <div class="flex flex-wrap items-center gap-3">
                <a
                    href="{{ $listUrl }}"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                    Back to in-progress list
                </a>
                @if (!empty($selected['site_url']))
                    <a
                        href="{{ $selected['site_url'] }}"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-brand-primary/25 bg-brand-primary/5 px-3 py-2 text-xs font-semibold text-brand-primary shadow-sm transition hover:bg-brand-primary/10"
                    >
                        View whole site
                    </a>
                @endif
            </div>

            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
                <div class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-4 sm:px-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-[0.14em] text-brand-primary">Location map</p>
                            <h2 class="mt-1 text-xl font-semibold text-brand-text">{{ $selected['employee_name'] ?? 'Employee' }}</h2>
                            <p class="mt-1 text-sm text-brand-text-secondary" data-live-status>
                                @if (($selected['status'] ?? '') === 'ended')
                                    This shift is no longer open.
                                @else
                                    Live position and movement trail — refreshes every 20 seconds.
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1
                            {{ ($selected['status'] ?? '') === 'ended'
                                ? 'bg-slate-50 text-slate-600 ring-slate-500/15'
                                : 'bg-emerald-50 text-emerald-700 ring-emerald-600/15' }}">
                            {{ $selected['status_label'] ?? 'In progress' }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-brand-border/80 bg-white/80 px-3 py-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Work location</dt>
                            <dd class="mt-1 text-sm font-semibold text-brand-text">{{ $selected['work_location_name'] ?: '—' }}</dd>
                            @if (!empty($selected['work_location_address']))
                                <dd class="mt-0.5 text-xs text-brand-text-secondary">{{ $selected['work_location_address'] }}</dd>
                            @endif
                        </div>
                        <div class="rounded-xl border border-brand-border/80 bg-white/80 px-3 py-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Shift</dt>
                            <dd class="mt-1 text-sm font-semibold text-brand-text">
                                {{ $selected['shift_name'] ?: ($selected['shift_label'] ?: '—') }}
                            </dd>
                            @if (!empty($selected['shift_name']) && !empty($selected['shift_label']))
                                <dd class="mt-0.5 text-xs text-brand-text-secondary">{{ $selected['shift_label'] }}</dd>
                            @endif
                        </div>
                        <div class="rounded-xl border border-brand-border/80 bg-white/80 px-3 py-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Clocked in</dt>
                            <dd class="mt-1 text-sm font-semibold tabular-nums text-brand-text">
                                @if (!empty($selected['clocked_in_at']))
                                    {{ DisplayTimezone::format(\Illuminate\Support\Carbon::parse($selected['clocked_in_at']), 'D j M · g:i A') }}
                                @else
                                    —
                                @endif
                            </dd>
                            @if (!empty($selected['has_idle_alert']))
                                <dd class="mt-0.5 text-xs font-semibold text-amber-700">Little movement detected</dd>
                            @endif
                        </div>
                    </dl>

                    @php $stats = $movementStats ?? []; @endphp
                    <dl class="mt-3 grid gap-3 sm:grid-cols-3" data-movement-stats>
                        <div class="rounded-xl border border-brand-border/80 bg-white/80 px-3 py-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Distance travelled</dt>
                            <dd class="mt-1 text-sm font-semibold text-brand-text" data-stat-distance>{{ $stats['distance_label'] ?? '0 m' }}</dd>
                            <dd class="mt-0.5 text-xs text-brand-text-secondary" data-stat-samples>{{ (int) ($stats['sample_count'] ?? 0) }} GPS samples on footpath</dd>
                        </div>
                        <div class="rounded-xl border border-brand-border/80 bg-white/80 px-3 py-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Waiting in one place</dt>
                            <dd class="mt-1 text-sm font-semibold text-brand-text" data-stat-waiting>{{ $stats['waiting_label'] ?? '0 min' }}</dd>
                            <dd class="mt-0.5 text-xs text-brand-text-secondary" data-stat-waiting-note>
                                @if (!empty($stats['is_currently_waiting']))
                                    Currently low movement
                                @else
                                    Longest wait {{ \App\Support\MovementTrailAnalyzer::formatMinutes((int) ($stats['longest_wait_minutes'] ?? 0)) }}
                                @endif
                            </dd>
                        </div>
                        <div class="rounded-xl border border-brand-border/80 bg-white/80 px-3 py-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-brand-label">Tracking window</dt>
                            <dd class="mt-1 text-sm font-semibold text-brand-text" data-stat-duration>{{ \App\Support\MovementTrailAnalyzer::formatMinutes((int) ($stats['duration_minutes'] ?? 0)) }}</dd>
                            <dd class="mt-0.5 text-xs text-brand-text-secondary">Hover the pin or footpath for details</dd>
                        </div>
                    </dl>
                </div>

                <div id="location-tracking-map" class="h-[32rem] w-full bg-brand-surface" role="img" aria-label="Employee location and movement trail"></div>
                <div class="border-t border-brand-border px-4 py-3 text-xs text-brand-text-secondary sm:px-6" data-map-meta>
                    Waiting for location data…
                </div>
            </section>
        </div>
    @endif
@endsection
