@php
    /** @var string $name */
    /** @var string $value */
    /** @var string $storageFormat */
    /** @var string $inputClass */
    $storageFormat = $storageFormat ?? 'Y-m-d';
    $storageFormatName = $storageFormatName ?? \App\Support\RegistrationDisplay::adminDateStorageFormatFieldName($name);
@endphp
<input type="date" name="{{ $name }}" value="{{ $value }}" class="{{ $inputClass }}" />
<input type="hidden" name="{{ $storageFormatName }}" value="{{ $storageFormat }}" />
