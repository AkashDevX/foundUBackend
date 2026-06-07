@php
    $docHasFile = ($doc['storage_path'] ?? null) !== null && ($doc['storage_path'] ?? '') !== '';
    $rawType = $doc['row_key'] !== null ? $idTypeForKey($doc['row_key']) : '';
    $picklist = $registrationPicklists->get('id_document_type', collect());
    $docTypeSelected = $doc['row_key'] !== null
        ? \App\Support\RegistrationDisplay::matchPicklistValue(
            (string) old('id_document_type.'.$doc['row_key'], $rawType),
            $picklist
        )
        : '';
    $uploadInputId = 'id-doc-upload-'.($doc['row_key'] ?? uniqid());
    $previewId = 'id-doc-preview-'.($doc['row_key'] ?? uniqid());
@endphp
<div class="overflow-hidden rounded-xl border p-4 {{ $docHasFile ? 'border-emerald-200/90 bg-emerald-50/40' : 'border-brand-border bg-brand-surface/40' }}" data-reg-id-doc-card>
    @if ($canEditProfile && $doc['row_key'] !== null)
        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">Document type</label>
        <select name="id_document_type[{{ $doc['row_key'] }}]" class="{{ $editIn }} mb-3">
            <option value="">— Select type —</option>
            @foreach ($picklist as $item)
                <option value="{{ $item->value }}" @selected($docTypeSelected === $item->value)>{{ $item->label ?: $item->value }}</option>
            @endforeach
        </select>
    @endif
    <div class="mb-3 flex items-center justify-between gap-3">
        @if ($docHasFile)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600/15 px-2.5 py-1 text-xs font-bold text-emerald-800">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                Uploaded
            </span>
        @else
            <span class="text-xs font-medium text-brand-text-secondary">No file yet</span>
        @endif
    </div>
    <div class="mt-1 overflow-hidden rounded-lg border border-brand-border bg-white" data-reg-doc-preview-wrap>
        @if ($docHasFile && $doc['row_key'] !== null)
            @php $url = $fileUrl('id-document', $doc['row_key']); @endphp
            @if (\App\Support\RegistrationDisplay::isLikelyImagePath($doc['storage_path']))
                <img src="{{ $url }}" alt="Uploaded document" id="{{ $previewId }}" class="max-h-72 w-full object-contain" loading="lazy" data-reg-doc-preview />
            @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($doc['storage_path']))
                <div class="flex items-center justify-between gap-3 px-3 py-3" data-reg-doc-preview>
                    <span class="text-sm font-medium text-brand-text">PDF on file</span>
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-brand-primary-dark">View PDF</a>
                </div>
            @else
                <div class="px-3 py-3" data-reg-doc-preview>
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex text-xs font-bold text-brand-link hover:underline">Open uploaded file</a>
                </div>
            @endif
        @else
            <img src="" alt="" id="{{ $previewId }}" class="hidden max-h-72 w-full object-contain" data-reg-doc-preview />
            <p class="hidden px-3 py-6 text-center text-xs text-brand-text-secondary" data-reg-doc-preview-empty>No preview</p>
        @endif
    </div>
    @if ($canEditProfile && $doc['row_key'] !== null)
        <div class="mt-3">
            <input type="file" name="id_document_upload[{{ $doc['row_key'] }}]" id="{{ $uploadInputId }}" class="hidden" accept="image/*,.pdf" data-reg-doc-input />
            <label for="{{ $uploadInputId }}" class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-brand-border bg-white px-3 py-2 text-xs font-semibold text-brand-primary shadow-sm transition hover:bg-brand-surface">
                {{ $docHasFile ? 'Replace file' : 'Upload file' }}
            </label>
            <p class="mt-1 text-xs text-brand-text-secondary" data-reg-doc-filename hidden></p>
        </div>
    @endif
</div>
