<?php

namespace App\Modules\IdentityVerification\Domain\ValueObjects;

final class IdentityVerificationStatus
{
    public const NOT_REQUIRED = 'not_required';

    public const PENDING = 'pending';

    public const GOV_VERIFIED = 'gov_verified';

    public const FACE_VERIFIED = 'face_verified';

    public const MANUALLY_APPROVED = 'manually_approved';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    /** @return list<string> */
    public static function values(): array
    {
        return [
            self::NOT_REQUIRED,
            self::PENDING,
            self::GOV_VERIFIED,
            self::FACE_VERIFIED,
            self::MANUALLY_APPROVED,
            self::REJECTED,
            self::EXPIRED,
        ];
    }
}
