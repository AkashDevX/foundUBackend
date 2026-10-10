@php
    /** @var list<array<string, mixed>> $weekIndex */
    /** @var \Carbon\Carbon|null $selectedWeek */
    /** @var callable(string): string $weekDetailsUrl */
@endphp

@foreach ($weekIndex as $week)
    @php
        $stats = $week['stats'];
        $isSelected = $selectedWeek?->toDateString() === $week['week_start'];
    @endphp
    <tr class="transition {{ $isSelected ? 'bg-brand-primary/[0.05]' : 'hover:bg-brand-surface/40' }}">
        <td class="px-4 py-4 sm:px-6">
            <div class="flex flex-wrap items-center gap-2">
                <p class="font-semibold text-brand-text">{{ $week['week_label_long'] }}</p>
                @if ($week['is_current'])
                    <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-primary ring-1 ring-brand-primary/20">Current week</span>
                @endif
            </div>
            <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $week['week_label'] }}</p>
        </td>
        <td class="hidden px-4 py-4 text-center tabular-nums font-semibold text-brand-text sm:table-cell sm:px-6">{{ $stats['employees'] }}</td>
        <td class="hidden px-4 py-4 text-center tabular-nums text-brand-text md:table-cell sm:px-6">{{ $stats['rows'] }}</td>
        <td class="px-4 py-4 text-center sm:px-6">
            @if (($stats['pending'] ?? 0) > 0)
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200">
                    {{ $stats['pending'] }}
                </span>
            @else
                <span class="text-xs text-brand-text-secondary">0</span>
            @endif
        </td>
        <td class="hidden px-4 py-4 text-center tabular-nums text-emerald-700 lg:table-cell sm:px-6">{{ $stats['approved'] }}</td>
        <td class="px-4 py-4 text-right sm:px-6">
            <a
                href="{{ $weekDetailsUrl($week['week_start']) }}"
                class="inline-flex items-center gap-2 rounded-xl {{ $isSelected ? 'bg-brand-primary-dark' : 'bg-brand-primary' }} px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark"
            >
                Details
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </td>
    </tr>
@endforeach
