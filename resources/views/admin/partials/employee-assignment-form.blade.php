@php
    /** @var \App\Models\Employee $employee */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Department> $departments */
    /** @var \Illuminate\Support\Collection<int, \App\Models\WorkLocation> $workLocations */
@endphp

<form method="post" action="{{ route('admin.employees.assignment.update', ['publicId' => $employee->public_id]) }}" class="grid gap-4 lg:grid-cols-3">
    @csrf
    <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Department</label>
        <select name="department_id" class="mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25">
            <option value="">— None —</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected((string) $employee->department_id === (string) $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Work location</label>
        <select name="work_location_id" class="mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25">
            <option value="">— None —</option>
            @foreach ($workLocations as $loc)
                <option value="{{ $loc->id }}" @selected((string) $employee->work_location_id === (string) $loc->id)>{{ $loc->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Effective from</label>
        @php
            $effFrom = \App\Support\RegistrationDisplay::toHtmlDateInput(old('assignment_effective_from', $employee->assignment_effective_from));
        @endphp
        <input type="date" name="assignment_effective_from" value="{{ $effFrom }}" class="mt-1.5 w-full rounded-xl border border-brand-border bg-white px-3 py-2.5 text-sm text-brand-text shadow-sm [color-scheme:light] focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" />
    </div>

    <div class="lg:col-span-3">
        <label class="block text-xs font-semibold uppercase tracking-wide text-brand-label">Assignment notes</label>
        <textarea name="assignment_notes" rows="2" maxlength="5000" class="mt-1.5 w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/25" placeholder="Parking bay, supervisor, uniform, etc.">{{ $employee->assignment_notes }}</textarea>
    </div>

    <div class="lg:col-span-3 flex flex-wrap items-center justify-between gap-3 border-t border-brand-border pt-3">
        <p class="text-xs text-brand-text-secondary">
            Current: {{ $employee->assignedDepartment?->name ?? 'No department' }} · {{ $employee->workLocation?->name ?? 'No location' }}
            · Schedule shifts on the
            <a href="{{ route('admin.employees.weekly-schedule') }}" class="font-semibold text-brand-primary hover:underline">weekly calendar</a>
        </p>
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
            Save assignment
        </button>
    </div>
</form>
