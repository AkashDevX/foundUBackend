<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pay run {{ $period_label }}</title>
    <style>
        @page { margin: 26px 30px 46px 30px; }
        * { box-sizing: border-box; }
        html, body { background: #ffffff; color: #1c2834; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.4;
        }
        table { border-collapse: collapse; }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -30px;
            height: 22px;
            border-top: 1px solid #d7e0ea;
            color: #6b7785;
            font-size: 8px;
            padding-top: 6px;
            padding-right: 78px;
        }
        .kicker {
            color: #5f7fa3;
            font-size: 8px;
            letter-spacing: 1.4px;
        }
        .pill {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.6px;
            padding: 4px 9px;
        }
        .fact {
            background: #f5f8fb;
            border: 1px solid #e4ebf2;
            border-top: 3px solid #003d7a;
            padding: 9px 12px;
            vertical-align: top;
        }
        .fact-label {
            color: #003366;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }
        .fact-value {
            margin-top: 3px;
            font-size: 15px;
            font-weight: bold;
            color: #002855;
        }
        .fact-sub { margin-top: 2px; color: #5c6b7a; font-size: 8px; }
        .section-title {
            background: #003d7a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.7px;
            padding: 7px 12px;
        }
        .card { margin-top: 10px; border: 1px solid #d5e1ee; }
        .mini-label {
            color: #5f7fa3;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.7px;
        }
        .mini-value { margin-top: 2px; font-size: 10px; font-weight: bold; color: #1c2834; }
        .line-head td {
            background: #eef4fa;
            color: #003366;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 5px 10px;
            border-bottom: 1px solid #d5e0ea;
        }
        .line td {
            padding: 6px 10px;
            border-bottom: 1px solid #eef2f6;
            font-size: 9px;
            vertical-align: middle;
        }
        .num { text-align: right; }
        .name { font-weight: bold; }
        .muted { color: #5c6b7a; font-size: 8px; }
        .reason { color: #8a5a00; }
    </style>
</head>
<body>
    @php
        $tone = match ($status) {
            'Finalized' => ['bg' => '#e7f6d8', 'fg' => '#2f6b12', 'label' => 'FINALIZED'],
            'Draft' => ['bg' => '#fff4d4', 'fg' => '#8a5a00', 'label' => 'DRAFT'],
            default => ['bg' => '#eef2f6', 'fg' => '#475569', 'label' => 'NOT STARTED'],
        };
    @endphp

    <div class="footer">
        Confidential payroll record · {{ $company }} · {{ $period_label }} · Generated {{ $generated }}
    </div>

    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="140" style="padding: 0 8px 8px 0; vertical-align: middle;">
                @if ($logo ?? null)
                    <img src="{{ $logo }}" alt="CruLynk" width="112" style="width: 112px; height: auto;">
                @else
                    <div style="font-size: 20px; font-weight: bold;"><span style="color: #003d7a;">Cru</span><span style="color: #7ac143;">Lynk</span></div>
                    <div style="margin-top: 2px; color: #5f7fa3; font-size: 7px; letter-spacing: 1px;">CONNECT · TRACK · MANAGE</div>
                @endif
            </td>
            <td align="right" style="padding: 0 0 8px 8px; vertical-align: middle;">
                <div class="kicker">PAYROLL STATEMENT</div>
                <div style="margin-top: 2px; color: #002855; font-size: 15px; font-weight: bold;">{{ $company }}</div>
                <div style="margin-top: 7px;"><span class="pill" style="background: {{ $tone['bg'] }}; color: {{ $tone['fg'] }};">{{ $tone['label'] }}</span></div>
            </td>
        </tr>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" style="background: #002855; color: #ffffff;">
        <tr>
            <td style="padding: 12px 16px;">
                <div style="color: #9ec5ef; font-size: 8px; letter-spacing: 1.4px;">FORTNIGHTLY PAY RUN</div>
                <div style="margin-top: 2px; font-size: 17px; font-weight: bold;">{{ $period_label }}</div>
            </td>
            <td width="190" align="right" style="padding: 12px 16px; color: #d5e4f4; font-size: 9px;">
                Approved timesheets only<br>
                Generated {{ $generated }}
            </td>
        </tr>
    </table>
    <div style="height: 5px; background: #7ac143;"></div>

    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 12px;">
        <tr>
            <td class="fact" width="24%">
                <div class="fact-label">EMPLOYEES INCLUDED</div>
                <div class="fact-value">{{ $included_count }}</div>
                <div class="fact-sub">Ready to pay</div>
            </td>
            <td width="10"></td>
            <td class="fact" width="24%">
                <div class="fact-label">NOT INCLUDED</div>
                <div class="fact-value">{{ $excluded_count }}</div>
                <div class="fact-sub">Held out of this run</div>
            </td>
            <td width="10"></td>
            <td class="fact" width="24%">
                <div class="fact-label">HOURS WORKED</div>
                <div class="fact-value">{{ number_format($total_hours, 2) }}</div>
                <div class="fact-sub">Approved hours</div>
            </td>
            <td width="10"></td>
            <td class="fact" width="24%" style="border-top-color: #7ac143;">
                <div class="fact-label">GROSS PAY</div>
                <div class="fact-value">{{ \App\Support\AdminPayroll::formatMoney($total_amount) }}</div>
                <div class="fact-sub">This pay period</div>
            </td>
        </tr>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 14px;">
        <tr>
            <td class="section-title">INCLUDED IN THIS PAY PERIOD</td>
        </tr>
    </table>

    @forelse ($employees as $employee)
        <table class="card" width="100%" cellspacing="0" cellpadding="0" style="page-break-inside: avoid;">
            <tr>
                <td style="background: #002855; color: #ffffff; padding: 9px 12px;">
                    <table width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td style="vertical-align: middle;">
                                <div style="font-size: 12px; font-weight: bold;">{{ $employee['name'] }}</div>
                                <div style="margin-top: 2px; color: #b9d4f0; font-size: 8px;">
                                    {{ $employee['code'] }}{{ $employee['email'] !== '' ? ' · '.$employee['email'] : '' }}
                                </div>
                            </td>
                            <td width="130" align="right" style="vertical-align: middle;">
                                <div style="color: #9ec5ef; font-size: 7px; letter-spacing: 0.8px;">GROSS PAY</div>
                                <div style="margin-top: 1px; font-size: 15px; font-weight: bold;">{{ $employee['gross_label'] }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="background: #f4f8fb; padding: 7px 12px; border-bottom: 1px solid #e4ebf2;">
                    <table width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td width="33%">
                                <div class="mini-label">WORKED</div>
                                <div class="mini-value">{{ number_format($employee['worked_hours'], 2) }} hrs</div>
                            </td>
                            <td width="33%">
                                <div class="mini-label">SCHEDULED</div>
                                <div class="mini-value">{{ number_format($employee['scheduled_hours'], 2) }} hrs</div>
                            </td>
                            <td width="34%">
                                <div class="mini-label">COMPARED TO SCHEDULE</div>
                                <div class="mini-value">{{ $employee['variance'] }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding: 0;">
                    <table width="100%" cellspacing="0" cellpadding="0">
                        <tr class="line-head">
                            <td>PAY LINE</td>
                            <td class="num" width="16%">HOURS</td>
                            <td class="num" width="20%">RATE</td>
                            <td class="num" width="18%">AMOUNT</td>
                        </tr>
                        @forelse ($employee['lines'] as $line)
                            <tr class="line">
                                <td style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }}; font-weight: bold; color: #002855;">{{ $line['label'] }}</td>
                                <td class="num" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ number_format($line['hours'], 2) }}</td>
                                <td class="num" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ $line['rate'] > 0 ? \App\Support\AdminPayroll::formatMoney($line['rate']).'/hr' : '—' }}</td>
                                <td class="num" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }}; font-weight: bold;">{{ $line['amount_label'] }}</td>
                            </tr>
                        @empty
                            <tr class="line">
                                <td colspan="4" class="muted">No pay lines for this employee.</td>
                            </tr>
                        @endforelse
                    </table>
                </td>
            </tr>
        </table>
    @empty
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td style="padding: 12px; color: #5c6b7a;">No employees have payable hours in this pay period.</td>
            </tr>
        </table>
    @endforelse

    @if ($included_count > 0)
        <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 12px;">
            <tr>
                <td style="background: #003d7a; color: #ffffff; padding: 10px 14px; font-size: 10px; font-weight: bold;">
                    Pay period total
                    <div style="margin-top: 2px; color: #c5d7ee; font-size: 8px; font-weight: normal;">
                        {{ $included_count }} employee{{ $included_count === 1 ? '' : 's' }} · {{ number_format($total_hours, 2) }} hours
                    </div>
                </td>
                <td width="160" align="right" style="background: #003d7a; color: #ffffff; padding: 10px 14px; font-size: 16px; font-weight: bold;">
                    {{ \App\Support\AdminPayroll::formatMoney($total_amount) }}
                </td>
            </tr>
        </table>
        <div style="height: 4px; background: #7ac143;"></div>
    @endif

    @if ($excluded_count > 0)
        <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 16px;">
            <tr>
                <td class="section-title">NOT INCLUDED</td>
            </tr>
        </table>
        <table width="100%" cellspacing="0" cellpadding="0" style="border: 1px solid #ead9b0; border-top: none;">
            <tr class="line-head">
                <td style="background: #fff8ea; color: #8a5a00;">EMPLOYEE</td>
                <td class="num" width="16%" style="background: #fff8ea; color: #8a5a00;">SCHEDULED</td>
                <td width="48%" style="background: #fff8ea; color: #8a5a00;">REASON</td>
            </tr>
            @foreach ($excluded as $employee)
                <tr class="line">
                    <td style="background: {{ $loop->even ? '#fffdf8' : '#ffffff' }};">
                        <div class="name">{{ $employee['name'] }}</div>
                        <div class="muted">{{ $employee['code'] }}</div>
                    </td>
                    <td class="num" style="background: {{ $loop->even ? '#fffdf8' : '#ffffff' }};">{{ number_format($employee['scheduled_hours'], 2) }} hrs</td>
                    <td class="reason" style="background: {{ $loop->even ? '#fffdf8' : '#ffffff' }};">{{ $employee['reason'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
