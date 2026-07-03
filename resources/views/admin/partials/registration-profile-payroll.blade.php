@php
    use App\Support\PayrollEmployeeRates;
    use App\Support\PayrollRateTypes;
    /** @var \App\Models\Employee $e */
    /** @var bool $canEditProfile */
    /** @var string $connection */
    $connection = $connection ?? ($e->getConnectionName() ?? config('database.default'));
    $employeeRates = PayrollEmployeeRates::forEmployee($connection, $e);
    $allowances = is_array($e->payroll_allowances_json) ? $e->payroll_allowances_json : [];
    if ($allowances === []) {
        $allowances = [['name' => '', 'amount' => '']];
    }
    $leaveRecords = ($e->leaveRecords ?? collect())->sortByDesc('leave_date')->take(8);
    $rateIn = $editIn.' font-mono tabular-nums max-w-[8rem]';
@endphp

<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Employment type</dt><dd class="min-w-0">@if ($canEditProfile)<select name="employment_type" class="{{ $editIn }}"><option value="">— Select —</option>@foreach (PayrollRateTypes::employmentTypes() as $et)<option value="{{ $et }}" @selected(old('employment_type', $e->employment_type) === $et)>{{ PayrollRateTypes::employmentTypeLabel($et) }}</option>@endforeach</select>@else<span class="text-brand-text">{{ PayrollRateTypes::employmentTypeLabel($e->employment_type) }}</span>@endif</dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Award level</dt><dd class="min-w-0">@if ($canEditProfile)<select name="award_level" class="{{ $editIn }}"><option value="">— Select —</option>@foreach (PayrollRateTypes::awardLevels() as $al)<option value="{{ $al }}" @selected(old('award_level', $e->award_level) === $al)>{{ PayrollRateTypes::awardLevelLabel($al) }}</option>@endforeach</select><p class="mt-1 text-xs text-brand-text-secondary">Part-time overtime uses <strong>Hours per week</strong> from Work Eligibility.</p>@else<span class="text-brand-text">{{ PayrollRateTypes::awardLevelLabel($e->award_level) }}</span>@endif</dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Non-rotating shift</dt><dd class="min-w-0">@if ($canEditProfile)<label class="inline-flex items-center gap-2 text-sm text-brand-text"><input type="hidden" name="is_non_rotating_shift" value="0" /><input type="checkbox" name="is_non_rotating_shift" value="1" class="rounded border-brand-border text-brand-primary focus:ring-brand-primary/30" @checked(old('is_non_rotating_shift', $e->is_non_rotating_shift)) /> Qualifies for midnight-shift penalty rate (finish after midnight, ≤8am)</label>@else<span class="text-brand-text">{{ $e->is_non_rotating_shift ? 'Yes' : 'No' }}</span>@endif</dd></div>

@if ($canEditProfile && in_array($e->employment_type, PayrollRateTypes::employmentTypes(), true) && in_array($e->award_level, PayrollRateTypes::awardLevels(), true))
<div class="{{ $dlStart ?? $dl }}">
    <dt class="font-medium text-brand-label pt-1">Pay rates ($/hr)</dt>
    <dd class="min-w-0">
        <p class="mb-3 text-xs text-brand-text-secondary">Stored on this employee profile (PDF requirement). Pre-filled from award — edit any rate below.</p>
        <div class="overflow-x-auto rounded-xl border border-brand-border/80">
            <table class="min-w-full text-left text-sm">
                <tbody class="divide-y divide-brand-border/80">
                    @foreach (PayrollRateTypes::awardRateKeys() as $rateType)
                        <tr>
                            <td class="px-3 py-2 text-brand-text">{{ PayrollRateTypes::label($rateType) }}</td>
                            <td class="px-3 py-2 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <span class="text-xs text-brand-text-secondary">$</span>
                                    <input type="number" step="0.01" min="0" name="payroll_rates[{{ $rateType }}]" value="{{ old('payroll_rates.'.$rateType, $employeeRates[$rateType] ?? '') }}" class="{{ $rateIn }}" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </dd>
</div>
@elseif (! $canEditProfile && $employeeRates !== [])
<div class="{{ $dlStart ?? $dl }}">
    <dt class="font-medium text-brand-label">Pay rates</dt>
    <dd class="min-w-0 text-sm text-brand-text-secondary">
        @foreach (PayrollRateTypes::awardRateKeys() as $rateType)
            @if (($employeeRates[$rateType] ?? 0) > 0)
                <div class="flex justify-between gap-4 border-b border-brand-border/50 py-1 last:border-0"><span>{{ PayrollRateTypes::label($rateType) }}</span><span class="font-mono">${{ number_format((float) $employeeRates[$rateType], 2) }}</span></div>
            @endif
        @endforeach
    </dd>
</div>
@endif

<div class="{{ $dlStart ?? $dl }}">
    <dt class="font-medium text-brand-label">Allowances</dt>
    <dd class="min-w-0 space-y-3">
        @if ($canEditProfile)
            @foreach ($allowances as $i => $row)
                <div class="grid gap-3 sm:grid-cols-2">
                    <input type="text" name="allowance_name[]" maxlength="120" value="{{ old('allowance_name.'.$i, $row['name'] ?? '') }}" class="{{ $editIn }}" placeholder="Allowance name" />
                    <input type="number" step="0.01" min="0" name="allowance_amount[]" value="{{ old('allowance_amount.'.$i, $row['amount'] ?? '') }}" class="{{ $editIn }}" placeholder="Amount per fortnight $" />
                </div>
            @endforeach
            <p class="text-xs text-brand-text-secondary">Add multiple rows for different allowance types.</p>
        @else
            @php $hasAllowance = false; @endphp
            @foreach ($allowances as $row)
                @if (trim((string) ($row['name'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0)
                    @php $hasAllowance = true; @endphp
                    <div class="text-brand-text">{{ $row['name'] }} — ${{ number_format((float) $row['amount'], 2) }} / fortnight</div>
                @endif
            @endforeach
            @if (! $hasAllowance)<span class="text-brand-text">—</span>@endif
        @endif
    </dd>
</div>

<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Sick leave balance</dt><dd class="min-w-0"><span class="text-brand-text">{{ number_format((float) ($e->sick_leave_balance_hours ?? 0), 2) }} hrs</span> · <span class="font-mono text-brand-text">${{ number_format((float) ($e->sick_leave_balance_amount ?? 0), 2) }}</span><p class="mt-1 text-xs text-brand-text-secondary">Accrued at ordinary hourly rate when pay runs are finalized.</p></dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Annual leave balance</dt><dd class="min-w-0"><span class="text-brand-text">{{ number_format((float) ($e->annual_leave_balance_hours ?? 0), 2) }} hrs</span> · <span class="font-mono text-brand-text">${{ number_format((float) ($e->annual_leave_balance_amount ?? 0), 2) }}</span><p class="mt-1 text-xs text-brand-text-secondary">Paid at ordinary rate + 17.5% loading when leave is taken and processed in a pay run.</p></dd></div>

@if ($canEditProfile)
<div class="{{ $dlStart ?? $dl }}">
    <dt class="font-medium text-brand-label pt-1">Record leave taken</dt>
    <dd class="min-w-0">
        <form method="post" action="{{ route('admin.registrations.leave.store', ['companySlug' => $company->slug, 'publicId' => $e->public_id]) }}" class="grid gap-3 rounded-xl border border-brand-border bg-brand-surface/40 p-4 sm:grid-cols-2">
            @csrf
            <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-brand-label">Type</label>
                <select name="leave_type" class="{{ $editIn }}" required>
                    <option value="sick">Sick leave</option>
                    <option value="annual">Annual leave</option>
                </select>
            </div>
            <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-brand-label">Date</label>
                <input type="date" name="leave_date" required class="{{ $editIn }}" />
            </div>
            <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-brand-label">Hours</label>
                <input type="number" step="0.25" min="0.25" max="24" name="leave_hours" required class="{{ $editIn }}" placeholder="e.g. 7.6" />
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-brand-label">Notes</label>
                <input type="text" name="leave_notes" maxlength="500" class="{{ $editIn }}" placeholder="Optional" />
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-xl border border-brand-border bg-white px-4 py-2.5 text-sm font-semibold text-brand-primary shadow-sm hover:bg-brand-surface">Add pending leave</button>
                <p class="mt-2 text-xs text-brand-text-secondary">Processed in the next finalized pay run for that fortnight.</p>
            </div>
        </form>
        @if ($leaveRecords->isNotEmpty())
            <ul class="mt-4 divide-y divide-brand-border/80 text-sm">
                @foreach ($leaveRecords as $rec)
                    <li class="flex justify-between gap-3 py-2">
                        <span class="text-brand-text">{{ ucfirst($rec->leave_type) }} · {{ \App\Support\DisplayTimezone::format($rec->leave_date, 'M j, Y') }} · {{ number_format((float) $rec->hours, 2) }}h</span>
                        <span class="text-xs font-semibold {{ $rec->status === 'paid' ? 'text-emerald-700' : 'text-amber-800' }}">{{ ucfirst($rec->status) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </dd>
</div>
@endif
