<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:0;background-color:#EEF2F7;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#EEF2F7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 12px 40px rgba(0,61,122,0.10);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#003D7A 0%,#0052A2 100%);padding:36px 32px 28px 32px;">
                            <p style="margin:0 0 8px 0;font-size:13px;letter-spacing:1.2px;text-transform:uppercase;color:rgba(255,255,255,0.75);font-weight:600;">
                                {{ config('app.name', 'CruLynk') }}
                            </p>
                            <h1 style="margin:0;font-size:26px;line-height:1.25;color:#ffffff;font-weight:700;">
                                {{ $headline }}
                            </h1>
                            <p style="margin:12px 0 0 0;font-size:15px;line-height:1.5;color:rgba(255,255,255,0.9);">
                                {{ $company->name }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.55;color:#111827;">
                                Hello{{ $employee->first_name ? ' '.$employee->first_name : '' }},
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px 0;">
                                <tr>
                                    <td align="center" style="background-color:{{ $accentSoft }};border:1px solid #D8E2F0;border-radius:16px;padding:18px 16px;">
                                        <p style="margin:0 0 6px 0;font-size:12px;letter-spacing:1.4px;text-transform:uppercase;color:{{ $accent }};font-weight:700;">
                                            Application status
                                        </p>
                                        <p style="margin:0;font-size:20px;line-height:1.3;font-weight:700;color:{{ $accent }};">
                                            {{ $statusLabel }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            @foreach ($paragraphs as $paragraph)
                                <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#4B5563;">
                                    {{ $paragraph }}
                                </p>
                            @endforeach

                            @if ($nextSteps !== [])
                                <p style="margin:8px 0 12px 0;font-size:13px;letter-spacing:1.1px;text-transform:uppercase;color:#003D7A;font-weight:700;">
                                    What to do next
                                </p>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 8px 0;">
                                    @foreach ($nextSteps as $index => $step)
                                        <tr>
                                            <td valign="top" width="28" style="padding:0 0 12px 0;font-size:14px;line-height:1.55;font-weight:700;color:#003D7A;">
                                                {{ $index + 1 }}.
                                            </td>
                                            <td valign="top" style="padding:0 0 12px 0;font-size:15px;line-height:1.55;color:#374151;">
                                                {{ $step }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px 32px;">
                            <div style="height:1px;background-color:#E5E7EB;margin-bottom:20px;"></div>
                            <p style="margin:0;font-size:12px;line-height:1.55;color:#9CA3AF;text-align:center;">
                                Sent by {{ config('app.name', 'CruLynk') }} for {{ $company->name }}.<br>
                                This is an automated message about your application. Please keep it for your records.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
