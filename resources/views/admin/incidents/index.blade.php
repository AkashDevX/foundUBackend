@extends('layouts.admin')

@section('title', 'Incidents')
@section('heading', 'Incident reports')
@section('subheading', 'Reports submitted by employees in the CruLynk app')

@section('content')
    @php
        /** @var bool $ready */
        /** @var string $status */
        /** @var array{new: int, acknowledged: int, resolved: int} $counts */
        $filters = [
            'open' => 'Open',
            'new' => 'New ('.$counts['new'].')',
            'acknowledged' => 'Acknowledged ('.$counts['acknowledged'].')',
            'resolved' => 'Resolved ('.$counts['resolved'].')',
            'all' => 'All',
        ];
    @endphp

    @if (! $ready)
        <section class="rounded-lg border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            Incident reporting is not set up on this organization database yet. Apply the latest CruLynk database update, then refresh this page.
        </section>
    @else
        <div class="mb-5 flex flex-wrap gap-2">
            @foreach ($filters as $key => $label)
                <a
                    href="{{ route('admin.incidents.index', ['status' => $key]) }}"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $status === $key ? 'bg-red-600 text-white' : 'border border-brand-border bg-white text-brand-text hover:border-red-200 hover:text-red-700' }}"
                >{{ $label }}</a>
            @endforeach
        </div>

        <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
            @if ($reports === null || $reports->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-brand-text-secondary">No incident reports in this view.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-brand-border bg-brand-surface text-xs font-semibold uppercase tracking-wide text-brand-text-secondary">
                            <tr>
                                <th class="px-4 py-3">Submitted</th>
                                <th class="px-4 py-3">Employee</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Site</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            @foreach ($reports as $report)
                                @php
                                    $badge = match ($report->status) {
                                        'new' => 'bg-red-100 text-red-700',
                                        'acknowledged' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-emerald-100 text-emerald-800',
                                    };
                                @endphp
                                <tr class="hover:bg-red-50/40">
                                    <td class="whitespace-nowrap px-4 py-3 text-brand-text-secondary">{{ \App\Support\DisplayTimezone::formatDateTime($report->created_at) }}</td>
                                    <td class="px-4 py-3 font-medium text-brand-text">
                                        <a href="{{ route('admin.incidents.show', $report->id) }}" class="hover:text-red-700 hover:underline">{{ $report->reporterName() }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-brand-text">{{ $report->typeLabel() }}</td>
                                    <td class="px-4 py-3 text-brand-text">{{ $report->site_name }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide {{ $badge }}">{{ $report->statusLabel() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($reports->hasPages())
                    <div class="border-t border-brand-border px-4 py-3">{{ $reports->links() }}</div>
                @endif
            @endif
        </section>
    @endif
@endsection
