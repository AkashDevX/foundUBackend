@php
    $applicantSearchUrl = route('admin.applicants.search');
    $applicantSearchInputClass = 'w-full rounded-xl border border-brand-border bg-white py-2.5 pl-10 pr-20 text-sm text-brand-text shadow-sm placeholder:text-brand-text-secondary transition focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20';
@endphp

{{-- Mobile: open spotlight search --}}
<button
    type="button"
    data-admin-applicant-search-open
    class="inline-flex items-center justify-center rounded-xl border border-brand-border bg-white p-2.5 text-brand-icon shadow-sm transition hover:border-brand-primary/40 hover:text-brand-primary sm:hidden"
    aria-label="Search applicants"
>
    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
</button>

{{-- Desktop inline search --}}
<div
    class="relative hidden w-full max-w-[22rem] sm:block lg:max-w-[24rem]"
    data-admin-applicant-search
    data-search-url="{{ $applicantSearchUrl }}"
    data-search-mode="inline"
>
    @include('admin.partials.applicant-search-field', [
        'inputClass' => $applicantSearchInputClass.' sm:min-w-[13rem] lg:min-w-[15rem]',
        'inputId' => 'admin-applicant-search-input',
        'listboxId' => 'admin-applicant-search-suggestions',
    ])
</div>

{{-- Mobile + keyboard shortcut spotlight overlay --}}
<div
    data-admin-applicant-search-overlay
    class="fixed inset-0 z-[70] hidden items-start justify-center bg-brand-primary-dark/45 p-4 pt-[max(1rem,env(safe-area-inset-top))] backdrop-blur-[2px] sm:items-start sm:justify-end sm:bg-transparent sm:p-0 sm:pt-[4.25rem] sm:pr-6 sm:backdrop-blur-none lg:pr-8"
    aria-hidden="true"
>
    <button
        type="button"
        data-admin-applicant-search-close
        class="absolute inset-0 sm:hidden"
        aria-label="Close search"
        tabindex="-1"
    ></button>

    <div
        class="relative z-10 w-full max-w-lg sm:max-w-[24rem]"
        data-admin-applicant-search
        data-search-url="{{ $applicantSearchUrl }}"
        data-search-mode="overlay"
    >
        <div class="mb-3 flex items-center justify-between gap-3 sm:hidden">
            <div>
                <p class="text-sm font-bold text-white drop-shadow-sm">Search applicants</p>
                <p class="text-xs text-white/80">Find someone and open their profile.</p>
            </div>
            <button
                type="button"
                data-admin-applicant-search-close
                class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/95 p-2 text-brand-text shadow-sm"
                aria-label="Close search"
            >
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        @include('admin.partials.applicant-search-field', [
            'inputClass' => $applicantSearchInputClass.' text-base shadow-lg ring-1 ring-black/[0.06]',
            'inputId' => 'admin-applicant-search-input-overlay',
            'listboxId' => 'admin-applicant-search-suggestions-overlay',
        ])
    </div>
</div>
