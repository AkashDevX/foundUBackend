<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Password reset code</title>
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
                                Password reset code
                            </h1>
                            <p style="margin:12px 0 0 0;font-size:15px;line-height:1.5;color:rgba(255,255,255,0.9);">
                                {{ $company->name }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.55;color:#111827;">
                                Hello{{ $employee->first_name ? ' '.e($employee->first_name) : '' }},
                            </p>
                            <p style="margin:0 0 28px 0;font-size:15px;line-height:1.6;color:#4B5563;">
                                Use this one-time code in the app to verify your identity and choose a new password.
                                It expires in <strong style="color:#111827;">{{ $expiresInMinutes }} minutes</strong>.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 28px 0;">
                                <tr>
                                    <td align="center" style="background-color:#F3F6FB;border:1px solid #D8E2F0;border-radius:16px;padding:24px 16px;">
                                        <p style="margin:0 0 10px 0;font-size:12px;letter-spacing:1.4px;text-transform:uppercase;color:#003D7A;font-weight:700;">
                                            Your verification code
                                        </p>
                                        <p style="margin:0;font-size:36px;letter-spacing:10px;font-weight:700;color:#003D7A;font-family:Consolas,Menlo,Monaco,monospace;">
                                            {{ $otp }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:#6B7280;">
                                Enter this code on the Forgot Password screen, then create your new password.
                            </p>
                            <p style="margin:0;font-size:14px;line-height:1.6;color:#6B7280;">
                                If you did not request this, you can ignore this email. Your password will stay the same.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px 32px;">
                            <div style="height:1px;background-color:#E5E7EB;margin-bottom:20px;"></div>
                            <p style="margin:0;font-size:12px;line-height:1.55;color:#9CA3AF;text-align:center;">
                                Sent by {{ config('app.name', 'CruLynk') }} for {{ $company->name }}.<br>
                                This code can only be used once.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
