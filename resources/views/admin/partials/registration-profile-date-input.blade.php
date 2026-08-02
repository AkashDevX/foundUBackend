@php
    /** @var string $name */
    /** @var string $value */
    /** @var string $storageFormat */
    /** @var string $inputClass */
    $storageFormat = $storageFormat ?? 'Y-m-d';
@endphp
<input type="date" name="{{ $name }}" value="{{ $value }}" class="{{ $inputClass }}" />
<input type="hidden" name="{{ $name }}_storage_format" value="{{ $storageFormat }}" />
