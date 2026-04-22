@extends('layouts.admin')

@section('title', 'Workforce setup')

@section('heading', 'Departments, locations & shifts')

@section('subheading')
    {{ $company->name }} — define options admins can assign to active employees (stored in your organization database).
@endsection

@section('content')
    @php
        /** @var \App\Models\Company $company */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Department> $departments */
        /** @var \Illuminate\Support\Collection<int, \App\Models\WorkLocation> $workLocations */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Shift> $shifts */
        $fmtShift = static function (\App\Models\Shift $s): string {
            $st = $s->start_time instanceof \Carbon\CarbonInterface ? $s->start_time->format('g:i A') : '';
            $en = $s->end_time instanceof \Carbon\CarbonInterface ? $s->end_time->format('g:i A') : '';

            return trim($st.'–'.$en);
        };
    @endphp

    @if (session('status'))
        <div class="mb-8 rounded-2xl border border-emerald-200/90 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-950 shadow-sm ring-1 ring-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-8 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-link hover:underline">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Back to registrations
        </a>
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        {{-- Departments --}}
        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border bg-gradient-to-r from-brand-surface to-white px-6 py-4">
                <h2 class="text-lg font-bold text-brand-text">Departments</h2>
                <p class="mt-1 text-sm text-brand-text-secondary">Used for HR grouping and assignment.</p>
            </div>
            <div class="border-b border-brand-border p-6">
                <form method="post" action="{{ route('admin.workforce.departments.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Name</label>
                        <input name="name" required maxlength="160" class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="e.g. Facilities" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Code <span class="font-normal normal-case text-brand-text-secondary">(optional)</span></label>
                        <input name="code" maxlength="32" class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="e.g. FAC" />
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-primary-dark">Add department</button>
                </form>
            </div>
            <div class="max-h-80 overflow-auto p-4">
                @forelse ($departments as $d)
                    <div class="flex items-center justify-between gap-3 border-b border-brand-border py-3 text-sm last:border-b-0">
                        <div>
                            <p class="font-semibold text-brand-text">{{ $d->name }}</p>
                            @if ($d->code)
                                <p class="mt-0.5 font-mono text-xs text-brand-text-secondary">{{ $d->code }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-2 py-6 text-center text-sm text-brand-text-secondary">No departments yet.</p>
                @endforelse
            </div>
        </section>

        {{-- Work locations --}}
        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border bg-gradient-to-r from-brand-surface to-white px-6 py-4">
                <h2 class="text-lg font-bold text-brand-text">Work locations</h2>
                <p class="mt-1 text-sm text-brand-text-secondary">Sites or venues employees report to.</p>
            </div>
            <div class="border-b border-brand-border p-6">
                <form method="post" action="{{ route('admin.workforce.work-locations.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Name</label>
                        <input name="name" required maxlength="200" class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="e.g. CBD depot" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Address <span class="font-normal normal-case text-brand-text-secondary">(optional)</span></label>
                        <textarea name="address" rows="2" maxlength="2000" class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="Street, suburb"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Notes <span class="font-normal normal-case text-brand-text-secondary">(optional)</span></label>
                        <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-primary-dark">Add location</button>
                </form>
            </div>
            <div class="max-h-80 overflow-auto p-4">
                @forelse ($workLocations as $loc)
                    <div class="border-b border-brand-border py-3 text-sm last:border-b-0">
                        <p class="font-semibold text-brand-text">{{ $loc->name }}</p>
                        @if ($loc->address)
                            <p class="mt-1 whitespace-pre-wrap text-xs text-brand-text-secondary">{{ $loc->address }}</p>
                        @endif
                    </div>
                @empty
                    <p class="px-2 py-6 text-center text-sm text-brand-text-secondary">No locations yet.</p>
                @endforelse
            </div>
        </section>

        {{-- Shifts --}}
        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border bg-gradient-to-r from-brand-surface to-white px-6 py-4">
                <h2 class="text-lg font-bold text-brand-text">Shifts</h2>
                <p class="mt-1 text-sm text-brand-text-secondary">Named rosters with start and end times.</p>
            </div>
            <div class="border-b border-brand-border p-6">
                <form method="post" action="{{ route('admin.workforce.shifts.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Name</label>
                        <input name="name" required maxlength="160" class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="e.g. Morning" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Start</label>
                            <input name="start_time" type="time" required class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">End</label>
                            <input name="end_time" type="time" required class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Breaks <span class="font-normal normal-case text-brand-text-secondary">(optional)</span></label>
                        <input name="breaks_summary" maxlength="255" class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="e.g. 30m unpaid lunch" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Notes <span class="font-normal normal-case text-brand-text-secondary">(optional)</span></label>
                        <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-primary-dark">Add shift</button>
                </form>
            </div>
            <div class="max-h-80 overflow-auto p-4">
                @forelse ($shifts as $sh)
                    <div class="border-b border-brand-border py-3 text-sm last:border-b-0">
                        <p class="font-semibold text-brand-text">{{ $sh->name }}</p>
                        <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $fmtShift($sh) }}</p>
                        @if ($sh->breaks_summary)
                            <p class="mt-1 text-xs text-brand-text-secondary">{{ $sh->breaks_summary }}</p>
                        @endif
                    </div>
                @empty
                    <p class="px-2 py-6 text-center text-sm text-brand-text-secondary">No shifts yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
