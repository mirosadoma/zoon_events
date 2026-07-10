<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Organizer registration update</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1c2434; background: #f8fafc; padding: 24px;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
    <tr>
        <td style="background: #b91c1c; color: #ffffff; padding: 24px 28px;">
            <h1 style="margin: 0; font-size: 22px;">{{ $appName }} registration update</h1>
            <p style="margin: 8px 0 0; opacity: 0.9;">Your organizer registration request was not approved.</p>
        </td>
    </tr>
    <tr>
        <td style="padding: 28px;">
            <p>Dear {{ $organizerName }},</p>
            <p>
                Thank you for your interest in joining {{ $appName }} as an organizer for
                <strong>{{ $organizationName }}</strong>.
            </p>
            <p>
                After review, the administration team has decided
                <strong style="color: #b91c1c;">not to approve</strong> your registration request at this time.
            </p>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 20px 0;">
                <p style="margin: 0 0 8px; font-weight: bold; color: #991b1b;">Reason provided by administration:</p>
                <p style="margin: 0; white-space: pre-wrap;">{{ $reason }}</p>
            </div>
            <p>
                If you believe this decision was made in error, or if you would like to provide additional information,
                please reply to this email or contact
                <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
            </p>
            <p style="font-size: 14px; color: #64748b; margin-bottom: 0;">Regards,<br>{{ $appName }} Administration Team</p>
        </td>
    </tr>
</table>
</body>
</html>
