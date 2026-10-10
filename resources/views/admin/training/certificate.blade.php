<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate {{ $certificate->reference_number }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #e7eef6;
            color: #1e293b;
            font-family: Georgia, "Times New Roman", serif;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            max-width: 920px;
            margin: 0 auto;
            padding: 20px 16px 0;
            font-family: "Segoe UI", sans-serif;
        }
        .toolbar a, .toolbar button {
            border: 0;
            background: #0f3d66;
            color: #fff;
            border-radius: 10px;
            padding: 10px 16px;
            font: 600 14px/1 "Segoe UI", sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        .sheet {
            max-width: 920px;
            margin: 20px auto 40px;
            padding: 14px;
            background: #003d7a;
            border-radius: 6px;
            box-shadow: 0 16px 40px rgba(0, 40, 85, 0.18);
        }
        .inner {
            border: 1px solid #c4a35a;
            background: #fbf7ef;
            padding: 48px 56px 40px;
            text-align: center;
        }
        .logo {
            display: block;
            width: 190px;
            height: auto;
            margin: 0 auto 12px;
        }
        .company {
            margin: 0;
            font-family: "Segoe UI", sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #8a6a2f;
        }
        h1 {
            margin: 14px 0 0;
            font-size: 34px;
            font-weight: 500;
            color: #003d7a;
        }
        .rule {
            width: 92px;
            height: 2px;
            margin: 16px auto 0;
            background: #c4a35a;
        }
        .lead {
            margin: 22px 0 0;
            font-size: 16px;
            color: #5c5346;
        }
        .name {
            margin: 8px 0 0;
            font-size: 42px;
            color: #1c1915;
        }
        .training {
            margin: 8px 0 0;
            font-size: 24px;
            color: #003d7a;
        }
        .dates {
            display: flex;
            justify-content: center;
            gap: 56px;
            margin-top: 36px;
            font-family: "Segoe UI", sans-serif;
        }
        .dates p { margin: 6px 0 0; font-size: 16px; color: #1c1915; }
        .dates span, .ref span, .signature span {
            display: block;
            font-family: "Segoe UI", sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #8a6a2f;
        }
        .rule-wide {
            width: 70%;
            height: 1px;
            margin: 36px auto 0;
            background: #c4a35a;
        }
        .ref {
            margin: 18px 0 0;
            font-family: "Segoe UI", sans-serif;
        }
        .ref strong {
            display: block;
            margin-top: 6px;
            font-size: 16px;
            letter-spacing: 0.14em;
            color: #003d7a;
        }
        .signature {
            margin: 22px auto 0;
            max-width: 360px;
        }
        .signature svg {
            display: block;
            width: 100%;
            height: 110px;
            margin-bottom: 4px;
            background: transparent;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin.training.results', $module->id) }}">Back to results</a>
        <button type="button" onclick="window.print()">Print / save PDF</button>
    </div>
    <article class="sheet">
        <div class="inner">
            <img class="logo" src="{{ asset('images/crulynk-logo-mark.png') }}" alt="CruLynk">
            <p class="company">{{ $certificate->company_name }}</p>
            <h1>Certificate of completion</h1>
            <div class="rule"></div>
            <p class="lead">This is to certify that</p>
            <p class="name">{{ $certificate->employee_name }}</p>
            <p class="lead">has successfully completed</p>
            <p class="training">{{ $certificate->training_name }}</p>
            <div class="dates">
                <div>
                    <span>Completion date</span>
                    <p>{{ $certificate->completed_on?->format('j F Y') }}</p>
                </div>
                @if ($certificate->expires_on)
                    <div>
                        <span>Valid until</span>
                        <p>{{ $certificate->expires_on->format('j F Y') }}</p>
                    </div>
                @endif
            </div>
            <div class="rule-wide"></div>
            <p class="ref"><span>Certificate number</span><strong>{{ $certificate->reference_number }}</strong></p>
            @php $signature = $certificate->signatureDrawing(); @endphp
            @if ($signature)
                <div class="signature">
                    <svg viewBox="0 0 {{ $signature['width'] }} {{ $signature['height'] }}" role="img" aria-label="Employee signature">
                        @foreach ($signature['strokes'] as $stroke)
                            @php
                                $points = [];
                                foreach ($stroke as $point) {
                                    if (! is_array($point)) {
                                        continue;
                                    }
                                    $points[] = ((float) ($point['x'] ?? 0)).','.((float) ($point['y'] ?? 0));
                                }
                            @endphp
                            @if ($points !== [])
                                <polyline fill="none" stroke="#111827" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" points="{{ implode(' ', $points) }}" />
                            @endif
                        @endforeach
                    </svg>
                    <span>Employee signature</span>
                </div>
            @endif
        </div>
    </article>
</body>
</html>
