@extends('layouts.admin')

@section('title', 'Training')

@section('heading', 'Training')

@section('subheading')
    {{ $company->name }}
@endsection

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-xl">
            <!-- <p class="text-sm leading-relaxed text-brand-text/70">
                Build cleaning SOP modules with study pages and a quiz, then assign them to your team.
            </p> -->
        </div>
        <a href="{{ route('admin.training.create') }}"
           class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-primary/90">
            <span class="text-lg leading-none">+</span> New module
        </a>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($modules->isEmpty())
        <section class="rounded-2xl border border-dashed border-brand-border bg-white px-6 py-20 text-center shadow-sm">
            <p class="text-lg font-bold text-brand-text">No training modules yet</p>
            <p class="mx-auto mt-2 max-w-md text-sm text-brand-text/60">Create something like “Bathroom cleaning standards” or “Chemical safety”, write the study pages, then add a quiz.</p>
            <a href="{{ route('admin.training.create') }}" class="mt-8 inline-flex rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white">Create first module</a>
        </section>
    @else
        <div class="grid gap-4">
            @foreach ($modules as $row)
                @php
                    $module = $row['module'];
                    $summary = $row['summary'];
                    $isPublished = $module->status === 'published';
                @endphp
                <article class="rounded-2xl border border-brand-border bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold text-brand-text">{{ $module->title }}</h2>
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $isPublished ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $isPublished ? 'Published' : 'Draft' }}
                                </span>
                            </div>
                            @if ($module->description)
                                <p class="mt-1 line-clamp-1 text-sm text-brand-text/60">{{ $module->description }}</p>
                            @endif
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-text/50">
                                <span>{{ $module->pages_count }} page{{ $module->pages_count === 1 ? '' : 's' }}</span>
                                <span>{{ $module->questions_count }} question{{ $module->questions_count === 1 ? '' : 's' }}</span>
                                <span>{{ $summary['assigned'] }} assigned</span>
                                <span>{{ $summary['completed'] }} completed</span>
                                @if ($summary['average_percent'] !== null)
                                    <span>Avg {{ $summary['average_percent'] }}%</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'overview']) }}"
                               class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-primary/90">Edit</a>
                            <a href="{{ route('admin.training.results', $module->id) }}"
                               class="rounded-xl border border-brand-border bg-white px-4 py-2 text-sm font-semibold text-brand-text hover:bg-brand-surface">Results</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
