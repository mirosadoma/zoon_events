<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $locale === 'ar' ? 'حسابك موجود' : 'Your account already exists' }}</title>
</head>
<body dir="{{ $direction }}" style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 28px 16px;text-align:{{ $textAlign }};background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);color:#ffffff;">
                        <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;">
                            {{ $locale === 'ar' ? 'لوحة الزائر' : 'Visitor portal' }}
                        </p>
                        <h1 style="margin:0;font-size:22px;line-height:1.35;font-weight:700;color:#ffffff;">
                            {{ $locale === 'ar' ? 'تم التسجيل بنجاح' : 'Registration complete' }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;text-align:{{ $textAlign }};">
                        @if($locale === 'ar')
                            <p style="margin:0 0 12px;font-size:16px;line-height:1.6;color:#0f172a;">مرحباً،</p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#475569;">
                                تم تسجيل اشتراكك في الفعالية بنجاح. حسابك موجود مسبقاً — استخدم بريدك وكلمة المرور الحالية لتسجيل الدخول.
                            </p>
                        @else
                            <p style="margin:0 0 12px;font-size:16px;line-height:1.6;color:#0f172a;">Hello,</p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#475569;">
                                Your event registration was completed successfully. You already have an account — sign in with your existing email and password.
                            </p>
                        @endif

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;">
                            <tr>
                                <td style="padding:20px 22px;text-align:{{ $textAlign }};">
                                    <p style="margin:0 0 6px;font-size:12px;letter-spacing:0.04em;text-transform:uppercase;color:#94a3b8;">
                                        {{ $locale === 'ar' ? 'بريدك المسجّل' : 'Your registered email' }}
                                    </p>
                                    <p style="margin:0;font-size:16px;line-height:1.4;font-weight:700;color:#0f172a;word-break:break-all;">
                                        {{ $email }}
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 8px;">
                            <tr>
                                <td align="center" bgcolor="#0f172a" style="border-radius:12px;">
                                    <a href="{{ $loginUrl }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;background:#0f172a;">
                                        {{ $locale === 'ar' ? 'تسجيل الدخول' : 'Sign in' }}
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:24px 0 0;font-size:13px;line-height:1.5;color:#94a3b8;">
                            {{ $locale === 'ar'
                                ? 'لم نُرسل كلمة مرور جديدة. إذا نسيت كلمة المرور، استخدم «نسيت كلمة المرور» في صفحة تسجيل الدخول.'
                                : 'We did not send a new password. If you forgot yours, use “Forgot password?” on the login page.' }}
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
