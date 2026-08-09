<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Application\Support\EventWallClockDateTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('events')]
final class EventWallClockDateTimeTest extends TestCase
{
    public function test_parse_keeps_wall_clock_digits_without_shifting(): void
    {
        $stored = EventWallClockDateTime::parseToAppStorage('2026-07-19T11:00', 'Asia/Riyadh');

        self::assertNotNull($stored);
        self::assertSame('2026-07-19 11:00:00', $stored->format('Y-m-d H:i:s'));
    }

    public function test_formats_stored_digits_back_to_input_without_shifting(): void
    {
        $stored = CarbonImmutable::parse('2026-07-19 11:00:00', 'UTC');
        $input = EventWallClockDateTime::toInput($stored, 'Asia/Riyadh');

        self::assertSame('2026-07-19T11:00', $input);
    }

    public function test_formats_iso8601_with_event_offset_on_same_digits(): void
    {
        $stored = CarbonImmutable::parse('2026-07-19 11:00:00', 'UTC');
        $iso = EventWallClockDateTime::toIso8601($stored, 'Asia/Riyadh');

        self::assertNotNull($iso);
        self::assertStringContainsString('11:00:00', $iso);
        self::assertStringContainsString('+03:00', $iso);
    }

    public function test_formats_calendar_date_from_stored_digits(): void
    {
        $stored = CarbonImmutable::parse('2026-07-19 21:00:00', 'UTC');

        self::assertSame('2026-07-19', EventWallClockDateTime::toDateString($stored, 'Asia/Riyadh'));
    }

    public function test_as_event_local_reinterprets_digits_in_event_timezone(): void
    {
        $stored = CarbonImmutable::parse('2026-07-19 11:00:00', 'UTC');
        $local = EventWallClockDateTime::asEventLocal($stored, 'Asia/Riyadh');

        self::assertNotNull($local);
        self::assertSame('Asia/Riyadh', $local->timezoneName);
        self::assertSame('2026-07-19 11:00:00', $local->format('Y-m-d H:i:s'));
        self::assertSame('2026-07-19 08:00:00', $local->utc()->format('Y-m-d H:i:s'));
    }
}
