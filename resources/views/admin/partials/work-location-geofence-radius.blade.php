@php
    $inputId = $inputId ?? 'loc-radius';
    $value = $value ?? \App\Models\WorkLocation::GEOFENCE_RADIUS_DEFAULT;
    $min = \App\Models\WorkLocation::GEOFENCE_RADIUS_MIN;
    $max = \App\Models\WorkLocation::GEOFENCE_RADIUS_MAX;
@endphp
<div class="{{ $wfGrid }}">
    <label for="{{ $inputId }}" class="{{ $lbl }} sm:pt-2.5">Geofence radius</label>
    <div>
        <div class="relative w-36">
            <input
                id="{{ $inputId }}"
                name="geofence_radius_meters"
                type="number"
                inputmode="numeric"
                required
                min="{{ $min }}"
                max="{{ $max }}"
                step="1"
                value="{{ $value }}"
                data-wf-radius
                class="{{ $in }} pe-8 tabular-nums"
            />
            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-brand-text-secondary">m</span>
        </div>
        @error('geofence_radius_meters')
            <p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
    </div>
</div>
