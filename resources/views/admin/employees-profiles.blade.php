@extends('layouts.admin')

@php
    /** @var \App\Models\Company $company */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Employee> $employees */
    /** @var \App\Models\Employee|null $selectedEmployee */
    /** @var string $selectedPublicId */
    use App\Support\DisplayTimezone;

    $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';

    $selectedFilterEmployee = $selectedEmployee;
    $selectedFilterLabel = $selectedFilterEmployee
        ? ($selectedFilterEmployee->full_legal_name ?: $selectedFilterEmployee->email)
        : '';
    $employeeSearchOptions = $employees->map(static function ($employee): array {
        $label = trim((string) ($employee->full_legal_name ?: $employee->email ?: ''));
        $email = trim((string) ($employee->email ?: ''));
        $code = trim((string) ($employee->employee_code ?: ''));
        $status = trim((string) ($employee->employment_status ?: ''));

        return [
            'id' => $employee->public_id,
            'label' => $label.($status !== '' && $status !== 'active' ? ' ('.$status.')' : ''),
            'email' => $email,
            'search' => strtolower(trim($label.' '.$email.' '.$code.' '.$status)),
        ];
    })->values();
@endphp

@section('title', 'Employees — Profiles')

@section('heading', 'Employee profiles')

@section('subheading')
    {{ $company->name }} — search for an employee to view their full registration profile.
@endsection

@section('content')
    @push('scripts')
        @vite(['resources/js/employee-autocomplete.js'])
    @endpush

    <section class="mb-8 overflow-visible rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
        <div class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-4 sm:px-6">
            <h2 class="text-sm font-bold text-brand-text">Find an employee</h2>
            <p class="mt-1 text-xs text-brand-text-secondary">Search by name, email, employee code, or status. Select someone to load their complete profile below.</p>
        </div>

        <form method="get" action="{{ route('admin.employees.profiles') }}" class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-end sm:px-6">
            <div class="min-w-0 flex-1" data-employee-autocomplete data-employees='@json($employeeSearchOptions)'>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employee</label>
                <input type="hidden" name="employee" value="{{ $selectedPublicId }}" data-employee-autocomplete-value>
                <div class="relative">
                    <input
                        type="text"
                        value="{{ $selectedFilterLabel }}"
                        data-employee-autocomplete-input
                        class="{{ $in }} pe-9"
                        placeholder="Search by name or email…"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="profile-employee-suggestions"
                    >
                    <button
                        type="button"
                        data-employee-autocomplete-clear
                        class="absolute right-1 top-1/2 z-10 -translate-y-1/2 rounded-md p-1 text-brand-text-secondary/80 transition hover:bg-brand-surface hover:text-brand-text focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 {{ $selectedFilterLabel === '' ? 'hidden' : '' }}"
                        aria-label="Clear employee"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <div id="profile-employee-suggestions" data-employee-autocomplete-suggestions class="hidden" role="listbox" aria-label="Employee suggestions"></div>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark">
                    View profile
                </button>
                @if ($selectedPublicId !== '')
                    <a href="{{ route('admin.employees.profiles') }}" class="inline-flex items-center justify-center rounded-xl border border-brand-border bg-white px-5 py-2.5 text-sm font-semibold text-brand-text-secondary transition hover:bg-brand-surface">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </section>

    @if ($selectedEmployee === null)
        <div class="rounded-2xl border border-dashed border-brand-border bg-brand-surface/40 px-6 py-16 text-center">
            <svg class="mx-auto size-12 text-brand-text-secondary/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            <p class="mt-4 text-sm font-semibold text-brand-text">No employee selected</p>
            <p class="mt-1 text-sm text-brand-text-secondary">Use the search bar above to find and view an employee profile.</p>
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-bold text-brand-text">{{ $selectedEmployee->full_legal_name ?: $selectedEmployee->email }}</h2>
            <span class="rounded-full bg-brand-surface px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-text-secondary">{{ $selectedEmployee->employment_status }}</span>
            @if ($selectedEmployee->created_at)
                <span class="text-xs text-brand-text-secondary">Registered {{ DisplayTimezone::formatDateTime($selectedEmployee->created_at) }}</span>
            @endif
        </div>

        @include('admin.partials.employee-profile-detail', ['showApprovalActions' => false])
    @endif
@endsection
