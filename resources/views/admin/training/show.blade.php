@extends('layouts.admin')

@section('title', $module->title)

@section('heading', $module->title)

@section('subheading')
    {{ $company->name }}
@endsection

@section('content')
    @php
        $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
        $isPublished = $module->status === 'published';
        $induction = (bool) $module->is_induction;
        $stepMeta = [
            'overview' => 'Overview',
            'study' => 'Slides',
            'questions' => 'Questions',
            'assign' => $induction ? 'Eligibility' : 'Assign',
        ];
        $stepIndex = array_search($step, $steps, true);
        $moduleUrl = function (array $params = []) use ($module): string {
            return route('admin.training.show', ['module' => $module->id] + $params);
        };
    @endphp

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admin.training.index') }}" class="text-sm font-semibold text-brand-primary hover:underline">← All training</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.training.results', $module->id) }}" class="rounded-xl border border-brand-border bg-white px-3 py-2 text-sm font-semibold text-brand-text hover:bg-brand-surface">Results</a>
            @unless ($induction)
            <form method="post"
                  action="{{ route('admin.training.destroy', $module->id) }}"
                  data-confirm="This training module, its study pages, questions, and assignments will be permanently removed."
                  data-confirm-title="Delete training module?"
                  data-confirm-confirm="Delete"
                  data-confirm-cancel="Keep module"
                  data-confirm-danger="1">
                @csrf
                <button type="submit" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Delete</button>
            </form>
            @endunless
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Step nav --}}
    <nav class="mb-6 overflow-x-auto rounded-2xl border border-brand-border bg-white p-2 shadow-sm" aria-label="Builder steps">
        <ol class="flex min-w-max gap-1">
            @foreach ($steps as $i => $key)
                @php $active = $step === $key; @endphp
                <li class="flex-1">
                    <a href="{{ $moduleUrl(['step' => $key]) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-3 transition {{ $active ? 'bg-brand-primary text-white' : 'text-brand-text/70 hover:bg-brand-surface' }}">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $active ? 'bg-white/20 text-white' : 'bg-brand-surface text-brand-primary' }}">{{ $i + 1 }}</span>
                        <span class="text-sm font-semibold">{{ $stepMeta[$key] }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- OVERVIEW --}}
    @if ($step === 'overview')
        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border px-5 py-5 sm:px-6">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-bold text-brand-text">Overview</h2>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $isPublished ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $isPublished ? 'Published' : 'Draft' }}
                    </span>
                </div>
            </div>
            <form method="post" action="{{ route('admin.training.update', $module->id) }}" class="grid gap-4 px-5 py-5 sm:grid-cols-2 sm:px-6">
                @csrf
                <input type="hidden" name="next_step" value="study">
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Title</span>
                    <input type="text" name="title" value="{{ old('title', $module->title) }}" required class="{{ $in }}">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Description</span>
                    <textarea name="description" rows="3" class="{{ $in }}">{{ old('description', $module->description) }}</textarea>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Status</span>
                    <select name="status" class="{{ $in }}">
                        <option value="draft" @selected(old('status', $module->status) === 'draft')>Draft</option>
                        <option value="published" @selected(old('status', $module->status) === 'published')>Published</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Pass mark %</span>
                    <input type="number" name="pass_percent" min="1" max="100" value="{{ old('pass_percent', $module->pass_percent ?? ($induction ? 92 : 70)) }}" class="{{ $in }}">
                </label>
                @if ($induction)
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Max attempts</span>
                        <input type="number" name="max_attempts" min="1" max="10" value="{{ old('max_attempts', $module->max_attempts ?? 3) }}" class="{{ $in }}">
                    </label>
                @endif
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">Save &amp; continue</button>
                </div>
            </form>
        </section>
    @endif

    {{-- STUDY PAGES --}}
    @if ($step === 'study')
        @include('admin.partials.training-slide-studio')
    @endif


    {{-- QUESTIONS --}}
    @if ($step === 'questions')
        @include('admin.partials.training-questions')
    @endif

    {{-- ASSIGN --}}
    @if ($step === 'assign')
        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border px-5 py-5 sm:px-6">
                <h2 class="text-lg font-bold text-brand-text">{{ $induction ? 'Eligibility' : 'Assign' }}</h2>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-brand-surface px-2.5 py-1 font-semibold text-brand-text/70">{{ $module->pages->count() }} pages</span>
                    <span class="rounded-full bg-brand-surface px-2.5 py-1 font-semibold text-brand-text/70">{{ $module->questions->count() }} questions</span>
                    <span class="rounded-full px-2.5 py-1 font-semibold {{ $isPublished ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $isPublished ? 'Published' : 'Draft' }}</span>
                </div>
            </div>

            @if ($induction)
                <div class="px-5 py-8 sm:px-6">
                    <a href="{{ route('admin.training.results', $module->id) }}" class="inline-flex rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white">View results</a>
                </div>
            @elseif (! $canAssign)
                <div class="px-5 py-8 sm:px-6">
                    <ul class="space-y-2 text-sm">
                        <li class="{{ $module->pages->count() > 0 ? 'text-emerald-700' : 'text-brand-text/70' }}">{{ $module->pages->count() > 0 ? '✓' : '○' }} At least one study page</li>
                        <li class="{{ ! ($module->quiz_required ?? true) || $module->questions->count() > 0 ? 'text-emerald-700' : 'text-brand-text/70' }}">{{ ! ($module->quiz_required ?? true) || $module->questions->count() > 0 ? '✓' : '○' }} {{ ($module->quiz_required ?? true) ? 'At least one quiz question' : 'Quiz is optional' }}</li>
                    </ul>
                    <div class="mt-6 flex flex-wrap gap-2">
                        @if ($module->pages->count() < 1)
                            <a href="{{ $moduleUrl(['step' => 'study']) }}" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white">Add study pages</a>
                        @else
                            <a href="{{ $moduleUrl(['step' => 'questions']) }}" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white">Add questions</a>
                        @endif
                    </div>
                </div>
            @else
                <form method="post" action="{{ route('admin.training.assign', $module->id) }}" class="px-5 py-5 sm:px-6">
                    @csrf
                    <div class="mb-4 flex flex-wrap items-end gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-brand-text">
                            <input type="checkbox" id="select-all-employees" class="size-4 rounded border-brand-border text-brand-primary">
                            Select all active
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Due date</span>
                            <input type="date" name="due_date" value="{{ old('due_date') }}" class="{{ $in }} max-w-xs">
                        </label>
                    </div>
                    <div class="mb-4 max-h-72 overflow-y-auto rounded-xl border border-brand-border">
                        @forelse ($employees as $employee)
                            @php $already = in_array((int) $employee->id, $assignedIds, true); @endphp
                            <label class="flex items-center gap-3 border-b border-brand-border px-3 py-2.5 last:border-b-0 {{ $already ? 'bg-brand-surface/60' : 'hover:bg-brand-surface/40' }}">
                                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="employee-assign-cb size-4 rounded border-brand-border text-brand-primary" @disabled($already) @checked($already)>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-brand-text">{{ $employee->full_legal_name ?: $employee->email }}</span>
                                    <span class="block truncate text-xs text-brand-text/55">{{ $employee->email }}</span>
                                </span>
                                @if ($already)
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-brand-text/45">Assigned</span>
                                @endif
                            </label>
                        @empty
                            <p class="px-3 py-6 text-sm text-brand-text/55">No active employees found.</p>
                        @endforelse
                    </div>
                    <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">
                        {{ $isPublished ? 'Assign selected' : 'Publish & assign selected' }}
                    </button>
                </form>
            @endif
        </section>
    @endif

    @push('scripts')
        <script>
            (function () {
                const selectAll = document.getElementById('select-all-employees');
                if (!selectAll) return;
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('.employee-assign-cb:not(:disabled)').forEach(function (cb) {
                        cb.checked = selectAll.checked;
                    });
                });
            })();
        </script>
    @endpush
@endsection
