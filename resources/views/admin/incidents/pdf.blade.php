<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }}</title>
    <style>
        @page { margin: 28px 32px 46px 32px; }
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
        .kicker {
            color: #5f7fa3;
            font-size: 8px;
            letter-spacing: 1.4px;
        }
        .gold { height: 5px; background: #7ac143; }
        .facts { width: 100%; margin-top: 14px; }
        .fact {
            background: #f5f8fb;
            border: 1px solid #e4ebf2;
            padding: 10px 12px;
            vertical-align: top;
        }
        .fact-label {
            color: #003366;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }
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
        .photo-name { margin-top: 4px; color: #64748b; font-size: 8px; }
        .missing {
            border: 1px dashed #cbd5e1;
            color: #64748b;
            padding: 18px 10px;
            text-align: center;
            font-size: 9px;
        }
        .signature-wrap {
            margin: 10px 12px 12px;
            border: 1px solid #e7d7b8;
            background: #fffdf8;
            padding: 10px 12px 12px;
        }
        .review {
            margin-top: 16px;
            border: 1px solid #e4ebf2;
            border-left: 5px solid {{ $tone['bar'] }};
        }
        .review-head {
            background: #f8fafc;
            padding: 8px 12px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.6px;
            color: #002855;
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
        Confidential workplace record · {{ $company }} · {{ $reference }} · Generated {{ $generated }}
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
                <div class="kicker">REFERENCE</div>
                <div style="margin-top: 2px; color: #002855; font-size: 16px; font-weight: bold;">{{ $reference }}</div>
                <div style="margin-top: 8px;"><span class="pill">{{ strtoupper($status_label) }}</span></div>
            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="0" cellpadding="0" style="background: #002855; color: #ffffff;">
        <tr>
            <td style="padding: 12px 16px;">
                <div style="color: #9ec5ef; font-size: 8px; letter-spacing: 1.4px;">WORKPLACE RECORD</div>
                <div style="margin-top: 2px; font-size: 18px; font-weight: bold;">Incident report</div>
                <div style="margin-top: 2px; color: #d5e4f4; font-size: 10px;">{{ $company }}</div>
            </td>
        </tr>
    </table>
    <div class="gold"></div>

    <table class="facts" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td class="fact" width="34%">
                <div class="fact-label">REPORTED BY</div>
                <div class="fact-value">{{ $reporter }}</div>
                <div class="fact-sub">Submitted {{ $submitted }}</div>
            </td>
            <td width="8"></td>
            <td class="fact" width="33%">
                <div class="fact-label">SITE</div>
                <div class="fact-value">{{ $site }}</div>
                <div class="fact-sub">{{ $location }}</div>
            </td>
            <td width="8"></td>
            <td class="fact" width="33%">
                <div class="fact-label">WHEN IT HAPPENED</div>
                <div class="fact-value">{{ $occurred }}</div>
                <div class="fact-sub">{{ $type }}</div>
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
            @if ($section['rows'] !== [])
                <table width="100%" cellspacing="0" cellpadding="0">
                    @foreach ($section['rows'] as $row)
                        <tr>
                            <td class="row-label" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{{ $row['label'] }}</td>
                            <td class="row-value" style="background: {{ $loop->even ? '#f8fafc' : '#ffffff' }};">{!! nl2br(e($row['value'])) !!}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if ($section['photos'] !== [])
                <div style="padding: 8px 8px 4px;">
                    <div style="padding: 0 4px 6px; color: #003366; font-size: 9px; font-weight: bold;">
                        {{ $section['title'] === 'PROPERTY LOSS OR DAMAGE' ? 'Property photos' : 'Supporting photos' }}
                    </div>
                    @foreach (array_chunk($section['photos'], 2) as $pair)
                        <table width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                @foreach ($pair as $photo)
                                    <td width="50%" style="padding: 4px; vertical-align: top;">
                                        @if ($photo['src'])
                                            <img src="{{ $photo['src'] }}" alt="" width="248" style="width: 248px; height: auto; border: 1px solid #e2e8f0;">
                                        @else
                                            <div class="missing">Photo could not be embedded</div>
                                        @endif
                                        <div class="photo-name">{{ $photo['name'] }}</div>
                                    </td>
                                @endforeach
                                @if (count($pair) === 1)
                                    <td width="50%"></td>
                                @endif
                            </tr>
                        </table>
                    @endforeach
                </div>
            @endif

            @if ($section['signature'])
                <div class="signature-wrap">
                    <div class="fact-label">EMPLOYEE SIGNATURE</div>
                    <div style="margin-top: 8px;">
                        <img src="{{ $section['signature'] }}" alt="Employee signature" width="320" style="width: 320px; height: auto;">
                    </div>
                    <div class="fact-sub">Captured in the CruLynk app at the time of submission.</div>
                </div>
            @endif
        </div>
    @endforeach

    <div class="review">
        <div class="review-head">ADMINISTRATION REVIEW</div>
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="row-label" style="background: #ffffff;">Status</td>
                <td class="row-value" style="background: #ffffff;"><span class="pill">{{ strtoupper($status_label) }}</span></td>
            </tr>
            <tr>
                <td class="row-label" style="background: #f8fafc;">Reviewed by</td>
                <td class="row-value" style="background: #f8fafc;">
                    @if ($review['reviewed_by'] !== '')
                        {{ $review['reviewed_by'] }}@if ($review['reviewed_at'] !== '') · {{ $review['reviewed_at'] }}@endif
                    @else
                        Not yet reviewed
                    @endif
                </td>
            </tr>
            <tr>
                <td class="row-label" style="background: #ffffff;">Admin note</td>
                <td class="row-value" style="background: #ffffff;">
                    @if ($review['note'] !== '')
                        {!! nl2br(e($review['note'])) !!}
                    @else
                        No note recorded.
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
