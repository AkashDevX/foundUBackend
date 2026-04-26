@extends('layouts.admin')

@php
    /** @var \App\Models\Company $company */
    /** @var \App\Models\Employee $employee */
    $e = $employee;
    $line = static function (?string $v): string {
        return $v !== null && trim($v) !== '' ? e(trim($v)) : '—';
    };
    $yesNo = static function ($v): string {
        if ($v === null || $v === '') {
            return '—';
        }
        $s = is_string($v) ? strtolower(trim($v)) : (string) $v;
        if (in_array($s, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return 'Yes';
        }
        if (in_array($s, ['0', 'false', 'no', 'n', 'off', ''], true)) {
            return 'No';
        }

        return e((string) $v);
    };
    $bank = $e->bank_account_number;
    $bankMasked = ($bank !== null && $bank !== '') ? '········'.substr((string) $bank, max(-4, -strlen((string) $bank))) : '—';

    $weeklySections = \App\Support\RegistrationDisplay::weeklyAvailabilitySections($e->weekly_availability_json);
    $idDocRows = \App\Support\RegistrationDisplay::idDocumentRows($e->id_documents_json);
    $licenceRows = \App\Support\RegistrationDisplay::licenceRows($e->licences_json);
    $insuranceRows = \App\Support\RegistrationDisplay::insuranceRows($e->insurances_json);

    $fileUrl = static function (string $slot, ?string $itemKey = null) use ($company, $e): string {
        $p = [
            'companySlug' => $company->slug,
            'publicId' => $e->public_id,
            'slot' => $slot,
        ];
        if ($itemKey !== null && $itemKey !== '') {
            $p['itemKey'] = $itemKey;
        }

        return route('admin.registration.file', $p);
    };
@endphp

@section('title', 'Registration — '.$e->full_legal_name)

@section('heading', 'Application details')

@section('subheading')
    {{ $company->name }} · Submitted {{ $e->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}
@endsection

@section('content')
    @if (session('status'))
        <div class="mb-8 rounded-2xl border border-emerald-200/90 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-950 shadow-sm ring-1 ring-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-8 rounded-2xl border border-red-200/90 bg-red-50 px-5 py-4 text-sm text-red-950 shadow-sm ring-1 ring-red-100">
            <p class="font-semibold">Could not save assignment</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-8 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-link hover:underline">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Back to all requests
        </a>
        <span class="hidden text-brand-text-secondary sm:inline">·</span>
        <span class="font-mono text-xs text-brand-text-secondary">public_id {{ $e->public_id }}</span>
    </div>

    @if (($e->employment_status ?? '') === 'pending')
        <div class="mb-10 rounded-2xl border border-amber-300/80 bg-gradient-to-br from-amber-50 to-white p-6 shadow-md ring-1 ring-amber-200/60">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-brand-text">Review this application</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-brand-text-secondary">
                        <strong>Approve</strong> to allow this person to open the mobile app and sign in with the same email and password they used at registration.
                        <strong>Decline</strong> if they should not get app access.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <form method="post" action="{{ route('admin.registrations.accept', ['companySlug' => $company->slug, 'publicId' => $e->public_id]) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 transition hover:bg-emerald-700">
                            Approve
                        </button>
                    </form>
                    <form
                        method="post"
                        action="{{ route('admin.registrations.decline', ['companySlug' => $company->slug, 'publicId' => $e->public_id]) }}"
                        class="inline"
                        onsubmit="return confirm('Decline this registration? This person will not be able to sign in to the app.');"
                    >
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border-2 border-red-200 bg-white px-6 py-3 text-sm font-bold text-red-800 shadow-sm transition hover:bg-red-50">
                            Decline
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($e->profile_photo_path)
        <div class="mb-10 rounded-2xl border border-brand-border bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Profile photo</p>
            <div class="mt-3 overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-inner">
                <img
                    src="{{ $fileUrl('profile-photo') }}"
                    alt="Applicant profile photo"
                    class="mx-auto max-h-[min(360px,55vh)] w-auto max-w-full object-contain"
                    loading="lazy"
                    decoding="async"
                />
            </div>
            <a href="{{ $fileUrl('profile-photo') }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex text-xs font-semibold text-brand-link hover:underline">Open full size</a>
        </div>
    @endif

    {{-- Summary strip --}}
    <div class="mb-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-brand-border bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Employment status</p>
            <p class="mt-2 text-lg font-semibold text-brand-text">{{ $line($e->employment_status) }}</p>
        </div>
        <div class="rounded-xl border border-brand-border bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Email</p>
            <p class="mt-2 break-all text-sm font-semibold text-brand-text">{{ $line($e->email) }}</p>
        </div>
        <div class="rounded-xl border border-brand-border bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Phone</p>
            <p class="mt-2 text-lg font-semibold text-brand-text">{{ $line($e->phone) }}</p>
        </div>
        <div class="rounded-xl border border-brand-border bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Registration org</p>
            <p class="mt-2 text-sm font-semibold text-brand-text">{{ $line($e->company_display_name ?: $company->name) }}</p>
            <p class="mt-1 font-mono text-xs text-brand-text-secondary">{{ $line($e->registration_company_slug) }}</p>
        </div>
    </div>

    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\Department> $departments */
        /** @var \Illuminate\Support\Collection<int, \App\Models\WorkLocation> $workLocations */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Shift> $shifts */
        $shiftTimes = static function (?\App\Models\Shift $s): string {
            if ($s === null) {
                return '—';
            }
            $st = $s->start_time instanceof \Carbon\CarbonInterface ? $s->start_time->format('g:i A') : '—';
            $en = $s->end_time instanceof \Carbon\CarbonInterface ? $s->end_time->format('g:i A') : '—';

            return $st.'–'.$en;
        };
        $shiftDays = static function (?\App\Models\Shift $s): string {
            if ($s === null || ! is_array($s->shift_days) || $s->shift_days === []) {
                return 'All days';
            }
            $map = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
            return collect($s->shift_days)->map(fn ($d) => $map[$d] ?? null)->filter()->join(', ');
        };
    @endphp

    <section class="mb-10 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
        <div class="border-b border-brand-border bg-gradient-to-r from-brand-surface to-white px-6 py-4 sm:px-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-brand-text">Work assignment</h3>
                    <p class="mt-1 text-sm text-brand-text-secondary">
                        Shown to the employee in the mobile app after sign-in (<code class="rounded bg-brand-surface px-1 py-0.5 font-mono text-xs">GET /api/v1/me</code>).
                    </p>
                </div>
                <a href="{{ route('admin.workforce') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-brand-border bg-white px-4 py-2 text-sm font-semibold text-brand-primary shadow-sm transition hover:border-brand-primary/40 hover:bg-brand-surface">
                    Workforce setup
                </a>
            </div>
        </div>
        <div class="space-y-8 px-6 py-6 sm:px-8">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-brand-border bg-brand-surface/50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Department</p>
                    <p class="mt-2 text-sm font-semibold text-brand-text">{{ $line($e->assignedDepartment?->name) }}</p>
                    @if ($e->assignedDepartment?->code)
                        <p class="mt-1 font-mono text-xs text-brand-text-secondary">{{ $line($e->assignedDepartment->code) }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-brand-border bg-brand-surface/50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Work location</p>
                    <p class="mt-2 text-sm font-semibold text-brand-text">{{ $line($e->workLocation?->name) }}</p>
                    @if ($e->workLocation?->address)
                        <p class="mt-2 whitespace-pre-wrap text-xs leading-relaxed text-brand-text-secondary">{{ $line($e->workLocation->address) }}</p>
                    @endif
                    @if ($e->workLocation && $e->workLocation->latitude !== null && $e->workLocation->longitude !== null)
                        @php
                            $empOsm = 'https://www.openstreetmap.org/?mlat='.urlencode((string) $e->workLocation->latitude).'&mlon='.urlencode((string) $e->workLocation->longitude).'#map=17/'.$e->workLocation->latitude.'/'.$e->workLocation->longitude;
                        @endphp
                        <p class="mt-2 font-mono text-[11px] tabular-nums text-brand-text-secondary">
                            {{ number_format((float) $e->workLocation->latitude, 5) }}, {{ number_format((float) $e->workLocation->longitude, 5) }}
                            · <a href="{{ $empOsm }}" target="_blank" rel="noopener noreferrer" class="font-sans font-semibold text-brand-link hover:underline">Map ↗</a>
                        </p>
                    @endif
                </div>
                <div class="rounded-xl border border-brand-border bg-brand-surface/50 p-4 sm:col-span-2 lg:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Shift</p>
                    <p class="mt-2 text-sm font-semibold text-brand-text">{{ $line($e->assignedShift?->name) }}</p>
                    <p class="mt-1 text-xs text-brand-text-secondary">{{ $shiftTimes($e->assignedShift) }}</p>
                    <p class="mt-1 text-xs text-brand-text-secondary">Days: {{ $shiftDays($e->assignedShift) }}</p>
                    @if ($e->assignedShift?->breaks_summary)
                        <p class="mt-2 text-xs text-brand-text-secondary">{{ $line($e->assignedShift->breaks_summary) }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-brand-border bg-brand-surface/50 p-4 sm:col-span-2 lg:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Effective / notes</p>
                    <p class="mt-2 text-sm font-semibold text-brand-text">{{ $e->assignment_effective_from?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</p>
                    <p class="mt-3 whitespace-pre-wrap text-xs leading-relaxed text-brand-text-secondary">{{ $line($e->assignment_notes) }}</p>
                </div>
            </div>

            @if (($e->employment_status ?? '') === 'active')
                <div class="border-t border-brand-border pt-8">
                    <p class="text-base font-bold text-brand-text">Update assignment</p>
                    <p class="mt-1 text-sm text-brand-text-secondary">Choose catalogs from Workforce setup. Leave as “None” to clear a field.</p>
                    <form method="post" action="{{ route('admin.registrations.assignment.update', ['companySlug' => $company->slug, 'publicId' => $e->public_id]) }}" class="mt-6 grid gap-5 lg:grid-cols-2">
                        @csrf
                        <div class="lg:col-span-2 grid gap-5 sm:grid-cols-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Department</label>
                                <select name="department_id" class="mt-2 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25">
                                    <option value="">— None —</option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}" @selected((string) old('department_id', $e->department_id) === (string) $d->id)>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Work location</label>
                                <select name="work_location_id" class="mt-2 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25">
                                    <option value="">— None —</option>
                                    @foreach ($workLocations as $loc)
                                        <option value="{{ $loc->id }}" @selected((string) old('work_location_id', $e->work_location_id) === (string) $loc->id)>{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Shift</label>
                                <select name="shift_id" class="mt-2 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25">
                                    <option value="">— None —</option>
                                    @foreach ($shifts as $sh)
                                        <option value="{{ $sh->id }}" @selected((string) old('shift_id', $e->shift_id) === (string) $sh->id)>{{ $sh->name }} ({{ $shiftTimes($sh) }}, {{ $shiftDays($sh) }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Effective from</label>
                            <input type="date" name="assignment_effective_from" value="{{ old('assignment_effective_from', optional($e->assignment_effective_from)?->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" />
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Assignment notes</label>
                            <textarea name="assignment_notes" rows="3" maxlength="5000" class="mt-2 w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm shadow-inner focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="Parking bay, supervisor, uniform, etc.">{{ old('assignment_notes', $e->assignment_notes) }}</textarea>
                        </div>
                        <div class="lg:col-span-2 flex flex-wrap items-center gap-3">
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-primary/25 transition hover:bg-brand-primary-dark">
                                Save assignment
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <p class="text-sm text-brand-text-secondary">When this application is approved and active, you can assign department, location, and shift here.</p>
            @endif
        </div>
    </section>

    @php
        $dl = 'grid gap-4 border-b border-brand-border py-4 text-sm sm:grid-cols-[minmax(0,220px)_1fr] last:border-b-0';
        $card = 'mb-10 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm';
        $cardHead = 'border-b border-brand-border bg-gradient-to-r from-brand-surface to-white px-6 py-4 sm:px-8';
    @endphp

    <section class="{{ $card }}">
        <div class="{{ $cardHead }}">
            <h3 class="text-lg font-bold text-brand-text">Identity &amp; personal</h3>
        </div>
        <div class="divide-y divide-brand-border px-6 sm:px-8">
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Full legal name</dt><dd class="text-brand-text">{{ $line($e->full_legal_name) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">First / last (split)</dt><dd class="text-brand-text">{{ $line($e->first_name) }} · {{ $line($e->last_name) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Date of birth</dt><dd class="text-brand-text">{{ $line($e->date_of_birth) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Sex</dt><dd class="text-brand-text">{{ $line($e->sex) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Marital status</dt><dd class="text-brand-text">{{ $line($e->marital_status) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Address</dt><dd class="whitespace-pre-wrap text-brand-text">{{ $line($e->address) }}</dd></div>
        </div>
    </section>

    <section class="{{ $card }}">
        <div class="{{ $cardHead }}">
            <h3 class="text-lg font-bold text-brand-text">Emergency contact</h3>
        </div>
        <div class="divide-y divide-brand-border px-6 sm:px-8">
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Name</dt><dd class="text-brand-text">{{ $line($e->emergency_contact_name) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Phone</dt><dd class="text-brand-text">{{ $line($e->emergency_contact_phone) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Relationship</dt><dd class="text-brand-text">{{ $line($e->emergency_contact_relationship) }}</dd></div>
        </div>
    </section>

    <section class="{{ $card }}">
        <div class="{{ $cardHead }}">
            <h3 class="text-lg font-bold text-brand-text">Work eligibility &amp; availability</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">Readable schedule from the app — no raw data.</p>
        </div>
        <div class="divide-y divide-brand-border px-6 sm:px-8">
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Visa status</dt><dd class="text-brand-text">{{ $line($e->visa_status) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Unrestricted work rights</dt><dd class="text-brand-text">{{ $yesNo($e->unrestricted_work_rights) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Visa expiry</dt><dd class="text-brand-text">{{ $line($e->visa_expiry) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Hours per week</dt><dd class="text-brand-text">{{ $line($e->hours_per_week) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Summary</dt><dd class="whitespace-pre-wrap text-brand-text">{{ $line($e->weekly_availability_summary) }}</dd></div>
            <div class="{{ $dl }} items-start">
                <dt class="pt-1 font-medium text-brand-label">Weekly availability</dt>
                <dd class="min-w-0">
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
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">ID documents (notes)</dt><dd class="whitespace-pre-wrap text-brand-text">{{ $line($e->id_documents_summary) }}</dd></div>
            <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">ID document uploads</dt>
                <dd class="min-w-0 w-full">
                    @if ($idDocRows === [])
                        <p class="text-sm text-brand-text-secondary">No ID document rows on file.</p>
                    @else
                        <div class="grid gap-4 sm:grid-cols-1 lg:grid-cols-2">
                            @foreach ($idDocRows as $doc)
                                <div class="overflow-hidden rounded-xl border border-brand-border bg-brand-surface/40 p-4">
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
                                </div>
                            @endforeach
                        </div>
                    @endif
                </dd>
            </div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Police check expiry</dt><dd class="text-brand-text">{{ $line($e->police_check_expiry) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Police check uploaded (declaration)</dt><dd class="text-brand-text">{{ $yesNo($e->police_check_uploaded) }}</dd></div>
            @if ($e->police_check_path)
                <div class="{{ $dl }} items-start">
                    <dt class="pt-1 font-medium text-brand-label">Police check file</dt>
                    <dd class="min-w-0">
                        @if (\App\Support\RegistrationDisplay::isLikelyImagePath($e->police_check_path))
                            <div class="overflow-hidden rounded-xl border border-brand-border bg-white shadow-inner">
                                <img src="{{ $fileUrl('police-check') }}" alt="Police check" class="max-h-80 w-full object-contain" loading="lazy" />
                            </div>
                        @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($e->police_check_path))
                            <a href="{{ $fileUrl('police-check') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-primary-dark">View police check (PDF)</a>
                        @else
                            <a href="{{ $fileUrl('police-check') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand-link hover:underline">Download police check file</a>
                        @endif
                    </dd>
                </div>
            @endif
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Fit to work expiry</dt><dd class="text-brand-text">{{ $line($e->fit_to_work_expiry) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Fit to work uploaded (declaration)</dt><dd class="text-brand-text">{{ $yesNo($e->fit_to_work_uploaded) }}</dd></div>
            @if ($e->fit_to_work_path)
                <div class="{{ $dl }} items-start">
                    <dt class="pt-1 font-medium text-brand-label">Fit to work file</dt>
                    <dd class="min-w-0">
                        @if (\App\Support\RegistrationDisplay::isLikelyImagePath($e->fit_to_work_path))
                            <div class="overflow-hidden rounded-xl border border-brand-border bg-white shadow-inner">
                                <img src="{{ $fileUrl('fit-to-work') }}" alt="Fit to work" class="max-h-80 w-full object-contain" loading="lazy" />
                            </div>
                        @elseif (\App\Support\RegistrationDisplay::isLikelyPdfPath($e->fit_to_work_path))
                            <a href="{{ $fileUrl('fit-to-work') }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-primary-dark">View fit to work (PDF)</a>
                        @else
                            <a href="{{ $fileUrl('fit-to-work') }}" target="_blank" class="font-semibold text-brand-link hover:underline">Download fit to work file</a>
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
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Licences (notes)</dt><dd class="whitespace-pre-wrap text-brand-text">{{ $line($e->licences_summary) }}</dd></div>
            <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">Licence uploads</dt>
                <dd class="min-w-0 w-full">
                    @if ($licenceRows === [])
                        <p class="text-sm text-brand-text-secondary">No licence rows on file.</p>
                    @else
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($licenceRows as $doc)
                                <div class="rounded-xl border border-brand-border bg-brand-surface/40 p-4">
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
                                </div>
                            @endforeach
                        </div>
                    @endif
                </dd>
            </div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Insurances (notes)</dt><dd class="whitespace-pre-wrap text-brand-text">{{ $line($e->insurances_summary) }}</dd></div>
            <div class="{{ $dl }} items-start"><dt class="pt-1 font-medium text-brand-label">Insurance uploads</dt>
                <dd class="min-w-0 w-full">
                    @if ($insuranceRows === [])
                        <p class="text-sm text-brand-text-secondary">No insurance rows on file.</p>
                    @else
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($insuranceRows as $doc)
                                <div class="rounded-xl border border-brand-border bg-brand-surface/40 p-4">
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
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Account name</dt><dd class="text-brand-text">{{ $line($e->bank_account_name) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Account number</dt><dd class="font-mono text-brand-text">{{ $bankMasked }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Branch code</dt><dd class="text-brand-text">{{ $line($e->bank_branch_code) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Bank name</dt><dd class="text-brand-text">{{ $line($e->bank_name) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Mode of transport</dt><dd class="text-brand-text">{{ $line($e->mode_of_transport) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle registration</dt><dd class="text-brand-text">{{ $line($e->vehicle_registration) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle expiry</dt><dd class="text-brand-text">{{ $line($e->vehicle_expiry) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Vehicle insurance uploaded (declaration)</dt><dd class="text-brand-text">{{ $yesNo($e->vehicle_insurance_uploaded) }}</dd></div>
            @if ($e->vehicle_insurance_path)
                <div class="{{ $dl }} items-start">
                    <dt class="pt-1 font-medium text-brand-label">Vehicle insurance file</dt>
                    <dd class="min-w-0">
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
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Job title</dt><dd class="text-brand-text">{{ $line($e->job_title) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Department</dt><dd class="text-brand-text">{{ $line($e->department) }}</dd></div>
            <div class="{{ $dl }}"><dt class="font-medium text-brand-label">Employee code</dt><dd class="text-brand-text">{{ $line($e->employee_code) }}</dd></div>
        </div>
    </section>
@endsection
