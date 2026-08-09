@extends('layouts.admin')

@section('title', $module->title)

@section('heading', $module->title)

@section('subheading')
    {{ $company->name }} — training builder
@endsection

@section('content')
    @php
        $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
        $isPublished = $module->status === 'published';
        $stepMeta = [
            'overview' => ['label' => 'Overview', 'hint' => 'Title, pass mark, publish'],
            'study' => ['label' => 'Study pages', 'hint' => 'Content employees read first'],
            'questions' => ['label' => 'Questions', 'hint' => 'Multiple-choice quiz'],
            'assign' => ['label' => 'Assign', 'hint' => 'Send to employees'],
        ];
        $stepIndex = array_search($step, $steps, true);
    @endphp

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admin.training.index') }}" class="text-sm font-semibold text-brand-primary hover:underline">← All training</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.training.results', $module->id) }}" class="rounded-xl border border-brand-border bg-white px-3 py-2 text-sm font-semibold text-brand-text hover:bg-brand-surface">Results</a>
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
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
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
                    <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => $key]) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-3 transition {{ $active ? 'bg-brand-primary text-white' : 'text-brand-text/70 hover:bg-brand-surface' }}">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $active ? 'bg-white/20 text-white' : 'bg-brand-surface text-brand-primary' }}">{{ $i + 1 }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">{{ $stepMeta[$key]['label'] }}</span>
                            <span class="hidden text-[11px] opacity-80 sm:block {{ $active ? 'text-white/80' : 'text-brand-text/45' }}">{{ $stepMeta[$key]['hint'] }}</span>
                        </span>
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
                <p class="mt-1 text-sm text-brand-text/60">Publish only after you have study pages and questions.</p>
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
                    <input type="number" name="pass_percent" min="1" max="100" value="{{ old('pass_percent', $module->pass_percent ?? 70) }}" class="{{ $in }}">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Seconds per question</span>
                    <input type="number" name="question_time_seconds" min="10" max="600" value="{{ old('question_time_seconds', $module->question_time_seconds ?? 45) }}" class="{{ $in }}">
                    <span class="mt-1 block text-xs text-brand-text/50">Employees get this long on each quiz question.</span>
                </label>
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">Save &amp; continue</button>
                </div>
            </form>
        </section>
    @endif

    {{-- STUDY PAGES --}}
    @if ($step === 'study')
        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
                <div class="border-b border-brand-border px-5 py-5 sm:px-6">
                    <h2 class="text-lg font-bold text-brand-text">Study pages</h2>
                    <p class="mt-1 text-sm text-brand-text/60">Employees read these pages in the app before the quiz. Add optional subtopics that expand/collapse on mobile.</p>
                </div>
                <div class="space-y-3 px-5 py-5 sm:px-6">
                    @forelse ($module->pages as $index => $page)
                        <div class="rounded-xl border border-brand-border bg-brand-surface/30 p-4 {{ $editingPage && (int) $editingPage->id === (int) $page->id ? 'ring-2 ring-brand-primary/30' : '' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Page {{ $index + 1 }}</p>
                                    <p class="mt-0.5 font-semibold text-brand-text">{{ $page->title }}</p>
                                    @if (filled($page->body))
                                        <p class="mt-1 line-clamp-2 text-sm text-brand-text/60 whitespace-pre-line">{{ $page->body }}</p>
                                    @endif
                                    <p class="mt-2 text-xs text-brand-text/50">
                                        {{ $page->sections->count() }} subtopic{{ $page->sections->count() === 1 ? '' : 's' }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <div class="flex gap-1">
                                        <form method="post" action="{{ route('admin.training.pages.move', [$module->id, $page->id]) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="rounded-lg border border-brand-border bg-white px-2 py-1 text-xs font-semibold text-brand-text hover:bg-brand-surface" @disabled($index === 0)>↑</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.training.pages.move', [$module->id, $page->id]) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="rounded-lg border border-brand-border bg-white px-2 py-1 text-xs font-semibold text-brand-text hover:bg-brand-surface" @disabled($index === $module->pages->count() - 1)>↓</button>
                                        </form>
                                    </div>
                                    <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'study', 'edit_page' => $page->id]) }}" class="text-xs font-semibold text-brand-primary hover:underline">Edit</a>
                                    <form method="post"
                                          action="{{ route('admin.training.pages.destroy', [$module->id, $page->id]) }}"
                                          data-confirm="This study page and its subtopics will be removed from the module."
                                          data-confirm-title="Remove study page?"
                                          data-confirm-confirm="Remove"
                                          data-confirm-cancel="Keep page"
                                          data-confirm-danger="1">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Remove</button>
                                    </form>
                                </div>
                            </div>

                            @if ($editingPage && (int) $editingPage->id === (int) $page->id)
                                <div class="mt-4 border-t border-brand-border pt-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Subtopics (toggleable in app)</p>
                                    <div class="mt-3 space-y-2">
                                        @forelse ($editingPage->sections as $sIndex => $section)
                                            <div class="rounded-lg border border-brand-border bg-white px-3 py-3">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-semibold text-brand-text">{{ $section->title }}</p>
                                                        <p class="mt-0.5 line-clamp-2 text-xs text-brand-text/55 whitespace-pre-line">{{ $section->body }}</p>
                                                    </div>
                                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                                        <div class="flex gap-1">
                                                            <form method="post" action="{{ route('admin.training.sections.move', [$module->id, $page->id, $section->id]) }}">
                                                                @csrf
                                                                <input type="hidden" name="direction" value="up">
                                                                <button type="submit" class="rounded border border-brand-border px-1.5 py-0.5 text-[10px] font-semibold" @disabled($sIndex === 0)>↑</button>
                                                            </form>
                                                            <form method="post" action="{{ route('admin.training.sections.move', [$module->id, $page->id, $section->id]) }}">
                                                                @csrf
                                                                <input type="hidden" name="direction" value="down">
                                                                <button type="submit" class="rounded border border-brand-border px-1.5 py-0.5 text-[10px] font-semibold" @disabled($sIndex === $editingPage->sections->count() - 1)>↓</button>
                                                            </form>
                                                        </div>
                                                        <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'study', 'edit_page' => $page->id, 'edit_section' => $section->id]) }}" class="text-[11px] font-semibold text-brand-primary hover:underline">Edit</a>
                                                        <form method="post"
                                                              action="{{ route('admin.training.sections.destroy', [$module->id, $page->id, $section->id]) }}"
                                                              data-confirm="This subtopic will be removed."
                                                              data-confirm-title="Remove subtopic?"
                                                              data-confirm-confirm="Remove"
                                                              data-confirm-cancel="Keep"
                                                              data-confirm-danger="1">
                                                            @csrf
                                                            <button type="submit" class="text-[11px] font-semibold text-red-600 hover:underline">Remove</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-sm text-brand-text/55">No subtopics yet. Add one in the form on the right if this page needs expandable sections.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-brand-border px-4 py-10 text-center">
                            <p class="text-sm font-semibold text-brand-text">No pages yet</p>
                            <p class="mt-1 text-sm text-brand-text/55">Add your first study page using the form on the right.</p>
                        </div>
                    @endforelse

                    <div class="pt-2">
                        <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'questions']) }}"
                           class="inline-flex rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">
                            Continue to questions
                        </a>
                    </div>
                </div>
            </section>

            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
                    <div class="border-b border-brand-border px-5 py-4">
                        <h3 class="font-bold text-brand-text">{{ $editingPage ? 'Edit page' : 'Add page' }}</h3>
                    </div>
                    <form method="post"
                          action="{{ $editingPage ? route('admin.training.pages.update', [$module->id, $editingPage->id]) : route('admin.training.pages.store', $module->id) }}"
                          class="space-y-3 px-5 py-4">
                        @csrf
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Page title</span>
                            <input type="text" name="title" required maxlength="200" class="{{ $in }}"
                                   value="{{ old('title', $editingPage->title ?? '') }}"
                                   placeholder="e.g. How to mop floors">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Intro content <span class="normal-case font-normal text-brand-text/45">(optional)</span></span>
                            <textarea name="body" rows="6" class="{{ $in }}" placeholder="Optional intro shown above subtopics…">{{ old('body', $editingPage->body ?? '') }}</textarea>
                        </label>
                        <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">
                            {{ $editingPage ? 'Save page' : 'Add page' }}
                        </button>
                        @if ($editingPage)
                            <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'study']) }}" class="block text-center text-sm font-semibold text-brand-text/60 hover:underline">Done editing</a>
                        @endif
                    </form>
                </div>

                @if ($editingPage)
                    <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
                        <div class="border-b border-brand-border px-5 py-4">
                            <h3 class="font-bold text-brand-text">{{ $editingSection ? 'Edit subtopic' : 'Add subtopic' }}</h3>
                            <p class="mt-1 text-xs text-brand-text/55">Shown as a toggle under this page in the mobile app.</p>
                        </div>
                        <form method="post"
                              action="{{ $editingSection
                                  ? route('admin.training.sections.update', [$module->id, $editingPage->id, $editingSection->id])
                                  : route('admin.training.sections.store', [$module->id, $editingPage->id]) }}"
                              class="space-y-3 px-5 py-4">
                            @csrf
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Subtopic title</span>
                                <input type="text" name="section_title" required maxlength="200" class="{{ $in }}"
                                       value="{{ old('section_title', $editingSection->title ?? '') }}"
                                       placeholder="e.g. Safety checklist">
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Subtopic content</span>
                                <textarea name="section_body" rows="7" required class="{{ $in }}" placeholder="Content shown when the employee expands this subtopic…">{{ old('section_body', $editingSection->body ?? '') }}</textarea>
                            </label>
                            <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">
                                {{ $editingSection ? 'Save subtopic' : 'Add subtopic' }}
                            </button>
                            @if ($editingSection)
                                <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'study', 'edit_page' => $editingPage->id]) }}" class="block text-center text-sm font-semibold text-brand-text/60 hover:underline">Cancel edit</a>
                            @endif
                        </form>
                    </div>
                @endif
            </aside>
        </div>
    @endif

    {{-- QUESTIONS --}}
    @if ($step === 'questions')
        <div class="grid gap-6 lg:grid-cols-[1fr_400px]">
            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
                <div class="border-b border-brand-border px-5 py-5 sm:px-6">
                    <h2 class="text-lg font-bold text-brand-text">Quiz questions</h2>
                    <p class="mt-1 text-sm text-brand-text/60">Multiple choice only. Question order is randomized for each employee.</p>
                </div>
                <div class="space-y-3 px-5 py-5 sm:px-6">
                    @forelse ($module->questions as $index => $question)
                        <div class="rounded-xl border border-brand-border bg-brand-surface/30 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-brand-text">{{ $index + 1 }}. {{ $question->question_text }}</p>
                                <form method="post"
                                      action="{{ route('admin.training.questions.destroy', [$module->id, $question->id]) }}"
                                      data-confirm="This question will be removed from the quiz."
                                      data-confirm-title="Remove question?"
                                      data-confirm-confirm="Remove"
                                      data-confirm-cancel="Keep question"
                                      data-confirm-danger="1">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Remove</button>
                                </form>
                            </div>
                            <ul class="mt-2 space-y-1">
                                @foreach ($question->options as $option)
                                    <li class="text-xs {{ $option->is_correct ? 'font-semibold text-emerald-700' : 'text-brand-text/70' }}">
                                        {{ $option->is_correct ? '✓' : '○' }} {{ $option->option_text }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-brand-border px-4 py-10 text-center">
                            <p class="text-sm font-semibold text-brand-text">No questions yet</p>
                            <p class="mt-1 text-sm text-brand-text/55">Add quiz questions using the form on the right.</p>
                        </div>
                    @endforelse

                    <div class="pt-2">
                        <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'assign']) }}"
                           class="inline-flex rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">
                            Continue to assign
                        </a>
                    </div>
                </div>
            </section>

            <aside class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm lg:sticky lg:top-6 lg:self-start">
                <div class="border-b border-brand-border px-5 py-4">
                    <h3 class="font-bold text-brand-text">Add question</h3>
                </div>
                <form method="post" action="{{ route('admin.training.questions.store', $module->id) }}" class="space-y-3 px-5 py-4">
                    @csrf
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Question</span>
                        <textarea name="question_text" rows="2" required class="{{ $in }}" placeholder="e.g. How long should disinfectant sit?">{{ old('question_text') }}</textarea>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Points</span>
                        <input type="number" name="points" min="1" max="100" value="{{ old('points', 1) }}" class="{{ $in }}">
                    </label>
                    <div class="space-y-2">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Options (mark correct)</span>
                        @for ($i = 0; $i < 4; $i++)
                            <div class="flex items-center gap-2">
                                <input type="radio" name="correct_index" value="{{ $i }}" @checked((int) old('correct_index', 0) === $i) class="size-4 text-brand-primary" {{ $i < 2 ? 'required' : '' }}>
                                <input type="text" name="options[{{ $i }}][text]" value="{{ old('options.'.$i.'.text') }}" class="{{ $in }}" placeholder="Option {{ $i + 1 }}{{ $i < 2 ? '' : ' (optional)' }}" {{ $i < 2 ? 'required' : '' }}>
                            </div>
                        @endfor
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">Add question</button>
                </form>
            </aside>
        </div>
    @endif

    {{-- ASSIGN --}}
    @if ($step === 'assign')
        <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border px-5 py-5 sm:px-6">
                <h2 class="text-lg font-bold text-brand-text">Assign to employees</h2>
                <p class="mt-1 text-sm text-brand-text/60">
                    @if ($canAssign)
                        Select who should receive this module. Already-assigned people are skipped.
                        @if (! $isPublished)
                            Assigning will also publish this module so employees can see it.
                        @endif
                    @else
                        Add study pages and questions first, then come back here to assign.
                    @endif
                </p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-brand-surface px-2.5 py-1 font-semibold text-brand-text/70">{{ $module->pages->count() }} pages</span>
                    <span class="rounded-full bg-brand-surface px-2.5 py-1 font-semibold text-brand-text/70">{{ $module->questions->count() }} questions</span>
                    <span class="rounded-full px-2.5 py-1 font-semibold {{ $isPublished ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $isPublished ? 'Published' : 'Draft' }}</span>
                </div>
            </div>

            @if (! $canAssign)
                <div class="px-5 py-8 sm:px-6">
                    <p class="text-sm text-brand-text/65">Checklist before assign:</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li class="{{ $module->pages->count() > 0 ? 'text-emerald-700' : 'text-brand-text/70' }}">{{ $module->pages->count() > 0 ? '✓' : '○' }} At least one study page</li>
                        <li class="{{ $module->questions->count() > 0 ? 'text-emerald-700' : 'text-brand-text/70' }}">{{ $module->questions->count() > 0 ? '✓' : '○' }} At least one question</li>
                    </ul>
                    <div class="mt-6 flex flex-wrap gap-2">
                        @if ($module->pages->count() < 1)
                            <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'study']) }}" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white">Add study pages</a>
                        @else
                            <a href="{{ route('admin.training.show', ['module' => $module->id, 'step' => 'questions']) }}" class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white">Add questions</a>
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
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Due date (optional)</span>
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
