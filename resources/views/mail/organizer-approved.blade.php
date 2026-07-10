<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Organizer account approved</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1c2434; background: #f8fafc; padding: 24px;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
    <tr>
        <td style="background: #3c50e0; color: #ffffff; padding: 24px 28px;">
            <h1 style="margin: 0; font-size: 22px;">Welcome to {{ $appName }}</h1>
            <p style="margin: 8px 0 0; opacity: 0.9;">Your organizer registration has been approved.</p>
        </td>
    </tr>
    <tr>
        <td style="padding: 28px;">
            <p>Dear {{ $organizerName }},</p>
            <p>
                We are pleased to inform you that your request to register
                <strong>{{ $organizationName }}</strong> as an event organizer on {{ $appName }} has been
                <strong style="color: #059669;">approved</strong>.
            </p>
            <p>Your tenant workspace has been created and your account has been granted full organizer administration permissions.</p>
            <p style="margin: 24px 0;">
                <a href="{{ $loginUrl }}" style="display: inline-block; background: #3c50e0; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: bold;">
                    Sign in to your dashboard
                </a>
            </p>
            <p>Use the email address you registered with and the password you chose during registration.</p>
            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
            <p style="font-size: 14px; color: #64748b;">
                If you need help getting started, contact us at
                <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
            </p>
            <p style="font-size: 14px; color: #64748b; margin-bottom: 0;">Thank you,<br>{{ $appName }} Team</p>
        </td>
    </tr>
</table>
</body>
</html>
