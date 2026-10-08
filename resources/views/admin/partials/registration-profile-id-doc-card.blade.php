@php
    $docHasFile = ($doc['storage_path'] ?? null) !== null && ($doc['storage_path'] ?? '') !== '';
    $backPath = is_string($doc['back_storage_path'] ?? null) && $doc['back_storage_path'] !== '' ? $doc['back_storage_path'] : null;
    $rawType = $doc['row_key'] !== null ? $idTypeForKey($doc['row_key']) : '';
    $picklist = $registrationPicklists->get('id_document_type', collect());
    $docTypeSelected = $doc['row_key'] !== null
        ? \App\Support\RegistrationDisplay::matchPicklistValue(
            (string) old('id_document_type.'.$doc['row_key'], $rawType),
            $picklist
        )
        : '';
    $isLicence = \App\Support\RegistrationDisplay::isDriversLicenceType($docTypeSelected !== '' ? $docTypeSelected : $rawType);
    $showBack = $isLicence || $backPath !== null;
    $uploadInputId = 'id-doc-upload-'.($doc['row_key'] ?? uniqid());
    $previewId = 'id-doc-preview-'.($doc['row_key'] ?? uniqid());
    $backUploadInputId = 'id-doc-back-upload-'.($doc['row_key'] ?? uniqid());
    $backPreviewId = 'id-doc-back-preview-'.($doc['row_key'] ?? uniqid());
@endphp
<div class="overflow-hidden rounded-xl border p-4 {{ $docHasFile ? 'border-emerald-200/90 bg-emerald-50/40' : 'border-brand-border bg-brand-surface/40' }}" data-reg-id-doc-card>
    @unless ($canEditProfile)
        <p class="mb-3 text-sm font-bold text-brand-text">{{ $doc['title'] ?? 'ID document' }}</p>
    @endunless
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
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-label {{ $isLicence ? '' : 'hidden' }}" data-reg-id-doc-front-label>Front</p>
        @include('admin.partials.registration-profile-file-upload-controls', [
            'storagePath' => $doc['storage_path'],
            'fileUrl' => $docHasFile ? $fileUrl('id-document', $doc['row_key']) : null,
            'inputName' => 'id_document_upload['.$doc['row_key'].']',
            'removeInputName' => 'remove_id_document_upload['.$doc['row_key'].']',
            'uploadInputId' => $uploadInputId,
            'canEditProfile' => $canEditProfile,
            'previewId' => $previewId,
            'accept' => '.jpg,.jpeg,.png,.pdf,.doc,.docx,image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])
        @if ($canEditProfile || $showBack)
            <div class="mt-4 {{ $showBack ? '' : 'hidden' }}" data-reg-id-doc-back @if ($backPath) data-reg-id-doc-back-has-file="1" @endif>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-label">Back</p>
                @include('admin.partials.registration-profile-file-upload-controls', [
                    'storagePath' => $backPath,
                    'fileUrl' => $backPath ? $fileUrl('id-document-back', $doc['row_key']) : null,
                    'inputName' => 'id_document_back_upload['.$doc['row_key'].']',
                    'removeInputName' => 'remove_id_document_back_upload['.$doc['row_key'].']',
                    'uploadInputId' => $backUploadInputId,
                    'canEditProfile' => $canEditProfile,
                    'previewId' => $backPreviewId,
                    'accept' => '.jpg,.jpeg,.png,.pdf,.doc,.docx,image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
            </div>
        @endif
    @endif
</div>
