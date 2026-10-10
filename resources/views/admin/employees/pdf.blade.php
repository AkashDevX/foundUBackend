<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }}</title>
    <style>
        @page { margin: 28px 32px 46px 32px; }
        * { box-sizing: border-box; }
        html, body { background: #ffffff; color: #1c2834; color-scheme: light; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
        }
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
        table { border-collapse: collapse; }
        .kicker { color: #5f7fa3; font-size: 8px; letter-spacing: 1.4px; }
        .fact {
            background: #f5f8fb;
            border: 1px solid #e4ebf2;
            padding: 10px 12px;
            vertical-align: top;
        }
        .fact-label { color: #003366; font-size: 8px; font-weight: bold; letter-spacing: 0.8px; }
        .fact-value { margin-top: 3px; font-size: 12px; font-weight: bold; }
        .fact-sub { margin-top: 2px; color: #5c6b7a; font-size: 9px; }
        .section { margin-top: 14px; }
        .section-title {
            background: #003d7a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.6px;
            padding: 7px 12px;
        }
        .row-label {
            width: 34%;
            padding: 7px 12px;
            color: #003366;
            font-weight: bold;
            vertical-align: top;
            border-bottom: 1px solid #e8eef4;
        }
        .row-value {
            padding: 7px 12px;
            vertical-align: top;
            border-bottom: 1px solid #e8eef4;
        }
        .pill {
            display: inline-block;
            background: {{ $tone['bg'] }};
            color: {{ $tone['fg'] }};
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.6px;
            padding: 3px 8px;
        }
    </style>
</head>
<body>
    <div class="footer">
        Confidential employee record · {{ $company }} · {{ $name }} · Generated {{ $generated }}
    </div>

    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td style="padding: 4px 8px 10px 0; vertical-align: middle;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="CruLynk" width="132" style="width: 132px; height: auto;">
                @else
                    <div style="font-size: 18px; font-weight: bold; color: #003d7a;">CruLynk</div>
                @endif
            </td>
            <td width="170" align="right" style="padding: 4px 0 10px 8px; vertical-align: middle;">
                <div class="kicker">EMPLOYEE FILE</div>
                <div style="margin-top: 8px;"><span class="pill">{{ strtoupper($status_label) }}</span></div>
            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="0" cellpadding="0" style="background: #002855; color: #ffffff;">
        <tr>
            <td style="padding: 12px 16px;">
                <div style="color: #9ec5ef; font-size: 8px; letter-spacing: 1.4px;">WORKFORCE RECORD</div>
                <div style="margin-top: 2px; font-size: 18px; font-weight: bold;">Employee profile</div>
                <div style="margin-top: 2px; color: #d5e4f4; font-size: 10px;">{{ $company }}</div>
            </td>
        </tr>
    </table>
    <div style="height: 5px; background: #7ac143;"></div>

    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 14px;">
        <tr>
            @if ($photo)
                <td width="92" style="vertical-align: top; padding-right: 12px;">
                    <img src="{{ $photo }}" alt="" width="78" style="width: 78px; height: auto; border: 1px solid #e2e8f0;">
                </td>
            @endif
            <td style="vertical-align: middle;">
                <div style="font-size: 18px; font-weight: bold; color: #002855;">{{ $name }}</div>
                <div style="margin-top: 3px; color: #5c6b7a; font-size: 10px;">
                    Employee code {{ $code }}
                    @if ($public_id !== '')
                        · ID {{ $public_id }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 12px;">
        <tr>
            <td class="fact" width="34%">
                <div class="fact-label">EMAIL</div>
                <div class="fact-value">{{ $email }}</div>
            </td>
            <td width="8"></td>
            <td class="fact" width="33%">
                <div class="fact-label">PHONE</div>
                <div class="fact-value">{{ $phone }}</div>
            </td>
            <td width="8"></td>
            <td class="fact" width="33%">
                <div class="fact-label">REGISTERED</div>
                <div class="fact-value">{{ $registered }}</div>
            </td>
        </tr>
    </table>

    @foreach ($sections as $section)
        <div class="section">
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="section-title">{{ $section['title'] }}</td>
                </tr>
            </table>
            <table width="100%" cellspacing="0" cellpadding="0">
                @foreach ($section['rows'] as $row)
                    @php $bg = $loop->even ? '#f8fafc' : '#ffffff'; @endphp
                    <tr>
                        <td class="row-label" style="background: {{ $bg }};">{{ $row['label'] }}</td>
                        <td class="row-value" style="background: {{ $bg }};">{!! nl2br(e($row['value'])) !!}</td>
                    </tr>
                @endforeach
            </table>
            @foreach ($section['images'] ?? [] as $image)
                <table width="100%" cellspacing="0" cellpadding="0" style="page-break-inside: avoid;">
                    <tr>
                        <td style="padding: 10px 12px 2px; color: #003366; font-size: 9px; font-weight: bold;">{{ $image['label'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 12px 12px;">
                            <img src="{{ $image['src'] }}" alt="" width="{{ $image['width'] }}" height="{{ $image['height'] }}" style="width: {{ $image['width'] }}px; height: {{ $image['height'] }}px; border: 1px solid #e2e8f0;">
                        </td>
                    </tr>
                </table>
            @endforeach
        </div>
    @endforeach
</body>
</html>
