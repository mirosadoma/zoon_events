<?php

namespace App\Modules\IdentityVerification\Domain\ValueObjects;

final class IdentityRequirementLevel
{
    public const NOT_REQUIRED = 'not_required';

    public const OPTIONAL = 'optional';

    public const REQUIRED_BEFORE_CREDENTIAL = 'required_before_credential';

    public const REQUIRED_BEFORE_GATE = 'required_before_gate';

    public const REQUIRED_VIP = 'required_vip';

    public const REQUIRED_VVIP = 'required_vvip';

    /** @return list<string> */
    public static function values(): array
    {
        return [
            self::NOT_REQUIRED,
            self::OPTIONAL,
            self::REQUIRED_BEFORE_CREDENTIAL,
            self::REQUIRED_BEFORE_GATE,
            self::REQUIRED_VIP,
            self::REQUIRED_VVIP,
        ];
    }
}
