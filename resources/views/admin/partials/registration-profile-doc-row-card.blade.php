@php
    /** @var array{title: string, meta: list<string>, storage_path: ?string, row_key: ?string, expiry_input?: string, expiry_display?: ?string} $doc */
    /** @var bool $canEditProfile */
  $hasFile = ($doc['storage_path'] ?? null) !== null && ($doc['storage_path'] ?? '') !== '';
    $uploadInputId = $uploadInputId ?? ('reg-'.$fileUrlKind.'-upload-'.($doc['row_key'] ?? uniqid()));
    $typePicklist = $registrationPicklists->get($picklistKey, collect());
    $expiryFieldName = $expiryFieldName ?? 'licence_expiry_row';
    $expiryInput = $doc['expiry_input'] ?? '';
    $expiryDisplay = $doc['expiry_display'] ?? null;
@endphp
<div class="overflow-hidden rounded-xl border p-4 {{ $hasFile ? 'border-emerald-200/90 bg-emerald-50/40' : 'border-brand-border bg-brand-surface/40' }}" data-reg-id-doc-card>
    @if ($canEditProfile && $doc['row_key'] !== null)
        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">{{ $typeLabel }}</label>
        <select name="{{ $typeFieldName }}[{{ $doc['row_key'] }}]" class="{{ $editIn }} mb-3">
            <option value="">—</option>
            @foreach ($typePicklist as $item)
                <option value="{{ $item->value }}" @selected(old($typeFieldName.'.'.$doc['row_key'], $typeSelectedValue ?? '') === $item->value)>{{ $item->label ?: $item->value }}</option>
            @endforeach
        </select>
        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">Expiry date</label>
        @include('admin.partials.registration-profile-date-input', [
            'name' => $expiryFieldName.'['.$doc['row_key'].']',
            'value' => old($expiryFieldName.'.'.$doc['row_key'], $expiryInput),
            'storageFormat' => 'Y-m-d',
            'inputClass' => $editIn.' mb-3',
        ])
    @elseif ($expiryDisplay)
        <p class="mb-3 text-sm text-brand-text-secondary">Expiry: <span class="font-medium text-brand-text">{{ $expiryDisplay }}</span></p>
    @endif
    @if ($doc['row_key'] !== null)
        @include('admin.partials.registration-profile-file-upload-controls', [
            'storagePath' => $doc['storage_path'],
            'fileUrl' => $hasFile ? $fileUrl($fileUrlKind, $doc['row_key']) : null,
            'inputName' => $uploadFieldName.'['.$doc['row_key'].']',
            'uploadInputId' => $uploadInputId,
            'canEditProfile' => $canEditProfile,
        ])
    @endif
</div>
