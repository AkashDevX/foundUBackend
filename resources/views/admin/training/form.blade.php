@extends('layouts.admin')

@section('title', $module ? 'Edit training' : 'New training')

@section('heading', $module ? 'Edit module' : 'New training module')

@section('subheading')
    {{ $company->name }}
@endsection

@section('content')
    @php
        $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.training.index') }}" class="text-sm font-semibold text-brand-primary hover:underline">← All training</a>
    </div>

    <section class="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border px-5 py-5 sm:px-6">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Step 1 of 4</p>
            <h2 class="mt-1 text-lg font-bold text-brand-text">Module overview</h2>
            <p class="mt-1 text-sm text-brand-text/60">Name the module. You’ll add study pages and questions next.</p>
        </div>

        <form method="post" action="{{ route('admin.training.store') }}" class="space-y-4 px-5 py-5 sm:px-6">
            @csrf
            <input type="hidden" name="status" value="draft">

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Title</span>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="200" class="{{ $in }}" placeholder="e.g. Office kitchen deep clean">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Description</span>
                <textarea name="description" rows="3" class="{{ $in }}" placeholder="What employees should learn">{{ old('description') }}</textarea>
            </label>

            <label class="block max-w-xs">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Pass mark %</span>
                <input type="number" name="pass_percent" min="1" max="100" value="{{ old('pass_percent', 70) }}" class="{{ $in }}">
            </label>

            <label class="block max-w-xs">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-brand-label">Seconds per question</span>
                <input type="number" name="question_time_seconds" min="10" max="600" value="{{ old('question_time_seconds', 45) }}" class="{{ $in }}">
            </label>

            <div class="flex flex-wrap gap-2 pt-2">
                <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primary/90">
                    Create &amp; add pages
                </button>
                <a href="{{ route('admin.training.index') }}" class="rounded-xl border border-brand-border px-5 py-2.5 text-sm font-semibold text-brand-text hover:bg-brand-surface">Cancel</a>
            </div>
        </form>
    </section>
@endsection
