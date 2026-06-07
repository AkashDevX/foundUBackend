@php
    /** @var string|null $storagePath */
    /** @var string|null $fileUrl */
    /** @var string $inputName */
    /** @var string $uploadInputId */
    /** @var bool $canEditProfile */
    $hasFile = ($storagePath ?? null) !== null && ($storagePath ?? '') !== '';
@endphp
<div class="overflow-hidden rounded-xl border p-4 {{ $hasFile ? 'border-emerald-200/90 bg-emerald-50/40' : 'border-brand-border bg-brand-surface/40' }}" data-reg-id-doc-card>
    @isset($beforeUpload)
        {!! $beforeUpload !!}
    @endisset
    @include('admin.partials.registration-profile-file-upload-controls', [
        'storagePath' => $storagePath,
        'fileUrl' => $fileUrl,
        'inputName' => $inputName,
        'uploadInputId' => $uploadInputId,
        'canEditProfile' => $canEditProfile,
        'previewId' => $previewId ?? null,
        'accept' => $accept ?? null,
    ])
</div>
