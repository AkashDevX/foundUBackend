@php
    /** @var bool $isUploaded */
    /** @var string $inputName */
    /** @var string|null $declarationValue */
    $declVal = $yesNoPickVal($declarationValue ?? null);
    if ($isUploaded && $declVal !== 'Yes') {
        $declVal = 'Yes';
    }
@endphp
@if ($canEditProfile)
    <input type="hidden" name="{{ $inputName }}" value="{{ $isUploaded || $declVal === 'Yes' ? 'Yes' : 'No' }}" />
@endif
<div class="inline-flex min-w-0 items-center gap-3 rounded-xl border px-4 py-3 {{ $isUploaded ? 'border-emerald-200/90 bg-emerald-50/70' : 'border-brand-border bg-brand-surface/50' }}">
    <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $isUploaded ? 'bg-emerald-600 text-white' : 'bg-brand-border/80 text-brand-text-secondary' }}" aria-hidden="true">
        @if ($isUploaded)
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        @else
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        @endif
    </span>
    <span class="min-w-0">
        <span class="block text-sm font-bold text-brand-text">{{ $isUploaded ? 'Uploaded' : 'Not uploaded' }}</span>
        @if (! $isUploaded)
            <span class="mt-0.5 block text-xs text-brand-text-secondary">No file on record yet.</span>
        @endif
    </span>
</div>
