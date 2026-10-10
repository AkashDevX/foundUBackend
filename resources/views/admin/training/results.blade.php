@extends('layouts.admin')

@section('title', 'Results — '.$module->title)

@section('heading', 'Training results')

@section('subheading')
    {{ $module->title }}
@endsection

@section('content')
    @php
        $bandClasses = [
            'strong' => 'bg-emerald-50 text-emerald-800',
            'pass' => 'bg-sky-50 text-sky-800',
            'weak' => 'bg-amber-50 text-amber-800',
            'fail' => 'bg-red-50 text-red-800',
            'pending' => 'bg-slate-100 text-slate-600',
        ];
        $statusLabels = [
            'not_started' => 'Not started',
            'studying' => 'Studying',
            'in_quiz' => 'In quiz',
            'pending_review' => 'Needs review',
            'failed' => 'Failed',
            'completed' => 'Completed',
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'overview']) }}" class="text-sm font-semibold text-brand-primary hover:underline">← Edit module</a>
        <a href="{{ route('admin.training.index') }}" class="text-sm font-semibold text-brand-text/60 hover:underline">All training</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-brand-border bg-white px-5 py-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Assigned</p>
            <p class="mt-1 text-3xl font-bold text-brand-text">{{ $summary['assigned'] }}</p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-white px-5 py-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Completed</p>
            <p class="mt-1 text-3xl font-bold text-brand-text">{{ $summary['completed'] }}</p>
            <p class="mt-1 text-xs text-brand-text/50">{{ $summary['in_progress'] }} in progress · {{ $summary['not_started'] }} not started</p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-white px-5 py-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Average score</p>
            <p class="mt-1 text-3xl font-bold text-brand-text">{{ $summary['average_percent'] !== null ? $summary['average_percent'].'%' : '—' }}</p>
        </div>
        <div class="rounded-2xl border border-brand-border bg-white px-5 py-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Pass rate</p>
            <p class="mt-1 text-3xl font-bold text-brand-text">{{ $summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—' }}</p>
            <p class="mt-1 text-xs text-brand-text/50">
                Pass mark {{ $module->pass_percent ?? 70 }}%
                · {{ ($module->quiz_required ?? true) ? 'Quiz required' : 'Quiz optional' }}
                @if ($module->is_induction || ($module->allow_retakes ?? false))
                    · {{ $module->max_attempts ?? 1 }} attempts
                @endif
            </p>
        </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border px-5 py-5 sm:px-6">
            <h2 class="text-lg font-bold text-brand-text">Employee scores</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-brand-surface/50 text-[11px] font-semibold uppercase tracking-wide text-brand-label">
                    <tr>
                        <th class="whitespace-nowrap px-5 py-3.5">Employee</th>
                        <th class="whitespace-nowrap px-5 py-3.5">Status</th>
                        <th class="whitespace-nowrap px-5 py-3.5">Attempts</th>
                        <th class="whitespace-nowrap px-5 py-3.5">Score</th>
                        <th class="whitespace-nowrap px-5 py-3.5">Result</th>
                        <th class="whitespace-nowrap px-5 py-3.5">Studied</th>
                        <th class="whitespace-nowrap px-5 py-3.5">Quiz taken</th>
                        <th class="whitespace-nowrap px-5 py-3.5">Certificate</th>
                        <th class="whitespace-nowrap px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border">
                    @forelse ($rows as $row)
                        <tr class="align-middle hover:bg-brand-surface/20">
                            <td class="px-5 py-3.5 align-middle">
                                <p class="font-semibold text-brand-text">{{ $row['employee_name'] }}</p>
                                <p class="text-xs text-brand-text/50">{{ $row['employee_email'] }}</p>
                                @if ($module->is_induction && ! empty($row['induction_attempts']))
                                    <ul class="mt-2 space-y-0.5 text-xs text-brand-text/60">
                                        @foreach ($row['induction_attempts'] as $attempt)
                                            <li>
                                                Attempt {{ $attempt['attempt_number'] }}:
                                                {{ $attempt['percent'] !== null ? rtrim(rtrim(number_format((float) $attempt['percent'], 1), '0'), '.').'%' : '—' }}
                                                {{ $attempt['passed'] ? 'passed' : 'not passed' }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 align-middle text-brand-text/75">{{ $statusLabels[$row['status']] ?? $row['status'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 align-middle">
                                <p class="font-semibold text-brand-text">Tried {{ (int) ($row['attempts_used'] ?? 0) }} of {{ (int) ($row['attempts_allocated'] ?? 1) }}</p>
                                <p class="mt-0.5 text-xs text-brand-text/55">In progress {{ (int) ($row['attempts_in_progress'] ?? 0) }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 align-middle">
                                @if ($row['percent'] !== null)
                                    <span class="font-semibold text-brand-text">{{ rtrim(rtrim(number_format((float) $row['percent'], 1), '0'), '.') }}%</span>
                                    <span class="text-xs text-brand-text/50">({{ $row['score'] }}/{{ $row['max_score'] }})</span>
                                @else
                                    <span class="text-brand-text/40">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 align-middle">
                                @php $band = $row['band'] ?? 'pending'; @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $bandClasses[$band] ?? $bandClasses['pending'] }}">
                                    @if ($band === 'strong') Strong
                                    @elseif ($band === 'pass') Pass
                                    @elseif ($band === 'weak') Weak
                                    @elseif ($band === 'fail') Fail
                                    @else Pending
                                    @endif
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 align-middle text-xs text-brand-text/60">
                                @if ($row['materials_acknowledged_at'])
                                    {{ \App\Support\DisplayTimezone::format(\Carbon\Carbon::parse($row['materials_acknowledged_at'], 'UTC'), 'j M Y, g:ia') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 align-middle text-xs text-brand-text/60">
                                @if ($row['submitted_at'])
                                    {{ \App\Support\DisplayTimezone::format(\Carbon\Carbon::parse($row['submitted_at'], 'UTC'), 'j M Y, g:ia') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 align-middle text-xs">
                                @if (! empty($row['certificate']['reference_number']))
                                    <a href="{{ route('admin.training.assignments.certificate', [$module->id, $row['assignment_id']]) }}" class="font-semibold text-brand-primary hover:underline">{{ $row['certificate']['reference_number'] }}</a>
                                    <span class="mt-0.5 block text-brand-text/50">
                                        {{ $row['certificate']['completed_on_label'] }}
                                    </span>
                                @else
                                    <span class="text-brand-text/40">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 align-middle text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    @if ($module->is_induction && ($row['induction_status'] ?? '') === 'required' && ! empty($row['employee_public_id']))
                                        <a href="{{ route('admin.registrations.show', ['companySlug' => $company->slug, 'publicId' => $row['employee_public_id']]) }}" class="text-xs font-semibold text-amber-700 hover:underline">Override</a>
                                    @endif
                                    @if (! empty($row['submitted_at']) || ($row['status'] ?? '') === 'pending_review')
                                        <a href="{{ route('admin.training.assignments.review', [$module->id, $row['assignment_id']]) }}" class="text-xs font-semibold text-brand-primary hover:underline">Review</a>
                                    @endif
                                    @if ($row['status'] !== 'not_started')
                                        <form method="post"
                                              action="{{ route('admin.training.assignments.reset', [$module->id, $row['assignment_id']]) }}"
                                              data-confirm="The employee can study again and retake the quiz with a new question order."
                                              data-confirm-title="Reset this attempt?"
                                              data-confirm-confirm="Reset"
                                              data-confirm-cancel="Cancel"
                                              data-confirm-icon="warning">
                                            @csrf
                                            <button type="submit" class="score-row-action text-xs font-semibold text-brand-primary hover:underline">Reset</button>
                                        </form>
                                    @endif
                                    <form method="post"
                                          action="{{ route('admin.training.assignments.destroy', [$module->id, $row['assignment_id']]) }}"
                                          data-confirm="This employee will no longer have this training assignment."
                                          data-confirm-title="Remove assignment?"
                                          data-confirm-confirm="Remove"
                                          data-confirm-cancel="Keep"
                                          data-confirm-danger="1">
                                        @csrf
                                        <button type="submit" class="score-row-action text-xs font-semibold text-red-600 hover:underline">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-sm text-brand-text/50">No employees assigned yet. Open the module and use the Assign step.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        document.querySelectorAll('.score-row-action').forEach((button) => {
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
            });
        });
    </script>
@endsection
