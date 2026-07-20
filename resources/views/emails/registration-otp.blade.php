<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $locale === 'ar' ? 'رمز التحقق' : 'Verification code' }}</title>
</head>
<body dir="{{ $direction }}" style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 28px 16px;text-align:{{ $textAlign }};background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);color:#ffffff;">
                        <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;">
                            {{ $locale === 'ar' ? 'تأكيد التسجيل' : 'Registration verification' }}
                        </p>
                        <h1 style="margin:0;font-size:22px;line-height:1.35;font-weight:700;color:#ffffff;">
                            {{ $eventName }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;text-align:{{ $textAlign }};">
                        @if($locale === 'ar')
                            <p style="margin:0 0 12px;font-size:16px;line-height:1.6;color:#0f172a;">مرحباً،</p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#475569;">
                                استخدم رمز التحقق التالي لإكمال تسجيلك. الرمز صالح لمدة <strong>15 دقيقة</strong>.
                            </p>
                        @else
                            <p style="margin:0 0 12px;font-size:16px;line-height:1.6;color:#0f172a;">Hello,</p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#475569;">
                                Use the verification code below to finish your registration. This code expires in <strong>15 minutes</strong>.
                            </p>
                        @endif

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td align="center" style="padding:8px 0 4px;">
                                    <div style="display:inline-block;padding:18px 28px;border-radius:16px;background:#0f172a;border:1px solid #1e293b;">
                                        <span style="font-size:34px;line-height:1;letter-spacing:0.28em;font-weight:700;color:#ffffff;font-family:Consolas,Monaco,monospace;">
                                            {{ $code }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:24px 0 0;font-size:13px;line-height:1.5;color:#94a3b8;">
                            {{ $locale === 'ar'
                                ? 'إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة بأمان.'
                                : 'If you did not request this code, you can safely ignore this email.' }}
                        </p>
                        <p style="margin:12px 0 0;font-size:12px;color:#cbd5e1;">{{ $appName }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
