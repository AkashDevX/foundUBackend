@extends('layouts.admin')

@section('title', 'Employees — Tasks')

@section('heading', 'Employee tasks')

@section('subheading')
    {{ $company->name }}
@endsection


@section('content')
    @php
        /** @var string $date */
        /** @var list<array<string, mixed>> $taskRows */
        /** @var array<string, string> $filters */
        /** @var array<string, string|null> $redirectQuery */
        /** @var \Illuminate\Support\Collection<int, \App\Models\WorkLocation> $workLocations */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Employee> $employees */
        use App\Support\DisplayTimezone;

        $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
        $dateLabel = \Carbon\Carbon::parse($date, DisplayTimezone::name())->format('l, j M Y');

        $selectedFilterEmployee = $employees->firstWhere('public_id', $filters['employee'] ?? '');
        $selectedFilterLabel = $selectedFilterEmployee
            ? ($selectedFilterEmployee->full_legal_name ?: $selectedFilterEmployee->email)
            : '';
        $employeeSearchOptions = $employees->map(static function ($employee): array {
            $label = trim((string) ($employee->full_legal_name ?: $employee->email ?: ''));
            $email = trim((string) ($employee->email ?: ''));

            return [
                'id' => $employee->public_id,
                'label' => $label,
                'email' => $email,
                'search' => strtolower(trim($label.' '.$email)),
            ];
        })->values();

        $focusedRow = ($filters['employee'] ?? '') !== '' && count($taskRows) === 1 ? $taskRows[0] : null;
        $failedAssignEmployeeId = old('employee_public_id', '');

        $statEmployees = count($taskRows);
        $statPersonalTasks = collect($taskRows)->sum(static fn (array $row): int => count($row['assigned_tasks']));
        $statCompleted = collect($taskRows)->sum(static function (array $row): int {
            return collect($row['assigned_tasks'] ?? [])
                ->filter(static fn (array $task): bool => ($task['completed'] ?? false) === true)
                ->count();
        });
        $hasActiveFilters = ($filters['work_location_id'] ?? '') !== '' || ($filters['employee'] ?? '') !== '';

        $clearFiltersUrl = route('admin.employees.tasks', ['date' => $date]);
    @endphp

    @push('scripts')
        @vite(['resources/js/employee-autocomplete.js'])
    @endpush

    <section class="mb-6 overflow-visible rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
        <div class="flex flex-col gap-3 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-4 py-4 sm:px-6">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Viewing tasks for</p>
                <p class="mt-1 text-lg font-bold text-brand-text">{{ $dateLabel }}</p>
            </div>
        </div>

        <form method="get" action="{{ route('admin.employees.tasks') }}" class="grid gap-3 border-b border-brand-border bg-brand-surface/40 px-4 py-4 sm:grid-cols-2 lg:grid-cols-5 sm:px-6">
            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Date</span>
                <input type="date" name="date" value="{{ $date }}" class="{{ $in }}">
            </label>

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Site</span>
                <select name="work_location_id" class="{{ $in }}">
                    <option value="">All sites</option>
                    @foreach ($workLocations as $location)
                        <option value="{{ $location->id }}" @selected((string) $filters['work_location_id'] === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="block sm:col-span-2 lg:col-span-1" data-employee-autocomplete data-employees='@json($employeeSearchOptions)'>
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employee</span>
                <input type="hidden" name="employee" value="{{ $filters['employee'] }}" data-employee-autocomplete-value>
                <div class="relative">
                    <input
                        type="text"
                        value="{{ $selectedFilterLabel }}"
                        data-employee-autocomplete-input
                        class="{{ $in }} pe-9"
                        placeholder="Search by name or email…"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="filter-employee-suggestions"
                    >
                    <button
                        type="button"
                        data-employee-autocomplete-clear
                        class="absolute right-1 top-1/2 z-10 -translate-y-1/2 rounded-md p-1 text-brand-text-secondary/80 transition hover:bg-brand-surface hover:text-brand-text focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40 {{ $selectedFilterLabel === '' ? 'hidden' : '' }}"
                        aria-label="Clear employee"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <div id="filter-employee-suggestions" data-employee-autocomplete-suggestions class="hidden" role="listbox" aria-label="Employee suggestions"></div>
                </div>
            </div>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
                <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark">
                    Apply filters
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ $clearFiltersUrl }}" class="inline-flex items-center justify-center rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-text-secondary transition hover:bg-brand-surface">
                        Clear
                    </a>
                @endif
            </div>
        </form>

        <div class="grid gap-px bg-brand-border/70 sm:grid-cols-3">
            <div class="bg-white px-4 py-3 sm:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employees</p>
                <p class="mt-1 text-lg font-bold text-brand-text">{{ $statEmployees }}</p>
            </div>
            <div class="bg-white px-4 py-3 sm:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Tasks</p>
                <p class="mt-1 text-lg font-bold text-brand-text">{{ $statPersonalTasks }}</p>
            </div>
            <div class="bg-white px-4 py-3 sm:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Completed on this date</p>
                <p class="mt-1 text-lg font-bold text-emerald-700">{{ $statCompleted }}</p>
            </div>
        </div>
    </section>

    @if ($focusedRow !== null)
        @php
            /** @var \App\Models\Employee $employeeModel */
            $employeeModel = $focusedRow['employee'];
            $assignedTasks = $focusedRow['assigned_tasks'];
            $completionSummary = $focusedRow['completion_summary'] ?? ['total' => 0, 'completed' => 0, 'pending' => 0];
            $isAssignTarget = $failedAssignEmployeeId === $employeeModel->public_id;
        @endphp

        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="flex flex-wrap items-center gap-4 border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-5 sm:px-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-sm font-bold text-brand-primary ring-1 ring-brand-primary/15">
                    {{ $focusedRow['initials'] }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Employee</p>
                    <h2 class="mt-0.5 text-xl font-bold text-brand-text">{{ $focusedRow['name'] }}</h2>
                    <p class="mt-1 text-sm text-brand-text-secondary">{{ $focusedRow['job_title'] }} · {{ $employeeModel->workLocation?->name ?: 'No site assigned' }}</p>
                    @if (($completionSummary['total'] ?? 0) > 0)
                        <p class="mt-2 text-xs font-semibold text-brand-text-secondary">
                            <span class="text-emerald-700">{{ $completionSummary['completed'] }} completed</span>
                            @if (($completionSummary['pending'] ?? 0) > 0)
                                <span class="text-brand-text-secondary"> · {{ $completionSummary['pending'] }} pending</span>
                            @endif
                        </p>
                    @endif
                </div>
                <a href="{{ $clearFiltersUrl }}" class="inline-flex items-center rounded-xl border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-text-secondary shadow-sm transition hover:bg-brand-surface">
                    ← All employees
                </a>
            </header>

            <div class="p-5 sm:p-6">
                <h3 class="text-sm font-bold text-brand-text">Tasks</h3>

                @if ($assignedTasks === [])
                    <p class="mt-5 rounded-xl border border-dashed border-brand-border bg-brand-surface/40 px-4 py-6 text-center text-sm text-brand-text-secondary">No tasks for this date.</p>
                @else
                    <ul class="mt-4 space-y-2">
                        @foreach ($assignedTasks as $task)
                            <li class="flex items-start gap-3 rounded-xl border px-4 py-3 {{ ! empty($task['completed']) ? 'border-emerald-200/80 bg-emerald-50/35' : 'border-violet-200/70 bg-violet-50/30' }}">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <p class="font-semibold text-brand-text">{{ $task['title'] }}</p>
                                        @if (! empty($task['completed']))
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200">Completed</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800 ring-1 ring-amber-200">Pending</span>
                                        @endif
                                    </div>
                                    @if (! empty($task['description']))
                                        <p class="mt-1 text-sm leading-relaxed text-brand-text-secondary">{{ $task['description'] }}</p>
                                    @endif
                                    @if (! empty($task['scheduled_date']))
                                        <p class="mt-1 text-xs font-medium text-brand-text-secondary">
                                            Due {{ $task['scheduled_date_display'] ?? $task['scheduled_date'] }}
                                        </p>
                                    @else
                                        <p class="mt-1 text-xs text-brand-text-secondary">Ongoing</p>
                                    @endif
                                    @if (! empty($task['completed_at_display']))
                                        <p class="mt-1 text-xs font-medium text-emerald-800">
                                            Marked complete {{ $task['completed_at_display'] }}
                                            @if (! empty($task['completion_date_display']) && ($task['scheduled_date'] ?? null) !== ($task['completion_date'] ?? null))
                                                <span class="text-emerald-700/80"> · recorded for {{ $task['completion_date_display'] }}</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>
                                <form
                                    method="post"
                                    action="{{ route('admin.employees.tasks.destroy', ['taskAssignment' => $task['id']]) }}"
                                    data-confirm="This task will be removed from the employee's list."
                                    data-confirm-title="Remove task?"
                                    data-confirm-confirm="Remove"
                                    data-confirm-cancel="Keep task"
                                    data-confirm-danger="1"
                                >
                                    @csrf
                                    @method('DELETE')
                                    @foreach ($redirectQuery as $key => $value)
                                        @if ($value !== null && $value !== '')
                                            <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                                        @endif
                                    @endforeach
                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:text-red-900">Remove</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="post" action="{{ route('admin.employees.tasks.store') }}" class="mt-5 rounded-xl border border-brand-border bg-brand-surface/30 p-4">
                    @csrf
                    <input type="hidden" name="employee_public_id" value="{{ $employeeModel->public_id }}">
                    @foreach ($redirectQuery as $key => $value)
                        @if ($value !== null && $value !== '')
                            <input type="hidden" name="redirect[{{ $key }}]" value="{{ $value }}">
                        @endif
                    @endforeach
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Add task</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_10rem_auto] sm:items-end">
                        <label class="block">
                            <span class="sr-only">Task</span>
                            <input type="text" name="title" required maxlength="200" value="{{ $isAssignTarget ? old('title') : '' }}" class="{{ $in }}" placeholder="e.g. Cover register at lunch">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-brand-text-secondary">Due (optional)</span>
                            <input type="date" name="scheduled_date" value="{{ $isAssignTarget ? old('scheduled_date') : '' }}" class="{{ $in }}">
                        </label>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-primary-dark">
                            Add task
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @elseif ($taskRows === [])
        <div class="rounded-2xl border border-dashed border-brand-border bg-brand-surface/50 px-6 py-12 text-center">
            <p class="text-sm font-semibold text-brand-text">No employees match these filters</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
            <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-4 sm:px-6">
                <h2 class="text-sm font-bold text-brand-text">Employees</h2>
            </header>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                        <tr>
                            <th class="px-5 py-3">Employee</th>
                            <th class="hidden px-5 py-3 md:table-cell">Site</th>
                            <th class="px-5 py-3 text-center">Tasks</th>
                            <th class="px-5 py-3 text-center">Completed</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border/80">
                        @foreach ($taskRows as $row)
                            @php
                                /** @var \App\Models\Employee $employeeModel */
                                $employeeModel = $row['employee'];
                                $openUrl = route('admin.employees.tasks', array_filter([
                                    'date' => $date,
                                    'work_location_id' => ($filters['work_location_id'] ?? '') !== '' ? $filters['work_location_id'] : null,
                                    'employee' => $employeeModel->public_id,
                                ]));
                            @endphp
                            <tr class="transition hover:bg-brand-surface/50">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-bold text-brand-primary ring-1 ring-brand-primary/15">
                                            {{ $row['initials'] }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-brand-text">{{ $row['name'] }}</p>
                                            <p class="mt-0.5 text-xs text-brand-text-secondary">{{ $row['job_title'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden px-5 py-4 text-xs text-brand-text-secondary md:table-cell">
                                    {{ $employeeModel->workLocation?->name ?: '—' }}
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex min-w-[2rem] justify-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-bold tabular-nums text-violet-900 ring-1 ring-violet-200">
                                        {{ count($row['assigned_tasks']) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    @php $summary = $row['completion_summary'] ?? ['total' => 0, 'completed' => 0]; @endphp
                                    @if (($summary['total'] ?? 0) === 0)
                                        <span class="text-xs text-brand-text-secondary">—</span>
                                    @else
                                        <span class="inline-flex min-w-[2.5rem] justify-center rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums ring-1 {{ ($summary['completed'] ?? 0) === ($summary['total'] ?? 0) ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-amber-50 text-amber-900 ring-amber-200' }}">
                                            {{ $summary['completed'] }}/{{ $summary['total'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ $openUrl }}" class="inline-flex items-center rounded-lg border border-brand-border bg-white px-3 py-1.5 text-xs font-semibold text-brand-primary shadow-sm transition hover:border-brand-primary/35 hover:bg-brand-surface">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
