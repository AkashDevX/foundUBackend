<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }}</title>
    <style>
        @page { margin: 26px 28px 42px 28px; }
        * { box-sizing: border-box; }
        html, body {
            background: #ffffff;
            color: #1c2834;
            color-scheme: light;
        }
        body {
            margin: 0;
            color: #1c2834;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.4;
        }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -26px;
            height: 18px;
            border-top: 1px solid #d7e0ea;
            color: #6b7785;
            font-size: 8px;
            padding-top: 5px;
            padding-right: 78px;
        }
        table { border-collapse: collapse; }
        .kicker {
            color: #5f7fa3;
            font-size: 8px;
            letter-spacing: 1.4px;
        }
        .stat {
            background: #f5f8fb;
            border: 1px solid #e4ebf2;
            padding: 8px 10px;
            vertical-align: top;
        }
        .stat-label {
            color: #003366;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.7px;
        }
        .stat-value { margin-top: 2px; font-size: 16px; font-weight: bold; }
        th {
            background: #003d7a;
            color: #ffffff;
            font-size: 8px;
            letter-spacing: 0.5px;
            text-align: left;
            padding: 7px 8px;
        }
        td.cell {
            padding: 7px 8px;
            border-bottom: 1px solid #e8eef4;
            vertical-align: top;
        }
        .pill {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.4px;
            padding: 2px 6px;
        }
    </style>
</head>
<body>
    <div class="footer">
        Confidential · {{ $company }} · Incident register · {{ $filter_label }} · Generated {{ $generated }}
    </div>

    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td style="padding: 2px 8px 10px 0; vertical-align: middle;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="CruLynk" width="120" style="width: 120px; height: auto;">
                @else
                    <div style="font-size: 16px; font-weight: bold; color: #003d7a;">CruLynk</div>
                @endif
            </td>
            <td width="140" align="right" style="padding: 2px 0 10px 8px; vertical-align: middle;">
                <div class="kicker">REPORTS</div>
                <div style="margin-top: 2px; color: #002855; font-size: 18px; font-weight: bold;">{{ $total }}</div>
            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="0" cellpadding="0" style="background: #002855; color: #ffffff;">
        <tr>
            <td style="padding: 12px 16px;">
                <div style="color: #9ec5ef; font-size: 8px; letter-spacing: 1.4px;">ADMINISTRATION</div>
                <div style="margin-top: 2px; font-size: 18px; font-weight: bold;">Incident register</div>
                <div style="margin-top: 2px; color: #d5e4f4; font-size: 10px;">{{ $company }} · {{ $filter_label }}</div>
            </td>
        </tr>
    </table>
    <div style="height: 5px; background: #7ac143;"></div>

    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 12px;">
        <tr>
            <td class="stat" width="25%">
                <div class="stat-label">IN THIS EXPORT</div>
                <div class="stat-value">{{ $total }}</div>
            </td>
            <td width="8"></td>
            <td class="stat" width="25%">
                <div class="stat-label" style="color: #991b1b;">NEW</div>
                <div class="stat-value">{{ $counts['new'] }}</div>
            </td>
            <td width="8"></td>
            <td class="stat" width="25%">
                <div class="stat-label" style="color: #92400e;">ACKNOWLEDGED</div>
                <div class="stat-value">{{ $counts['acknowledged'] }}</div>
            </td>
            <td width="8"></td>
            <td class="stat" width="25%">
                <div class="stat-label" style="color: #065f46;">RESOLVED</div>
                <div class="stat-value">{{ $counts['resolved'] }}</div>
            </td>
        </tr>
    </table>

    @if ($rows === [])
        <div style="margin-top: 28px; border: 1px solid #e4ebf2; background: #f8fafc; padding: 28px; text-align: center; color: #5c6b7a;">
            No incident reports in this view.
        </div>
    @else
        <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 14px;">
            <thead>
                <tr>
                    <th width="12%">Reference</th>
                    <th width="16%">Submitted</th>
                    <th width="18%">Employee</th>
                    <th width="20%">Type</th>
                    <th width="18%">Site</th>
                    <th width="16%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="cell" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }}; font-weight: bold; color: #003d7a;">{{ $row['reference'] }}</td>
                        <td class="cell" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">
                            {{ $row['submitted'] }}
                            <div style="color: #6b7785; font-size: 8px;">Occurred {{ $row['occurred'] }}</div>
                        </td>
                        <td class="cell" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ $row['reporter'] }}</td>
                        <td class="cell" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ $row['type'] }}</td>
                        <td class="cell" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ $row['site'] }}</td>
                        <td class="cell" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">
                            <span class="pill" style="background: {{ $row['tone']['bg'] }}; color: {{ $row['tone']['fg'] }};">{{ strtoupper($row['status_label']) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
