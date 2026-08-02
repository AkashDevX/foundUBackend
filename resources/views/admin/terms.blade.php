@extends('layouts.admin')

@section('title', 'Terms and conditions')

@section('heading', 'Terms and conditions')

@section('subheading')
    {{ $company->name }} — edit the legal text shown in the mobile create-account modal.
@endsection

@section('content')
    @php
        $in = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
        $lbl = 'text-xs font-semibold uppercase tracking-wide text-brand-label';
        /** @var \App\Models\TermsAndConditions $terms */
        $contentValue = old('content', $terms->content);
        $dateValue = old('last_updated_on', optional($terms->last_updated_on)->format('Y-m-d'));
    @endphp

    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
        <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-5 sm:px-6">
            <h2 class="text-base font-bold text-brand-text">Edit Terms and Conditions</h2>
            <p class="mt-1 text-sm text-brand-text-secondary">
                Applicants see this when they tap “Terms and conditions” on the last create-account step.
                Use numbered headings (e.g. <span class="font-mono text-xs">1. Introduction</span>) on their own line to create sections.
            </p>
        </header>

        <form method="post" action="{{ route('admin.terms.update') }}" class="space-y-5 px-5 py-6 sm:px-6">
            @csrf
            @method('PUT')

            <div class="space-y-1.5">
                <label for="last_updated_on" class="{{ $lbl }}">Last updated</label>
                <input
                    id="last_updated_on"
                    type="date"
                    name="last_updated_on"
                    required
                    class="{{ $in }} max-w-xs"
                    value="{{ $dateValue }}"
                />
            </div>

            <div class="space-y-1.5">
                <label for="terms-content" class="{{ $lbl }}">Content</label>
                <textarea
                    id="terms-content"
                    name="content"
                    required
                    rows="22"
                    class="{{ $in }} min-h-[28rem] font-mono text-[13px] leading-relaxed"
                    placeholder="Write the full terms and conditions here…"
                >{{ $contentValue }}</textarea>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    class="rounded-xl bg-brand-primary px-5 py-3 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark"
                >
                    Save terms and conditions
                </button>
                <p class="text-xs text-brand-text-secondary">Changes appear in the app after the next refresh of the modal.</p>
            </div>
        </form>
    </section>
@endsection
