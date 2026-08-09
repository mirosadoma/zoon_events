<?php

namespace App\Modules\Events\Application\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Event schedule datetimes are stored as wall-clock digits in the DB datetime column
 * (same numbers the organizer typed). They are NOT converted to a real UTC instant on write.
 *
 * Why: production data was already saved this way. Converting on read/write would shift
 * every existing time (e.g. 11:00 → 14:00 in UTC+3) and force organizers to re-edit.
 *
 * - parseToAppStorage: keep entered digits
 * - toInput / toDateString: return those digits
 * - toIso8601: attach the event timezone offset to those digits for APIs
 * - asEventLocal: reinterpret digits in the event timezone for "now" comparisons
 */
final class EventWallClockDateTime
{
    public static function toInput(?CarbonImmutable $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::wallClockDigits($value)->format('Y-m-d\TH:i');
    }

    /** Calendar date (`Y-m-d`) from stored wall-clock digits. */
    public static function toDateString(?CarbonImmutable $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::wallClockDigits($value)->toDateString();
    }

    /** ISO-8601 with the event timezone offset applied to the stored wall-clock digits. */
    public static function toIso8601(?CarbonImmutable $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::asEventLocal($value, $timezone)?->toIso8601String();
    }

    /**
     * Persist organizer-entered wall-clock digits as-is (no timezone conversion).
     * `$timezone` is accepted for call-site compatibility / future validation.
     */
    public static function parseToAppStorage(?string $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC');
    }

    /** Reinterpret stored wall-clock digits as a real instant in the event timezone. */
    public static function asEventLocal(?CarbonImmutable $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse(
            self::wallClockDigits($value)->format('Y-m-d H:i:s'),
            self::safeTimezone($timezone),
        );
    }

    public static function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '' || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("Invalid timezone [{$timezone}].");
        }

        return $timezone;
    }

    private static function wallClockDigits(CarbonImmutable $value): CarbonImmutable
    {
        return $value->timezone('UTC');
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
