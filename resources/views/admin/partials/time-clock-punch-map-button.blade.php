@php
    /** @var array<string, mixed>|null $map */
    $map = $map ?? null;
@endphp

@if (is_array($map))
    @php
        $within = (bool) ($map['within_geofence'] ?? false);
        $buttonClasses = $within
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:border-emerald-300'
            : 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 hover:border-amber-300';
    @endphp
    <button
        type="button"
        class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg border shadow-sm transition {{ $buttonClasses }}"
        data-time-clock-punch-map
        data-punch='@json($map)'
        title="{{ $map['icon_title'] ?? 'View punch location on map' }}"
        aria-label="{{ $map['icon_title'] ?? 'View punch location on map' }}"
    >
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 6.75-7.5 11.25-7.5 11.25S4.5 17.25 4.5 10.5a7.5 7.5 0 1115 0z" />
        </svg>
    </button>
@endif
