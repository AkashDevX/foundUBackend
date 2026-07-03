@php
    /** @var \App\Models\Company $company */
    /** @var \App\Models\Employee $employee */
    /** @var bool $showApprovalActions */
    use App\Support\DisplayTimezone;

    $e = $employee;
    $showApprovalActions = $showApprovalActions ?? false;
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
    $bankHasAccount = $bank !== null && trim((string) $bank) !== '';
    $bankMasked = $bankHasAccount ? str_repeat('X', 10).substr((string) $bank, -4) : '—';

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

    $canEditProfile = ($e->employment_status ?? '') === 'active';
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\RegistrationPicklistItem>> $registrationPicklists */
    $registrationPicklists = $registrationPicklists ?? collect();
    /** @var array<string, array{morning: bool, evening: bool}> $weeklyGrid */
    $weeklyGrid = $weeklyGrid ?? \App\Support\AdminWeeklyAvailability::mobileGridStateForEmployee(
        $e->weekly_availability_json,
        $e->weekly_availability_summary
    );
    $editIn = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-inner [color-scheme:light] placeholder:text-brand-text-secondary/60 focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25';
    $nativeDateIn = 'w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm [color-scheme:light] focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25';
    /** @var array<string, string> $registrationDateInputs */
    $registrationDateInputs = $registrationDateInputs ?? [];
    $profileDateLine = static function (string $field, array $metaKeys) use ($line, $e): string {
        $iso = \App\Support\RegistrationDisplay::toHtmlDateInput(
            \App\Support\RegistrationDisplay::employeeRawDateValue($e, $field, $metaKeys)
        );

        return $line($iso !== '' ? $iso : null);
    };
    $sexForSelect = static function (mixed $v): string {
        if ($v === null || $v === '') {
            return '';
        }
        $s = strtolower(trim((string) $v));
        if (in_array($s, ['male', 'm', 'man'], true)) {
            return 'Male';
        }
        if (in_array($s, ['female', 'f', 'woman'], true)) {
            return 'Female';
        }
        if (str_starts_with($s, 'male')) {
            return 'Male';
        }
        if (str_starts_with($s, 'female')) {
            return 'Female';
        }
        $t = trim((string) $v);
        if (strcasecmp($t, 'Male') === 0) {
            return 'Male';
        }
        if (strcasecmp($t, 'Female') === 0) {
            return 'Female';
        }

        return '';
    };
    $yesNoPickVal = static function ($v): string {
        if ($v === null || $v === '') {
            return '';
        }
        $s = is_string($v) ? strtolower(trim($v)) : (string) $v;
        if (in_array($s, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return 'Yes';
        }
        if (in_array($s, ['0', 'false', 'no', 'n', 'off'], true)) {
            return 'No';
        }

        return is_string($v) ? trim($v) : '';
    };
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
    $dl = 'grid gap-4 border-b border-brand-border py-4 text-sm sm:grid-cols-[minmax(0,220px)_1fr] sm:items-center last:border-b-0';
    $dlStart = 'grid gap-4 border-b border-brand-border py-4 text-sm sm:grid-cols-[minmax(0,220px)_1fr] sm:items-start last:border-b-0';
    $card = 'mb-10 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm';
    $cardHead = 'border-b border-brand-border bg-gradient-to-r from-brand-surface to-white px-6 py-4 sm:px-8';
@endphp

@if ($showApprovalActions && ($e->employment_status ?? '') === 'pending')
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
                <form
                    method="post"
                    action="{{ route('admin.registrations.accept', ['companySlug' => $company->slug, 'publicId' => $e->public_id]) }}"
                    class="inline"
                    data-confirm="This person will be able to sign in to the mobile app with their registration email and password."
                    data-confirm-title="Approve registration?"
                    data-confirm-confirm="Approve"
                    data-confirm-cancel="Not yet"
                    data-confirm-icon="question"
                >
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 transition hover:bg-emerald-700">
                        Approve
                    </button>
                </form>
                <form
                    method="post"
                    action="{{ route('admin.registrations.decline', ['companySlug' => $company->slug, 'publicId' => $e->public_id]) }}"
                    class="inline"
                    data-confirm="This person will not be able to sign in to the app."
                    data-confirm-title="Decline registration?"
                    data-confirm-confirm="Decline"
                    data-confirm-cancel="Go back"
                    data-confirm-danger="1"
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

@if ($e->profile_photo_path && ! $canEditProfile)
    <div class="mb-10 rounded-2xl border border-brand-border bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Profile photo</p>
        <div class="mt-3 overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-inner">
            <img
                src="{{ $fileUrl('profile-photo') }}"
                alt="Profile photo"
                class="mx-auto max-h-[min(360px,55vh)] w-auto max-w-full object-contain"
                loading="lazy"
                decoding="async"
            />
        </div>
        <a href="{{ $fileUrl('profile-photo') }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex text-xs font-semibold text-brand-link hover:underline">Open full size</a>
    </div>
@endif

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
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Employee ID</p>
        <p class="mt-2 font-mono text-sm font-semibold text-brand-text">{{ $line($e->public_id) }}</p>
    </div>
</div>

<section class="mb-10 overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm">
    <div class="border-b border-brand-border bg-gradient-to-r from-brand-surface to-white px-6 py-4 sm:px-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-brand-text">Work assignment</h3>
            </div>
            <a href="{{ route('admin.workforce') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-brand-border bg-white px-4 py-2 text-sm font-semibold text-brand-primary shadow-sm transition hover:border-brand-primary/40 hover:bg-brand-surface">
                Organization setup
            </a>
        </div>
    </div>
    <div class="space-y-8 px-6 py-6 sm:px-8">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-brand-border bg-brand-surface/50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-brand-label">Department</p>
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
            </div>
            <div class="rounded-xl border border-brand-border bg-brand-surface/50 p-4 sm:col-span-2 lg:col-span-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Shift</p>
                <p class="mt-2 text-sm font-semibold text-brand-text">{{ $line($e->assignedShift?->name) }}</p>
                <p class="mt-1 text-xs text-brand-text-secondary">{{ $shiftTimes($e->assignedShift) }}</p>
                <p class="mt-1 text-xs text-brand-text-secondary">Days: {{ $shiftDays($e->assignedShift) }}</p>
            </div>
            <div class="rounded-xl border border-brand-border bg-brand-surface/50 p-4 sm:col-span-2 lg:col-span-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-label">Effective / notes</p>
                <p class="mt-2 text-sm font-semibold text-brand-text">{{ DisplayTimezone::formatDate($e->assignment_effective_from) }}</p>
                <p class="mt-3 whitespace-pre-wrap text-xs leading-relaxed text-brand-text-secondary">{{ $line($e->assignment_notes) }}</p>
            </div>
        </div>

        @if (($e->employment_status ?? '') === 'active')
            <div class="border-t border-brand-border pt-8">
                <p class="text-base font-bold text-brand-text">Update assignment</p>
                <p class="mt-1 text-sm text-brand-text-secondary">Choose catalogs from Organization setup. Leave as “None” to clear a field.</p>
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
                        <input type="date" name="assignment_effective_from" value="{{ $registrationDateInputs['assignment_effective_from'] ?? '' }}" class="mt-2 {{ $nativeDateIn }}" />
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

@if ($canEditProfile)
    <form method="post" action="{{ route('admin.registrations.profile.update', ['companySlug' => $company->slug, 'publicId' => $e->public_id]) }}" enctype="multipart/form-data" class="block">
        @csrf
@endif

@include('admin.partials.registration-profile-body')

@if ($canEditProfile)
        <div class="mb-10 flex flex-wrap items-center gap-3 border-t border-brand-border pt-8">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-primary/25 transition hover:bg-brand-primary-dark">
                Save profile changes
            </button>
        </div>
    </form>

    @push('scripts')
        @vite(['resources/js/registration-admin-profile.js'])
    @endpush
@endif
