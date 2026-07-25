@extends('layouts.admin')

@section('title', 'Organization setup')

@section('heading', 'Organization setup')

@section('subheading', $company->name)

@push('scripts')
    @vite(['resources/js/workforce.js'])
@endpush

@section('content')
    @php
        /** @var \App\Models\Company $company */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Department> $departments */
        /** @var \Illuminate\Support\Collection<int, \App\Models\JobTitle> $jobTitles */
        /** @var \Illuminate\Support\Collection<int, \App\Models\WorkLocation> $workLocations */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Shift> $shifts */
        /** @var \Illuminate\Support\Collection<int, \App\Models\LeaveType> $leaveTypes */
        /** @var float $mapDefaultLat */
        /** @var float $mapDefaultLng */
        /** @var int $mapDefaultZoom */
        /** @var string $section */
        $fmtShift = static function (\App\Models\Shift $s): string {
            $st = $s->start_time instanceof \Carbon\CarbonInterface ? $s->start_time->format('g:i A') : '';
            $en = $s->end_time instanceof \Carbon\CarbonInterface ? $s->end_time->format('g:i A') : '';

            return trim($st.' – '.$en);
        };
        $shiftDaysMap = [
            'mon' => 'Mon',
            'tue' => 'Tue',
            'wed' => 'Wed',
            'thu' => 'Thu',
            'fri' => 'Fri',
            'sat' => 'Sat',
            'sun' => 'Sun',
        ];
        $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
        $lbl = 'text-xs font-semibold uppercase tracking-wide text-brand-label';
        $row = 'space-y-1.5';
        $wfGrid = 'grid gap-3 sm:grid-cols-[minmax(0,11rem)_1fr] sm:items-start sm:gap-x-5';
        $shiftDayLabel =
            'relative flex min-h-[2.75rem] cursor-pointer select-none items-center justify-center overflow-hidden rounded-xl border border-brand-border bg-white px-3 py-2 text-center text-xs font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/40 hover:bg-brand-surface/60 [&:has(input:checked)]:border-brand-primary [&:has(input:checked)]:bg-brand-primary [&:has(input:checked)]:text-white [&:has(input:checked)]:shadow-md [&:has(input:checked)]:shadow-brand-primary/25';
        $savedCard = 'relative isolate overflow-hidden rounded-2xl border border-brand-border/90 bg-gradient-to-br from-white via-white to-brand-primary/[0.06] p-4 shadow-sm ring-1 ring-black/[0.04] transition duration-200 hover:border-brand-primary/35 hover:shadow-md sm:p-5';
    @endphp

    <div class="grid gap-8 {{ $section === 'work-locations' ? 'max-w-4xl' : 'max-w-3xl' }}">
        @if ($section === 'departments')
        {{-- Departments --}}
        <section class="flex min-h-[32rem] flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="shrink-0 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-6 py-5 sm:px-7">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold tracking-tight text-brand-text">Departments</h2>
                    </div>
                </div>
            </header>
            <div class="shrink-0 border-b border-brand-border px-6 py-6 sm:px-7">
                <form method="post" action="{{ route('admin.workforce.departments.store') }}" class="space-y-4">
                    @csrf
                    <div class="{{ $row }}">
                        <label for="dept-name" class="{{ $lbl }}">Name</label>
                        <input id="dept-name" name="department_name" required maxlength="160" value="{{ old('department_name') }}" class="{{ $in }}" placeholder="e.g. Facilities" autocomplete="organization-title" />
                    </div>
                    <div class="{{ $row }}">
                        <label for="dept-code" class="{{ $lbl }}">Code</label>
                        <div>
                            <input id="dept-code" name="department_code" maxlength="32" value="{{ old('department_code') }}" class="{{ $in }}" placeholder="Optional short code" />
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-3 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                        Add department
                    </button>
                </form>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-gradient-to-b from-brand-surface/25 to-transparent px-4 py-4 sm:px-6 sm:py-5">
                @if ($departments->isNotEmpty())
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-[11px] font-bold uppercase tracking-[0.12em] text-brand-text-secondary">Saved departments</h3>
                        <span class="rounded-full bg-brand-primary/12 px-2.5 py-0.5 text-[10px] font-bold tabular-nums text-brand-primary">{{ $departments->count() }}</span>
                    </div>
                @endif
                @forelse ($departments as $d)
                    <article class="{{ $savedCard }} mb-4 last:mb-0">
                        <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-brand-primary to-brand-primary/50 opacity-90" aria-hidden="true"></div>
                        <div class="relative flex gap-4 pl-2">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-primary/12 text-brand-primary shadow-inner ring-1 ring-brand-primary/10">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008z" /></svg>
                            </div>
                            <div class="min-w-0 flex-1 text-sm">
                                <div class="flex flex-wrap items-center gap-2 gap-y-1">
                                    <h3 class="text-base font-bold leading-snug text-brand-text">{{ $d->name }}</h3>
                                    @if ($d->code)
                                        <span class="inline-flex items-center rounded-lg border border-brand-border/80 bg-white/80 px-2 py-0.5 font-mono text-[11px] font-semibold uppercase tracking-wide text-brand-text-secondary shadow-sm">{{ $d->code }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-[11px] font-medium text-brand-text-secondary">Department · ID {{ $d->id }}</p>
                            </div>
                        </div>
                        <details class="group/dept-edit relative mt-4 overflow-hidden rounded-xl border border-brand-border/90 bg-white/85 shadow-sm ring-1 ring-black/[0.03] open:shadow-md">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3.5 text-xs font-bold uppercase tracking-wide text-brand-primary transition hover:bg-white/60 [&::-webkit-details-marker]:hidden">
                                <span>Edit department</span>
                                <svg class="size-4 shrink-0 text-brand-primary/70 transition group-open/dept-edit:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <div class="border-t border-brand-border bg-white/90 px-4 py-5 sm:px-6">
                                <form method="post" action="{{ route('admin.workforce.departments.update', ['department' => $d->id]) }}" class="space-y-5">
                                    @csrf
                                    <div class="{{ $wfGrid }}">
                                        <label for="dept-edit-name-{{ $d->id }}" class="{{ $lbl }} sm:pt-2.5">Name</label>
                                        <input id="dept-edit-name-{{ $d->id }}" name="department_name" required maxlength="160" value="{{ $d->name }}" class="{{ $in }}" autocomplete="off" />
                                    </div>
                                    <div class="{{ $wfGrid }}">
                                        <label for="dept-edit-code-{{ $d->id }}" class="{{ $lbl }} sm:pt-2.5">Code</label>
                                        <div>
                                            <input id="dept-edit-code-{{ $d->id }}" name="department_code" maxlength="32" value="{{ $d->code }}" class="{{ $in }}" placeholder="Optional short code" />
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-border pt-4">
                                        <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                                            Save changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-brand-border bg-white/60 px-6 py-12 text-center">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-surface text-brand-text-secondary/80" aria-hidden="true">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008z" /></svg>
                        </span>
                        <p class="mt-4 text-sm font-semibold text-brand-text">No departments yet</p>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        @if ($section === 'job-titles')
        {{-- Job titles --}}
        <section class="flex min-h-[32rem] flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="shrink-0 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-6 py-5 sm:px-7">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold tracking-tight text-brand-text">Job titles</h2>
                    </div>
                </div>
            </header>
            <div class="shrink-0 border-b border-brand-border px-6 py-6 sm:px-7">
                <form method="post" action="{{ route('admin.workforce.job-titles.store') }}" class="space-y-4">
                    @csrf
                    <div class="{{ $row }}">
                        <label for="job-title-name" class="{{ $lbl }}">Name</label>
                        <input id="job-title-name" name="job_title_name" required maxlength="160" value="{{ old('job_title_name') }}" class="{{ $in }}" placeholder="e.g. Casual Cleaner Level 1" autocomplete="off" />
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-3 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                        Add job title
                    </button>
                </form>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-gradient-to-b from-brand-surface/25 to-transparent px-4 py-4 sm:px-6 sm:py-5">
                @if ($jobTitles->isNotEmpty())
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-[11px] font-bold uppercase tracking-[0.12em] text-brand-text-secondary">Saved job titles</h3>
                        <span class="rounded-full bg-brand-primary/12 px-2.5 py-0.5 text-[10px] font-bold tabular-nums text-brand-primary">{{ $jobTitles->count() }}</span>
                    </div>
                @endif
                @forelse ($jobTitles as $jt)
                    <article class="{{ $savedCard }} mb-4 last:mb-0">
                        <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-brand-primary to-brand-primary/50 opacity-90" aria-hidden="true"></div>
                        <div class="relative flex gap-4 pl-2">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-primary/12 text-brand-primary shadow-inner ring-1 ring-brand-primary/10">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                            </div>
                            <div class="min-w-0 flex-1 text-sm">
                                <h3 class="text-base font-bold leading-snug text-brand-text">{{ $jt->name }}</h3>
                                <p class="mt-1 text-[11px] font-medium text-brand-text-secondary">Job title · ID {{ $jt->id }}</p>
                            </div>
                        </div>
                        <details class="group/jt-edit relative mt-4 overflow-hidden rounded-xl border border-brand-border/90 bg-white/85 shadow-sm ring-1 ring-black/[0.03] open:shadow-md">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3.5 text-xs font-bold uppercase tracking-wide text-brand-primary transition hover:bg-white/60 [&::-webkit-details-marker]:hidden">
                                <span>Edit job title</span>
                                <svg class="size-4 shrink-0 text-brand-primary/70 transition group-open/jt-edit:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <div class="border-t border-brand-border bg-white/90 px-4 py-5 sm:px-6">
                                <form method="post" action="{{ route('admin.workforce.job-titles.update', ['jobTitle' => $jt->id]) }}" class="space-y-5">
                                    @csrf
                                    <div class="{{ $wfGrid }}">
                                        <label for="jt-edit-name-{{ $jt->id }}" class="{{ $lbl }} sm:pt-2.5">Name</label>
                                        <input id="jt-edit-name-{{ $jt->id }}" name="job_title_name" required maxlength="160" value="{{ $jt->name }}" class="{{ $in }}" autocomplete="off" />
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                                            Save changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-brand-border bg-white/60 px-6 py-12 text-center">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-surface text-brand-text-secondary/80" aria-hidden="true">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                        </span>
                        <p class="mt-4 text-sm font-semibold text-brand-text">No job titles yet</p>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        @if ($section === 'work-locations')
        {{-- Work locations --}}
        <section class="flex min-h-[32rem] flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02] xl:min-h-[36rem]">
            <header class="shrink-0 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-6 py-5 sm:px-7">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold tracking-tight text-brand-text">Work locations</h2>
                    </div>
                </div>
            </header>
            <div class="shrink-0 border-b border-brand-border px-6 py-6 sm:px-7">
                <form method="post" action="{{ route('admin.workforce.work-locations.store') }}" class="space-y-5" data-wf-location-form>
                    @csrf
                    <div class="{{ $wfGrid }}">
                        <label for="loc-name" class="{{ $lbl }} sm:pt-2.5">Name</label>
                        <input id="loc-name" name="location_name" required maxlength="200" value="{{ old('location_name') }}" class="{{ $in }}" placeholder="e.g. North depot" autocomplete="off" />
                    </div>

                    <div
                        class="rounded-2xl border border-brand-border bg-brand-surface/40 p-4 ring-1 ring-black/[0.03] sm:p-5"
                        data-wf-loc-root
                        data-map-only="true"
                        data-search-url="{{ route('admin.workforce.geocode.search') }}"
                        data-reverse-url="{{ route('admin.workforce.geocode.reverse') }}"
                        data-default-lat="{{ $mapDefaultLat }}"
                        data-default-lng="{{ $mapDefaultLng }}"
                        data-default-zoom="{{ $mapDefaultZoom }}"
                    >
                        <div class="mt-4 space-y-3">
                            <div class="{{ $wfGrid }}">
                                <label for="loc-address" class="{{ $lbl }} sm:pt-2.5">Address</label>
                                <div class="relative">
                                    <input
                                        id="loc-address"
                                        name="address"
                                        type="text"
                                        maxlength="2000"
                                        value="{{ old('address') }}"
                                        data-wf-address
                                        autocomplete="off"
                                        class="{{ $in }} pe-9"
                                        placeholder="Start typing — pick a suggestion to set the pin"
                                    />
                                    <button
                                        type="button"
                                        data-wf-clear-address
                                        class="absolute right-1 top-1/2 z-10 hidden -translate-y-1/2 rounded-md p-1 text-brand-text-secondary/80 transition hover:bg-brand-surface hover:text-brand-text focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
                                        aria-label="Clear address"
                                    >
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                    <div
                                        data-wf-address-suggestions
                                        class="absolute left-0 right-0 top-full z-30 mt-1 max-h-52 overflow-auto rounded-xl border border-brand-border bg-white py-1 shadow-lg ring-1 ring-black/[0.06] hidden"
                                        role="listbox"
                                        aria-label="Address suggestions"
                                    ></div>
                                </div>
                            </div>
                            <p
                                data-wf-geocode-status
                                class="flex min-h-[1.25rem] items-start gap-2 text-xs leading-relaxed text-brand-text-secondary"
                            >
                                Type an address and choose a match, or use the map.
                            </p>
                            <div
                                class="relative overflow-hidden rounded-xl border border-brand-border bg-white shadow-inner ring-1 ring-black/[0.04]"
                                data-wf-map-wrap
                            >
                                <div
                                    class="pointer-events-none absolute inset-0 z-[1000] hidden items-center justify-center bg-white/80 backdrop-blur-[1px]"
                                    data-wf-map-loader
                                    aria-live="polite"
                                >
                                    <div class="flex max-w-xs flex-col items-center gap-3 rounded-xl border border-brand-border/80 bg-white/95 px-5 py-4 text-center shadow-lg ring-1 ring-black/[0.04]">
                                        <span
                                            class="h-8 w-8 shrink-0 animate-spin rounded-full border-2 border-brand-primary/25 border-t-brand-primary"
                                            data-wf-map-loader-spin
                                            aria-hidden="true"
                                        ></span>
                                        <p class="text-sm font-medium text-brand-text" data-wf-map-loader-text>Preparing map…</p>
                                    </div>
                                </div>
                                <div data-wf-map class="h-[min(22rem,48vh)] w-full min-h-[220px] bg-brand-surface"></div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" data-wf-clear-pin class="rounded-lg border border-brand-border bg-white px-3 py-1.5 text-xs font-semibold text-brand-text shadow-sm transition hover:bg-brand-surface">
                                    Clear pin
                                </button>
                                <span class="text-xs text-brand-text-secondary">Click map to place pin · drag to adjust</span>
                            </div>
                            <div class="{{ $wfGrid }}">
                                <span class="{{ $lbl }} sm:pt-2.5">Coordinates</span>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Latitude</span>
                                        <input type="hidden" name="latitude" value="{{ old('latitude') }}" data-wf-lat />
                                        <input type="text" readonly class="{{ $in }} cursor-default bg-brand-surface/80 font-mono text-xs" value="{{ old('latitude') }}" data-wf-lat-display tabindex="-1" />
                                    </div>
                                    <div>
                                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Longitude</span>
                                        <input type="hidden" name="longitude" value="{{ old('longitude') }}" data-wf-lng />
                                        <input type="text" readonly class="{{ $in }} cursor-default bg-brand-surface/80 font-mono text-xs" value="{{ old('longitude') }}" data-wf-lng-display tabindex="-1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @error('latitude')
                        <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror

                    <div class="{{ $wfGrid }}">
                        <label for="loc-notes" class="{{ $lbl }} sm:pt-2.5">Notes</label>
                        <textarea id="loc-notes" name="location_notes" rows="2" maxlength="2000" class="{{ $in }} min-h-[4.5rem] resize-y" placeholder="Parking, gate code, site contact…">{{ old('location_notes') }}</textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-3 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                        Add work location
                    </button>
                </form>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-gradient-to-b from-brand-surface/25 to-transparent px-4 py-4 sm:px-6 sm:py-5">
                @if ($workLocations->isNotEmpty())
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-[11px] font-bold uppercase tracking-[0.12em] text-brand-text-secondary">Saved work locations</h3>
                        <span class="rounded-full bg-brand-primary/12 px-2.5 py-0.5 text-[10px] font-bold tabular-nums text-brand-primary">{{ $workLocations->count() }}</span>
                    </div>
                @endif
                @forelse ($workLocations as $loc)
                    @php
                        $hasCoords = $loc->latitude !== null && $loc->longitude !== null;
                        $osmHref = $hasCoords
                            ? 'https://www.openstreetmap.org/?mlat='.urlencode((string) $loc->latitude).'&mlon='.urlencode((string) $loc->longitude).'#map=17/'.$loc->latitude.'/'.$loc->longitude
                            : null;
                    @endphp
                    <article class="{{ $savedCard }} mb-4 last:mb-0">
                        <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-brand-primary to-brand-primary/50 opacity-90" aria-hidden="true"></div>
                        <div class="relative flex gap-4 pl-2">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-primary/12 text-brand-primary shadow-inner ring-1 ring-brand-primary/10">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                            </div>
                            <div class="min-w-0 flex-1 text-sm">
                                <div class="flex flex-wrap items-center gap-2 gap-y-1">
                                    <h3 class="text-base font-bold leading-snug text-brand-text">{{ $loc->name }}</h3>
                                    @if ($osmHref)
                                        <a href="{{ $osmHref }}" target="_blank" rel="noopener noreferrer" class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-brand-border/80 bg-white/80 px-2 py-0.5 text-[11px] font-semibold text-brand-primary shadow-sm transition hover:border-brand-primary/40 hover:bg-white">
                                            Map
                                            <svg class="size-3.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        </a>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 gap-y-1">
                                    @if ($hasCoords)
                                        <span class="inline-flex items-center rounded-lg border border-brand-border/80 bg-white/80 px-2 py-0.5 font-mono text-[10px] font-semibold tabular-nums text-brand-text-secondary shadow-sm">
                                            {{ number_format((float) $loc->latitude, 5) }}, {{ number_format((float) $loc->longitude, 5) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-lg border border-brand-border/80 bg-brand-surface/80 px-2 py-0.5 text-[10px] font-semibold text-brand-text-secondary shadow-sm">No pin saved</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-[11px] font-medium text-brand-text-secondary">Work location · ID {{ $loc->id }}</p>
                                @if ($loc->address)
                                    <div class="mt-3 rounded-xl border border-brand-border/60 bg-white/70 px-3 py-2.5 text-xs leading-relaxed text-brand-text shadow-sm">
                                        {{ $loc->address }}
                                    </div>
                                @endif
                                @if ($loc->notes)
                                    <p class="mt-2 text-xs italic leading-relaxed text-brand-text-secondary">{{ $loc->notes }}</p>
                                @endif
                            </div>
                        </div>
                        <details class="group/loc-edit relative mt-4 overflow-hidden rounded-xl border border-brand-border/90 bg-white/85 shadow-sm ring-1 ring-black/[0.03] open:shadow-md">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3.5 text-xs font-bold uppercase tracking-wide text-brand-primary transition hover:bg-white/60 [&::-webkit-details-marker]:hidden">
                                <span>Edit location</span>
                                <svg class="size-4 shrink-0 text-brand-primary/70 transition group-open/loc-edit:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <div class="border-t border-brand-border bg-white/95 px-4 py-5 sm:px-6">
                                <form method="post" action="{{ route('admin.workforce.work-locations.update', ['location' => $loc->id]) }}" class="space-y-5">
                                    @csrf
                                    <div class="{{ $wfGrid }}">
                                        <label for="loc-edit-name-{{ $loc->id }}" class="{{ $lbl }} sm:pt-2.5">Name</label>
                                        <input id="loc-edit-name-{{ $loc->id }}" name="location_name" required maxlength="200" value="{{ $loc->name }}" class="{{ $in }}" autocomplete="off" />
                                    </div>
                                    <div
                                        class="rounded-2xl border border-brand-border bg-brand-surface/35 p-4 ring-1 ring-black/[0.03] sm:p-5"
                                        data-wf-loc-root
                                        data-map-only="true"
                                        data-lazy-map="true"
                                        data-search-url="{{ route('admin.workforce.geocode.search') }}"
                                        data-reverse-url="{{ route('admin.workforce.geocode.reverse') }}"
                                        data-default-lat="{{ $hasCoords ? $loc->latitude : $mapDefaultLat }}"
                                        data-default-lng="{{ $hasCoords ? $loc->longitude : $mapDefaultLng }}"
                                        data-default-zoom="{{ $hasCoords ? '16' : $mapDefaultZoom }}"
                                    >
                                        <div class="mt-4 space-y-3">
                                            <div class="{{ $wfGrid }}">
                                                <label for="loc-edit-address-{{ $loc->id }}" class="{{ $lbl }} sm:pt-2.5">Address</label>
                                                <div class="relative">
                                                    <input
                                                        id="loc-edit-address-{{ $loc->id }}"
                                                        name="address"
                                                        type="text"
                                                        maxlength="2000"
                                                        value="{{ $loc->address }}"
                                                        data-wf-address
                                                        autocomplete="off"
                                                        class="{{ $in }} pe-9"
                                                        placeholder="Start typing — pick a suggestion to move the pin"
                                                    />
                                                    <button
                                                        type="button"
                                                        data-wf-clear-address
                                                        class="absolute right-1 top-1/2 z-10 hidden -translate-y-1/2 rounded-md p-1 text-brand-text-secondary/80 transition hover:bg-brand-surface hover:text-brand-text focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
                                                        aria-label="Clear address"
                                                    >
                                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                    <div
                                                        data-wf-address-suggestions
                                                        class="absolute left-0 right-0 top-full z-30 mt-1 max-h-52 overflow-auto rounded-xl border border-brand-border bg-white py-1 shadow-lg ring-1 ring-black/[0.06] hidden"
                                                        role="listbox"
                                                        aria-label="Address suggestions"
                                                    ></div>
                                                </div>
                                            </div>
                                            <p
                                                data-wf-geocode-status
                                                class="flex min-h-[1.25rem] items-start gap-2 text-xs leading-relaxed text-brand-text-secondary"
                                            ></p>
                                            <div
                                                class="relative overflow-hidden rounded-xl border border-brand-border bg-white shadow-inner ring-1 ring-black/[0.04]"
                                                data-wf-map-wrap
                                            >
                                                <div
                                                    class="pointer-events-none absolute inset-0 z-[1000] hidden items-center justify-center bg-white/80 backdrop-blur-[1px]"
                                                    data-wf-map-loader
                                                    aria-live="polite"
                                                >
                                                    <div class="flex max-w-xs flex-col items-center gap-3 rounded-xl border border-brand-border/80 bg-white/95 px-5 py-4 text-center shadow-lg ring-1 ring-black/[0.04]">
                                                        <span
                                                            class="h-8 w-8 shrink-0 animate-spin rounded-full border-2 border-brand-primary/25 border-t-brand-primary"
                                                            data-wf-map-loader-spin
                                                            aria-hidden="true"
                                                        ></span>
                                                        <p class="text-sm font-medium text-brand-text" data-wf-map-loader-text>Preparing map…</p>
                                                    </div>
                                                </div>
                                                <div data-wf-map class="h-[min(18rem,42vh)] w-full min-h-[200px] bg-brand-surface"></div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <button type="button" data-wf-clear-pin class="rounded-lg border border-brand-border bg-white px-3 py-1.5 text-xs font-semibold text-brand-text shadow-sm transition hover:bg-brand-surface">
                                                    Clear pin
                                                </button>
                                                <span class="text-xs text-brand-text-secondary">Click map to place pin · drag to adjust</span>
                                            </div>
                                            <div class="{{ $wfGrid }}">
                                                <span class="{{ $lbl }} sm:pt-2.5">Coordinates</span>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Latitude</span>
                                                        <input type="hidden" name="latitude" value="{{ $loc->latitude }}" data-wf-lat />
                                                        <input type="text" readonly class="{{ $in }} cursor-default bg-brand-surface/80 font-mono text-xs" value="{{ $loc->latitude }}" data-wf-lat-display tabindex="-1" />
                                                    </div>
                                                    <div>
                                                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Longitude</span>
                                                        <input type="hidden" name="longitude" value="{{ $loc->longitude }}" data-wf-lng />
                                                        <input type="text" readonly class="{{ $in }} cursor-default bg-brand-surface/80 font-mono text-xs" value="{{ $loc->longitude }}" data-wf-lng-display tabindex="-1" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="{{ $wfGrid }}">
                                        <label for="loc-edit-notes-{{ $loc->id }}" class="{{ $lbl }} sm:pt-2.5">Notes</label>
                                        <textarea id="loc-edit-notes-{{ $loc->id }}" name="location_notes" rows="2" maxlength="2000" class="{{ $in }} min-h-[4.5rem] resize-y" placeholder="Parking, gate code, site contact…">{{ $loc->notes }}</textarea>
                                    </div>
                                    <div class="flex flex-wrap justify-end gap-2 border-t border-brand-border pt-4">
                                        <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                                            Save changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-brand-border bg-white/60 px-6 py-12 text-center">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-surface text-brand-text-secondary/80" aria-hidden="true">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                        </span>
                        <p class="mt-4 text-sm font-semibold text-brand-text">No work locations yet</p>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        @if ($section === 'shifts')
        {{-- Shifts --}}
        <section class="flex min-h-[32rem] flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="shrink-0 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-6 py-5 sm:px-7">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold tracking-tight text-brand-text">Shifts</h2>
                    </div>
                </div>
            </header>
            <div class="shrink-0 border-b border-brand-border px-6 py-6 sm:px-7">
                <form method="post" action="{{ route('admin.workforce.shifts.store') }}" class="space-y-4">
                    @csrf
                    <div class="{{ $row }}">
                        <label for="shift-name" class="{{ $lbl }}">Name</label>
                        <input id="shift-name" name="shift_name" required maxlength="160" value="{{ old('shift_name') }}" class="{{ $in }}" placeholder="e.g. Morning" />
                    </div>
                    <div class="{{ $row }}">
                        <span class="{{ $lbl }}">Hours</span>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="shift-start" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Start</label>
                                <input id="shift-start" name="shift_start_time" type="time" required value="{{ old('shift_start_time') }}" class="{{ $in }}" />
                            </div>
                            <div>
                                <label for="shift-end" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">End</label>
                                <input id="shift-end" name="shift_end_time" type="time" required value="{{ old('shift_end_time') }}" class="{{ $in }}" />
                            </div>
                        </div>
                    </div>
                    <div class="{{ $row }}">
                        <span class="{{ $lbl }}">Days</span>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($shiftDaysMap as $dayKey => $dayLabel)
                                <label class="{{ $shiftDayLabel }}">
                                    <span class="relative z-0">{{ $dayLabel }}</span>
                                    <input type="checkbox" name="shift_days[]" value="{{ $dayKey }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" @checked(in_array($dayKey, old('shift_days', []), true)) />
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="{{ $row }}">
                        <div class="sm:col-span-1">
                            @include('admin.partials.shift-breaks-fields', [
                                'breaks' => old('shift_breaks', [['label' => '', 'minutes' => '', 'paid' => false]]),
                                'lbl' => $lbl,
                                'in' => $in,
                                'fieldId' => 'shift-breaks-create',
                            ])
                        </div>
                    </div>
                    <div class="{{ $row }}">
                        <label for="shift-notes" class="{{ $lbl }}">Notes</label>
                        <textarea id="shift-notes" name="shift_notes" rows="2" maxlength="2000" class="{{ $in }} min-h-[4.5rem] resize-y" placeholder="Optional roster notes">{{ old('shift_notes') }}</textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-3 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                        Add shift
                    </button>
                </form>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-gradient-to-b from-brand-surface/25 to-transparent px-4 py-4 sm:px-6 sm:py-5">
                @if ($shifts->isNotEmpty())
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-[11px] font-bold uppercase tracking-[0.12em] text-brand-text-secondary">Saved shifts</h3>
                        <span class="rounded-full bg-brand-primary/12 px-2.5 py-0.5 text-[10px] font-bold tabular-nums text-brand-primary">{{ $shifts->count() }}</span>
                    </div>
                @endif
                @forelse ($shifts as $sh)
                    @php
                        $shiftDayKeys = is_array($sh->shift_days ?? null) ? $sh->shift_days : [];
                        $allDaysSelected = $shiftDayKeys === [];
                    @endphp
                    <article class="{{ $savedCard }} mb-4 last:mb-0">
                        <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-brand-primary to-brand-primary/50 opacity-90" aria-hidden="true"></div>
                        <div class="relative flex gap-4 pl-2">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-primary/12 text-brand-primary shadow-inner ring-1 ring-brand-primary/10">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div class="min-w-0 flex-1 text-sm">
                                <div class="flex flex-wrap items-center gap-2 gap-y-1">
                                    <h3 class="text-base font-bold leading-snug text-brand-text">{{ $sh->name }}</h3>
                                    <span class="inline-flex items-center rounded-lg border border-brand-border/80 bg-white/80 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-brand-text-secondary shadow-sm">{{ $fmtShift($sh) }}</span>
                                </div>
                                <p class="mt-1 text-[11px] font-medium text-brand-text-secondary">Shift · ID {{ $sh->id }}</p>
                                <div class="mt-3">
                                    <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Days</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($shiftDaysMap as $dayKey => $dayLabel)
                                            @php
                                                $on = $allDaysSelected || in_array($dayKey, $shiftDayKeys, true);
                                            @endphp
                                            <span class="min-w-[2.25rem] rounded-lg px-2 py-1 text-center text-[10px] font-bold transition {{ $on ? 'bg-brand-primary text-white shadow-sm ring-1 ring-brand-primary/30' : 'bg-white/80 text-brand-text-secondary/45 ring-1 ring-brand-border/70' }}">{{ $dayLabel }}</span>
                                        @endforeach
                                    </div>
                                    @if ($allDaysSelected)
                                        <p class="mt-1.5 text-[11px] font-medium text-brand-text-secondary">Applies every day</p>
                                    @endif
                                </div>
                                @php
                                    $structuredBreaks = \App\Support\ShiftBreaks::normalize($sh->breaks ?? null);
                                @endphp
                                @if ($structuredBreaks !== [])
                                    <div class="mt-3">
                                        <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Breaks</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($structuredBreaks as $break)
                                                <span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-semibold ring-1 {{ ! empty($break['paid']) ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-amber-50 text-amber-900 ring-amber-200' }}">
                                                    <span class="tabular-nums">{{ $break['minutes'] }}m</span>
                                                    <span class="opacity-70">{{ ! empty($break['paid']) ? 'Paid' : 'Unpaid' }}</span>
                                                    <span>{{ $break['label'] }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @elseif ($sh->breaks_summary)
                                    <p class="mt-3 flex items-start gap-2 rounded-lg bg-white/60 px-2.5 py-2 text-xs leading-relaxed text-brand-text-secondary ring-1 ring-brand-border/50">
                                        <span class="mt-0.5 shrink-0 text-brand-primary" aria-hidden="true">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </span>
                                        <span>{{ $sh->breaks_summary }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>
                        <details class="group/shift-edit relative mt-4 overflow-hidden rounded-xl border border-brand-border/90 bg-white/85 shadow-sm ring-1 ring-black/[0.03] open:shadow-md">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3.5 text-xs font-bold uppercase tracking-wide text-brand-primary transition hover:bg-white/60 [&::-webkit-details-marker]:hidden">
                                <span>Adjust timing &amp; days</span>
                                <svg class="size-4 shrink-0 text-brand-primary/70 transition group-open/shift-edit:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <div class="border-t border-brand-border px-4 py-4 sm:px-5">
                            <form method="post" action="{{ route('admin.workforce.shifts.update', ['shift' => $sh->id]) }}" class="space-y-3">
                                @csrf
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div class="sm:col-span-1">
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Name</label>
                                        <input name="shift_name" required maxlength="160" value="{{ old('shift_name', $sh->name) }}" class="{{ $in }}" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Start</label>
                                        <input name="shift_start_time" type="time" required value="{{ old('shift_start_time', optional($sh->start_time)->format('H:i')) }}" class="{{ $in }}" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">End</label>
                                        <input name="shift_end_time" type="time" required value="{{ old('shift_end_time', optional($sh->end_time)->format('H:i')) }}" class="{{ $in }}" />
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Days</label>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-7">
                                        @php
                                            $selectedDays = is_array(old('shift_days')) ? old('shift_days') : ($sh->shift_days ?? []);
                                        @endphp
                                        @foreach ($shiftDaysMap as $dayKey => $dayLabel)
                                            <label class="{{ $shiftDayLabel }}">
                                                <span class="relative z-0">{{ $dayLabel }}</span>
                                                <input type="checkbox" name="shift_days[]" value="{{ $dayKey }}" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" @checked(in_array($dayKey, $selectedDays, true)) />
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    @include('admin.partials.shift-breaks-fields', [
                                        'breaks' => old('shift_breaks', \App\Support\ShiftBreaks::normalize($sh->breaks ?? null)),
                                        'in' => $in,
                                        'fieldId' => 'shift-breaks-'.$sh->id,
                                    ])
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-brand-text-secondary">Notes</label>
                                        <input name="shift_notes" maxlength="2000" value="{{ old('shift_notes', $sh->notes) }}" class="{{ $in }}" />
                                    </div>
                                </div>
                                <button type="submit" class="rounded-xl bg-brand-primary px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-brand-primary/15 transition hover:bg-brand-primary-dark">Save shift changes</button>
                            </form>
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-brand-border bg-white/60 px-6 py-12 text-center">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-surface text-brand-text-secondary/80" aria-hidden="true">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                        <p class="mt-4 text-sm font-semibold text-brand-text">No shifts yet</p>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        @if ($section === 'leave-types')
        {{-- Leave types --}}
        @php
            $leaveIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
            $toggleLabel = 'flex cursor-pointer items-center gap-2.5 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm font-semibold text-brand-text shadow-sm transition hover:border-brand-primary/40 [&:has(input:checked)]:border-brand-primary [&:has(input:checked)]:bg-brand-primary/[0.06]';
            $chip = 'inline-flex items-center gap-1 rounded-lg border border-brand-border/80 bg-white/80 px-2 py-0.5 text-[11px] font-semibold text-brand-text-secondary shadow-sm';
        @endphp
        <section class="flex min-h-[32rem] flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="shrink-0 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-6 py-5 sm:px-7">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">{!! $leaveIcon !!}</svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold tracking-tight text-brand-text">Leave types</h2>
                    </div>
                </div>
            </header>
            <div class="shrink-0 border-b border-brand-border px-6 py-6 sm:px-7">
                <form method="post" action="{{ route('admin.workforce.leave-types.store') }}" class="space-y-4">
                    @csrf
                    <div class="{{ $row }}">
                        <label for="leave-type-name" class="{{ $lbl }}">Name</label>
                        <input id="leave-type-name" name="leave_type_name" required maxlength="160" value="{{ old('leave_type_name') }}" class="{{ $in }}" placeholder="e.g. Annual leave" autocomplete="off" />
                    </div>
                    <div class="{{ $row }}">
                        <label for="leave-type-code" class="{{ $lbl }}">Code</label>
                        <div>
                            <input id="leave-type-code" name="leave_type_code" maxlength="32" value="{{ old('leave_type_code') }}" class="{{ $in }} font-mono" placeholder="Optional — auto-generated from name" />
                        </div>
                        @error('leave_type_code')
                            <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="{{ $toggleLabel }}">
                            <input type="checkbox" name="leave_type_is_paid" value="1" class="size-4 rounded border-brand-border text-brand-primary focus:ring-brand-primary/30" @checked(old('leave_type_is_paid', '1')) />
                            <span>Paid leave</span>
                        </label>
                        <label class="{{ $toggleLabel }}">
                            <input type="checkbox" name="leave_type_requires_approval" value="1" class="size-4 rounded border-brand-border text-brand-primary focus:ring-brand-primary/30" @checked(old('leave_type_requires_approval', '1')) />
                            <span>Requires approval</span>
                        </label>
                    </div>
                    <div class="{{ $row }}">
                        <label for="leave-type-annual-hours" class="{{ $lbl }}">Annual entitlement (hours)</label>
                        <input id="leave-type-annual-hours" name="leave_type_annual_hours" type="number" step="0.01" min="0" max="9999.99" value="{{ old('leave_type_annual_hours') }}" class="{{ $in }}" placeholder="Optional, e.g. 152" />
                    </div>
                    <div class="{{ $row }}">
                        <label for="leave-type-notes" class="{{ $lbl }}">Notes</label>
                        <textarea id="leave-type-notes" name="leave_type_notes" rows="2" maxlength="500" class="{{ $in }} min-h-[4.5rem] resize-y" placeholder="Optional eligibility or policy notes">{{ old('leave_type_notes') }}</textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-3 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                        Add leave type
                    </button>
                </form>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-gradient-to-b from-brand-surface/25 to-transparent px-4 py-4 sm:px-6 sm:py-5">
                @if ($leaveTypes->isNotEmpty())
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-[11px] font-bold uppercase tracking-[0.12em] text-brand-text-secondary">Saved leave types</h3>
                        <span class="rounded-full bg-brand-primary/12 px-2.5 py-0.5 text-[10px] font-bold tabular-nums text-brand-primary">{{ $leaveTypes->count() }}</span>
                    </div>
                @endif
                @forelse ($leaveTypes as $lt)
                    <article class="{{ $savedCard }} mb-4 last:mb-0 {{ $lt->is_active ? '' : 'opacity-70' }}">
                        <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-brand-primary to-brand-primary/50 opacity-90" aria-hidden="true"></div>
                        <div class="relative flex gap-4 pl-2">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-primary/12 text-brand-primary shadow-inner ring-1 ring-brand-primary/10">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">{!! $leaveIcon !!}</svg>
                            </div>
                            <div class="min-w-0 flex-1 text-sm">
                                <div class="flex flex-wrap items-center gap-2 gap-y-1">
                                    <h3 class="text-base font-bold leading-snug text-brand-text">{{ $lt->name }}</h3>
                                    <span class="inline-flex items-center rounded-lg border border-brand-border/80 bg-white/80 px-2 py-0.5 font-mono text-[11px] font-semibold uppercase tracking-wide text-brand-text-secondary shadow-sm">{{ $lt->code }}</span>
                                    @unless ($lt->is_active)
                                        <span class="inline-flex items-center rounded-lg bg-brand-text-secondary/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-text-secondary">Inactive</span>
                                    @endunless
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 gap-y-1">
                                    <span class="{{ $chip }} {{ $lt->is_paid ? 'text-emerald-700' : 'text-brand-text-secondary' }}">{{ $lt->is_paid ? 'Paid' : 'Unpaid' }}</span>
                                    @if ($lt->default_annual_hours !== null)
                                        <span class="{{ $chip }} tabular-nums">{{ rtrim(rtrim(number_format((float) $lt->default_annual_hours, 2), '0'), '.') }} h / yr</span>
                                    @endif
                                    <span class="{{ $chip }}">{{ $lt->requires_approval ? 'Approval required' : 'No approval' }}</span>
                                </div>
                                <p class="mt-1 text-[11px] font-medium text-brand-text-secondary">Leave type · ID {{ $lt->id }}</p>
                                @if ($lt->notes)
                                    <p class="mt-2 text-xs italic leading-relaxed text-brand-text-secondary">{{ $lt->notes }}</p>
                                @endif
                            </div>
                        </div>
                        <details class="group/leave-edit relative mt-4 overflow-hidden rounded-xl border border-brand-border/90 bg-white/85 shadow-sm ring-1 ring-black/[0.03] open:shadow-md">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3.5 text-xs font-bold uppercase tracking-wide text-brand-primary transition hover:bg-white/60 [&::-webkit-details-marker]:hidden">
                                <span>Edit leave type</span>
                                <svg class="size-4 shrink-0 text-brand-primary/70 transition group-open/leave-edit:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </summary>
                            <div class="border-t border-brand-border bg-white/90 px-4 py-5 sm:px-6">
                                <form method="post" action="{{ route('admin.workforce.leave-types.update', ['leaveType' => $lt->id]) }}" class="space-y-4">
                                    @csrf
                                    <div class="{{ $wfGrid }}">
                                        <label for="lt-edit-name-{{ $lt->id }}" class="{{ $lbl }} sm:pt-2.5">Name</label>
                                        <input id="lt-edit-name-{{ $lt->id }}" name="leave_type_name" required maxlength="160" value="{{ $lt->name }}" class="{{ $in }}" autocomplete="off" />
                                    </div>
                                    <div class="{{ $wfGrid }}">
                                        <span class="{{ $lbl }} sm:pt-2.5">Options</span>
                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <label class="{{ $toggleLabel }}">
                                                <input type="checkbox" name="leave_type_is_paid" value="1" class="size-4 rounded border-brand-border text-brand-primary focus:ring-brand-primary/30" @checked($lt->is_paid) />
                                                <span>Paid</span>
                                            </label>
                                            <label class="{{ $toggleLabel }}">
                                                <input type="checkbox" name="leave_type_requires_approval" value="1" class="size-4 rounded border-brand-border text-brand-primary focus:ring-brand-primary/30" @checked($lt->requires_approval) />
                                                <span>Approval</span>
                                            </label>
                                            <label class="{{ $toggleLabel }}">
                                                <input type="checkbox" name="leave_type_is_active" value="1" class="size-4 rounded border-brand-border text-brand-primary focus:ring-brand-primary/30" @checked($lt->is_active) />
                                                <span>Active</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="{{ $wfGrid }}">
                                        <label for="lt-edit-hours-{{ $lt->id }}" class="{{ $lbl }} sm:pt-2.5">Entitlement (h/yr)</label>
                                        <input id="lt-edit-hours-{{ $lt->id }}" name="leave_type_annual_hours" type="number" step="0.01" min="0" max="9999.99" value="{{ $lt->default_annual_hours !== null ? rtrim(rtrim(number_format((float) $lt->default_annual_hours, 2, '.', ''), '0'), '.') : '' }}" class="{{ $in }}" placeholder="Hours / year" />
                                    </div>
                                    <div class="{{ $wfGrid }}">
                                        <label for="lt-edit-notes-{{ $lt->id }}" class="{{ $lbl }} sm:pt-2.5">Notes</label>
                                        <textarea id="lt-edit-notes-{{ $lt->id }}" name="leave_type_notes" rows="2" maxlength="500" class="{{ $in }} min-h-[4.5rem] resize-y" placeholder="Optional policy notes">{{ $lt->notes }}</textarea>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-brand-border pt-4">
                                        <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                                            Save changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-brand-border bg-white/60 px-6 py-12 text-center">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-surface text-brand-text-secondary/80" aria-hidden="true">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25">{!! $leaveIcon !!}</svg>
                        </span>
                        <p class="mt-4 text-sm font-semibold text-brand-text">No leave types yet</p>
                    </div>
                @endforelse
            </div>
        </section>
        @endif
    </div>
@endsection
