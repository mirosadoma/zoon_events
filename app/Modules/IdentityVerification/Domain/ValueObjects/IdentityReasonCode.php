<?php

namespace App\Modules\IdentityVerification\Domain\ValueObjects;

final class IdentityReasonCode
{
    public const NOT_VERIFIED = 'identity_not_verified';

    public const EXPIRED = 'identity_expired';

    public const REJECTED = 'identity_rejected';

    public const CONSENT_MISSING = 'identity_consent_missing';

    public const PROVIDER_UNAVAILABLE = 'identity_provider_unavailable';

    /** @return list<string> */
    public static function values(): array
    {
        return [
            self::NOT_VERIFIED,
            self::EXPIRED,
            self::REJECTED,
            self::CONSENT_MISSING,
            self::PROVIDER_UNAVAILABLE,
        ];
    }
}
