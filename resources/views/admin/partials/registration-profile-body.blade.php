@php
    /** @var \App\Models\Employee $e */
    /** @var bool $canEditProfile */
    /** @var array<string, string> $registrationDateInputs */
    $registrationDateInputs = $registrationDateInputs ?? [];
    /** @var array<string, string> $registrationDateFormats */
    $registrationDateFormats = $registrationDateFormats ?? [];
    $nativeDateIn = $nativeDateIn ?? 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm [color-scheme:light] focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25';
    $idTypeForKey = static function (?string $key) use ($e): string {
        if ($key === null || $key === '') {
            return '';
        }
        foreach ($e->id_documents_json ?? [] as $row) {
            if (! is_array($row) || (string) ($row['documentKey'] ?? '') !== $key) {
                continue;
            }
            foreach (['documentType', 'document_type', 'idType', 'id_type', 'type', 'name', 'title', 'label'] as $field) {
                if (! empty($row[$field]) && is_scalar($row[$field])) {
                    return trim((string) $row[$field]);
                }
            }
        }

        return '';
    };
    $declarationIsUploaded = static function (?string $declaration, ?string $filePath) use ($yesNoPickVal): bool {
        if ($filePath !== null && trim($filePath) !== '') {
            return true;
        }

        return $yesNoPickVal($declaration) === 'Yes';
    };
    $dlRow = $dl ?? 'grid gap-4 border-b border-brand-border py-4 text-sm sm:grid-cols-[minmax(0,220px)_1fr] sm:items-center last:border-b-0';
    $dlRowStart = $dlStart ?? 'grid gap-4 border-b border-brand-border py-4 text-sm sm:grid-cols-[minmax(0,220px)_1fr] sm:items-start last:border-b-0';
    $licTypeForKey = static function (?string $key) use ($e): string {
        if ($key === null || $key === '') {
            return '';
        }
        foreach ($e->licences_json ?? [] as $row) {
            if (! is_array($row) || (string) ($row['id'] ?? '') !== $key) {
                continue;
            }

            return (string) ($row['documentType'] ?? $row['type'] ?? $row['licenceType'] ?? '');
        }

        return '';
    };
    $insTypeForKey = static function (?string $key) use ($e): string {
        if ($key === null || $key === '') {
            return '';
        }
        foreach ($e->insurances_json ?? [] as $row) {
            if (! is_array($row) || (string) ($row['id'] ?? '') !== $key) {
                continue;
            }

            return (string) ($row['documentType'] ?? $row['type'] ?? '');
        }

        return '';
    };
    $idDocLabel = static function (array $doc) use ($idTypeForKey, $registrationPicklists): string {
        $key = $doc['row_key'] ?? null;
        if ($key !== null) {
            $raw = $idTypeForKey($key);
            if ($raw !== '') {
                $picklist = $registrationPicklists->get('id_document_type', collect());
                $matched = \App\Support\RegistrationDisplay::matchPicklistValue($raw, $picklist);
                foreach ($picklist as $item) {
                    if ($item->value === $matched) {
                        return (string) ($item->label ?: $item->value);
                    }
                }

                return $raw;
            }
        }
        if (! empty($doc['display_label'])) {
            return (string) $doc['display_label'];
        }

        return (string) ($doc['title'] ?? 'ID document');
    };
    $profilePhotoInputId = 'reg-profile-photo-input';
    $ownVehicleValue = 'Own vehicle';
    $transportIsOwnVehicle = static function (?string $m) use ($ownVehicleValue): bool {
        if ($m === null || trim((string) $m) === '') {
            return false;
        }

        return strcasecmp(trim((string) $m), $ownVehicleValue) === 0;
    };
    $unrestrictedWorkRightsYes = static function (?string $v) use ($yesNoPickVal): bool {
        return strcasecmp($yesNoPickVal($v), 'Yes') === 0;
    };
    $currentUnrestrictedWorkRights = old('unrestricted_work_rights', $yesNoPickVal($e->unrestricted_work_rights));
    $showVisaExpiry = ! $unrestrictedWorkRightsYes($currentUnrestrictedWorkRights);
    $publicLiabilityExpiry = \App\Support\RegistrationDisplay::insuranceExpiryForType(
        $e->insurances_json,
        'Public Liability',
        $registrationPicklists->get('insurance_type', collect())
    );
    $transportModeLabel = \App\Support\RegistrationDisplay::picklistLabel(
        $e->mode_of_transport,
        $registrationPicklists->get('transport_mode', collect())
    );
    $roleDepartment = $e->assignedDepartment?->name ?? $e->department;
    $roleJobTitle = $e->assignedJobTitle?->name ?? $e->job_title;
    $roleEmployeeCode = ($e->employee_code !== null && trim((string) $e->employee_code) !== '')
        ? trim((string) $e->employee_code)
        : 'N/A';
@endphp

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Identity &amp; personal</h3>
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Full legal name</dt><dd class="min-w-0 text-brand-text">@if ($canEditProfile)<input type="text" name="full_legal_name" required maxlength="200" value="{{ old('full_legal_name', $e->full_legal_name) }}" class="{{ $editIn }}" autocomplete="name" />@else{{ $line($e->full_legal_name) }}@endif</dd></div>
        @if ($canEditProfile)
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Email</dt><dd class="min-w-0"><input type="email" name="email" required maxlength="255" value="{{ old('email', $e->email) }}" class="{{ $editIn }}" autocomplete="email" /></dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Phone</dt><dd class="min-w-0"><input type="text" name="phone" maxlength="48" value="{{ old('phone', $e->phone) }}" class="{{ $editIn }}" autocomplete="tel" /></dd></div>
            <div class="{{ $dlRowStart }}">
                <dt class="pt-1 font-medium text-brand-label">Profile photo</dt>
                <dd class="min-w-0 space-y-3">
                    <div class="flex flex-wrap items-start gap-4" data-reg-photo-root>
                        <div class="relative shrink-0 overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-inner" data-reg-photo-current-wrap>
                            @if ($e->profile_photo_path)
                                <img src="{{ $fileUrl('profile-photo') }}" alt="Current profile photo" class="size-28 object-cover sm:size-32" width="128" height="128" loading="lazy" data-reg-photo-current />
                            @else
                                <div class="flex size-28 items-center justify-center bg-brand-surface/80 text-xs font-medium text-brand-text-secondary sm:size-32" data-reg-photo-empty>No photo</div>
                            @endif
                            <img src="" alt="New profile photo preview" class="hidden size-28 object-cover sm:size-32" width="128" height="128" data-reg-photo-preview />
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <p class="text-sm text-brand-text-secondary">The current photo stays until you choose a replacement.</p>
                            <input type="file" name="profile_photo" id="{{ $profilePhotoInputId }}" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" data-reg-photo-input />
                            <label for="{{ $profilePhotoInputId }}" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-brand-border bg-white px-4 py-2 text-sm font-semibold text-brand-primary shadow-sm transition hover:border-brand-primary/40 hover:bg-brand-surface" data-reg-photo-replace>
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M16 12l-4-4m0 0L8 12m4-4v12" /></svg>
                                Replace photo
                            </label>
                            <p class="text-xs text-brand-text-secondary" data-reg-photo-filename hidden></p>
                            <p class="text-xs text-brand-text-secondary">Optional â€” JPEG, PNG, WebP or GIF up to 15&nbsp;MB.</p>
                        </div>
                    </div>
                </dd>
            </div>
        @elseif ($e->profile_photo_path)
            <div class="{{ $dlRowStart }}">
                <dt class="pt-1 font-medium text-brand-label">Profile photo</dt>
                <dd class="min-w-0">
                    <div class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-inner">
                        <img src="{{ $fileUrl('profile-photo') }}" alt="Profile photo" class="mx-auto max-h-64 w-auto max-w-full object-contain" loading="lazy" />
                    </div>
                </dd>
            </div>
        @endif
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Date of birth</dt><dd class="min-w-0 text-brand-text">@if ($canEditProfile)@include('admin.partials.registration-profile-date-input', ['name' => 'date_of_birth', 'value' => $registrationDateInputs['date_of_birth'] ?? '', 'storageFormat' => $registrationDateFormats['date_of_birth'] ?? 'Y-m-d', 'inputClass' => $nativeDateIn])@else{{ $profileDateLine('date_of_birth', ['dateOfBirth', 'date_of_birth', 'dob', 'birthDate']) }}@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Sex</dt><dd class="min-w-0">@if ($canEditProfile)@php $sexSel = old('sex', $sexForSelect($e->sex)); @endphp<select name="sex" class="{{ $editIn }}"><option value="">â€”</option><option value="Male" @selected($sexSel === 'Male')>Male</option><option value="Female" @selected($sexSel === 'Female')>Female</option></select>@else<span class="text-brand-text">{{ $line($e->sex) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Marital status</dt><dd class="min-w-0">@if ($canEditProfile)<select name="marital_status" class="{{ $editIn }}"><option value="">â€”</option>@foreach ($registrationPicklists->get('marital_status', collect()) as $item)<option value="{{ $item->value }}" @selected(old('marital_status', $e->marital_status) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $line($e->marital_status) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Address</dt><dd class="min-w-0 overflow-visible">@if ($canEditProfile)<div class="relative max-w-full" data-reg-addr-root data-search-url="{{ route('admin.workforce.geocode.search') }}"><div class="relative"><input type="text" name="address" data-reg-address maxlength="5000" value="{{ old('address', $e->address) }}" class="{{ $editIn }} w-full pe-11 shadow-sm" placeholder="Start typing â€” pick a suggestion" autocomplete="off" /><button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-xl text-brand-text-secondary transition hover:bg-brand-surface hover:text-brand-text {{ trim((string) old('address', $e->address ?? '')) === '' ? 'hidden' : '' }}" data-reg-address-clear aria-label="Clear address"><svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button></div><div data-reg-addr-suggestions class="hidden rounded-xl border border-brand-border bg-white py-1 shadow-2xl ring-1 ring-black/10" role="listbox" aria-label="Address suggestions"></div></div><p class="mt-1.5 text-xs text-brand-text-secondary">Suggestions from OpenStreetMap (Nominatim). Use Ã— to clear before entering a new address.</p>@else<span class="whitespace-pre-wrap text-brand-text">{{ $line($e->address) }}</span>@endif</dd></div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Emergency contact</h3>
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dlRow }}"><dt class="font-medium text-brand-label">Name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="emergency_contact_name" maxlength="160" value="{{ old('emergency_contact_name', $e->emergency_contact_name) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->emergency_contact_name) }}</span>@endif</dd></div>
        <div class="{{ $dlRow }}"><dt class="font-medium text-brand-label">Phone</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="emergency_contact_phone" maxlength="48" value="{{ old('emergency_contact_phone', $e->emergency_contact_phone) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->emergency_contact_phone) }}</span>@endif</dd></div>
        <div class="{{ $dlRow }}"><dt class="font-medium text-brand-label">Relationship</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="emergency_contact_relationship" maxlength="120" value="{{ old('emergency_contact_relationship', $e->emergency_contact_relationship) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->emergency_contact_relationship) }}</span>@endif</dd></div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Work eligibility &amp; availability</h3>
        <!-- <p class="mt-1 text-sm text-brand-text-secondary">@if ($canEditProfile)Picklists are managed in the master registry (<code class="rounded bg-brand-surface px-1 font-mono text-xs">registration_picklist_items</code>).@else Readable schedule from the app.@endif</p> -->
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Unrestricted work rights</dt><dd class="min-w-0">@if ($canEditProfile)<select name="unrestricted_work_rights" class="{{ $editIn }}" data-reg-unrestricted-work-rights><option value="">—</option>@foreach ($registrationPicklists->get('unrestricted_work_rights', collect()) as $item)<option value="{{ $item->value }}" @selected($currentUnrestrictedWorkRights === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $yesNo($e->unrestricted_work_rights) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Visa status</dt><dd class="min-w-0">@if ($canEditProfile)<select name="visa_status" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('visa_status', collect()) as $item)<option value="{{ $item->value }}" @selected(old('visa_status', $e->visa_status) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $line($e->visa_status) }}</span>@endif</dd></div>
        @if ($canEditProfile)
            <div class="{{ $dl }} {{ $showVisaExpiry ? '' : 'hidden' }}" data-reg-visa-expiry-field>
                <dt class="font-medium text-brand-label">Visa expiry</dt>
                <dd class="min-w-0">@include('admin.partials.registration-profile-date-input', ['name' => 'visa_expiry', 'value' => $registrationDateInputs['visa_expiry'] ?? '', 'storageFormat' => $registrationDateFormats['visa_expiry'] ?? 'Y-m-d', 'inputClass' => $nativeDateIn])</dd>
            </div>
        @elseif ($showVisaExpiry)
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Visa expiry</dt><dd class="min-w-0"><span class="text-brand-text">{{ $profileDateLine('visa_expiry', ['visaExpiry', 'visa_expiry']) }}</span></dd></div>
        @endif
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Hours per week</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="hours_per_week" maxlength="16" value="{{ old('hours_per_week', $e->hours_per_week) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->hours_per_week) }}</span>@endif</dd></div>
        <div class="{{ $dlRowStart }}">
            <dt class="pt-1 font-medium text-brand-label">Weekly availability</dt>
            <dd class="min-w-0 w-full">
                @include('admin.partials.registration-profile-weekly-calendar', [
                    'weeklyGrid' => $weeklyGrid,
                    'canEditProfile' => $canEditProfile,
                ])
            </dd>
        </div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">ID &amp; checks</h3>
        <!-- <p class="mt-1 text-sm text-brand-text-secondary">Uploaded copies open in a new tab. Images show inline.</p> -->
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dlRowStart }}">
            <dt class="pt-1 font-medium text-brand-label">ID documents (notes)</dt>
            <dd class="min-w-0 space-y-3">
                @if ($idDocRows === [])
                    <p class="text-sm text-brand-text-secondary">No ID document rows on file.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($idDocRows as $doc)
                            @php $docUploaded = ($doc['storage_path'] ?? null) !== null && ($doc['storage_path'] ?? '') !== ''; $docName = $idDocLabel($doc); @endphp
                            <li>
                                <div class="inline-flex w-full min-w-0 items-center gap-3 rounded-xl border px-4 py-3 {{ $docUploaded ? 'border-emerald-200/90 bg-emerald-50/70' : 'border-brand-border bg-brand-surface/50' }}">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $docUploaded ? 'bg-emerald-600 text-white' : 'bg-brand-border/80 text-brand-text-secondary' }}" aria-hidden="true">
                                        @if ($docUploaded)
                                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        @else
                                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-brand-text">{{ $docName }}</span>
                                        <span class="mt-0.5 block text-xs text-brand-text-secondary">{{ $docUploaded ? 'Uploaded' : 'Not uploaded' }}</span>
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($canEditProfile)
                    <input type="hidden" name="id_documents_summary" value="{{ old('id_documents_summary', $e->id_documents_summary) }}" />
                @elseif (trim((string) ($e->id_documents_summary ?? '')) !== '')
                    <p class="whitespace-pre-wrap text-sm text-brand-text-secondary">{{ $line($e->id_documents_summary) }}</p>
                @endif
            </dd>
        </div>
        <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">ID document uploads</dt>
            <dd class="min-w-0 w-full">
                @if ($idDocRows === [])
                    <p class="text-sm text-brand-text-secondary">No ID document rows on file.</p>
                @else
                    <div class="grid gap-4 sm:grid-cols-1 lg:grid-cols-2">
                        @foreach ($idDocRows as $doc)
                            @include('admin.partials.registration-profile-id-doc-card', ['doc' => $doc])
                        @endforeach
                    </div>
                @endif
            </dd>
        </div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Mode of transport</dt><dd class="min-w-0">@if ($canEditProfile)<select name="mode_of_transport" data-reg-mode-transport data-reg-own-value="{{ $ownVehicleValue }}" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('transport_mode', collect()) as $item)<option value="{{ $item->value }}" @selected(old('mode_of_transport', $e->mode_of_transport) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $line($transportModeLabel) }}</span>@endif</dd></div>
        @if ($canEditProfile)
            <div data-reg-vehicle-fields class="{{ $transportIsOwnVehicle(old('mode_of_transport', $e->mode_of_transport)) ? '' : 'hidden' }}">
                <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle registration</dt><dd class="min-w-0"><input type="text" name="vehicle_registration" maxlength="64" value="{{ old('vehicle_registration', $e->vehicle_registration) }}" class="{{ $editIn }}" /></dd></div>
                <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle expiry</dt><dd class="min-w-0">@include('admin.partials.registration-profile-date-input', ['name' => 'vehicle_expiry', 'value' => $registrationDateInputs['vehicle_expiry'] ?? '', 'storageFormat' => $registrationDateFormats['vehicle_expiry'] ?? 'Y-m-d', 'inputClass' => $nativeDateIn])</dd></div>
                <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle insurance uploaded (declaration)</dt><dd class="min-w-0"><select name="vehicle_insurance_uploaded" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('unrestricted_work_rights', collect()) as $item)<option value="{{ $item->value }}" @selected(old('vehicle_insurance_uploaded', $yesNoPickVal($e->vehicle_insurance_uploaded)) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select></dd></div>
                <div class="{{ $dl }} items-start">
                    <dt class="pt-1 font-medium text-brand-label">Vehicle insurance file</dt>
                    <dd class="min-w-0 w-full max-w-2xl">
                        @include('admin.partials.registration-profile-file-upload-card', [
                            'storagePath' => $e->vehicle_insurance_path,
                            'fileUrl' => $e->vehicle_insurance_path ? $fileUrl('vehicle-insurance') : null,
                            'inputName' => 'vehicle_insurance',
                            'uploadInputId' => 'reg-vehicle-insurance-upload',
                            'canEditProfile' => true,
                        ])
                    </dd>
                </div>
            </div>
        @elseif ($transportIsOwnVehicle($e->mode_of_transport))
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle registration</dt><dd class="min-w-0"><span class="text-brand-text">{{ $line($e->vehicle_registration) }}</span></dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle expiry</dt><dd class="min-w-0"><span class="text-brand-text">{{ $profileDateLine('vehicle_expiry', ['vehicleExpiry', 'vehicle_expiry']) }}</span></dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle insurance uploaded (declaration)</dt><dd class="min-w-0"><span class="text-brand-text">{{ $yesNo($e->vehicle_insurance_uploaded) }}</span></dd></div>
            @if ($e->vehicle_insurance_path)
                <div class="{{ $dl }} items-start">
                    <dt class="pt-1 font-medium text-brand-label">Vehicle insurance file</dt>
                    <dd class="min-w-0 w-full max-w-2xl">
                        @include('admin.partials.registration-profile-file-upload-card', [
                            'storagePath' => $e->vehicle_insurance_path,
                            'fileUrl' => $fileUrl('vehicle-insurance'),
                            'inputName' => 'vehicle_insurance',
                            'uploadInputId' => 'reg-vehicle-insurance-upload-ro',
                            'canEditProfile' => false,
                        ])
                    </dd>
                </div>
            @endif
        @endif
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Police check expiry</dt><dd class="min-w-0">@if ($canEditProfile)@include('admin.partials.registration-profile-date-input', ['name' => 'police_check_expiry', 'value' => $registrationDateInputs['police_check_expiry'] ?? '', 'storageFormat' => $registrationDateFormats['police_check_expiry'] ?? 'Y-m-d', 'inputClass' => $nativeDateIn])@else<span class="text-brand-text">{{ $profileDateLine('police_check_expiry', ['policeCheckExpiry', 'police_check_expiry']) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Fit to work expiry</dt><dd class="min-w-0">@if ($canEditProfile)@include('admin.partials.registration-profile-date-input', ['name' => 'fit_to_work_expiry', 'value' => $registrationDateInputs['fit_to_work_expiry'] ?? '', 'storageFormat' => $registrationDateFormats['fit_to_work_expiry'] ?? 'Y-m-d', 'inputClass' => $nativeDateIn])@else<span class="text-brand-text">{{ $profileDateLine('fit_to_work_expiry', ['fitToWorkExpiry', 'fit_to_work_expiry']) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Public liability expiry</dt><dd class="min-w-0"><span class="text-brand-text">{{ $line($publicLiabilityExpiry) }}</span></dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Police check uploaded (declaration)</dt><dd class="min-w-0">@include('admin.partials.registration-profile-declaration', ['isUploaded' => $declarationIsUploaded($e->police_check_uploaded, $e->police_check_path), 'inputName' => 'police_check_uploaded', 'declarationValue' => $e->police_check_uploaded])</dd></div>
        @if ($e->police_check_path || $canEditProfile)
            <div class="{{ $dl }} items-start">
                <dt class="pt-1 font-medium text-brand-label">Police check file</dt>
                <dd class="min-w-0 w-full max-w-2xl">
                    @include('admin.partials.registration-profile-file-upload-card', [
                        'storagePath' => $e->police_check_path,
                        'fileUrl' => $e->police_check_path ? $fileUrl('police-check') : null,
                        'inputName' => 'police_check',
                        'uploadInputId' => 'reg-police-check-upload',
                        'canEditProfile' => $canEditProfile,
                    ])
                </dd>
            </div>
        @endif
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Fit to work uploaded (declaration)</dt><dd class="min-w-0">@include('admin.partials.registration-profile-declaration', ['isUploaded' => $declarationIsUploaded($e->fit_to_work_uploaded, $e->fit_to_work_path), 'inputName' => 'fit_to_work_uploaded', 'declarationValue' => $e->fit_to_work_uploaded])</dd></div>
        @if ($e->fit_to_work_path || $canEditProfile)
            <div class="{{ $dl }} items-start">
                <dt class="pt-1 font-medium text-brand-label">Fit to work file</dt>
                <dd class="min-w-0 w-full max-w-2xl">
                    @include('admin.partials.registration-profile-file-upload-card', [
                        'storagePath' => $e->fit_to_work_path,
                        'fileUrl' => $e->fit_to_work_path ? $fileUrl('fit-to-work') : null,
                        'inputName' => 'fit_to_work',
                        'uploadInputId' => 'reg-fit-to-work-upload',
                        'canEditProfile' => $canEditProfile,
                    ])
                </dd>
            </div>
        @endif
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Licences &amp; insurance</h3>
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Licences summary</dt><dd class="min-w-0 text-sm text-brand-text">{{ $line($e->licences_summary) }}</dd></div>
        <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">Licence uploads</dt>
            <dd class="min-w-0 w-full">
                @if ($licenceRows === [])
                    <p class="text-sm text-brand-text-secondary">No licence rows on file.</p>
                @else
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($licenceRows as $doc)
                            @if ($doc['row_key'] !== null)
                                @include('admin.partials.registration-profile-doc-row-card', [
                                    'doc' => $doc,
                                    'canEditProfile' => $canEditProfile,
                                    'picklistKey' => 'licence_type',
                                    'typeLabel' => 'Licence type',
                                    'typeFieldName' => 'licence_type_row',
                                    'typeSelectedValue' => $licTypeForKey($doc['row_key']),
                                    'uploadFieldName' => 'licence_upload',
                                    'fileUrlKind' => 'licence',
                                    'expiryFieldName' => 'licence_expiry_row',
                                ])
                            @endif
                        @endforeach
                    </div>
                @endif
            </dd>
        </div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Insurances summary</dt><dd class="min-w-0 text-sm text-brand-text">{{ $line($e->insurances_summary) }}</dd></div>
        <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">Insurance uploads</dt>
            <dd class="min-w-0 w-full">
                @if ($insuranceRows === [])
                    <p class="text-sm text-brand-text-secondary">No insurance rows on file.</p>
                @else
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($insuranceRows as $doc)
                            @if ($doc['row_key'] !== null)
                                @include('admin.partials.registration-profile-doc-row-card', [
                                    'doc' => $doc,
                                    'canEditProfile' => $canEditProfile,
                                    'picklistKey' => 'insurance_type',
                                    'typeLabel' => 'Insurance type',
                                    'typeFieldName' => 'insurance_type_row',
                                    'typeSelectedValue' => $insTypeForKey($doc['row_key']),
                                    'uploadFieldName' => 'insurance_upload',
                                    'fileUrlKind' => 'insurance',
                                    'expiryFieldName' => 'insurance_expiry_row',
                                ])
                            @endif
                        @endforeach
                    </div>
                @endif
            </dd>
        </div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Role</h3>
        @if ($canEditProfile)
            <!-- <p class="mt-1 text-sm text-brand-text-secondary">Job titles and departments come from <a href="{{ route('admin.workforce.job-titles') }}" class="font-semibold text-brand-link hover:underline">Organization setup</a>.</p> -->
        @endif
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Job title</dt><dd class="min-w-0">@if ($canEditProfile)@php $jobTitles = $jobTitles ?? collect(); @endphp<select name="job_title_id" class="{{ $editIn }}"><option value="">—</option>@foreach ($jobTitles as $jt)<option value="{{ $jt->id }}" @selected((string) old('job_title_id', $e->job_title_id) === (string) $jt->id)>{{ $jt->name }}</option>@endforeach</select>@if ($jobTitles->isEmpty())<p class="mt-1 text-xs text-brand-text-secondary">No job titles yet — add them under Organization setup.</p>@elseif (! $e->job_title_id && trim((string) ($e->job_title ?? '')) !== '')<p class="mt-1 text-xs text-brand-text-secondary">Registration note: {{ $line($e->job_title) }}</p>@endif @else<span class="text-brand-text">{{ $line($roleJobTitle) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Department</dt><dd class="min-w-0">@if ($canEditProfile)@php $departments = $departments ?? collect(); @endphp<select name="department_id" class="{{ $editIn }}"><option value="">—</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected((string) old('department_id', $e->department_id) === (string) $d->id)>{{ $d->name }}</option>@endforeach</select>@if ($departments->isEmpty())<p class="mt-1 text-xs text-brand-text-secondary">No departments yet — add them under Organization setup.</p>@elseif (! $e->department_id && trim((string) ($e->department ?? '')) !== '')<p class="mt-1 text-xs text-brand-text-secondary">Registration note: {{ $line($e->department) }}</p>@endif @else<span class="text-brand-text">{{ $line($roleDepartment) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Employee code</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="employee_code" maxlength="64" value="{{ old('employee_code', $e->employee_code) }}" class="{{ $editIn }}" placeholder="N/A" />@else<span class="text-brand-text">{{ e($roleEmployeeCode) }}</span>@endif</dd></div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Payroll Information</h3>
        @if ($canEditProfile)
            <p class="mt-1 text-sm text-brand-text-secondary">Employment type and award level drive pay calculations. <a href="{{ route('admin.payroll.rates') }}" class="font-semibold text-brand-link hover:underline">Edit award rates</a></p>
        @endif
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        @php $connection = $company->tenant_connection; @endphp
        @include('admin.partials.registration-profile-payroll', ['e' => $e, 'company' => $company, 'connection' => $connection])
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Account name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_account_name" maxlength="160" value="{{ old('bank_account_name', $e->bank_account_name) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_account_name) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Account number</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_account_number" maxlength="500" class="{{ $editIn }} font-mono tracking-wide" data-reg-bank-account data-bank-masked="{{ $bankHasAccount ? $bankMasked : '' }}" value="{{ old('bank_account_number') ?: ($bankHasAccount ? $bankMasked : '') }}" placeholder="{{ $bankHasAccount ? '' : 'Enter account number' }}" autocomplete="off" /><p class="mt-1 text-xs text-brand-text-secondary">@if ($bankHasAccount)Masked as {{ $bankMasked }}. Click the field to enter a new number (leave empty to keep current).@elseEnter the full account number.@endif</p>@else<span class="font-mono text-brand-text">{{ $bankMasked }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Branch code</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_branch_code" maxlength="32" value="{{ old('bank_branch_code', $e->bank_branch_code) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_branch_code) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Bank name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_name" maxlength="160" value="{{ old('bank_name', $e->bank_name) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_name) }}</span>@endif</dd></div>
    </div>
</section>
