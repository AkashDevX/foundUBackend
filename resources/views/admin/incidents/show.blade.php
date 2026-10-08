@extends('layouts.admin')

@section('title', 'Incident report')
@section('heading', $report->typeLabel())
@section('subheading')
    Submitted by {{ $report->reporterName() }} · {{ \App\Support\DisplayTimezone::formatDateTime($report->created_at) }}
@endsection

@section('content')
    @php
        $badge = match ($report->status) {
            'new' => 'bg-red-100 text-red-700',
            'acknowledged' => 'bg-amber-100 text-amber-800',
            default => 'bg-emerald-100 text-emerald-800',
        };
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.incidents.index') }}" class="text-sm font-semibold text-brand-primary hover:underline">Back to incidents</a>
        <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $badge }}">{{ $report->statusLabel() }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-5">
            @php
                $signature = $report->signatureDrawing();
                $sawIncidentPhotos = false;
                $sawPropertyPhotos = false;
            @endphp
            @foreach ($report->presentationSections() as $section)
                <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
                    <h2 class="border-b border-brand-border px-5 py-3 text-sm font-bold text-brand-text">{{ $section['title'] }}</h2>
                    @if ($section['rows'] !== [])
                        <dl class="divide-y divide-brand-border">
                            @foreach ($section['rows'] as $row)
                                @continue($row['label'] === 'Signature' && $signature)
                                <div class="grid gap-1 px-5 py-3 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-4">
                                    <dt class="text-sm font-medium text-brand-label">{{ $row['label'] }}</dt>
                                    <dd class="whitespace-pre-wrap text-sm text-brand-text">{{ $row['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                    @if ($section['title'] === 'EMPLOYEE DECLARATION' && $signature)
                        @include('admin.incidents.partials.signature', ['signature' => $signature])
                    @endif
                    @if ($section['title'] === 'ATTACHMENTS / EVIDENCE')
                        @php $sawIncidentPhotos = true; @endphp
                        @include('admin.incidents.partials.photos', ['report' => $report, 'group' => 'incident'])
                    @endif
                    @if ($section['title'] === 'PROPERTY LOSS OR DAMAGE')
                        @php $sawPropertyPhotos = true; @endphp
                        @include('admin.incidents.partials.photos', ['report' => $report, 'group' => 'property'])
                    @endif
                </section>
            @endforeach
            @if (! $sawIncidentPhotos && $report->filesFor('incident') !== [])
                <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
                    <h2 class="border-b border-brand-border px-5 py-3 text-sm font-bold text-brand-text">ATTACHMENTS / EVIDENCE</h2>
                    @include('admin.incidents.partials.photos', ['report' => $report, 'group' => 'incident'])
                </section>
            @endif
            @if (! $sawPropertyPhotos && $report->filesFor('property') !== [])
                <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
                    <h2 class="border-b border-brand-border px-5 py-3 text-sm font-bold text-brand-text">PROPERTY LOSS OR DAMAGE</h2>
                    @include('admin.incidents.partials.photos', ['report' => $report, 'group' => 'property'])
                </section>
            @endif
            @if ($signature && ! collect($report->presentationSections())->contains(fn ($section) => ($section['title'] ?? '') === 'EMPLOYEE DECLARATION'))
                <section class="overflow-hidden rounded-lg border border-brand-border bg-white shadow-sm">
                    <h2 class="border-b border-brand-border px-5 py-3 text-sm font-bold text-brand-text">EMPLOYEE DECLARATION</h2>
                    @include('admin.incidents.partials.signature', ['signature' => $signature])
                </section>
            @endif
        </div>

        <aside class="h-fit rounded-lg border border-brand-border bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-brand-text">Review</h2>
            <p class="mt-1 text-xs leading-relaxed text-brand-text-secondary">Acknowledged and resolved reports are removed from the dashboard alert and the red siren. The report stays in the incident list.</p>
            @if ($report->reviewed_by)
                <p class="mt-3 text-xs text-brand-text-secondary">
                    Last updated by {{ $report->reviewed_by }}
                    @if ($report->reviewed_at)
                        · {{ \App\Support\DisplayTimezone::formatDateTime($report->reviewed_at) }}
                    @endif
                </p>
            @endif
            <form method="post" action="{{ route('admin.incidents.update', $report->id) }}" class="mt-4 space-y-3">
                @csrf
                <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label" for="incident-status">Status</label>
                <select id="incident-status" name="status" class="w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text">
                    @foreach (\App\Models\IncidentReport::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $report->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label" for="incident-note">Admin note</label>
                <textarea id="incident-note" name="admin_note" rows="5" maxlength="2000" class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm text-brand-text">{{ old('admin_note', $report->admin_note) }}</textarea>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary-dark">Save review</button>
            </form>
        </aside>
    </div>
@endsection
