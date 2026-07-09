<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('phase1.confirmation_subject', ['event' => $event_name], $locale) }}</title>
</head>
<body style="margin:0;padding:24px 16px;background:#e8e8e8;font-family:Arial,Helvetica,sans-serif;color:#111111;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:2px solid #1a1a1a;border-radius:20px;">
                <tr>
                    <td style="padding:36px 40px 28px;">
                        <h1 style="margin:0 0 18px;font-size:32px;line-height:1.2;font-weight:700;color:#111111;">
                            {{ __('phase1.confirmation_welcome', ['name' => $attendee_name], $locale) }}
                        </h1>
                        <p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#111111;">
                            {{ __('phase1.confirmation_greeting', [], $locale) }}
                        </p>
                        <p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#111111;">
                            {!! __('phase1.confirmation_thanks', ['event' => '<strong>'.e($event_name).'</strong>'], $locale) !!}
                        </p>
                        <p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#111111;">
                            {{ __('phase1.confirmation_received', [], $locale) }}
                        </p>
                        <p style="margin:0;font-size:16px;line-height:1.6;color:#111111;">
                            {{ __('phase1.confirmation_qr_instructions', [], $locale) }}
                        </p>
                    </td>
                </tr>

                @if (! empty($qr_payload))
                    <tr>
                        <td align="center" style="padding:12px 40px 8px;">
                            <img
                                src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($qr_payload) }}"
                                width="300"
                                height="300"
                                alt="{{ __('phase1.confirmation_qr_alt', [], $locale) }}"
                                style="display:block;margin:0 auto;"
                            />
                        </td>
                    </tr>
                @endif

                <tr>
                    <td align="center" style="padding:8px 40px 28px;">
                        <a href="{{ $credential_url }}" style="font-size:16px;line-height:1.6;color:#111111;text-decoration:underline;">
                            {{ __('phase1.view_credential', [], $locale) }}
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px 36px;">
                        <p style="margin:0 0 10px;font-size:14px;line-height:1.5;color:#111111;">
                            {{ __('phase1.confirmation_footer_powered', [], $locale) }}
                        </p>
                        @if (! empty($support_email))
                            <p style="margin:0;font-size:14px;line-height:1.5;color:#111111;">
                                {!! __('phase1.confirmation_footer_unsubscribe', ['link' => '<a href="mailto:'.e($support_email).'" style="color:#2563eb;text-decoration:underline;">'.e(__('phase1.confirmation_footer_here', [], $locale)).'</a>'], $locale) !!}
                            </p>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
