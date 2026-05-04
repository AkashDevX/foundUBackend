@php
    /** @var \App\Models\Employee $e */
    /** @var bool $canEditProfile */
    /** @var array<string, string> $registrationDateInputs */
    $registrationDateInputs = $registrationDateInputs ?? [];
    $nativeDateIn = $nativeDateIn ?? 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm [color-scheme:light] focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25';
    $idTypeForKey = static function (?string $key) use ($e): string {
        if ($key === null || $key === '') {
            return '';
        }
        foreach ($e->id_documents_json ?? [] as $row) {
            if (! is_array($row) || (string) ($row['documentKey'] ?? '') !== $key) {
                continue;
            }

            return (string) ($row['documentType'] ?? $row['type'] ?? '');
        }

        return '';
    };
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
    $ownVehicleValue = 'Own vehicle';
    $transportIsOwnVehicle = static function (?string $m) use ($ownVehicleValue): bool {
        if ($m === null || trim((string) $m) === '') {
            return false;
        }

        return strcasecmp(trim((string) $m), $ownVehicleValue) === 0;
    };
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
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Replace profile photo</dt><dd class="min-w-0"><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" class="{{ $editIn }}" /><p class="mt-1 text-xs text-brand-text-secondary">Optional — image up to 15&nbsp;MB.</p></dd></div>
        @endif
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">First / last (split)</dt><dd class="text-brand-text">@if ($canEditProfile)<span class="text-sm text-brand-text-secondary">Derived from full legal name when you save.</span><span class="mt-1 block font-medium text-brand-text">{{ $line($e->first_name) }} · {{ $line($e->last_name) }}</span>@else{{ $line($e->first_name) }} · {{ $line($e->last_name) }}@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Date of birth</dt><dd class="min-w-0 text-brand-text">@if ($canEditProfile)<input type="date" name="date_of_birth" value="{{ $registrationDateInputs['date_of_birth'] ?? '' }}" class="{{ $nativeDateIn }}" />@else{{ $profileDateLine('date_of_birth', ['dateOfBirth', 'date_of_birth', 'dob', 'birthDate']) }}@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Sex</dt><dd class="min-w-0">@if ($canEditProfile)@php $sexSel = old('sex', $sexForSelect($e->sex)); @endphp<select name="sex" class="{{ $editIn }}"><option value="">—</option><option value="Male" @selected($sexSel === 'Male')>Male</option><option value="Female" @selected($sexSel === 'Female')>Female</option></select>@else<span class="text-brand-text">{{ $line($e->sex) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Marital status</dt><dd class="min-w-0">@if ($canEditProfile)<select name="marital_status" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('marital_status', collect()) as $item)<option value="{{ $item->value }}" @selected(old('marital_status', $e->marital_status) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $line($e->marital_status) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Address</dt><dd class="min-w-0">@if ($canEditProfile)<div class="relative" data-reg-addr-root data-search-url="{{ route('admin.workforce.geocode.search') }}"><input type="text" name="address" data-reg-address maxlength="5000" value="{{ old('address', $e->address) }}" class="{{ $editIn }} pe-2" placeholder="Start typing — pick a suggestion" autocomplete="street-address" /><div data-reg-addr-suggestions class="absolute left-0 right-0 top-full z-30 mt-1 max-h-52 overflow-auto rounded-xl border border-brand-border bg-white py-1 shadow-lg ring-1 ring-black/[0.06] hidden" role="listbox"></div></div><p class="mt-1 text-xs text-brand-text-secondary">Suggestions from OpenStreetMap (Nominatim).</p>@else<span class="whitespace-pre-wrap text-brand-text">{{ $line($e->address) }}</span>@endif</dd></div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Emergency contact</h3>
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="emergency_contact_name" maxlength="160" value="{{ old('emergency_contact_name', $e->emergency_contact_name) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->emergency_contact_name) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Phone</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="emergency_contact_phone" maxlength="48" value="{{ old('emergency_contact_phone', $e->emergency_contact_phone) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->emergency_contact_phone) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Relationship</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="emergency_contact_relationship" maxlength="120" value="{{ old('emergency_contact_relationship', $e->emergency_contact_relationship) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->emergency_contact_relationship) }}</span>@endif</dd></div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Work eligibility &amp; availability</h3>
        <p class="mt-1 text-sm text-brand-text-secondary">@if ($canEditProfile)Picklists are managed in the master registry (<code class="rounded bg-brand-surface px-1 font-mono text-xs">registration_picklist_items</code>).@else Readable schedule from the app.@endif</p>
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Visa status</dt><dd class="min-w-0">@if ($canEditProfile)<select name="visa_status" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('visa_status', collect()) as $item)<option value="{{ $item->value }}" @selected(old('visa_status', $e->visa_status) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $line($e->visa_status) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Unrestricted work rights</dt><dd class="min-w-0">@if ($canEditProfile)<select name="unrestricted_work_rights" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('unrestricted_work_rights', collect()) as $item)<option value="{{ $item->value }}" @selected(old('unrestricted_work_rights', $yesNoPickVal($e->unrestricted_work_rights)) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $yesNo($e->unrestricted_work_rights) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Visa expiry</dt><dd class="min-w-0">@if ($canEditProfile)<input type="date" name="visa_expiry" value="{{ $registrationDateInputs['visa_expiry'] ?? '' }}" class="{{ $nativeDateIn }}" />@else<span class="text-brand-text">{{ $profileDateLine('visa_expiry', ['visaExpiry', 'visa_expiry']) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Hours per week</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="hours_per_week" maxlength="16" value="{{ old('hours_per_week', $e->hours_per_week) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->hours_per_week) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Summary</dt><dd class="min-w-0">@if ($canEditProfile)<textarea name="weekly_availability_summary" rows="3" maxlength="5000" class="{{ $editTa }}">{{ old('weekly_availability_summary', $e->weekly_availability_summary) }}</textarea>@else<span class="whitespace-pre-wrap text-brand-text">{{ $line($e->weekly_availability_summary) }}</span>@endif</dd></div>
        <div class="{{ $dl }} items-start">
            <dt class="pt-1 font-medium text-brand-label">Weekly availability</dt>
            <dd class="min-w-0 w-full">
                @if ($canEditProfile)
                    <div class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.04]">
                        <div class="border-b border-brand-border bg-gradient-to-r from-brand-primary/[0.07] via-brand-surface/50 to-transparent px-4 py-3 sm:px-5">
                            <p class="text-sm font-bold text-brand-text">Weekly hours</p>
                            <p class="mt-0.5 text-xs text-brand-text-secondary">Toggle days on, then set start and end times.</p>
                        </div>
                        <div class="-mx-px overflow-x-auto">
                            <div class="inline-block min-w-full align-middle">
                                <div class="grid min-w-[640px] grid-cols-7 divide-x divide-brand-border/70 border-b border-brand-border/70 bg-brand-surface/40 text-center sm:min-w-0">
                                    @foreach ($calDayLabels as $dKey => $dLabel)
                                        <div class="py-2.5 text-[11px] font-bold uppercase tracking-[0.14em] text-brand-text-secondary">{{ $dLabel }}</div>
                                    @endforeach
                                </div>
                                <div class="grid min-w-[640px] grid-cols-7 divide-x divide-brand-border/50 sm:min-w-0">
                                    @foreach ($calDayLabels as $dKey => $dLabel)
                                        @php
                                            $st = $availabilityCalendar[$dKey] ?? ['on' => false, 'start' => '09:00', 'end' => '17:00'];
                                            $dayOn = old("availability.$dKey.on", $st['on'] ? '1' : '0') === '1';
                                        @endphp
                                        <div class="flex flex-col gap-2.5 bg-white px-2 py-3 sm:px-3 has-[:checked]:bg-brand-primary/[0.04]">
                                            <label class="flex cursor-pointer items-center justify-center gap-2">
                                                <input type="hidden" name="availability[{{ $dKey }}][on]" value="0" />
                                                <input type="checkbox" name="availability[{{ $dKey }}][on]" value="1" class="peer sr-only" @checked($dayOn) />
                                                <span class="inline-flex h-7 items-center rounded-full border px-2.5 text-[10px] font-bold uppercase tracking-wide transition peer-checked:border-brand-primary peer-checked:bg-brand-primary peer-checked:text-white {{ $dayOn ? 'border-brand-primary bg-brand-primary text-white' : 'border-brand-border/90 bg-brand-surface/60 text-brand-text-secondary' }}">On</span>
                                            </label>
                                            <div class="flex flex-col gap-1">
                                                <input type="time" name="availability[{{ $dKey }}][start]" value="{{ old("availability.$dKey.start", $st['start']) }}" title="Start" class="w-full rounded-lg border border-brand-border/90 bg-white px-1.5 py-1.5 text-center text-xs tabular-nums text-brand-text shadow-sm [color-scheme:light] focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20" />
                                                <input type="time" name="availability[{{ $dKey }}][end]" value="{{ old("availability.$dKey.end", $st['end']) }}" title="End" class="w-full rounded-lg border border-brand-border/90 bg-white px-1.5 py-1.5 text-center text-xs tabular-nums text-brand-text shadow-sm [color-scheme:light] focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20" />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    @if ($weeklySections !== [])
                        <div class="space-y-5">
                            @foreach ($weeklySections as $block)
                                <div class="rounded-xl border border-brand-border bg-brand-surface/80 px-4 py-3">
                                    <p class="text-sm font-bold text-brand-text">{{ $block['label'] }}</p>
                                    <ul class="mt-2 list-inside list-disc space-y-1.5 text-sm leading-relaxed text-brand-text">
                                        @foreach ($block['lines'] as $ln)
                                            <li class="marker:text-brand-primary">{{ $ln }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-brand-text-secondary">No structured weekly hours were saved for this application.</p>
                    @endif
                @endif
            </dd>
        </div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">ID &amp; checks</h3>
        <p class="mt-1 text-sm text-brand-text-secondary">Uploaded copies open in a new tab. Images show inline.</p>
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">ID documents (notes)</dt><dd class="min-w-0">@if ($canEditProfile)<textarea name="id_documents_summary" rows="3" maxlength="5000" class="{{ $editTa }}">{{ old('id_documents_summary', $e->id_documents_summary) }}</textarea>@else<span class="whitespace-pre-wrap text-brand-text">{{ $line($e->id_documents_summary) }}</span>@endif</dd></div>
        <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">ID document uploads</dt>
            <dd class="min-w-0 w-full">
                @if ($idDocRows === [])
                    <p class="text-sm text-brand-text-secondary">No ID document rows on file.</p>
                @else
                    <div class="grid gap-4 sm:grid-cols-1 lg:grid-cols-2">
                        @foreach ($idDocRows as $doc)
                            <div class="overflow-hidden rounded-xl border border-brand-border bg-brand-surface/40 p-4">
                                @if ($canEditProfile && $doc['row_key'] !== null)
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">Document type</label>
                                    <select name="id_document_type[{{ $doc['row_key'] }}]" class="{{ $editIn }} mb-3">
                                        <option value="">—</option>
                                        @foreach ($registrationPicklists->get('id_document_type', collect()) as $item)
                                            <option value="{{ $item->value }}" @selected(old('id_document_type.'.$doc['row_key'], $idTypeForKey($doc['row_key'])) === $item->value)>{{ $item->label ?: $item->value }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <p class="font-semibold text-brand-text">{{ $doc['title'] }}</p>
                                @if ($doc['subtitle'] !== '')
                                    <p class="mt-1 text-sm text-brand-text-secondary">{{ $doc['subtitle'] }}</p>
                                @endif
                                @foreach ($doc['meta'] as $metaLine)
                                    <p class="mt-2 text-xs text-brand-text-secondary">{{ $metaLine }}</p>
                                @endforeach
                                @if ($doc['storage_path'] !== null && $doc['row_key'] !== null)
                                    @php $url = $fileUrl('id-document', $doc['row_key']); @endphp
                                    @if (\App\Support\RegistrationDisplay::isLikelyImagePath($doc['storage_path']))
                                        <div class="mt-3 overflow-hidden rounded-lg border border-brand-border bg-white">
                                            <img src="{{ $url }}" alt="{{ $doc['title'] }}" class="max-h-72 w-full object-contain" loading="lazy" />
                                        </div>
                                    @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($doc['storage_path']))
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-brand-primary px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-brand-primary-dark">View PDF</a>
                                    @else
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex text-xs font-bold text-brand-link hover:underline">Open uploaded file</a>
                                    @endif
                                @else
                                    <p class="mt-3 text-xs italic text-brand-text-secondary">No file attached for this row.</p>
                                @endif
                                @if ($canEditProfile && $doc['row_key'] !== null)
                                    <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-brand-label">Replace file</label>
                                    <input type="file" name="id_document_upload[{{ $doc['row_key'] }}]" class="mt-1 {{ $editIn }}" accept="image/*,.pdf" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </dd>
        </div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Police check expiry</dt><dd class="min-w-0">@if ($canEditProfile)<input type="date" name="police_check_expiry" value="{{ $registrationDateInputs['police_check_expiry'] ?? '' }}" class="{{ $nativeDateIn }}" />@else<span class="text-brand-text">{{ $profileDateLine('police_check_expiry', ['policeCheckExpiry', 'police_check_expiry']) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Police check uploaded (declaration)</dt><dd class="min-w-0">@if ($canEditProfile)<select name="police_check_uploaded" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('unrestricted_work_rights', collect()) as $item)<option value="{{ $item->value }}" @selected(old('police_check_uploaded', $yesNoPickVal($e->police_check_uploaded)) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $yesNo($e->police_check_uploaded) }}</span>@endif</dd></div>
        @if ($e->police_check_path || $canEditProfile)
            <div class="{{ $dl }} items-start">
                <dt class="pt-1 font-medium text-brand-label">Police check file</dt>
                <dd class="min-w-0 w-full">
                    @if ($e->police_check_path)
                        @if (\App\Support\RegistrationDisplay::isLikelyImagePath($e->police_check_path))
                            <div class="overflow-hidden rounded-xl border border-brand-border bg-white shadow-inner">
                                <img src="{{ $fileUrl('police-check') }}" alt="Police check" class="max-h-80 w-full object-contain" loading="lazy" />
                            </div>
                        @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($e->police_check_path))
                            <a href="{{ $fileUrl('police-check') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-primary-dark">View police check (PDF)</a>
                        @else
                            <a href="{{ $fileUrl('police-check') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand-link hover:underline">Download police check file</a>
                        @endif
                    @endif
                    @if ($canEditProfile)
                        <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">Replace file</label>
                        <input type="file" name="police_check" class="mt-1 {{ $editIn }}" accept="image/*,.pdf" />
                    @endif
                </dd>
            </div>
        @endif
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Fit to work expiry</dt><dd class="min-w-0">@if ($canEditProfile)<input type="date" name="fit_to_work_expiry" value="{{ $registrationDateInputs['fit_to_work_expiry'] ?? '' }}" class="{{ $nativeDateIn }}" />@else<span class="text-brand-text">{{ $profileDateLine('fit_to_work_expiry', ['fitToWorkExpiry', 'fit_to_work_expiry']) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Fit to work uploaded (declaration)</dt><dd class="min-w-0">@if ($canEditProfile)<select name="fit_to_work_uploaded" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('unrestricted_work_rights', collect()) as $item)<option value="{{ $item->value }}" @selected(old('fit_to_work_uploaded', $yesNoPickVal($e->fit_to_work_uploaded)) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $yesNo($e->fit_to_work_uploaded) }}</span>@endif</dd></div>
        @if ($e->fit_to_work_path || $canEditProfile)
            <div class="{{ $dl }} items-start">
                <dt class="pt-1 font-medium text-brand-label">Fit to work file</dt>
                <dd class="min-w-0 w-full">
                    @if ($e->fit_to_work_path)
                        @if (\App\Support\RegistrationDisplay::isLikelyImagePath($e->fit_to_work_path))
                            <div class="overflow-hidden rounded-xl border border-brand-border bg-white shadow-inner">
                                <img src="{{ $fileUrl('fit-to-work') }}" alt="Fit to work" class="max-h-80 w-full object-contain" loading="lazy" />
                            </div>
                        @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($e->fit_to_work_path))
                            <a href="{{ $fileUrl('fit-to-work') }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-primary-dark">View fit to work (PDF)</a>
                        @else
                            <a href="{{ $fileUrl('fit-to-work') }}" target="_blank" class="font-semibold text-brand-link hover:underline">Download fit to work file</a>
                        @endif
                    @endif
                    @if ($canEditProfile)
                        <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">Replace file</label>
                        <input type="file" name="fit_to_work" class="mt-1 {{ $editIn }}" accept="image/*,.pdf" />
                    @endif
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
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Licences (notes)</dt><dd class="min-w-0">@if ($canEditProfile)<textarea name="licences_summary" rows="3" maxlength="5000" class="{{ $editTa }}">{{ old('licences_summary', $e->licences_summary) }}</textarea>@else<span class="whitespace-pre-wrap text-brand-text">{{ $line($e->licences_summary) }}</span>@endif</dd></div>
        <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">Licence uploads</dt>
            <dd class="min-w-0 w-full">
                @if ($licenceRows === [])
                    <p class="text-sm text-brand-text-secondary">No licence rows on file.</p>
                @else
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($licenceRows as $doc)
                            <div class="rounded-xl border border-brand-border bg-brand-surface/40 p-4">
                                @if ($canEditProfile && $doc['row_key'] !== null)
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">Licence type</label>
                                    <select name="licence_type_row[{{ $doc['row_key'] }}]" class="{{ $editIn }} mb-3">
                                        <option value="">—</option>
                                        @foreach ($registrationPicklists->get('licence_type', collect()) as $item)
                                            <option value="{{ $item->value }}" @selected(old('licence_type_row.'.$doc['row_key'], $licTypeForKey($doc['row_key'])) === $item->value)>{{ $item->label ?: $item->value }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <p class="font-semibold text-brand-text">{{ $doc['title'] }}</p>
                                @foreach ($doc['meta'] as $metaLine)
                                    <p class="mt-2 text-xs text-brand-text-secondary">{{ $metaLine }}</p>
                                @endforeach
                                @if ($doc['storage_path'] !== null && $doc['row_key'] !== null)
                                    @php $url = $fileUrl('licence', $doc['row_key']); @endphp
                                    @if (\App\Support\RegistrationDisplay::isLikelyImagePath($doc['storage_path']))
                                        <div class="mt-3 overflow-hidden rounded-lg border border-brand-border bg-white">
                                            <img src="{{ $url }}" alt="{{ $doc['title'] }}" class="max-h-72 w-full object-contain" loading="lazy" />
                                        </div>
                                    @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($doc['storage_path']))
                                        <a href="{{ $url }}" target="_blank" class="mt-3 inline-flex rounded-lg bg-brand-primary px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-brand-primary-dark">View PDF</a>
                                    @else
                                        <a href="{{ $url }}" target="_blank" class="mt-3 inline-flex text-xs font-bold text-brand-link hover:underline">Open file</a>
                                    @endif
                                @endif
                                @if ($canEditProfile && $doc['row_key'] !== null)
                                    <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-brand-label">Replace file</label>
                                    <input type="file" name="licence_upload[{{ $doc['row_key'] }}]" class="mt-1 {{ $editIn }}" accept="image/*,.pdf" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </dd>
        </div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Insurances (notes)</dt><dd class="min-w-0">@if ($canEditProfile)<textarea name="insurances_summary" rows="3" maxlength="5000" class="{{ $editTa }}">{{ old('insurances_summary', $e->insurances_summary) }}</textarea>@else<span class="whitespace-pre-wrap text-brand-text">{{ $line($e->insurances_summary) }}</span>@endif</dd></div>
        <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">Insurance uploads</dt>
            <dd class="min-w-0 w-full">
                @if ($insuranceRows === [])
                    <p class="text-sm text-brand-text-secondary">No insurance rows on file.</p>
                @else
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($insuranceRows as $doc)
                            <div class="rounded-xl border border-brand-border bg-brand-surface/40 p-4">
                                @if ($canEditProfile && $doc['row_key'] !== null)
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">Insurance type</label>
                                    <select name="insurance_type_row[{{ $doc['row_key'] }}]" class="{{ $editIn }} mb-3">
                                        <option value="">—</option>
                                        @foreach ($registrationPicklists->get('insurance_type', collect()) as $item)
                                            <option value="{{ $item->value }}" @selected(old('insurance_type_row.'.$doc['row_key'], $insTypeForKey($doc['row_key'])) === $item->value)>{{ $item->label ?: $item->value }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <p class="font-semibold text-brand-text">{{ $doc['title'] }}</p>
                                @foreach ($doc['meta'] as $metaLine)
                                    <p class="mt-2 text-xs text-brand-text-secondary">{{ $metaLine }}</p>
                                @endforeach
                                @if ($doc['storage_path'] !== null && $doc['row_key'] !== null)
                                    @php $url = $fileUrl('insurance', $doc['row_key']); @endphp
                                    @if (\App\Support\RegistrationDisplay::isLikelyImagePath($doc['storage_path']))
                                        <div class="mt-3 overflow-hidden rounded-lg border border-brand-border bg-white">
                                            <img src="{{ $url }}" alt="{{ $doc['title'] }}" class="max-h-72 w-full object-contain" loading="lazy" />
                                        </div>
                                    @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($doc['storage_path']))
                                        <a href="{{ $url }}" target="_blank" class="mt-3 inline-flex rounded-lg bg-brand-primary px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-brand-primary-dark">View PDF</a>
                                    @else
                                        <a href="{{ $url }}" target="_blank" class="mt-3 inline-flex text-xs font-bold text-brand-link hover:underline">Open file</a>
                                    @endif
                                @endif
                                @if ($canEditProfile && $doc['row_key'] !== null)
                                    <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-brand-label">Replace file</label>
                                    <input type="file" name="insurance_upload[{{ $doc['row_key'] }}]" class="mt-1 {{ $editIn }}" accept="image/*,.pdf" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </dd>
        </div>
    </div>
</section>

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Payroll, transport &amp; role</h3>
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Account name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_account_name" maxlength="160" value="{{ old('bank_account_name', $e->bank_account_name) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_account_name) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Account number</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_account_number" maxlength="500" class="{{ $editIn }}" placeholder="Leave blank to keep current (ends {{ $bankMasked }})" autocomplete="off" />@else<span class="font-mono text-brand-text">{{ $bankMasked }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Branch code</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_branch_code" maxlength="32" value="{{ old('bank_branch_code', $e->bank_branch_code) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_branch_code) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Bank name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_name" maxlength="160" value="{{ old('bank_name', $e->bank_name) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_name) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Mode of transport</dt><dd class="min-w-0">@if ($canEditProfile)<select name="mode_of_transport" data-reg-mode-transport data-reg-own-value="{{ $ownVehicleValue }}" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('transport_mode', collect()) as $item)<option value="{{ $item->value }}" @selected(old('mode_of_transport', $e->mode_of_transport) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select>@else<span class="text-brand-text">{{ $line($e->mode_of_transport) }}</span>@endif</dd></div>
        @if ($canEditProfile)
            <div data-reg-vehicle-fields class="{{ $transportIsOwnVehicle(old('mode_of_transport', $e->mode_of_transport)) ? '' : 'hidden' }}">
                <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle registration</dt><dd class="min-w-0"><input type="text" name="vehicle_registration" maxlength="64" value="{{ old('vehicle_registration', $e->vehicle_registration) }}" class="{{ $editIn }}" /></dd></div>
                <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle expiry</dt><dd class="min-w-0"><input type="date" name="vehicle_expiry" value="{{ $registrationDateInputs['vehicle_expiry'] ?? '' }}" class="{{ $nativeDateIn }}" /></dd></div>
                <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle insurance uploaded (declaration)</dt><dd class="min-w-0"><select name="vehicle_insurance_uploaded" class="{{ $editIn }}"><option value="">—</option>@foreach ($registrationPicklists->get('unrestricted_work_rights', collect()) as $item)<option value="{{ $item->value }}" @selected(old('vehicle_insurance_uploaded', $yesNoPickVal($e->vehicle_insurance_uploaded)) === $item->value)>{{ $item->label ?: $item->value }}</option>@endforeach</select></dd></div>
                @if ($e->vehicle_insurance_path)
                    <div class="{{ $dl }} items-start">
                        <dt class="pt-1 font-medium text-brand-label">Vehicle insurance file</dt>
                        <dd class="min-w-0 w-full">
                            @if (\App\Support\RegistrationDisplay::isLikelyImagePath($e->vehicle_insurance_path))
                                <div class="overflow-hidden rounded-xl border border-brand-border bg-white shadow-inner">
                                    <img src="{{ $fileUrl('vehicle-insurance') }}" alt="Vehicle insurance" class="max-h-80 w-full object-contain" loading="lazy" />
                                </div>
                            @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($e->vehicle_insurance_path))
                                <a href="{{ $fileUrl('vehicle-insurance') }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-primary-dark">View vehicle insurance (PDF)</a>
                            @else
                                <a href="{{ $fileUrl('vehicle-insurance') }}" target="_blank" class="font-semibold text-brand-link hover:underline">Download vehicle insurance file</a>
                            @endif
                            <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-brand-label">Replace file</label>
                            <input type="file" name="vehicle_insurance" class="mt-1 {{ $editIn }}" accept="image/*,.pdf" />
                        </dd>
                    </div>
                @else
                    <div class="{{ $dl }} items-start">
                        <dt class="pt-1 font-medium text-brand-label">Vehicle insurance file</dt>
                        <dd class="min-w-0 w-full">
                            <input type="file" name="vehicle_insurance" class="{{ $editIn }}" accept="image/*,.pdf" />
                        </dd>
                    </div>
                @endif
            </div>
        @elseif ($transportIsOwnVehicle($e->mode_of_transport))
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle registration</dt><dd class="min-w-0"><span class="text-brand-text">{{ $line($e->vehicle_registration) }}</span></dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle expiry</dt><dd class="min-w-0"><span class="text-brand-text">{{ $profileDateLine('vehicle_expiry', ['vehicleExpiry', 'vehicle_expiry']) }}</span></dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle insurance uploaded (declaration)</dt><dd class="min-w-0"><span class="text-brand-text">{{ $yesNo($e->vehicle_insurance_uploaded) }}</span></dd></div>
            @if ($e->vehicle_insurance_path)
                <div class="{{ $dl }} items-start">
                    <dt class="pt-1 font-medium text-brand-label">Vehicle insurance file</dt>
                    <dd class="min-w-0 w-full">
                        @if (\App\Support\RegistrationDisplay::isLikelyImagePath($e->vehicle_insurance_path))
                            <div class="overflow-hidden rounded-xl border border-brand-border bg-white shadow-inner">
                                <img src="{{ $fileUrl('vehicle-insurance') }}" alt="Vehicle insurance" class="max-h-80 w-full object-contain" loading="lazy" />
                            </div>
                        @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($e->vehicle_insurance_path))
                            <a href="{{ $fileUrl('vehicle-insurance') }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-primary-dark">View vehicle insurance (PDF)</a>
                        @else
                            <a href="{{ $fileUrl('vehicle-insurance') }}" target="_blank" class="font-semibold text-brand-link hover:underline">Download vehicle insurance file</a>
                        @endif
                    </dd>
                </div>
            @endif
        @endif
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Job title</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="job_title" maxlength="160" value="{{ old('job_title', $e->job_title) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->job_title) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Department</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="department" maxlength="160" value="{{ old('department', $e->department) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->department) }}</span>@endif</dd></div>
        <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Employee code</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="employee_code" maxlength="64" value="{{ old('employee_code', $e->employee_code) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->employee_code) }}</span>@endif</dd></div>
    </div>
</section>
