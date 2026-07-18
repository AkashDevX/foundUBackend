@php
    use App\Support\PayrollEmployeeRates;
    use App\Support\PayrollRateTypes;
    /** @var \App\Models\Employee $e */
    /** @var bool $canEditProfile */
    /** @var string $connection */
    $connection = $connection ?? ($e->getConnectionName() ?? config('database.default'));
    $employeeRates = PayrollEmployeeRates::forEmployee($connection, $e);
    $rateIn = $editIn.' font-mono tabular-nums max-w-[8rem]';
@endphp

<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Employment type</dt><dd class="min-w-0">@if ($canEditProfile)<select name="employment_type" class="{{ $editIn }}"><option value="">— Select —</option>@foreach (PayrollRateTypes::employmentTypes() as $et)<option value="{{ $et }}" @selected(old('employment_type', $e->employment_type) === $et)>{{ PayrollRateTypes::employmentTypeLabel($et) }}</option>@endforeach</select>@else<span class="text-brand-text">{{ PayrollRateTypes::employmentTypeLabel($e->employment_type) }}</span>@endif</dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Award level</dt><dd class="min-w-0">@if ($canEditProfile)<select name="award_level" class="{{ $editIn }}"><option value="">— Select —</option>@foreach (PayrollRateTypes::awardLevels() as $al)<option value="{{ $al }}" @selected(old('award_level', $e->award_level) === $al)>{{ PayrollRateTypes::awardLevelLabel($al) }}</option>@endforeach</select>@else<span class="text-brand-text">{{ PayrollRateTypes::awardLevelLabel($e->award_level) }}</span>@endif</dd></div>

@if ($canEditProfile && in_array($e->employment_type, PayrollRateTypes::employmentTypes(), true) && in_array($e->award_level, PayrollRateTypes::awardLevels(), true))
<div class="{{ $dlStart ?? $dl }}">
    <dt class="font-medium text-brand-label pt-1">Pay rates ($/hr)</dt>
    <dd class="min-w-0">
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

<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Account name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_account_name" maxlength="160" value="{{ old('bank_account_name', $e->bank_account_name) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_account_name) }}</span>@endif</dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Account number</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_account_number" maxlength="500" class="{{ $editIn }} font-mono tracking-wide" data-reg-bank-account data-bank-masked="{{ $bankHasAccount ? $bankMasked : '' }}" value="{{ old('bank_account_number') ?: ($bankHasAccount ? $bankMasked : '') }}" placeholder="{{ $bankHasAccount ? '' : 'Enter account number' }}" autocomplete="off" /><p class="mt-1 text-xs text-brand-text-secondary">@if ($bankHasAccount)Masked as {{ $bankMasked }}. Click the field to enter a new number (leave empty to keep current).@elseEnter the full account number.@endif</p>@else<span class="font-mono text-brand-text">{{ $bankMasked }}</span>@endif</dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Branch code</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_branch_code" maxlength="32" value="{{ old('bank_branch_code', $e->bank_branch_code) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_branch_code) }}</span>@endif</dd></div>
<div class="{{ $dl }}"><dt class="font-medium text-brand-label">Bank name</dt><dd class="min-w-0">@if ($canEditProfile)<input type="text" name="bank_name" maxlength="160" value="{{ old('bank_name', $e->bank_name) }}" class="{{ $editIn }}" />@else<span class="text-brand-text">{{ $line($e->bank_name) }}</span>@endif</dd></div>
