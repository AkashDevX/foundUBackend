@php
    /** @var \App\Models\JobTitle $jobTitle */
    /** @var array<string, array<string, array<string, \App\Models\PayrollAwardRate|null>>> $groupedRates */
    /** @var string $moneyIn */
    $employmentType = $jobTitle->employment_type;
    $awardLevel = $jobTitle->award_level;
@endphp

@if ($jobTitle->hasAwardBand())
    <p class="mb-4 text-xs text-brand-text-secondary">
        {{ \App\Support\PayrollRateTypes::employmentTypeLabel($employmentType) }}
        · {{ str_replace(' Cleaner', '', \App\Support\PayrollRateTypes::awardLevelLabel($awardLevel)) }}
        @isset($effectiveFrom)
            · effective from {{ \App\Support\DisplayTimezone::format(\Carbon\Carbon::parse($effectiveFrom), 'M j, Y') }}
        @endisset
    </p>
    <form method="post" action="{{ route('admin.workforce.job-titles.rates.update', ['jobTitle' => $jobTitle->id]) }}" class="space-y-4">
        @csrf
        <div class="overflow-x-auto rounded-xl border border-brand-border/80">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                    <tr>
                        <th class="px-4 py-3">Rate type</th>
                        <th class="px-4 py-3 text-right">$/hour</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border/80">
                    @foreach (\App\Support\PayrollRateTypes::awardRateKeys() as $rateType)
                        @php
                            $rateModel = $groupedRates[$employmentType][$awardLevel][$rateType] ?? null;
                            $value = old("rates.{$rateType}", $rateModel?->amount ?? '0.00');
                        @endphp
                        <tr class="hover:bg-brand-surface/30">
                            <td class="px-4 py-2.5 text-brand-text">{{ \App\Support\PayrollRateTypes::label($rateType) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <span class="text-xs text-brand-text-secondary">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="9999.99"
                                        name="rates[{{ $rateType }}]"
                                        value="{{ $value }}"
                                        class="{{ $moneyIn }}"
                                        required
                                    />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-brand-primary px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                Save rates
            </button>
        </div>
    </form>
@endif
