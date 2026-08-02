@extends('layouts.admin')

@section('title', 'Dashboard')

@section('heading', 'Dashboard')

@section('subheading')
    {{ $currentCompany->name }}
@endsection

@section('content')
    @php
        /** @var \App\Models\Company $currentCompany */
        /** @var string|null $tenantError */
        /** @var array{sections: list<array>, alert_count: int} $notifications */
        use App\Support\DisplayTimezone;
        /** @var int $statsTotal */
        /** @var int $statsPending */
        /** @var int $statsActive */
        /** @var int $statsDeclined */

        $severityStyles = [
            'urgent' => 'border-l-red-500 bg-red-50/60',
            'warning' => 'border-l-amber-500 bg-amber-50/50',
            'info' => 'border-l-brand-primary-light bg-brand-surface/40',
            'success' => 'border-l-emerald-500 bg-emerald-50/50',
        ];

        $severityDots = [
            'urgent' => 'bg-red-500',
            'warning' => 'bg-amber-500',
            'info' => 'bg-brand-primary-light',
            'success' => 'bg-emerald-500',
        ];
    @endphp

    @if ($tenantError !== null)
        <div data-flash-warning="{{ e('Could not reach this organization\'s database. '.$tenantError) }}" hidden></div>
    @endif

    <div class="mb-6 overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border border-l-4 border-l-brand-primary bg-white px-5 py-4 sm:px-6 sm:py-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-brand-text sm:text-2xl">{{ $currentCompany->name }}</h2>
                    <p class="mt-1.5 text-sm text-brand-text-secondary">
                        <time datetime="{{ DisplayTimezone::now()->toDateString() }}">{{ DisplayTimezone::now()->format('l, F j, Y') }}</time>
                        <span class="px-1.5 text-brand-border" aria-hidden="true">·</span>
                        <span class="font-mono text-xs text-brand-text-secondary/90">{{ $currentCompany->slug }}</span>
                    </p>
                </div>
                @if (($notifications['alert_count'] ?? 0) > 0)
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-950 ring-1 ring-amber-200/80">
                        {{ $notifications['alert_count'] }} alert{{ $notifications['alert_count'] === 1 ? '' : 's' }}
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-col divide-y divide-brand-border sm:flex-row sm:divide-x sm:divide-y-0">
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $statsTotal }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Total employees</span>
            </div>
            <a
                href="{{ route('admin.registrations.index', ['status' => 'pending']) }}"
                class="min-w-0 flex-1 px-5 py-3.5 text-left transition hover:bg-brand-surface/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-primary sm:px-5 sm:py-4"
            >
                <span class="block text-2xl font-medium tabular-nums text-brand-primary-light sm:text-3xl">{{ $statsPending }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Pending registrations</span>
            </a>
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-primary sm:text-3xl">{{ $statsActive }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Active employees</span>
            </div>
            <div class="min-w-0 flex-1 px-5 py-3.5 sm:px-5 sm:py-4">
                <span class="block text-2xl font-medium tabular-nums text-brand-text sm:text-3xl">{{ $statsDeclined }}</span>
                <span class="mt-0.5 block text-sm text-brand-text-secondary">Declined</span>
            </div>
        </div>
    </div>

    <section class="space-y-4">
        @foreach ($notifications['sections'] ?? [] as $section)
            <div class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-brand-border bg-brand-surface/50 px-5 py-4 sm:px-6">
                    <h3 class="text-base font-bold text-brand-text">{{ $section['title'] }}</h3>
                    @if (! ($section['unavailable'] ?? false))
                        <span class="text-xs font-semibold uppercase tracking-wide text-brand-label">
                            {{ $section['total_count'] }} {{ $section['total_count'] === 1 ? 'item' : 'items' }}
                        </span>
                    @endif
                </div>

                @if ($section['unavailable'] ?? false)
                    <div class="px-5 py-6 text-sm text-brand-text-secondary sm:px-6">
                        {{ $section['unavailable_reason'] ?? 'Not available yet.' }}
                    </div>
                @elseif (($section['total_count'] ?? 0) === 0)
                    <div class="px-5 py-6 text-sm text-brand-text-secondary sm:px-6">
                        No notifications in this category right now.
                    </div>
                @else
                    <ul class="divide-y divide-brand-border">
                        @foreach ($section['items'] as $item)
                            @php
                                $severity = $item['severity'] ?? 'info';
                                $rowClass = $severityStyles[$severity] ?? $severityStyles['info'];
                                $dotClass = $severityDots[$severity] ?? $severityDots['info'];
                            @endphp
                            <li class="border-l-4 {{ $rowClass }}">
                                @if (! empty($item['url']))
                                    <a href="{{ $item['url'] }}" class="flex items-start gap-3 px-5 py-3.5 text-sm transition hover:bg-white/70 sm:px-6">
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                                        <span class="text-brand-text">{{ $item['message'] }}</span>
                                    </a>
                                @else
                                    <div class="flex items-start gap-3 px-5 py-3.5 text-sm sm:px-6">
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                                        <span class="text-brand-text">{{ $item['message'] }}</span>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if (($section['total_count'] ?? 0) > count($section['items']))
                        <div class="border-t border-brand-border bg-brand-surface/30 px-5 py-3 text-xs text-brand-text-secondary sm:px-6">
                            Showing {{ count($section['items']) }} of {{ $section['total_count'] }} notifications.
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    </section>
@endsection
