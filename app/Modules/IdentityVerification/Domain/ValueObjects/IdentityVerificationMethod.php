<?php

namespace App\Modules\IdentityVerification\Domain\ValueObjects;

final class IdentityVerificationMethod
{
    public const EMAIL_OTP = 'email_otp';

    public const PHONE_OTP = 'phone_otp';

    public const GOVERNMENT_IDENTITY = 'gov_identity';

    public const FACE_CAPTURE = 'face_capture';

    public const MANUAL_REVIEW = 'manual_review';

    /** @return list<string> */
    public static function values(): array
    {
        return [
            self::EMAIL_OTP,
            self::PHONE_OTP,
            self::GOVERNMENT_IDENTITY,
            self::FACE_CAPTURE,
            self::MANUAL_REVIEW,
        ];
    }
}
