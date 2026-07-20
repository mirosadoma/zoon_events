<?php

namespace App\Modules\Events\Application\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Treat datetime-local form values as wall-clock times in the event timezone.
 * Avoids the common bug where "11:00" is stored as UTC and shown as "14:00" in UTC+3.
 */
final class EventWallClockDateTime
{
    public static function toInput(?CarbonImmutable $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->timezone(self::safeTimezone($timezone))->format('Y-m-d\TH:i');
    }

    /** ISO-8601 with the event timezone offset (for display APIs). */
    public static function toIso8601(?CarbonImmutable $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->timezone(self::safeTimezone($timezone))->toIso8601String();
    }

    public static function parseToAppStorage(?string $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, self::safeTimezone($timezone))->utc();
    }

    public static function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '' || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("Invalid timezone [{$timezone}].");
        }

        return $timezone;
    }

    private static function safeTimezone(string $timezone): string
    {
        try {
            return self::normalizeTimezone($timezone);
        } catch (InvalidArgumentException) {
            return 'UTC';
        }
    }
}
