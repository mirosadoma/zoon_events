<?php

namespace App\Modules\WalletPasses\Domain;

enum WalletPassStatus: string
{
    case Created = 'created';
    case Active = 'active';
    case Updated = 'updated';
    case Revoked = 'revoked';
    case Expired = 'expired';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Created => in_array($next, [self::Active, self::Failed], true),
            self::Active => in_array($next, [self::Updated, self::Revoked, self::Expired], true),
            self::Updated => in_array($next, [self::Active, self::Revoked, self::Expired], true),
            self::Revoked, self::Expired, self::Failed => false,
        };
    }
}
