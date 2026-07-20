<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $locale === 'ar' ? 'شارة الطباعة' : 'Printable badge' }} — {{ $eventName }}</title>
</head>
<body dir="{{ $direction }}" style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
@php
    $qrSrc = null;
    if (! empty($qrPngBytes) && isset($message)) {
        $qrSrc = $message->embedData($qrPngBytes, 'badge-qr.png', 'image/png');
    }
    $renderedBadge = $badgeHtml ?? '';
    if (is_string($renderedBadge) && $renderedBadge !== '' && is_string($qrSrc) && $qrSrc !== '') {
        $renderedBadge = str_replace('cid:'.$qrContentId, $qrSrc, $renderedBadge);
    }
    if (is_string($renderedBadge) && $renderedBadge !== '' && ! empty($inlineImages) && isset($message)) {
        foreach ($inlineImages as $image) {
            if (! is_array($image) || empty($image['bytes']) || empty($image['cid'])) {
                continue;
            }
            $embeddedSrc = $message->embedData(
                $image['bytes'],
                $image['filename'] ?? ($image['cid'].'.png'),
                $image['mime'] ?? 'image/png',
            );
            $renderedBadge = str_replace('cid:'.$image['cid'], $embeddedSrc, $renderedBadge);
        }
    }

    $summaryRows = array_filter([
        ($locale === 'ar' ? 'الاسم' : 'Name') => $badge['attendee_name'] ?? null,
        ($locale === 'ar' ? 'الشركة' : 'Company') => $badge['company'] ?? null,
        ($locale === 'ar' ? 'المسمى الوظيفي' : 'Job title') => $badge['job_title'] ?? null,
        ($locale === 'ar' ? 'نوع التذكرة' : 'Ticket') => $badge['ticket_type'] ?? null,
        ($locale === 'ar' ? 'الفئة' : 'Category') => $badge['tier'] ?? null,
        ($locale === 'ar' ? 'المنطقة' : 'Zone') => $badge['zone'] ?? null,
    ], fn ($value) => is_string($value) && trim($value) !== '');
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 28px 12px;text-align:{{ $textAlign }};background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);color:#ffffff;">
                        <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#94a3b8;">
                            {{ $locale === 'ar' ? 'شارة الحضور' : 'Attendee badge' }}
                        </p>
                        <h1 style="margin:0;font-size:24px;line-height:1.3;font-weight:700;color:#ffffff;">
                            {{ $eventName }}
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 28px 8px;text-align:{{ $textAlign }};">
                        @if($locale === 'ar')
                            <p style="margin:0 0 10px;font-size:16px;line-height:1.6;color:#0f172a;">
                                مرحباً{{ !empty($badge['attendee_name']) ? ' '.e($badge['attendee_name']) : '' }}،
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#475569;">
                                شكراً لتسجيلك. هذه شارة الحضور الخاصة بك — اطبع هذه الرسالة أو احفظ مرفق رمز QR واعرضها عند الدخول.
                            </p>
                        @else
                            <p style="margin:0 0 10px;font-size:16px;line-height:1.6;color:#0f172a;">
                                Hello{{ !empty($badge['attendee_name']) ? ' '.e($badge['attendee_name']) : '' }},
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#475569;">
                                Thank you for registering. This is your printable attendee badge — print this email or keep the QR attachment ready for entry.
                            </p>
                        @endif
                    </td>
                </tr>

                @if($renderedBadge !== '')
                    <tr>
                        <td align="center" style="padding:20px 16px 8px;">
                            <div style="display:inline-block;max-width:100%;overflow:auto;">
                                {!! $renderedBadge !!}
                            </div>
                        </td>
                    </tr>
                @endif

                @if(count($summaryRows) > 0)
                    <tr>
                        <td style="padding:16px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
                                <tr>
                                    <td colspan="2" style="padding:12px 16px;background:#f8fafc;font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;text-align:{{ $textAlign }};">
                                        {{ $locale === 'ar' ? 'بيانات الشارة' : 'Badge details' }}
                                    </td>
                                </tr>
                                @foreach($summaryRows as $label => $value)
                                    <tr>
                                        <td style="padding:10px 16px;border-top:1px solid #e2e8f0;width:38%;font-size:13px;color:#64748b;text-align:{{ $textAlign }};">{{ $label }}</td>
                                        <td style="padding:10px 16px;border-top:1px solid #e2e8f0;font-size:14px;font-weight:600;color:#0f172a;text-align:{{ $textAlign }};">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif

                @if(!empty($qrSrc))
                    <tr>
                        <td align="center" style="padding:8px 28px 8px;">
                            <p style="margin:0 0 12px;font-size:13px;color:#64748b;text-align:center;">
                                {{ $locale === 'ar' ? 'رمز الدخول (QR)' : 'Entry QR code' }}
                            </p>
                            <img src="{{ $qrSrc }}" width="220" height="220" alt="QR" style="display:block;margin:0 auto;border:0;border-radius:12px;">
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:20px 28px 28px;text-align:{{ $textAlign }};">
                        <p style="margin:0;font-size:13px;line-height:1.5;color:#94a3b8;">
                            {{ $locale === 'ar'
                                ? 'اطبع الشارة قبل الحضور واحتفظ بها معك يوم الفعالية.'
                                : 'Print your badge before you arrive and keep it with you on the event day.' }}
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
