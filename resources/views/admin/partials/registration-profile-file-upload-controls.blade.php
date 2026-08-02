@php
    /** @var string|null $storagePath */
    /** @var string|null $fileUrl */
    /** @var string $inputName */
    /** @var string $uploadInputId */
    /** @var bool $canEditProfile */
    /** @var string|null $removeInputName */
    $hasFile = ($storagePath ?? null) !== null && ($storagePath ?? '') !== '';
    $previewId = $previewId ?? $uploadInputId.'-preview';
    $accept = $accept ?? 'image/*,.pdf';
    $removeInputName = $removeInputName ?? 'remove_'.$inputName;
@endphp
<div data-reg-doc-root>
<div class="mb-3 flex items-center justify-between gap-3">
    @if ($hasFile)
        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600/15 px-2.5 py-1 text-xs font-bold text-emerald-800" data-reg-doc-status>
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            Uploaded
        </span>
    @else
        <span class="text-xs font-medium text-brand-text-secondary" data-reg-doc-status>No file yet</span>
    @endif
</div>
<div class="mt-1 overflow-hidden rounded-lg border border-brand-border bg-white" data-reg-doc-preview-wrap>
    @if ($hasFile && ($fileUrl ?? null))
        @if (\App\Support\RegistrationDisplay::isLikelyImagePath($storagePath))
            <img src="{{ $fileUrl }}" alt="Uploaded document" id="{{ $previewId }}" class="max-h-72 w-full object-contain" loading="lazy" data-reg-doc-preview />
        @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($storagePath))
            <div class="flex items-center justify-between gap-3 px-3 py-3" data-reg-doc-preview>
                <span class="text-sm font-medium text-brand-text">PDF on file</span>
                <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-brand-primary-dark">View PDF</a>
            </div>
        @else
            <div class="px-3 py-3" data-reg-doc-preview>
                <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex text-xs font-bold text-brand-link hover:underline">Open uploaded file</a>
            </div>
        @endif
    @else
        <img src="" alt="" id="{{ $previewId }}" class="hidden max-h-72 w-full object-contain" data-reg-doc-preview />
    @endif
</div>
@if ($canEditProfile)
    <div class="mt-3" data-reg-doc-actions>
        <input type="hidden" name="{{ $removeInputName }}" value="0" data-reg-doc-remove-flag />
        <input type="file" name="{{ $inputName }}" id="{{ $uploadInputId }}" class="hidden" accept="{{ $accept }}" data-reg-doc-input />
        <div class="flex flex-wrap items-center gap-2">
            <label for="{{ $uploadInputId }}" class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-primary shadow-sm transition hover:bg-brand-surface" data-reg-doc-replace>
                {{ $hasFile ? 'Replace file' : 'Upload file' }}
            </label>
            @if ($hasFile)
                <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm transition hover:bg-red-50" data-reg-doc-remove>
                    Remove file
                </button>
            @endif
        </div>
        <p class="mt-1 text-xs text-brand-text-secondary" data-reg-doc-filename hidden></p>
    </div>
@endif
</div>
