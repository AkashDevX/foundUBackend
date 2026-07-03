@php
    use App\Support\PayrollRateTypes;
    /** @var array<string, array<string, array<string, \App\Models\PayrollAwardRate|null>>> $groupedRates */
    /** @var string $effectiveFrom */
@endphp

<section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
    <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-5 sm:px-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-brand-text">Cleaning award rates</h2>
                <p class="mt-1 max-w-2xl text-sm text-brand-text-secondary">
                    Rates effective from {{ \App\Support\DisplayTimezone::format(\Carbon\Carbon::parse($effectiveFrom), 'M j, Y') }} (per client PDF, 1 July 2025).
                    Edit any amount below — changes apply to future pay runs.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-brand-primary/20">
                All amounts editable
            </span>
        </div>
    </header>

    <form method="post" action="{{ route('admin.payroll.rates.update') }}" class="divide-y divide-brand-border">
        @csrf
        @foreach (PayrollRateTypes::employmentTypes() as $employmentType)
            @foreach (PayrollRateTypes::awardLevels() as $awardLevel)
                <div class="px-5 py-6 sm:px-7">
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-bold text-brand-text">
                            {{ PayrollRateTypes::employmentTypeLabel($employmentType) }}
                            · {{ PayrollRateTypes::awardLevelLabel($awardLevel) }}
                        </h3>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-brand-border/80">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                                <tr>
                                    <th class="px-4 py-3">Rate type</th>
                                    <th class="px-4 py-3 text-right">$/hour</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-border/80">
                                @foreach (PayrollRateTypes::awardRateKeys() as $rateType)
                                    @php
                                        $rateModel = $groupedRates[$employmentType][$awardLevel][$rateType] ?? null;
                                        $value = old("rates.{$employmentType}.{$awardLevel}.{$rateType}", $rateModel?->amount ?? '0.00');
                                    @endphp
                                    <tr class="hover:bg-brand-surface/30">
                                        <td class="px-4 py-3 text-brand-text">{{ PayrollRateTypes::label($rateType) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <span class="text-xs text-brand-text-secondary">$</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="9999.99"
                                                    name="rates[{{ $employmentType }}][{{ $awardLevel }}][{{ $rateType }}]"
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
                </div>
            @endforeach
        @endforeach

        <div class="sticky bottom-0 border-t border-brand-border bg-white/95 px-5 py-4 backdrop-blur sm:px-7">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-6 py-3 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                Save all rates
            </button>
        </div>
    </form>
</section>
