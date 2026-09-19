@extends('layouts.admin')

@section('title', 'Job titles')
@section('heading', 'Organization setup')
@section('subheading', $company->name)

@section('content')
@php
    $count = $jobTitles->count();
    $countLabel = $count.' '.($count === 1 ? 'Job title' : 'Job titles');
    $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
    $lbl = 'text-xs font-semibold uppercase tracking-wide text-brand-label';
    $primaryBtn = 'inline-flex items-center gap-1.5 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark';
    $quietBtn = 'inline-flex items-center justify-center rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary shadow-sm transition hover:border-brand-primary/30 hover:bg-brand-surface hover:text-brand-primary';
@endphp

<div class="mx-auto max-w-6xl overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
    <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-5 sm:px-7">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary/10 text-brand-primary">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-base font-bold tracking-tight text-brand-text">Job titles</h2>
            </div>
        </div>
    </header>

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-brand-border px-5 sm:px-7">
        <a
            href="{{ route('admin.workforce.job-titles') }}"
            class="relative -mb-px border-b-2 px-3 py-3.5 text-xs font-bold uppercase tracking-[0.12em] transition {{ ! $archived ? 'border-brand-primary text-brand-primary' : 'border-transparent text-brand-text-secondary hover:text-brand-primary' }}"
        >Job titles</a>
        <a
            href="{{ route('admin.workforce.job-titles', ['archived' => 1]) }}"
            class="relative -mb-px border-b-2 px-3 py-3.5 text-xs font-bold uppercase tracking-[0.12em] transition {{ $archived ? 'border-brand-primary text-brand-primary' : 'border-transparent text-brand-text-secondary hover:text-brand-primary' }}"
        >Archived</a>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 border-b border-brand-border/80 bg-gradient-to-b from-brand-surface/40 to-transparent px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-bold text-brand-primary">{{ $archived ? ($count.' Archived') : $countLabel }}</h3>
            @if ($count > 0 && ! $archived)
                <span class="rounded-full bg-brand-primary/12 px-2.5 py-0.5 text-[10px] font-bold tabular-nums text-brand-primary">{{ $count }}</span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <input
                    type="search"
                    data-jt-filter
                    placeholder="Search"
                    class="w-44 rounded-xl border border-brand-border bg-white py-2.5 pl-3 pr-9 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20 sm:w-56"
                    autocomplete="off"
                    aria-label="Search job titles"
                >
                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-brand-icon">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                </span>
            </div>
            @unless ($archived)
                <button type="button" data-jt-add-open class="{{ $primaryBtn }}">
                    Add job title
                    <span class="text-base leading-none" aria-hidden="true">+</span>
                </button>
            @endunless
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-brand-surface/80 text-[11px] font-bold uppercase tracking-[0.12em] text-brand-label">
                <tr>
                    <th class="w-12 px-5 py-3.5 sm:px-7" scope="col"><span class="sr-only">Color</span></th>
                    <th class="px-3 py-3.5" scope="col">Name</th>
                    <th class="px-3 py-3.5" scope="col">Wage</th>
                    <th class="px-3 py-3.5" scope="col">No. of employees</th>
                    <th class="w-20 px-5 py-3.5 text-right sm:px-7" scope="col">Options</th>
                </tr>
            </thead>
            <tbody data-jt-list class="divide-y divide-brand-border/80">
                @forelse ($jobTitles as $jt)
                    @php
                        $empCount = (int) ($jt->employees_count ?? 0);
                        $empLabel = $empCount === 1 ? '1 Employee' : $empCount.' Employees';
                        $wageLabel = $jt->hasHourlyWage() ? '$'.$jt->formattedWage() : null;
                        $search = strtolower(trim($jt->name.' '.$empLabel.' '.($wageLabel ?? '')));
                    @endphp
                    <tr class="group transition hover:bg-brand-surface/50" data-jt-row data-search="{{ $search }}">
                        <td class="px-5 py-4 sm:px-7">
                            <span class="inline-block size-3.5 rounded-full shadow-sm ring-2 ring-white" style="background-color: {{ $jt->accentColor() }}" aria-hidden="true"></span>
                        </td>
                        <td class="px-3 py-4">
                            <a
                                href="{{ route('admin.workforce.job-titles.show', ['jobTitle' => $jt->id]) }}"
                                class="font-semibold transition hover:underline"
                                style="color: {{ $jt->accentColor() }}"
                            >{{ $jt->name }}</a>
                        </td>
                        <td class="px-3 py-4 tabular-nums text-brand-text-secondary">{{ $wageLabel ?? '—' }}</td>
                        <td class="px-3 py-4 text-brand-text-secondary">{{ $empLabel }}</td>
                        <td class="px-5 py-4 text-right sm:px-7">
                            <details class="relative inline-block text-left" data-jt-menu>
                                <summary class="inline-flex size-9 cursor-pointer list-none items-center justify-center rounded-xl text-brand-icon transition hover:bg-brand-primary/10 hover:text-brand-primary [&::-webkit-details-marker]:hidden" aria-label="Options for {{ $jt->name }}">
                                    <svg class="size-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </summary>
                                <div class="absolute right-0 z-30 mt-1.5 w-48 overflow-hidden rounded-xl border border-brand-border bg-white py-1.5 shadow-lg ring-1 ring-black/[0.04]">
                                    @if ($archived)
                                        <form method="post" action="{{ route('admin.workforce.job-titles.restore', ['jobTitle' => $jt->id]) }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-3.5 py-2.5 text-left text-sm font-medium text-brand-text transition hover:bg-brand-surface">Restore</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.workforce.job-titles.show', ['jobTitle' => $jt->id, 'tab' => 'settings']) }}" class="block px-3.5 py-2.5 text-sm font-medium text-brand-text transition hover:bg-brand-surface">Edit</a>
                                        <a href="{{ route('admin.workforce.job-titles.show', ['jobTitle' => $jt->id, 'tab' => 'wages']) }}" class="block px-3.5 py-2.5 text-sm font-medium text-brand-text transition hover:bg-brand-surface">Edit wages</a>
                                        <div class="my-1 border-t border-brand-border/80"></div>
                                        <form method="post" action="{{ route('admin.workforce.job-titles.archive', ['jobTitle' => $jt->id]) }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-3.5 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50">Archive</button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-14 text-center sm:px-7">
                            <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-surface text-brand-text-secondary/80" aria-hidden="true">
                                <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                            </span>
                            <p class="mt-4 text-sm font-semibold text-brand-text">
                                {{ $archived ? 'No archived job titles.' : 'No job titles yet' }}
                            </p>
                            @unless ($archived)
                                <button type="button" data-jt-add-open class="{{ $primaryBtn }} mt-5">Add job title <span aria-hidden="true">+</span></button>
                            @endunless
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p data-jt-empty class="hidden px-5 py-10 text-center text-sm text-brand-text-secondary sm:px-7">No job titles match your search.</p>
    </div>
</div>

@unless ($archived)
@php
    $defaultEffective = old('wage_effective_from', (string) config('payroll.default_rates_effective_from', now()->toDateString()));
    $selectedAddColor = strtolower((string) old('color', \App\Models\JobTitle::colorPalette()[0]));
    $oldEmployeeIds = array_map('strval', (array) old('employee_ids', []));
    $addEmployeeRows = collect($employees ?? [])->map(function ($employee) use ($oldEmployeeIds) {
        $empName = trim((string) ($employee->full_legal_name ?: trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))));
        if ($empName === '') {
            $empName = $employee->email ?: 'Employee #'.$employee->id;
        }
        $parts = preg_split('/\s+/', $empName) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if ($initials === '') {
            $initials = '#';
        }

        return [
            'id' => (string) $employee->id,
            'name' => $empName,
            'initials' => $initials,
            'selected' => in_array((string) $employee->id, $oldEmployeeIds, true),
        ];
    });
@endphp
<div data-jt-add-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-primary-dark/50 p-4" role="dialog" aria-modal="true" aria-labelledby="jt-add-title">
    <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
        <header class="shrink-0 border-b border-brand-border border-l-4 border-l-brand-primary bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Job titles</p>
                    <h3 id="jt-add-title" class="mt-1 text-lg font-bold text-brand-text">Add job title</h3>
                </div>
                <button type="button" data-jt-add-close class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm hover:bg-brand-surface hover:text-brand-text" aria-label="Close">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-brand-border bg-white px-3 py-2.5 shadow-sm">
                <span class="size-3.5 shrink-0 rounded-full shadow-sm ring-2 ring-white" style="background-color: {{ $selectedAddColor }}" data-jt-add-preview aria-hidden="true"></span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold" style="color: {{ $selectedAddColor }}" data-jt-add-summary-name>New job title</p>
                    <p class="mt-0.5 truncate text-xs text-brand-text-secondary" data-jt-add-summary-meta>$0.00 / hr</p>
                </div>
            </div>
        </header>

        <form method="post" action="{{ route('admin.workforce.job-titles.store') }}" class="flex min-h-0 flex-1 flex-col">
            @csrf
            <div class="min-h-0 flex-1 space-y-5 overflow-visible px-5 py-5">
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Name</span>
                    <input id="job-title-name" name="job_title_name" required maxlength="160" value="{{ old('job_title_name') }}" class="{{ $in }}" data-jt-add-name autocomplete="off" />
                </label>

                <div>
                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-brand-label">Color</p>
                    <input type="hidden" name="color" value="{{ $selectedAddColor }}" data-jt-add-color>
                    <div class="flex flex-nowrap items-center justify-between gap-1" role="listbox" aria-label="Color">
                        @foreach (\App\Models\JobTitle::colorPalette() as $swatch)
                            @php $swatch = strtolower($swatch); $isSelected = $selectedAddColor === $swatch; @endphp
                            <button
                                type="button"
                                data-jt-add-swatch
                                data-color="{{ $swatch }}"
                                role="option"
                                aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                                class="relative flex size-7 shrink-0 items-center justify-center rounded-full border border-black/10 transition hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
                                style="background-color: {{ $swatch }};{{ $isSelected ? ' box-shadow: 0 0 0 2px #fff, 0 0 0 3px '.$swatch.';' : ' box-shadow: 0 1px 2px rgba(0,0,0,0.08);' }}"
                            >
                                <svg data-jt-add-check class="size-3 text-white drop-shadow-sm {{ $isSelected ? '' : 'hidden' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span class="sr-only">{{ $swatch }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Wage</span>
                        <div class="inline-flex items-stretch overflow-hidden rounded-xl border border-brand-border bg-white shadow-sm focus-within:border-brand-primary focus-within:ring-2 focus-within:ring-brand-primary/20">
                            <span class="flex items-center bg-brand-surface/60 px-2.5 text-sm font-semibold text-brand-text-secondary" aria-hidden="true">$</span>
                            <input id="job-title-hourly-wage" type="number" step="0.01" min="0" max="9999.99" name="hourly_wage" required value="{{ old('hourly_wage') }}" placeholder="0.00" class="w-full min-w-0 border-0 bg-transparent py-2.5 pl-2 pr-1 text-sm tabular-nums text-brand-text outline-none focus:ring-0" inputmode="decimal" data-jt-add-wage />
                            <span class="flex shrink-0 items-center pr-2.5 text-xs font-medium text-brand-text-secondary">/ hr</span>
                        </div>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Effective from</span>
                        <input id="job-title-effective-from" type="date" name="wage_effective_from" required value="{{ $defaultEffective }}" class="{{ $in }}" data-jt-add-effective />
                    </label>
                </div>

                <div class="relative" data-jt-emp-picker>
                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employees</p>
                    <button type="button" data-jt-emp-toggle class="flex w-full items-center gap-2 rounded-xl border border-brand-border bg-white px-3 py-2.5 text-left text-sm text-brand-text shadow-sm transition hover:border-brand-primary/30 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20" aria-expanded="false" aria-haspopup="listbox">
                        <span class="min-w-0 flex-1 truncate text-brand-text-secondary" data-jt-emp-label>Select employees</span>
                        <svg class="size-4 shrink-0 text-brand-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div data-jt-emp-menu class="absolute bottom-full left-0 right-0 z-30 mb-1.5 hidden overflow-hidden rounded-xl border border-brand-border bg-white shadow-xl ring-1 ring-black/[0.06]" role="listbox" aria-multiselectable="true">
                        <div class="relative border-b border-brand-border/80">
                            <input type="search" data-jt-emp-search class="w-full border-0 bg-transparent py-2.5 pl-3 pr-9 text-sm text-brand-text outline-none placeholder:text-brand-text-secondary/70 focus:ring-0" placeholder="Search" autocomplete="off" aria-label="Search employees" />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-brand-icon">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                            </span>
                        </div>
                        <div class="max-h-48 overflow-y-auto py-1" data-jt-emp-list>
                            @forelse ($addEmployeeRows as $employee)
                                <label data-jt-emp-option data-name="{{ $employee['name'] }}" class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-brand-text transition hover:bg-brand-primary/10 {{ $employee['selected'] ? 'bg-brand-primary/10 font-semibold text-brand-primary' : '' }}">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $employee['id'] }}" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary/30" @checked($employee['selected'])>
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-[10px] font-bold text-brand-primary">{{ $employee['initials'] }}</span>
                                    <span class="min-w-0 flex-1 truncate">{{ $employee['name'] }}</span>
                                </label>
                            @empty
                                <p class="px-3 py-4 text-center text-sm text-brand-text-secondary">No active employees.</p>
                            @endforelse
                        </div>
                        <p data-jt-emp-empty class="hidden px-3 py-4 text-center text-sm text-brand-text-secondary">No employees match your search.</p>
                    </div>
                </div>

                @error('hourly_wage')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('wage_effective_from')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex shrink-0 justify-end gap-2 border-t border-brand-border bg-brand-surface/30 px-5 py-4">
                <button type="button" data-jt-add-close class="{{ $quietBtn }}">Cancel</button>
                <button type="submit" class="{{ $primaryBtn }}">Add job title</button>
            </div>
        </form>
    </div>
</div>
@endunless

@push('scripts')
<script>
(function () {
    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }
    var input = document.querySelector('[data-jt-filter]');
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-jt-row]'));
    var empty = document.querySelector('[data-jt-empty]');
    var list = document.querySelector('[data-jt-list]');
    if (input) {
        input.addEventListener('input', function () {
            var q = normalize(input.value);
            var shown = 0;
            rows.forEach(function (row) {
                var match = q === '' || normalize(row.getAttribute('data-search')).indexOf(q) !== -1;
                row.classList.toggle('hidden', !match);
                if (match) shown += 1;
            });
            if (empty) empty.classList.toggle('hidden', shown !== 0 || rows.length === 0);
            if (list) list.classList.toggle('hidden', shown === 0 && q !== '' && rows.length > 0);
        });
    }

    document.addEventListener('click', function (event) {
        document.querySelectorAll('[data-jt-menu][open]').forEach(function (menu) {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });

    var modal = document.querySelector('[data-jt-add-modal]');
    function openModal() {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        var first = modal.querySelector('input,select');
        if (first) first.focus();
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    document.querySelectorAll('[data-jt-add-open]').forEach(function (btn) {
        btn.addEventListener('click', openModal);
    });
    document.querySelectorAll('[data-jt-add-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });
    }

    var addColor = document.querySelector('[data-jt-add-color]');
    var addPreview = document.querySelector('[data-jt-add-preview]');
    var addName = document.querySelector('[data-jt-add-name]');
    var summaryName = document.querySelector('[data-jt-add-summary-name]');
    var summaryMeta = document.querySelector('[data-jt-add-summary-meta]');
    var addWage = document.querySelector('[data-jt-add-wage]');
    var addEffective = document.querySelector('[data-jt-add-effective]');

    function applyAddColor(color) {
        if (addColor) addColor.value = color;
        if (addPreview) addPreview.style.backgroundColor = color;
        if (summaryName) summaryName.style.color = color;
        document.querySelectorAll('[data-jt-add-swatch]').forEach(function (other) {
            var otherColor = other.getAttribute('data-color');
            var selected = otherColor === color;
            var check = other.querySelector('[data-jt-add-check]');
            other.setAttribute('aria-selected', selected ? 'true' : 'false');
            other.style.boxShadow = selected
                ? '0 0 0 2px #fff, 0 0 0 4px ' + otherColor
                : '0 1px 2px rgba(0,0,0,0.08)';
            if (check) check.classList.toggle('hidden', !selected);
        });
    }

    document.querySelectorAll('[data-jt-add-swatch]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyAddColor(btn.getAttribute('data-color'));
        });
    });

    function refreshSummary() {
        var name = addName && addName.value.trim() ? addName.value.trim() : 'New job title';
        if (summaryName) summaryName.textContent = name;
        var wage = addWage && addWage.value !== '' ? Number(addWage.value) : 0;
        var wageLabel = '$' + (isFinite(wage) ? wage.toFixed(2) : '0.00') + ' / hr';
        var when = '';
        if (addEffective && addEffective.value) {
            var parts = addEffective.value.split('-');
            if (parts.length === 3) when = ' · from ' + parts[2] + '/' + parts[1] + '/' + parts[0];
        }
        if (summaryMeta) summaryMeta.textContent = wageLabel + when;
    }

    if (addName) addName.addEventListener('input', refreshSummary);
    if (addWage) addWage.addEventListener('input', refreshSummary);
    if (addEffective) addEffective.addEventListener('change', refreshSummary);
    refreshSummary();

    var picker = document.querySelector('[data-jt-emp-picker]');
    if (picker) {
        var toggle = picker.querySelector('[data-jt-emp-toggle]');
        var menu = picker.querySelector('[data-jt-emp-menu]');
        var search = picker.querySelector('[data-jt-emp-search]');
        var label = picker.querySelector('[data-jt-emp-label]');
        var options = Array.prototype.slice.call(picker.querySelectorAll('[data-jt-emp-option]'));
        var empty = picker.querySelector('[data-jt-emp-empty]');

        function setMenuOpen(open) {
            if (!menu || !toggle) return;
            menu.classList.toggle('hidden', !open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && search) search.focus();
        }

        function syncEmployees() {
            var names = [];
            var shown = 0;
            var q = normalize(search ? search.value : '');
            options.forEach(function (option) {
                var box = option.querySelector('input[type="checkbox"]');
                var selected = !!(box && box.checked);
                if (selected) names.push(option.getAttribute('data-name') || '');
                option.classList.toggle('bg-brand-primary/10', selected);
                option.classList.toggle('font-semibold', selected);
                option.classList.toggle('text-brand-primary', selected);
                var match = q === '' || normalize(option.getAttribute('data-name')).indexOf(q) !== -1;
                option.classList.toggle('hidden', !match);
                if (match) shown += 1;
            });
            if (label) {
                label.textContent = names.length ? names.join(', ') : 'Select employees';
                label.classList.toggle('text-brand-text-secondary', names.length === 0);
                label.classList.toggle('text-brand-text', names.length > 0);
            }
            if (empty) empty.classList.toggle('hidden', shown !== 0 || options.length === 0);
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                setMenuOpen(menu.classList.contains('hidden'));
            });
        }
        if (search) search.addEventListener('input', syncEmployees);
        options.forEach(function (option) {
            var box = option.querySelector('input[type="checkbox"]');
            if (box) box.addEventListener('change', syncEmployees);
        });
        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) setMenuOpen(false);
        });
        syncEmployees();
    }

    @if ($errors->any() && old('job_title_name') !== null)
        openModal();
    @endif
})();
</script>
@endpush
@endsection
