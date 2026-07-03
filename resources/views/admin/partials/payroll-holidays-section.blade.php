@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\PublicHoliday> $holidays */
@endphp

<div class="grid gap-8 lg:grid-cols-[minmax(0,22rem)_1fr]">
    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
        <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-5">
            <h2 class="text-base font-bold text-brand-text">Add public holiday</h2>
            <p class="mt-1 text-sm text-brand-text-secondary">Dates in this list are paid at public holiday rates when employees work.</p>
        </header>
        <form method="post" action="{{ route('admin.payroll.holidays.store') }}" class="space-y-4 px-5 py-6">
            @csrf
            <div class="space-y-1.5">
                <label for="holiday-date" class="{{ $lbl }}">Date</label>
                <input id="holiday-date" type="date" name="holiday_date" required class="{{ $in }}" value="{{ old('holiday_date') }}" />
            </div>
            <div class="space-y-1.5">
                <label for="holiday-name" class="{{ $lbl }}">Name</label>
                <input id="holiday-name" type="text" name="name" required maxlength="160" class="{{ $in }}" placeholder="e.g. Australia Day" value="{{ old('name') }}" />
            </div>
            <div class="space-y-1.5">
                <label for="holiday-region" class="{{ $lbl }}">Region (optional)</label>
                <input id="holiday-region" type="text" name="region" maxlength="32" class="{{ $in }}" placeholder="e.g. QLD" value="{{ old('region') }}" />
            </div>
            <button type="submit" class="w-full rounded-xl bg-brand-primary px-4 py-3 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                Save holiday
            </button>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]">
        <header class="border-b border-brand-border bg-gradient-to-br from-brand-surface via-white to-white px-5 py-5 sm:px-6">
            <h2 class="text-base font-bold text-brand-text">Configured holidays</h2>
        </header>
        @if ($holidays->isEmpty())
            <div class="px-6 py-14 text-center text-sm text-brand-text-secondary">No public holidays configured yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-brand-border bg-brand-surface/80 text-xs font-semibold uppercase tracking-wide text-brand-label">
                        <tr>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Region</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border/80">
                        @foreach ($holidays as $holiday)
                            <tr class="hover:bg-brand-surface/30">
                                <td class="px-5 py-3 font-medium">{{ \App\Support\DisplayTimezone::format($holiday->holiday_date, 'M j, Y') }}</td>
                                <td class="px-5 py-3">{{ $holiday->name }}</td>
                                <td class="px-5 py-3 text-brand-text-secondary">{{ $holiday->region ?: '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="post" action="{{ route('admin.payroll.holidays.destroy', ['holiday' => $holiday->id]) }}" data-confirm="Remove this public holiday?" data-confirm-title="Remove holiday?" data-confirm-confirm="Remove" data-confirm-cancel="Keep">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
