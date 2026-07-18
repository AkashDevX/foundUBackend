<div
    id="time-clock-punch-map-modal"
    class="fixed inset-0 z-[80] hidden items-center justify-center bg-brand-primary-dark/50 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="time-clock-punch-map-title"
    data-default-lat="{{ $mapDefaultLat }}"
    data-default-lng="{{ $mapDefaultLng }}"
    data-default-zoom="{{ $mapDefaultZoom }}"
>
    <div class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
        <header class="border-b border-brand-border border-l-4 border-l-brand-primary bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label" data-time-clock-punch-map-kicker>Punch location</p>
                    <h2 id="time-clock-punch-map-title" class="mt-1 truncate text-lg font-bold text-brand-text" data-time-clock-punch-map-title>Clock in</h2>
                    <p class="mt-1 text-sm text-brand-text-secondary" data-time-clock-punch-map-subtitle>—</p>
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm transition hover:bg-brand-surface hover:text-brand-text"
                    data-time-clock-punch-map-close
                    aria-label="Close map"
                >
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1"
                    data-time-clock-punch-map-geofence-badge
                >
                    —
                </span>
                <span class="text-xs text-brand-text-secondary" data-time-clock-punch-map-distance>—</span>
            </div>
        </header>

        <div class="relative min-h-[18rem] bg-brand-surface">
            <div id="time-clock-punch-map-canvas" class="h-[min(22rem,48vh)] w-full min-h-[220px]"></div>
        </div>

        <footer class="grid gap-px border-t border-brand-border/80 bg-brand-border/70 sm:grid-cols-2">
            <div class="bg-white px-4 py-3 sm:px-5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Punch GPS</p>
                <p class="mt-1 font-mono text-xs text-brand-text" data-time-clock-punch-map-device-coords>—</p>
            </div>
            <div class="bg-white px-4 py-3 sm:px-5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Expected site</p>
                <p class="mt-1 font-mono text-xs text-brand-text" data-time-clock-punch-map-expected-coords>—</p>
            </div>
        </footer>
    </div>
</div>
