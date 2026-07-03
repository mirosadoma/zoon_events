<?php

namespace App\Modules\Shared\Domain;

enum LifecycleStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
