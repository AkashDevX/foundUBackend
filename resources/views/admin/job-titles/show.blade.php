@extends('layouts.admin')

@section('title', $jobTitle->name)
@section('heading', 'Organization setup')
@section('subheading', $company->name)

@section('content')
@php
    $in = 'w-full max-w-xs rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
    $lbl = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-brand-label';
    $moneyIn = 'w-28 rounded-xl border border-brand-border bg-white py-2.5 pl-7 pr-2.5 text-sm font-mono tabular-nums text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
    $empCount = (int) ($jobTitle->employees_count ?? $employees->count());
    $empLabel = $empCount === 1 ? '1 Employee' : $empCount.' Employees';
    $primaryBtn = 'inline-flex items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark';
    $navBtn = 'inline-flex items-center gap-1.5 rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-brand-text-secondary shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface hover:text-brand-primary';
    $iconBtn = 'inline-flex size-9 items-center justify-center rounded-xl border border-brand-border bg-white text-brand-icon shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface hover:text-brand-primary';
@endphp

<div class="mx-auto max-w-6xl overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
    {{-- Breadcrumb + actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4 sm:px-7">
        <nav class="flex items-center gap-2 text-sm">
            <a href="{{ route('admin.workforce.job-titles') }}" class="font-medium text-brand-text-secondary transition hover:text-brand-primary">Job titles</a>
            <span class="text-brand-icon" aria-hidden="true">›</span>
            <span class="inline-flex items-center gap-2 font-semibold text-brand-primary">
                <span class="size-2.5 rounded-full" style="background-color: {{ $jobTitle->accentColor() }}" aria-hidden="true"></span>
                {{ $jobTitle->name }}
            </span>
        </nav>
        <div class="flex items-center gap-1.5">
            <a href="{{ route('admin.workforce.job-titles') }}" class="{{ $navBtn }}">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back
            </a>
            @if ($prevJobTitleId)
                <a href="{{ route('admin.workforce.job-titles.show', ['jobTitle' => $prevJobTitleId, 'tab' => $tab]) }}" class="{{ $iconBtn }}" aria-label="Previous job title">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @else
                <span class="{{ $iconBtn }} opacity-40" aria-disabled="true"><svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></span>
            @endif
            @if ($nextJobTitleId)
                <a href="{{ route('admin.workforce.job-titles.show', ['jobTitle' => $nextJobTitleId, 'tab' => $tab]) }}" class="{{ $iconBtn }}" aria-label="Next job title">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="{{ $iconBtn }} opacity-40" aria-disabled="true"><svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>
            @endif
            <details class="relative" data-jt-menu>
                <summary class="{{ $iconBtn }} cursor-pointer list-none [&::-webkit-details-marker]:hidden" aria-label="More options">
                    <svg class="size-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                </summary>
                <div class="absolute right-0 z-30 mt-1.5 w-48 overflow-hidden rounded-xl border border-brand-border bg-white py-1.5 shadow-lg ring-1 ring-black/[0.04]">
                    <a href="{{ route('admin.workforce.job-titles.show', ['jobTitle' => $jobTitle->id, 'tab' => 'settings']) }}" class="block px-3.5 py-2.5 text-sm font-medium text-brand-text transition hover:bg-brand-surface">Edit</a>
                    <a href="{{ route('admin.workforce.job-titles.show', ['jobTitle' => $jobTitle->id, 'tab' => 'wages']) }}" class="block px-3.5 py-2.5 text-sm font-medium text-brand-text transition hover:bg-brand-surface">Edit wages</a>
                    <div class="my-1 border-t border-brand-border/80"></div>
                    @if ($jobTitle->is_active)
                        <form method="post" action="{{ route('admin.workforce.job-titles.archive', ['jobTitle' => $jobTitle->id]) }}">
                            @csrf
                            <button type="submit" class="block w-full px-3.5 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50">Archive</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.workforce.job-titles.restore', ['jobTitle' => $jobTitle->id]) }}">
                            @csrf
                            <button type="submit" class="block w-full px-3.5 py-2.5 text-left text-sm font-medium text-brand-text transition hover:bg-brand-surface">Restore</button>
                        </form>
                    @endif
                </div>
            </details>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-brand-border px-5 sm:px-7">
        @foreach ([
            'employees' => 'Employees',
            'settings' => 'Settings',
            'wages' => 'Wages',
        ] as $tabKey => $tabLabel)
            <a
                href="{{ route('admin.workforce.job-titles.show', ['jobTitle' => $jobTitle->id, 'tab' => $tabKey]) }}"
                class="relative -mb-px border-b-2 px-3 py-3.5 text-xs font-bold uppercase tracking-[0.12em] transition {{ $tab === $tabKey ? 'border-brand-primary text-brand-primary' : 'border-transparent text-brand-text-secondary hover:text-brand-primary' }}"
            >{{ $tabLabel }}</a>
        @endforeach
    </div>

    @if ($tab === 'employees')
        @php
            $assignRows = collect($assignableEmployees ?? [])->map(function ($employee) {
                $empName = trim((string) ($employee->full_legal_name ?: trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))));
                if ($empName === '') {
                    $empName = $employee->email ?: 'Employee #'.$employee->id;
                }
                $parts = preg_split('/\s+/', $empName) ?: [];
                $initials = '';
                foreach (array_slice($parts, 0, 2) as $part) {
                    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                }

                return [
                    'id' => (string) $employee->id,
                    'name' => $empName,
                    'initials' => $initials !== '' ? $initials : '#',
                ];
            });
        @endphp
        <div class="flex flex-col gap-3 border-b border-brand-border/80 bg-gradient-to-b from-brand-surface/40 to-transparent px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
            <h2 class="text-lg font-bold text-brand-primary">{{ $empLabel }}</h2>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <input
                        type="search"
                        data-jt-emp-filter
                        placeholder="Search"
                        class="w-44 rounded-xl border border-brand-border bg-white py-2.5 pl-3 pr-9 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20 sm:w-56"
                        autocomplete="off"
                        aria-label="Search employees"
                    >
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-brand-icon">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                    </span>
                </div>
                <div class="relative" data-jt-assign>
                    <button type="button" data-jt-assign-toggle class="{{ $primaryBtn }} gap-1.5" aria-expanded="false" aria-haspopup="listbox">
                        Add employee
                        <span class="text-base leading-none" aria-hidden="true">+</span>
                    </button>
                    <form method="post" action="{{ route('admin.workforce.job-titles.employees.assign', ['jobTitle' => $jobTitle->id]) }}" data-jt-assign-menu class="hidden w-80 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-2xl ring-1 ring-black/[0.06]">
                        @csrf
                        <div class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-3.5 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Add to {{ $jobTitle->name }}</p>
                            <div class="relative mt-2">
                                <input type="search" data-jt-assign-search class="w-full rounded-xl border border-brand-border bg-white py-2.5 pl-3 pr-9 text-sm text-brand-text shadow-sm outline-none placeholder:text-brand-text-secondary/70 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20" placeholder="Search employees" autocomplete="off" aria-label="Search employees to add" />
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-brand-icon">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                                </span>
                            </div>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto py-1" data-jt-assign-list role="listbox" aria-multiselectable="true">
                            @forelse ($assignRows as $employee)
                                <label data-jt-assign-option data-name="{{ $employee['name'] }}" class="mx-1.5 flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-brand-text transition hover:bg-brand-primary/10">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $employee['id'] }}" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary/30">
                                    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-[11px] font-bold text-brand-primary ring-1 ring-brand-primary/15">{{ $employee['initials'] }}</span>
                                    <span class="min-w-0 flex-1 truncate font-medium">{{ $employee['name'] }}</span>
                                </label>
                            @empty
                                <p class="px-3 py-8 text-center text-sm text-brand-text-secondary">Everyone is already assigned.</p>
                            @endforelse
                        </div>
                        <p data-jt-assign-empty class="hidden px-3 py-8 text-center text-sm text-brand-text-secondary">No employees match your search.</p>
                        <div class="flex items-center justify-between gap-3 border-t border-brand-border bg-brand-surface/30 px-3.5 py-3">
                            <span class="text-xs font-semibold text-brand-text-secondary" data-jt-assign-count>0 selected</span>
                            <button type="submit" class="{{ $primaryBtn }}" data-jt-assign-submit @disabled($assignRows->isEmpty())>Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-brand-surface/80 text-[11px] font-bold uppercase tracking-[0.12em] text-brand-label">
                    <tr>
                        <th class="w-12 px-5 py-3.5 sm:px-7" scope="col"><span class="sr-only">Avatar</span></th>
                        <th class="px-3 py-3.5" scope="col">Name</th>
                        <th class="px-3 py-3.5" scope="col">Role</th>
                        <th class="px-3 py-3.5" scope="col">Location</th>
                        <th class="w-20 px-5 py-3.5 text-right sm:px-7" scope="col">Options</th>
                    </tr>
                </thead>
                <tbody data-jt-emp-list class="divide-y divide-brand-border/80">
                    @forelse ($employees as $employee)
                        @php
                            $displayName = trim((string) ($employee->full_legal_name ?: trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))));
                            if ($displayName === '') {
                                $displayName = $employee->email ?: 'Employee #'.$employee->id;
                            }
                            $parts = preg_split('/\s+/', $displayName) ?: [];
                            $initials = strtoupper(mb_substr($parts[0] ?? '?', 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
                            $location = $employee->workLocation?->name ?? '—';
                            $search = strtolower($displayName.' '.$location);
                        @endphp
                        <tr class="transition hover:bg-brand-surface/50" data-jt-emp-row data-search="{{ $search }}">
                            <td class="px-5 py-3.5 sm:px-7">
                                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-bold text-brand-primary">{{ $initials }}</span>
                            </td>
                            <td class="px-3 py-3.5">
                                <a href="{{ route('admin.employees.profiles', ['employee' => $employee->public_id]) }}" class="font-semibold text-brand-text transition hover:text-brand-primary hover:underline">{{ $displayName }}</a>
                            </td>
                            <td class="px-3 py-3.5 text-brand-text-secondary">Employee</td>
                            <td class="px-3 py-3.5 text-brand-text-secondary">{{ $location }}</td>
                            <td class="px-5 py-3.5 text-right sm:px-7">
                                <a href="{{ route('admin.employees.profiles', ['employee' => $employee->public_id]) }}" class="inline-flex size-9 items-center justify-center rounded-xl text-brand-icon transition hover:bg-brand-primary/10 hover:text-brand-primary" aria-label="Open profile">
                                    <svg class="size-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-14 text-center text-sm text-brand-text-secondary sm:px-7">No employees assigned to this job title yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <p data-jt-emp-empty class="hidden px-5 py-10 text-center text-sm text-brand-text-secondary sm:px-7">No employees match your search.</p>
        </div>
    @elseif ($tab === 'settings')
        @php
            $selectedColor = strtolower((string) old('color', $jobTitle->accentColor()));
            $palette = \App\Models\JobTitle::colorPalette();
            $paletteLower = array_map('strtolower', $palette);
            if (! in_array($selectedColor, $paletteLower, true)) {
                array_unshift($palette, $selectedColor);
            }
            $settingsIn = 'w-full max-w-md rounded-xl border border-brand-border bg-white px-3.5 py-2.5 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
            $previewName = old('job_title_name', $jobTitle->name);
        @endphp
        <div class="border-b border-brand-border/80 bg-gradient-to-b from-brand-surface/40 to-transparent px-5 py-4 sm:px-7">
            <h2 class="text-lg font-bold text-brand-primary">Settings</h2>
        </div>

        <form
            method="post"
            action="{{ route('admin.workforce.job-titles.update', ['jobTitle' => $jobTitle->id]) }}"
            data-jt-settings-form
        >
            @csrf
            <div class="space-y-6 px-5 py-6 sm:px-7">
                <div class="max-w-md space-y-1.5">
                    <label for="jt-settings-name" class="block text-sm font-semibold text-brand-text">Name</label>
                    <div class="flex items-center gap-3">
                        <span
                            class="size-3.5 shrink-0 rounded-full shadow-sm ring-2 ring-white"
                            style="background-color: {{ $selectedColor }}"
                            data-jt-preview-dot
                            aria-hidden="true"
                        ></span>
                        <input
                            id="jt-settings-name"
                            name="job_title_name"
                            required
                            maxlength="160"
                            value="{{ $previewName }}"
                            class="{{ $settingsIn }}"
                            autocomplete="off"
                            data-jt-name-input
                            data-jt-preview-name
                            style="color: {{ $selectedColor }}"
                        />
                    </div>
                    @error('job_title_name')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2.5">
                    <p class="text-sm font-semibold text-brand-text">Colour</p>
                    <input type="hidden" name="color" value="{{ $selectedColor }}" data-jt-color-input>
                    <div class="flex flex-wrap gap-2" role="listbox" aria-label="Colour">
                        @foreach ($palette as $swatch)
                            @php
                                $swatch = strtolower($swatch);
                                $isSelected = $selectedColor === $swatch;
                            @endphp
                            <button
                                type="button"
                                data-jt-color-swatch
                                data-color="{{ $swatch }}"
                                role="option"
                                aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                                class="relative flex size-8 items-center justify-center rounded-full border border-black/10 transition hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
                                style="background-color: {{ $swatch }};{{ $isSelected ? ' box-shadow: 0 0 0 2px #fff, 0 0 0 4px '.$swatch.';' : ' box-shadow: 0 1px 2px rgba(0,0,0,0.08);' }}"
                            >
                                <svg
                                    data-jt-color-check
                                    class="size-3 text-white drop-shadow-sm {{ $isSelected ? '' : 'hidden' }}"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="3"
                                    aria-hidden="true"
                                ><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span class="sr-only">{{ $swatch }}</span>
                            </button>
                        @endforeach
                    </div>
                    @error('color')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end border-t border-brand-border bg-brand-surface/30 px-5 py-4 sm:px-7">
                <button type="submit" class="{{ $primaryBtn }}">Save</button>
            </div>
        </form>
    @else
        @php
            $wageValue = old('hourly_wage', $jobTitle->hourly_wage !== null ? number_format((float) $jobTitle->hourly_wage, 2, '.', '') : '');
        @endphp
        <div class="border-b border-brand-border/80 bg-gradient-to-b from-brand-surface/40 to-transparent px-5 py-4 sm:px-7">
            <h2 class="text-lg font-bold text-brand-primary">Wages</h2>
        </div>

        <form method="post" action="{{ route('admin.workforce.job-titles.rates.update', ['jobTitle' => $jobTitle->id]) }}">
            @csrf
            <div class="px-5 py-6 sm:px-7">
                <label for="jt-base-wage" class="mb-1.5 block text-sm font-semibold text-brand-text">Hourly wage</label>
                <div class="inline-flex items-stretch overflow-hidden rounded-xl border border-brand-border bg-white shadow-sm focus-within:border-brand-primary focus-within:ring-2 focus-within:ring-brand-primary/20">
                    <span class="flex items-center bg-brand-surface/60 px-2.5 text-sm font-semibold text-brand-text-secondary" aria-hidden="true">$</span>
                    <input
                        id="jt-base-wage"
                        type="number"
                        step="0.01"
                        min="0"
                        max="9999.99"
                        name="hourly_wage"
                        value="{{ $wageValue }}"
                        class="w-24 border-0 bg-transparent py-2.5 pl-1.5 pr-1 text-sm tabular-nums text-brand-text outline-none focus:ring-0"
                        required
                        inputmode="decimal"
                    />
                    <span class="flex items-center pr-2.5 text-xs font-medium text-brand-text-secondary">/ hr</span>
                </div>
                @error('hourly_wage')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end border-t border-brand-border bg-brand-surface/30 px-5 py-4 sm:px-7">
                <button type="submit" class="{{ $primaryBtn }}">Save</button>
            </div>
        </form>
    @endif
</div>

@push('scripts')
<script>
(function () {
    document.addEventListener('click', function (event) {
        document.querySelectorAll('[data-jt-menu][open]').forEach(function (menu) {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });

    var colorInput = document.querySelector('[data-jt-color-input]');
    var previewDot = document.querySelector('[data-jt-preview-dot]');
    var previewName = document.querySelector('[data-jt-preview-name]');
    var swatches = Array.prototype.slice.call(document.querySelectorAll('[data-jt-color-swatch]'));

    function applyColor(color) {
        if (colorInput) colorInput.value = color;
        if (previewDot) previewDot.style.backgroundColor = color;
        if (previewName) previewName.style.color = color;
        swatches.forEach(function (other) {
            var selected = other.getAttribute('data-color') === color;
            var otherColor = other.getAttribute('data-color');
            var check = other.querySelector('[data-jt-color-check]');
            other.setAttribute('aria-selected', selected ? 'true' : 'false');
            other.style.boxShadow = selected
                ? '0 0 0 2px #fff, 0 0 0 4px ' + otherColor
                : '0 1px 2px rgba(0,0,0,0.08)';
            if (check) check.classList.toggle('hidden', !selected);
        });
    }

    if (swatches.length) {
        swatches.forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyColor(btn.getAttribute('data-color'));
            });
        });
    }


    @if ($tab === 'employees')
    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }
    var input = document.querySelector('[data-jt-emp-filter]');
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-jt-emp-row]'));
    var empty = document.querySelector('[data-jt-emp-empty]');
    var list = document.querySelector('[data-jt-emp-list]');
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

    var assign = document.querySelector('[data-jt-assign]');
    if (assign) {
        var toggle = assign.querySelector('[data-jt-assign-toggle]');
        var menu = assign.querySelector('[data-jt-assign-menu]');
        var search = assign.querySelector('[data-jt-assign-search]');
        var options = Array.prototype.slice.call(assign.querySelectorAll('[data-jt-assign-option]'));
        var assignEmpty = assign.querySelector('[data-jt-assign-empty]');

        var assignCount = assign.querySelector('[data-jt-assign-count]');
        var assignList = menu ? menu.querySelector('[data-jt-assign-list]') : null;

        function placeAssignMenu() {
            if (!menu || !toggle) return;
            var rect = toggle.getBoundingClientRect();
            var width = 320;
            var left = Math.min(Math.max(12, rect.right - width), window.innerWidth - width - 12);
            var spaceBelow = window.innerHeight - rect.bottom - 16;
            var spaceAbove = rect.top - 16;
            var openBelow = spaceBelow >= 280 || spaceBelow >= spaceAbove;
            var maxHeight = Math.max(240, Math.min(420, (openBelow ? spaceBelow : spaceAbove) - 8));
            menu.style.position = 'fixed';
            menu.style.zIndex = '80';
            menu.style.width = width + 'px';
            menu.style.maxHeight = maxHeight + 'px';
            menu.style.left = left + 'px';
            menu.style.right = 'auto';
            if (openBelow) {
                menu.style.top = (rect.bottom + 8) + 'px';
                menu.style.bottom = 'auto';
            } else {
                menu.style.top = 'auto';
                menu.style.bottom = (window.innerHeight - rect.top + 8) + 'px';
            }
        }

        function setAssignOpen(open) {
            if (!menu || !toggle) return;
            if (open) {
                document.body.appendChild(menu);
                menu.classList.remove('hidden');
                menu.classList.add('flex', 'flex-col');
                placeAssignMenu();
                toggle.setAttribute('aria-expanded', 'true');
                if (search) search.focus();
            } else {
                menu.classList.add('hidden');
                menu.classList.remove('flex', 'flex-col');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }

        function filterAssign() {
            var q = normalize(search ? search.value : '');
            var shown = 0;
            var selectedCount = 0;
            options.forEach(function (option) {
                var box = option.querySelector('input[type="checkbox"]');
                var selected = !!(box && box.checked);
                if (selected) selectedCount += 1;
                option.classList.toggle('bg-brand-primary/10', selected);
                option.classList.toggle('text-brand-primary', selected);
                var match = q === '' || normalize(option.getAttribute('data-name')).indexOf(q) !== -1;
                option.classList.toggle('hidden', !match);
                if (match) shown += 1;
            });
            if (assignCount) assignCount.textContent = selectedCount + ' selected';
            if (assignEmpty) assignEmpty.classList.toggle('hidden', shown !== 0 || options.length === 0);
            if (assignList) assignList.classList.toggle('hidden', shown === 0 && options.length > 0);
        }

        if (toggle) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                setAssignOpen(menu.classList.contains('hidden'));
            });
        }
        if (search) search.addEventListener('input', filterAssign);
        options.forEach(function (option) {
            var box = option.querySelector('input[type="checkbox"]');
            if (box) box.addEventListener('change', filterAssign);
        });
        document.addEventListener('click', function (event) {
            if (assign.contains(event.target) || (menu && menu.contains(event.target))) return;
            setAssignOpen(false);
        });
        window.addEventListener('resize', function () {
            if (menu && !menu.classList.contains('hidden')) placeAssignMenu();
        });
        window.addEventListener('scroll', function () {
            if (menu && !menu.classList.contains('hidden')) placeAssignMenu();
        }, true);
    }
    @endif
})();
</script>
@endpush
@endsection
