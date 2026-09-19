<span class="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-xl {{ $iconWellClass }}">
    @include('admin.partials.dashboard-workflow-icon', ['key' => $card['key'], 'highlight' => $highlight, 'class' => 'size-[18px] shrink-0'])
</span>

<div class="min-w-0 flex-1">
    <div class="flex items-start gap-2.5">
        <span class="mt-0.5 inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-lg px-1.5 text-[12px] font-bold tabular-nums tracking-tight {{ $countClass }}">{{ $countValue }}</span>
        <p class="min-w-0 flex-1 text-[13px] font-semibold leading-5 {{ $summaryClass }}">{{ $summaryLabel }}</p>
    </div>
</div>

<svg class="workflow-card-chevron mt-1 size-4 shrink-0 {{ $chevronClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
