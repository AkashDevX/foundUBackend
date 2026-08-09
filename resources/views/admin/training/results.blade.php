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
            <p class="mt-1 text-xs text-brand-text/50">Pass mark {{ $module->pass_percent ?? 70 }}%</p>
        </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border px-5 py-5 sm:px-6">
            <h2 class="text-lg font-bold text-brand-text">Employee scores</h2>
            <p class="mt-1 text-sm text-brand-text/60">How each assigned employee performed on this module.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-brand-surface/50 text-[11px] font-semibold uppercase tracking-wide text-brand-label">
                    <tr>
                        <th class="px-5 py-3.5">Employee</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5">Score</th>
                        <th class="px-5 py-3.5">Result</th>
                        <th class="px-5 py-3.5">Studied</th>
                        <th class="px-5 py-3.5">Quiz taken</th>
                        <th class="px-5 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-brand-surface/20">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-brand-text">{{ $row['employee_name'] }}</p>
                                <p class="text-xs text-brand-text/50">{{ $row['employee_email'] }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-brand-text/75">{{ $statusLabels[$row['status']] ?? $row['status'] }}</td>
                            <td class="px-5 py-3.5">
                                @if ($row['percent'] !== null)
                                    <span class="font-semibold text-brand-text">{{ rtrim(rtrim(number_format((float) $row['percent'], 1), '0'), '.') }}%</span>
                                    <span class="text-xs text-brand-text/50">({{ $row['score'] }}/{{ $row['max_score'] }})</span>
                                @else
                                    <span class="text-brand-text/40">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
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
                            <td class="px-5 py-3.5 text-xs text-brand-text/60 whitespace-nowrap">
                                @if ($row['materials_acknowledged_at'])
                                    {{ \Carbon\Carbon::parse($row['materials_acknowledged_at'])->timezone(config('app.timezone'))->format('j M Y, g:ia') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-xs text-brand-text/60 whitespace-nowrap">
                                @if ($row['submitted_at'])
                                    {{ \Carbon\Carbon::parse($row['submitted_at'])->timezone(config('app.timezone'))->format('j M Y, g:ia') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex flex-wrap justify-end gap-3">
                                    @if ($row['status'] !== 'not_started')
                                        <form method="post"
                                              action="{{ route('admin.training.assignments.reset', [$module->id, $row['assignment_id']]) }}"
                                              data-confirm="The employee can study again and retake the quiz with a new question order."
                                              data-confirm-title="Reset this attempt?"
                                              data-confirm-confirm="Reset"
                                              data-confirm-cancel="Cancel"
                                              data-confirm-icon="warning">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-brand-primary hover:underline">Reset</button>
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
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-brand-text/50">No employees assigned yet. Open the module and use the Assign step.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
