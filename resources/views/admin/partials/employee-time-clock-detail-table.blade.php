@php
    /** @var array<string, mixed> $group */
    /** @var array<string, string|null> $redirectQuery */
    /** @var string $th */
    /** @var string $td */
    /** @var string $tdText */
    /** @var bool $embedded */

    $summary = $group['summary'];
    $embedded = $embedded ?? false;
    $wrapperClass = $embedded
        ? 'overflow-x-auto'
        : 'overflow-x-auto rounded-xl border border-brand-border bg-white shadow-sm ring-1 ring-black/[0.02]';
@endphp

<div class="{{ $wrapperClass }}" data-timesheet-table-scroll>
    <table class="min-w-[1820px] w-full border-collapse text-left text-sm">
        <thead>
            <tr class="border-b border-brand-border bg-brand-surface/80">
                <th class="{{ $th }} min-w-[130px]">Employment type</th>
                <th class="{{ $th }} min-w-[180px]">Locations</th>
                <th class="{{ $th }} text-center">Sch. shift start</th>
                <th class="{{ $th }} text-center">Sch. shift end</th>
                <th class="{{ $th }} text-center">Clock in time</th>
                <th class="{{ $th }} text-center">Clock in distance (m)</th>
                <th class="{{ $th }} text-center">Break start</th>
                <th class="{{ $th }} text-center">Break end</th>
                <th class="{{ $th }} text-center">Break duration</th>
                <th class="{{ $th }} text-center">Clock out time</th>
                <th class="{{ $th }} text-center">Sch. shift duration</th>
                <th class="{{ $th }} text-center">Shift duration</th>
                <th class="{{ $th }} text-center">Difference</th>
                <th class="{{ $th }} text-center">Date</th>
                <th class="{{ $th }} text-center">Status</th>
                <th class="{{ $th }} text-center">Auto clock-out</th>
                <th class="{{ $th }} text-center">Break type</th>
                <th class="{{ $th }} min-w-[150px] text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-brand-border/80">
            <tr class="bg-slate-100/80 font-semibold">
                <td class="{{ $tdText }} bg-slate-100/80" colspan="8">Week totals</td>
                <td class="{{ $td }} bg-slate-100/80 text-center">{{ $summary['break_duration_hours'] }}</td>
                <td class="{{ $td }} bg-slate-100/80 text-center">—</td>
                <td class="{{ $td }} bg-slate-100/80 text-center">{{ $summary['scheduled_duration_hours'] }}</td>
                <td class="{{ $td }} bg-slate-100/80 text-center">{{ $summary['worked_duration_hours'] }}</td>
                <td class="{{ $td }} bg-slate-100/80 text-center {{ $summary['difference_is_alert'] ? 'text-red-600' : '' }}">{{ $summary['difference_hours'] }}</td>
                <td class="{{ $td }} bg-slate-100/80 text-center" colspan="5">
                    @if (($group['pending_days'] ?? 0) > 0)
                        {{ $group['pending_days'] }} day{{ ($group['pending_days'] ?? 0) === 1 ? '' : 's' }} pending
                    @else
                        All days signed off
                    @endif
                </td>
            </tr>
            @foreach ($group['rows'] as $row)
                @php
                    $palette = $row['employment_type_palette'];
                @endphp
                <tr
                    class="cursor-pointer bg-white transition hover:bg-brand-surface/30"
                    data-timesheet-row
                    data-row='@json($row['modal'] ?? [])'
                >
                    <td class="{{ $tdText }}">
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $palette['bg'] }} {{ $palette['border'] }} {{ $palette['text'] }}">
                            {{ $row['employment_type'] }}
                        </span>
                    </td>
                    <td class="{{ $tdText }} max-w-[220px] truncate">{{ $row['location'] }}</td>
                    <td class="{{ $td }} text-center">{{ $row['scheduled_start'] }}</td>
                    <td class="{{ $td }} text-center">{{ $row['scheduled_end'] }}</td>
                    <td class="{{ $td }} text-center font-medium" data-timesheet-row-ignore>
                        <div class="inline-flex items-center justify-center gap-1.5">
                            <span>{{ $row['clock_in'] }}</span>
                            @include('admin.partials.time-clock-punch-map-button', ['map' => $row['clock_in_map'] ?? null])
                        </div>
                    </td>
                    <td class="{{ $td }} text-center font-medium">{{ $row['clock_in_distance_meters'] }}</td>
                    <td class="{{ $td }} text-center align-middle">
                        @php $breakItems = $row['break_items'] ?? []; @endphp
                        @if ($breakItems === [])
                            <span class="text-brand-text-secondary">—</span>
                        @else
                            <div class="inline-flex flex-col items-center gap-0.5 leading-tight">
                                @foreach ($breakItems as $item)
                                    <span class="text-[11px] font-medium tabular-nums text-brand-text">{{ $item['start'] }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="{{ $td }} text-center align-middle">
                        @if (($row['break_items'] ?? []) === [])
                            <span class="text-brand-text-secondary">—</span>
                        @else
                            <div class="inline-flex flex-col items-center gap-0.5 leading-tight">
                                @foreach ($row['break_items'] as $item)
                                    <span class="text-[11px] font-medium tabular-nums {{ ($item['is_open'] ?? false) ? 'text-emerald-700' : 'text-brand-text' }}">{{ $item['end'] }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="{{ $td }} text-center align-middle">
                        @if (($row['break_items'] ?? []) === [])
                            <span class="text-brand-text-secondary">—</span>
                        @else
                            <div class="inline-flex flex-col items-center gap-0.5 leading-tight">
                                @foreach ($row['break_items'] as $item)
                                    <span class="text-[11px] font-medium tabular-nums text-brand-text">{{ $item['duration_hours'] }}</span>
                                @endforeach
                                @if (count($row['break_items']) > 1)
                                    <span class="mt-0.5 border-t border-brand-border/70 pt-0.5 text-[10px] font-bold tabular-nums text-slate-600">Σ {{ $row['break_duration_hours'] }}</span>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td class="{{ $td }} text-center font-medium {{ ($row['is_open'] ?? false) ? 'text-emerald-700' : '' }}" data-timesheet-row-ignore>
                        <div class="inline-flex items-center justify-center gap-1.5">
                            <span>{{ $row['clock_out'] }}</span>
                            @include('admin.partials.time-clock-punch-map-button', ['map' => $row['clock_out_map'] ?? null])
                        </div>
                    </td>
                    <td class="{{ $td }} text-center">{{ $row['scheduled_duration_hours'] }}</td>
                    <td class="{{ $td }} text-center font-semibold" data-timesheet-row-ignore>
                        @php $durationBreakdown = $row['worked_duration_breakdown'] ?? null; @endphp
                        @if (is_array($durationBreakdown) && ($durationBreakdown['lines'] ?? []) !== [])
                            <button
                                type="button"
                                class="cursor-help border-b border-dotted border-brand-text-secondary/60 font-semibold tabular-nums text-brand-text"
                                data-shift-duration-tip
                                data-tip-lines='@json($durationBreakdown['lines'])'
                                aria-label="How shift duration is calculated: {{ $durationBreakdown['summary'] ?? $row['worked_duration_hours'] }}"
                            >{{ $row['worked_duration_hours'] }}</button>
                        @else
                            {{ $row['worked_duration_hours'] }}
                        @endif
                    </td>
                    <td class="{{ $td }} text-center font-semibold {{ $row['difference_is_alert'] ? 'text-red-600' : '' }}">{{ $row['difference_hours'] }}</td>
                    <td class="{{ $td }} text-center">{{ $row['date_label'] }}</td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 {{ $row['status_badge_classes'] }}">
                            {{ $row['status_label'] }}
                        </span>
                    </td>
                    <td class="{{ $td }} text-center">{{ $row['auto_clock_out'] }}</td>
                    <td class="{{ $td }} text-center text-brand-text-secondary">{{ $row['break_type'] }}</td>
                    <td class="px-3 py-2.5 text-center" data-timesheet-row-ignore>
                        @if (($row['can_review'] ?? false) || ($row['can_reset'] ?? false))
                            <details class="relative inline-block text-left" data-timesheet-row-menu data-timesheet-row-ignore>
                                <summary
                                    class="inline-flex size-8 cursor-pointer list-none items-center justify-center rounded-lg border border-brand-border bg-white text-brand-text-secondary shadow-sm transition hover:bg-brand-surface hover:text-brand-text [&::-webkit-details-marker]:hidden"
                                    data-timesheet-row-menu-open
                                    aria-label="Row actions"
                                >
                                    <svg class="size-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                                    </svg>
                                </summary>
                                <div
                                    class="absolute right-0 z-40 mt-1 min-w-[11rem] overflow-hidden rounded-xl border border-brand-border bg-white py-1 shadow-lg ring-1 ring-black/[0.04]"
                                    data-timesheet-row-menu-panel
                                >
                                    @if ($row['can_review'] ?? false)
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm font-medium text-brand-text transition hover:bg-brand-surface" data-timesheet-row-action="edit">
                                            Edit
                                        </button>
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm font-medium text-brand-text transition hover:bg-brand-surface" data-timesheet-row-action="approve">
                                            Approve
                                        </button>
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm font-medium text-red-700 transition hover:bg-red-50" data-timesheet-row-action="reject">
                                            Reject
                                        </button>
                                    @elseif ($row['can_reset'] ?? false)
                                        <form
                                            method="post"
                                            action="{{ route('admin.employees.time-clock.timesheets.reset') }}"
                                            data-confirm="This day's timesheet will return to pending approval."
                                            data-confirm-title="Mark as pending?"
                                            data-confirm-confirm="Mark as pending"
                                            data-confirm-cancel="Cancel"
                                            data-confirm-icon="question"
                                        >
                                            @csrf
                                            <input type="hidden" name="employee" value="{{ $group['employee_public_id'] }}" />
                                            <input type="hidden" name="work_date" value="{{ $row['work_date'] }}" />
                                            <input type="hidden" name="clock_in_entry_id" value="{{ $row['modal']['clock_in_entry_id'] ?? '' }}" />
                                            @foreach ($redirectQuery as $key => $value)
                                                @if ($value !== null && $value !== '')
                                                    @if ($key === 'employee')
                                                        <input type="hidden" name="list_employee" value="{{ $value }}" />
                                                    @else
                                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                                                    @endif
                                                @endif
                                            @endforeach
                                            <button type="submit" class="block w-full px-3 py-2 text-left text-sm font-medium text-brand-text transition hover:bg-brand-surface">
                                                Mark as pending
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @else
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-brand-text-secondary">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
