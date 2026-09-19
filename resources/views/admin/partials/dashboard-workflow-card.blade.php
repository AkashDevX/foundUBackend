@php
    /** @var array{key: string, title: string, summary: string, highlight: bool, items: list<array>, total_count: int, hub_url?: string|null} $card */
    $items = $card['items'] ?? [];
    $itemCount = count($items);
    $highlight = (bool) ($card['highlight'] ?? false);
    $summary = (string) ($card['summary'] ?? $card['title'] ?? '');
    $countValue = (int) ($card['total_count'] ?? 0);
    $summaryLabel = trim((string) preg_replace('/^\d+\s+/u', '', $summary));
    if ($summaryLabel === '') {
        $summaryLabel = $summary;
    }

    $shellClass = $highlight
        ? 'border-amber-200/90 bg-gradient-to-br from-amber-50 via-white to-white'
        : 'border-brand-border bg-white';
    $iconWellClass = $highlight
        ? 'bg-amber-100 text-amber-700 ring-1 ring-inset ring-amber-200/70'
        : 'bg-brand-surface text-brand-primary ring-1 ring-inset ring-brand-border';
    $countClass = $highlight
        ? 'bg-amber-500 text-white shadow-sm shadow-amber-500/25'
        : 'bg-brand-primary text-white shadow-sm shadow-brand-primary/20';
    $summaryClass = $highlight ? 'text-amber-950' : 'text-brand-text';
    $chevronClass = $highlight ? 'text-amber-600/55' : 'text-brand-icon';

    $firstItem = $itemCount === 1 ? ($items[0] ?? null) : null;
    $firstTimeOff = is_array($firstItem) ? ($firstItem['time_off_review'] ?? null) : null;
    $firstUrl = is_array($firstItem) ? ($firstItem['url'] ?? null) : null;
    $hubUrl = $card['hub_url'] ?? null;

    $headerIsTimeOff = is_array($firstTimeOff) && ! empty($firstTimeOff['id']);
    $headerUrl = null;
    if (! $headerIsTimeOff) {
        if (is_string($firstUrl) && $firstUrl !== '') {
            $headerUrl = $firstUrl;
        } elseif (is_string($hubUrl) && $hubUrl !== '') {
            $headerUrl = $hubUrl;
        }
    }

    $headerClass = 'flex w-full items-start gap-3 px-3.5 py-3.5 text-left transition hover:bg-black/[0.015] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary/30';
@endphp

<div
    class="workflow-card rounded-2xl border {{ $shellClass }} shadow-sm ring-1 ring-black/[0.02]"
    data-workflow-card
>
    @if ($headerIsTimeOff)
        <button
            type="button"
            class="{{ $headerClass }}"
            data-open-time-off-review="{{ (int) $firstTimeOff['id'] }}"
        >
            @include('admin.partials.dashboard-workflow-card-header', [
                'card' => $card,
                'iconWellClass' => $iconWellClass,
                'countClass' => $countClass,
                'countValue' => $countValue,
                'summaryClass' => $summaryClass,
                'summaryLabel' => $summaryLabel,
                'chevronClass' => $chevronClass,
                'highlight' => $highlight,
            ])
        </button>
    @elseif ($headerUrl !== null)
        <a href="{{ $headerUrl }}" class="{{ $headerClass }}">
            @include('admin.partials.dashboard-workflow-card-header', [
                'card' => $card,
                'iconWellClass' => $iconWellClass,
                'countClass' => $countClass,
                'countValue' => $countValue,
                'summaryClass' => $summaryClass,
                'summaryLabel' => $summaryLabel,
                'chevronClass' => $chevronClass,
                'highlight' => $highlight,
            ])
        </a>
    @else
        <div class="flex items-start gap-3 px-3.5 py-3.5">
            @include('admin.partials.dashboard-workflow-card-header', [
                'card' => $card,
                'iconWellClass' => $iconWellClass,
                'countClass' => $countClass,
                'countValue' => $countValue,
                'summaryClass' => $summaryClass,
                'summaryLabel' => $summaryLabel,
                'chevronClass' => $chevronClass,
                'highlight' => $highlight,
            ])
        </div>
    @endif

    <div class="workflow-card-details" aria-hidden="true">
        <ul class="space-y-0.5 border-t border-brand-border/70 bg-brand-surface/40 px-2.5 py-2.5">
            @foreach ($items as $item)
                @php
                    $timeOffReview = $item['time_off_review'] ?? null;
                    $dotClass = match ($item['severity'] ?? 'info') {
                        'urgent' => 'bg-red-500',
                        'warning' => 'bg-amber-500',
                        'success' => 'bg-emerald-500',
                        default => 'bg-brand-primary-light',
                    };
                @endphp
                <li>
                    @if (is_array($timeOffReview) && ! empty($timeOffReview['id']))
                        <button
                            type="button"
                            class="flex w-full items-start gap-2.5 rounded-xl px-2.5 py-2 text-left text-xs leading-snug text-brand-text transition hover:bg-white hover:shadow-sm"
                            data-open-time-off-review="{{ (int) $timeOffReview['id'] }}"
                        >
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1 whitespace-normal">{{ $item['message'] }}</span>
                        </button>
                    @elseif (! empty($item['url']))
                        <a href="{{ $item['url'] }}" class="flex items-start gap-2.5 rounded-xl px-2.5 py-2 text-xs leading-snug text-brand-text transition hover:bg-white hover:shadow-sm">
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1 whitespace-normal">{{ $item['message'] }}</span>
                        </a>
                    @else
                        <div class="flex items-start gap-2.5 rounded-xl px-2.5 py-2 text-xs leading-snug text-brand-text">
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1 whitespace-normal">{{ $item['message'] }}</span>
                        </div>
                    @endif
                </li>
            @endforeach
            @if (($card['total_count'] ?? 0) > $itemCount)
                <li class="px-2.5 py-1 text-[11px] text-brand-text-secondary">
                    Showing {{ $itemCount }} of {{ $card['total_count'] }} items.
                </li>
            @endif
        </ul>
    </div>
</div>
