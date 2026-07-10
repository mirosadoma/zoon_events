<?php

namespace App\Modules\FeatureFlags\Domain;

enum FeatureFlagLifecycle: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';
    case Retired = 'retired';
}
