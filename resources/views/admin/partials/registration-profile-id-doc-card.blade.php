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
    @if ($doc['row_key'] !== null)
        @include('admin.partials.registration-profile-file-upload-controls', [
            'storagePath' => $doc['storage_path'],
            'fileUrl' => $docHasFile ? $fileUrl('id-document', $doc['row_key']) : null,
            'inputName' => 'id_document_upload['.$doc['row_key'].']',
            'removeInputName' => 'remove_id_document_upload['.$doc['row_key'].']',
            'uploadInputId' => $uploadInputId,
            'canEditProfile' => $canEditProfile,
            'previewId' => $previewId,
        ])
    @endif
</div>
