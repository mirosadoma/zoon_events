<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('unsubscribe.page_title', [], $locale) }}</title>
</head>

<body
    style="margin:0;min-height:100vh;background:#0b1220;color:#e2e8f0;font-family:Arial,Helvetica,sans-serif;padding:32px 16px 24px;">
    <p style="margin:0 0 24px;text-align:center;">
        <a href="{{ route('public.notifications.unsubscribe', ['locale' => $alternateLocale]) }}"
            style="color:#94a3b8;font-size:14px;text-decoration:underline;">
            {{ __('unsubscribe.language_toggle', [], $locale) }}
        </a>
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                    style="max-width:760px;background:#111827;border:1px solid #1d4ed8;border-radius:18px;">
                    <tr>
                        <td style="padding:40px 48px 32px;">
                            <h1
                                style="margin:0 0 20px;font-size:34px;line-height:1.2;font-weight:700;color:#d9f99d;text-align:center;">
                                {{ __('unsubscribe.company_name', [], $locale) }}
                            </h1>
                            <p style="margin:0 0 28px;font-size:16px;line-height:1.7;color:#e2e8f0;text-align:center;">
                                {{ __('unsubscribe.company_intro', [], $locale) }}
                            </p>

                            <h2 style="margin:0 0 14px;font-size:22px;line-height:1.4;color:#38bdf8;">
                                {{ __('unsubscribe.company_features_heading', [], $locale) }}
                            </h2>
                            <ul style="margin:0 0 28px;padding:0 0 0 22px;font-size:15px;line-height:1.8;color:#cbd5e1;">
                                @foreach (__('unsubscribe.company_features', [], $locale) as $feature)
                                    <li style="margin:0 0 6px;">{{ $feature }}</li>
                                @endforeach
                            </ul>

                            <h2 style="margin:0 0 14px;font-size:22px;line-height:1.4;color:#38bdf8;">
                                {{ __('unsubscribe.project_name', [], $locale) }}
                            </h2>
                            <p style="margin:0 0 18px;font-size:16px;line-height:1.7;color:#e2e8f0;">
                                {{ __('unsubscribe.project_intro', [], $locale) }}
                            </p>

                            <h3 style="margin:0 0 14px;font-size:22px;line-height:1.4;color:#38bdf8;">
                                {{ __('unsubscribe.project_features_heading', [], $locale) }}
                            </h3>
                            <ul style="margin:0 0 36px;padding:0 0 0 22px;font-size:15px;line-height:1.8;color:#cbd5e1;">
                                @foreach (__('unsubscribe.project_features', [], $locale) as $feature)
                                    <li style="margin:0 0 6px;">{{ $feature }}</li>
                                @endforeach
                            </ul>

                            @if ($unsubscribed)
                                <p
                                    style="margin:0 0 24px;padding:16px 20px;border-radius:12px;background:#14532d;color:#bbf7d0;font-size:18px;font-weight:700;text-align:center;">
                                    {{ __('unsubscribe.success_message', [], $locale) }}
                                </p>
                            @endif

                            @if (! $unsubscribed)
                                <form method="post"
                                    action="{{ route('public.notifications.unsubscribe.confirm', ['locale' => $locale]) }}"
                                    style="margin:0;text-align:center;">
                                    @csrf
                                    <button type="submit"
                                        style="display:inline-block;padding:16px 42px;border:0;border-radius:999px;background:linear-gradient(90deg,#ef4444,#f97316);color:#ffffff;font-size:16px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;cursor:pointer;">
                                        {{ __('unsubscribe.cancel_button', [], $locale) }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:32px 0 0;text-align:center;font-size:13px;color:#94a3b8;">
        {{ __('unsubscribe.footer_copyright', [], $locale) }}
    </p>
</body>

</html>
