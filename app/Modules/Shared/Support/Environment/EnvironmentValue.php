<?php

namespace App\Modules\Shared\Support\Environment;

/**
 * Resolve named environment values in a config:cache-safe order:
 * 1) config('secrets.{NAME}') — baked at config load / cache time
 * 2) env('{NAME}') — available when .env is still loaded (local / no cache)
 */
final class EnvironmentValue
{
    public static function get(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $fromConfig = config('secrets.'.$name);
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        $fromEnv = env($name);
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        return null;
    }
}
