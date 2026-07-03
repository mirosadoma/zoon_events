<?php

namespace App\Modules\FeatureFlags\Domain;

enum FeatureFlagValueType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case String = 'string';

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::Boolean => is_bool($value),
            self::Integer => is_int($value),
            self::String => is_string($value) && mb_strlen($value) <= 500,
        };
    }
}
