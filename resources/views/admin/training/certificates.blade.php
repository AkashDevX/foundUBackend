@extends('layouts.admin')

@section('title', 'Certificates')

@section('heading', 'Certificates')

@section('subheading')
    {{ $company->name }}
@endsection

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admin.training.index') }}" class="text-sm font-semibold text-brand-primary hover:underline">← All training</a>
    </div>

    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border px-5 py-5 sm:px-6">
            <h2 class="text-lg font-bold text-brand-text">Certificates obtained</h2>
            <p class="mt-1 text-sm text-brand-text/60">Issued when an employee passes a training module.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-brand-surface/50 text-[11px] font-semibold uppercase tracking-wide text-brand-label">
                    <tr>
                        <th class="px-5 py-3.5">Employee</th>
                        <th class="px-5 py-3.5">Training</th>
                        <th class="px-5 py-3.5">Completed</th>
                        <th class="px-5 py-3.5">Refresher</th>
                        <th class="px-5 py-3.5">Reference</th>
                        <th class="px-5 py-3.5">Signature</th>
                        <th class="px-5 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border">
                    @forelse ($certificates as $certificate)
                        @php
                            $moduleId = $certificate->assignment?->module?->id ?? $certificate->assignment?->training_module_id;
                            $signature = $certificate->signatureDrawing();
                        @endphp
                        <tr class="hover:bg-brand-surface/20">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-brand-text">{{ $certificate->employee_name }}</p>
                                @if ($certificate->employee?->email)
                                    <p class="text-xs text-brand-text/50">{{ $certificate->employee->email }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-medium text-brand-text">{{ $certificate->training_name }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-brand-text/75">{{ $certificate->completed_on?->format('j M Y') ?? '—' }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-brand-text/75">{{ $certificate->expires_on?->format('j M Y') ?? '—' }}</td>
                            <td class="px-5 py-3.5 font-semibold text-brand-text">{{ $certificate->reference_number }}</td>
                            <td class="px-5 py-3.5 text-brand-text/75">{{ $signature ? 'Signed' : '—' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($moduleId)
                                    <a href="{{ route('admin.training.assignments.certificate', [$moduleId, $certificate->training_assignment_id]) }}" class="text-xs font-semibold text-brand-primary hover:underline">View certificate</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-brand-text/50">No certificates yet. They appear here after an employee passes a module that issues one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
