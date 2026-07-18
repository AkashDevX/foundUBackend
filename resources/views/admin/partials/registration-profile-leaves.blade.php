@php
    /** @var \App\Models\Employee $e */
    /** @var \App\Models\Company $company */
    /** @var bool $canEditProfile */
    $leaveTypes = collect($leaveTypes ?? collect());
    $entitlements = ($e->leaveEntitlements ?? collect())->sortBy(fn ($ent) => optional($ent->leaveType)->sort_order ?? 999)->values();
    $assignedTypeIds = $entitlements->pluck('leave_type_id')->all();
    $availableLeaveTypes = $leaveTypes->whereNotIn('id', $assignedTypeIds)->values();
    $fmtHours = static fn ($h) => $h === null ? null : rtrim(rtrim(number_format((float) $h, 2), '0'), '.');

    $usedByCode = ($e->leaveRecords ?? collect())
        ->filter(fn ($r) => $r->status !== \App\Models\EmployeeLeaveRecord::STATUS_CANCELLED)
        ->groupBy('leave_type')
        ->map(fn ($rows) => (float) $rows->sum('hours'));

    $leaveAllocatedFor = static function ($ent) {
        $h = $ent->entitlement_hours ?? optional($ent->leaveType)->default_annual_hours;

        return $h !== null ? (float) $h : null;
    };
    $leaveUsedFor = static function ($ent) use ($usedByCode) {
        $code = optional($ent->leaveType)->code;

        return $code !== null ? (float) ($usedByCode[$code] ?? 0.0) : 0.0;
    };

    $totalAllocatedHours = $entitlements->sum(fn ($ent) => $leaveAllocatedFor($ent) ?? 0.0);
    $totalUsedHours = $entitlements->sum(fn ($ent) => $leaveUsedFor($ent));
    $totalRemainingHours = $entitlements->sum(function ($ent) use ($leaveAllocatedFor, $leaveUsedFor) {
        $alloc = $leaveAllocatedFor($ent);

        return $alloc !== null ? $alloc - $leaveUsedFor($ent) : 0.0;
    });
@endphp

<section class="{{ $card }}">
    <div class="{{ $cardHead }}">
        <h3 class="text-lg font-bold text-brand-text">Leaves</h3>
    </div>
    <div class="divide-y divide-brand-border px-6 sm:px-8">
        {{-- Leave entitlements --}}
        <div class="{{ $dlStart ?? $dl }}">
            <dt class="font-medium text-brand-label pt-1">Leave entitlements</dt>
            <dd class="min-w-0">
                @if ($entitlements->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($entitlements as $ent)
                            @php
                                $lt = $ent->leaveType;
                                $ltName = $lt?->name ?? 'Leave type #'.$ent->leave_type_id;
                                $ltUnpaid = $lt ? ! $lt->is_paid : false;
                            @endphp
                            <li class="inline-flex items-center gap-2 rounded-full border border-brand-border bg-white py-1.5 pl-3 {{ $canEditProfile ? 'pr-1.5' : 'pr-3.5' }} shadow-sm">
                                <span class="size-2 shrink-0 rounded-full {{ $ltUnpaid ? 'bg-slate-400' : 'bg-emerald-500' }}"></span>
                                <span class="text-sm font-semibold text-brand-text">{{ $ltName }}</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide {{ $ltUnpaid ? 'text-slate-500' : 'text-emerald-600' }}">{{ $ltUnpaid ? 'Unpaid' : 'Paid' }}</span>
                                @if ($canEditProfile)
                                    <button type="submit" form="leave-entitlement-delete-{{ $ent->id }}" title="Remove {{ $ltName }}" aria-label="Remove {{ $ltName }}" class="inline-flex size-5 items-center justify-center rounded-full text-brand-text-secondary transition hover:bg-red-100 hover:text-red-700">
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-brand-text-secondary">No leave types assigned yet.</p>
                @endif

                @if ($canEditProfile)
                    @if ($availableLeaveTypes->isNotEmpty())
                        <div class="mt-4 max-w-2xl rounded-2xl border border-brand-border bg-brand-surface/40 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-label">Assign leave types</p>
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                @foreach ($availableLeaveTypes as $lt)
                                    <label class="relative cursor-pointer">
                                        <input type="checkbox" name="leave_type_ids[]" value="{{ $lt->id }}" form="employee-leave-entitlement-form" class="peer absolute inset-0 z-10 m-0 h-full w-full cursor-pointer appearance-none opacity-0" />
                                        <span class="inline-flex items-center gap-2 rounded-full border border-brand-border bg-white py-2 pl-2.5 pr-3.5 text-sm font-medium text-brand-text shadow-sm transition hover:border-brand-primary/50 peer-checked:border-brand-primary peer-checked:bg-brand-primary peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-primary/30">
                                            <span class="flex size-4 items-center justify-center rounded-full border border-current">
                                                <svg class="size-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                            </span>
                                            {{ $lt->name }}
                                            @unless ($lt->is_paid)<span class="text-[10px] font-bold uppercase tracking-wide opacity-70">Unpaid</span>@endunless
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('leave_type_ids')<p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            <div class="mt-4">
                                <button type="submit" form="employee-leave-entitlement-form" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-primary/20 transition hover:bg-brand-primary-dark">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    Assign selected
                                </button>
                            </div>
                        </div>
                    @elseif ($entitlements->isNotEmpty())
                        <p class="mt-3 text-xs text-brand-text-secondary">All available leave types are assigned. Manage the catalogue under <span class="font-semibold text-brand-text">Organization setup &rarr; Leave types</span>.</p>
                    @endif
                @endif
            </dd>
        </div>

        {{-- Leave balances --}}
        <div class="{{ $dlStart ?? $dl }}">
            <dt class="font-medium text-brand-label pt-1">Leave balances</dt>
            <dd class="min-w-0">
                @if ($entitlements->isNotEmpty())
                    <ul class="max-w-2xl space-y-2">
                        @foreach ($entitlements as $ent)
                            @php
                                $lt = $ent->leaveType;
                                $ltName = $lt?->name ?? 'Leave type #'.$ent->leave_type_id;
                                $unpaid = $lt ? ! $lt->is_paid : false;
                                $allocatedHours = $leaveAllocatedFor($ent);
                                $usedHours = $leaveUsedFor($ent);
                                $remainingHours = $allocatedHours !== null ? $allocatedHours - $usedHours : null;
                                $overdrawn = $remainingHours !== null && $remainingHours < 0;
                            @endphp
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-brand-border bg-white px-4 py-3 shadow-sm">
                                <span class="flex min-w-0 items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $unpaid ? 'bg-slate-100 text-slate-500' : 'bg-brand-primary/10 text-brand-primary' }}">
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M4.5 6h15A1.5 1.5 0 0121 7.5v12A1.5 1.5 0 0119.5 21h-15A1.5 1.5 0 013 19.5v-12A1.5 1.5 0 014.5 6z" /></svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-brand-text">{{ $ltName }}</span>
                                        <span class="text-xs text-brand-text-secondary">{{ $unpaid ? 'Unpaid leave' : 'Annual entitlement' }}</span>
                                    </span>
                                </span>
                                <span class="shrink-0 text-right">
                                    @if ($allocatedHours !== null)
                                        <span class="font-mono text-base font-bold tabular-nums {{ $overdrawn ? 'text-red-600' : 'text-brand-text' }}">{{ $fmtHours($remainingHours) }}</span>
                                        <span class="text-xs text-brand-text-secondary"> hrs left</span>
                                        <span class="mt-0.5 block text-[11px] text-brand-text-secondary">{{ $fmtHours($usedHours) }} used of {{ $fmtHours($allocatedHours) }} hrs</span>
                                    @else
                                        <span class="text-sm text-brand-text-secondary">No balance set</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <span class="text-brand-text">&mdash;</span>
                    <p class="mt-1 text-xs text-brand-text-secondary">Assign leave types above to set their balances.</p>
                @endif
            </dd>
        </div>

        {{-- Total leave balances --}}
        <div class="{{ $dl }}">
            <dt class="font-medium text-brand-label">Total leave balances</dt>
            <dd class="min-w-0">
                <div class="flex max-w-2xl items-center justify-between gap-3 rounded-xl border border-brand-primary/20 bg-brand-primary/5 px-4 py-3">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-brand-text">Total remaining</span>
                        <span class="text-xs text-brand-text-secondary">{{ $fmtHours($totalUsedHours) ?? '0' }} used of {{ $fmtHours($totalAllocatedHours) ?? '0' }} hrs entitled</span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="font-mono text-lg font-bold tabular-nums text-brand-primary">{{ $fmtHours($totalRemainingHours) ?? '0' }}</span>
                        <span class="text-xs font-semibold text-brand-primary/70"> hrs</span>
                    </span>
                </div>
            </dd>
        </div>
    </div>
</section>
