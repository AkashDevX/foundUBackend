@extends('layouts.admin')

@php
    /** @var \App\Models\Company $company */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Employee> $employees */
    /** @var \App\Models\Employee|null $selectedEmployee */
    /** @var string $selectedPublicId */
    use App\Support\DisplayTimezone;

    $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';

    $employeeInitials = static function (string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
        }
        if (count($parts) === 1 && $parts[0] !== '') {
            return strtoupper(mb_substr($parts[0], 0, 2));
        }

        return '?';
    };

    $statusBadge = static fn (string $status): string => match (strtolower($status)) {
        'active' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'pending' => 'border-amber-200 bg-amber-50 text-amber-900',
        'inactive' => 'border-slate-300 bg-slate-100 text-slate-800',
        'declined', 'rejected' => 'border-slate-200 bg-slate-100 text-slate-700',
        '' => '',
        default => 'border-brand-border bg-brand-surface text-brand-text-secondary',
    };
@endphp

@section('title', 'Employees — Profiles')

@section('heading', 'Employee profiles')

@section('subheading')
    {{ $company->name }}
@endsection

@section('content')
    <section class="mb-8 overflow-visible rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]" data-employee-profile-search>
        <div class="flex flex-col gap-3 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h2 class="text-sm font-bold text-brand-text">Find an employee</h2>
            <div class="relative w-full sm:max-w-xs">
                <input
                    type="text"
                    data-employee-filter
                    class="{{ $in }} pe-9"
                    placeholder="Search by name, email, or code…"
                    autocomplete="off"
                    aria-label="Search employees"
                >
                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-brand-text-secondary/70">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                </span>
            </div>
        </div>

        <div class="px-4 py-4 sm:px-6">
            @if ($employees->isEmpty())
                <p class="rounded-xl border border-dashed border-brand-border bg-brand-surface/40 px-4 py-8 text-center text-sm text-brand-text-secondary">No employees yet.</p>
            @else
                <ul data-employee-list class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($employees as $employee)
                        @php
                            $name = $employee->full_legal_name ?: ($employee->email ?: 'Unnamed');
                            $status = (string) ($employee->employment_status ?: '');
                            $searchStr = strtolower(trim(($employee->full_legal_name ?? '').' '.($employee->email ?? '').' '.($employee->employee_code ?? '').' '.$status));
                            $isSelected = $selectedPublicId !== '' && $selectedPublicId === $employee->public_id;
                        @endphp
                        <li data-employee-item data-search="{{ $searchStr }}">
                            <a
                                href="{{ route('admin.employees.profiles', ['employee' => $employee->public_id]) }}"
                                @class([
                                    'flex h-full items-center gap-3 rounded-xl border px-3 py-2.5 transition',
                                    'border-brand-primary bg-brand-primary/[0.06] ring-1 ring-brand-primary/20' => $isSelected,
                                    'border-brand-border bg-white hover:border-brand-primary/40 hover:bg-brand-surface' => ! $isSelected,
                                ])
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-bold text-brand-primary">{{ $employeeInitials($name) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-brand-text">{{ $name }}</span>
                                    @if ($employee->email && $employee->email !== $name)
                                        <span class="block truncate text-xs text-brand-text-secondary">{{ $employee->email }}</span>
                                    @endif
                                </span>
                                @if ($status !== '')
                                    <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $statusBadge($status) }}">{{ ucfirst($status) }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p data-employee-empty class="hidden rounded-xl border border-dashed border-brand-border bg-brand-surface/40 px-4 py-8 text-center text-sm text-brand-text-secondary">No employees match your search.</p>
            @endif
        </div>
    </section>

    @if ($selectedEmployee !== null)
        <div
            class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto p-3 sm:p-6"
            data-employee-profile-modal
            role="dialog"
            aria-modal="true"
            aria-label="Employee profile"
        >
            <a href="{{ route('admin.employees.profiles') }}" class="fixed inset-0 bg-brand-primary-dark/60" aria-label="Close profile" data-employee-profile-close></a>
            <div class="relative z-10 my-2 flex max-h-[calc(100vh-1rem)] w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-2xl ring-1 ring-black/[0.06] sm:my-3 sm:max-h-[calc(100vh-1.5rem)]">
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-brand-border bg-white px-4 py-4 sm:px-6">
                    <div class="flex min-w-0 flex-wrap items-center gap-2 sm:gap-3">
                        <h2 class="truncate text-lg font-bold text-brand-text sm:text-xl">{{ $selectedEmployee->full_legal_name ?: $selectedEmployee->email }}</h2>
                        @if (($selectedEmployee->employment_status ?? '') !== '')
                            <span class="shrink-0 rounded-full bg-brand-surface px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-text-secondary">{{ $selectedEmployee->employment_status }}</span>
                        @endif
                        @if ($selectedEmployee->created_at)
                            <span class="text-xs text-brand-text-secondary">Registered {{ DisplayTimezone::formatDateTime($selectedEmployee->created_at) }}</span>
                        @endif
                    </div>
                    <a
                        href="{{ route('admin.employees.profiles') }}"
                        class="shrink-0 rounded-xl border border-brand-border bg-white p-2 text-brand-text-secondary shadow-sm transition hover:bg-brand-surface hover:text-brand-text"
                        aria-label="Close"
                        data-employee-profile-close
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </a>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 pt-5 pb-0 sm:px-6">
                    @include('admin.partials.employee-profile-detail', [
                        'employee' => $selectedEmployee,
                        'showApprovalActions' => false,
                    ])
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            (function () {
                function normalize(value) {
                    return String(value || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .trim();
                }

                document.querySelectorAll('[data-employee-profile-search]').forEach(function (root) {
                    var input = root.querySelector('[data-employee-filter]');
                    var list = root.querySelector('[data-employee-list]');
                    var empty = root.querySelector('[data-employee-empty]');
                    if (!input || !list) {
                        return;
                    }

                    var items = Array.prototype.slice.call(list.querySelectorAll('[data-employee-item]'));

                    function applyFilter() {
                        var query = normalize(input.value);
                        var shown = 0;
                        items.forEach(function (item) {
                            var haystack = normalize(item.getAttribute('data-search'));
                            var match = query === '' || haystack.indexOf(query) !== -1;
                            item.classList.toggle('hidden', !match);
                            if (match) {
                                shown += 1;
                            }
                        });
                        if (empty) {
                            empty.classList.toggle('hidden', shown !== 0);
                        }
                    }

                    input.addEventListener('input', applyFilter);
                });

                var modal = document.querySelector('[data-employee-profile-modal]');
                if (modal) {
                    var closeUrl = modal.querySelector('[data-employee-profile-close]');
                    closeUrl = closeUrl ? closeUrl.getAttribute('href') : null;
                    document.body.classList.add('overflow-hidden');
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && closeUrl) {
                            window.location.href = closeUrl;
                        }
                    });
                }
            })();
        </script>
    @endpush
@endsection
