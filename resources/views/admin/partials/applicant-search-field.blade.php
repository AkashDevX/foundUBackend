<label class="relative block">
    <span class="sr-only">Search applicants</span>
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-brand-icon">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
    </span>
    <input
        type="search"
        id="{{ $inputId }}"
        data-admin-applicant-search-input
        placeholder="Name, email, or employee ID…"
        autocomplete="off"
        aria-autocomplete="list"
        aria-controls="{{ $listboxId }}"
        aria-expanded="false"
        class="{{ $inputClass }}"
    />
    <span
        data-admin-applicant-search-shortcut
        class="pointer-events-none absolute inset-y-0 right-10 my-auto hidden h-6 items-center rounded-md border border-brand-border bg-brand-surface px-1.5 text-[10px] font-semibold uppercase tracking-wide text-brand-text-secondary sm:inline-flex"
        aria-hidden="true"
    >Ctrl K</span>
    <button
        type="button"
        data-admin-applicant-search-clear
        class="absolute right-1.5 top-1/2 z-10 hidden -translate-y-1/2 rounded-lg p-1.5 text-brand-text-secondary transition hover:bg-brand-surface hover:text-brand-text focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/40"
        aria-label="Clear search"
        tabindex="-1"
    >
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>
    <span
        data-admin-applicant-search-loading
        class="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2"
        aria-hidden="true"
    >
        <span class="inline-block size-4 animate-spin rounded-full border-2 border-brand-primary/20 border-t-brand-primary"></span>
    </span>
</label>

<div
    id="{{ $listboxId }}"
    data-admin-applicant-search-suggestions
    class="hidden"
    role="listbox"
    aria-label="Applicant suggestions"
></div>
