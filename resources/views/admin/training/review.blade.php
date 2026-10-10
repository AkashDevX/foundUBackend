@extends('layouts.admin')

@section('title', 'Review — '.$module->title)

@section('heading', 'Quiz review')

@section('subheading')
    {{ $assignment->employee?->full_legal_name ?: $assignment->employee?->email ?: 'Employee' }}
@endsection

@section('content')
    @php
        $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
    @endphp

    <div class="mb-5">
        <a href="{{ route('admin.training.results', $module->id) }}" class="text-sm font-semibold text-brand-primary hover:underline">← Results</a>
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

    @forelse ($attempts as $attempt)
        @php
            $pending = $attempt->answers->contains(fn ($answer) => $answer->review_status === 'pending');
            $latest = $loop->first;
        @endphp
        <section class="mb-6 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
            <div class="border-b border-brand-border px-5 py-5 sm:px-6">
                <h2 class="text-lg font-bold text-brand-text">Attempt {{ $attempt->attempt_number ?: $loop->iteration }}</h2>
                <p class="mt-1 text-sm text-brand-text/60">
                    @if ($attempt->waived)
                        Quiz skipped. Training completed from the study pages.
                    @elseif ($attempt->submitted_at)
                        Submitted {{ \App\Support\DisplayTimezone::format($attempt->submitted_at, 'j M Y, g:ia') }}
                        · {{ $attempt->score ?? 0 }}/{{ $attempt->max_score ?? 0 }}
                        @if ($attempt->percent !== null)
                            · {{ rtrim(rtrim(number_format((float) $attempt->percent, 1), '0'), '.') }}%
                        @endif
                        @if ($pending)
                            · Waiting for review
                        @elseif ($attempt->passed === true)
                            · Passed
                        @elseif ($attempt->passed === false)
                            · Failed
                        @endif
                    @else
                        In progress
                    @endif
                </p>
            </div>

            @if ($latest && $pending)
                <form method="post" action="{{ route('admin.training.assignments.review.store', [$module->id, $assignment->id]) }}" class="divide-y divide-brand-border">
                    @csrf
                    @foreach ($attempt->answers as $answer)
                        @php $question = $answer->question; @endphp
                        @continue (! $question)
                        <div class="px-5 py-4 sm:px-6">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">{{ \App\Support\TrainingQuiz::label(\App\Support\TrainingQuiz::typeOf($question)) }} · {{ $question->points }} pts</p>
                            @if ($question->prompt)
                                <p class="mt-1 text-sm text-brand-text/70">{{ $question->prompt }}</p>
                            @endif
                            <p class="mt-1 text-sm font-semibold text-brand-text">{{ $question->question_text }}</p>
                            <p class="mt-2 text-sm text-brand-text"><span class="font-semibold">Response:</span> {{ \App\Support\TrainingQuiz::givenText($question, $answer) }}</p>
                            <p class="mt-1 text-sm text-emerald-700"><span class="font-semibold">Expected:</span> {{ \App\Support\TrainingQuiz::preview($question) }}</p>
                            @if ($answer->review_status)
                                <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_140px] sm:items-end">
                                    <div class="flex gap-4 text-sm">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="radio" name="marks[{{ $answer->id }}][correct]" value="1" @checked($answer->review_status === 'approved' || $answer->is_correct) class="size-4 text-brand-primary" required>
                                            Correct
                                        </label>
                                        <label class="inline-flex items-center gap-2">
                                            <input type="radio" name="marks[{{ $answer->id }}][correct]" value="0" @checked($answer->review_status === 'rejected') class="size-4 text-brand-primary">
                                            Incorrect
                                        </label>
                                    </div>
                                    <label class="block">
                                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Points if correct</span>
                                        <input type="number" name="marks[{{ $answer->id }}][points]" min="0" max="{{ max(1, (int) $question->points) }}" value="{{ $answer->points_awarded ?: $question->points }}" class="{{ $in }}">
                                    </label>
                                </div>
                            @else
                                <p class="mt-2 text-xs font-semibold {{ $answer->is_correct ? 'text-emerald-700' : 'text-red-600' }}">{{ $answer->is_correct ? 'Marked correct' : 'Marked incorrect' }}</p>
                            @endif
                        </div>
                    @endforeach
                    <div class="px-5 py-4 sm:px-6">
                        <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">Save marks</button>
                    </div>
                </form>
            @else
                <div class="divide-y divide-brand-border">
                    @foreach ($attempt->answers as $answer)
                        @php $question = $answer->question; @endphp
                        @continue (! $question)
                        <div class="px-5 py-4 sm:px-6">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">{{ \App\Support\TrainingQuiz::label(\App\Support\TrainingQuiz::typeOf($question)) }}</p>
                            <p class="mt-1 text-sm font-semibold text-brand-text">{{ $question->question_text }}</p>
                            <p class="mt-2 text-sm text-brand-text">{{ \App\Support\TrainingQuiz::givenText($question, $answer) }}</p>
                            <p class="mt-1 text-xs font-semibold {{ $answer->review_status === 'pending' ? 'text-amber-700' : ($answer->is_correct ? 'text-emerald-700' : 'text-red-600') }}">
                                @if ($answer->review_status === 'pending') Waiting for review
                                @elseif ($answer->is_correct) Correct · {{ $answer->points_awarded ?? $question->points }} pts
                                @else Incorrect
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @empty
        <section class="rounded-2xl border border-brand-border bg-white px-5 py-12 text-center text-sm text-brand-text/60 shadow-sm">
            This employee has not started the quiz.
        </section>
    @endforelse
@endsection
