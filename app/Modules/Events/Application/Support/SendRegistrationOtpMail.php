<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Mail\CustomEventEmailMail;
use App\Modules\Registration\Mail\RegistrationOtpMail;
use Illuminate\Support\Facades\Mail;

final class SendRegistrationOtpMail
{
    public function __construct(
        private ResolveEventEmailTemplate $templates,
    ) {}

    public function execute(Event $event, string $email, string $code, string $locale = 'en'): void
    {
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $eventName = $resolvedLocale === 'ar'
            ? ($event->name_ar ?: $event->name_en)
            : $event->name_en;

        $custom = $this->templates->render(
            $event,
            ResolveEventEmailTemplate::TYPE_OTP,
            $resolvedLocale,
            [
                'otp' => $code,
            ],
        );

        if ($custom !== null) {
            Mail::to($email)->send(new CustomEventEmailMail(
                $custom['subject'],
                $custom['html'],
                $resolvedLocale,
            ));

            return;
        }

        Mail::to($email)->send(new RegistrationOtpMail($code, $eventName, $resolvedLocale));
    }
}
