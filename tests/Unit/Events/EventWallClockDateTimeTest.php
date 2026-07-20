<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Application\Support\EventWallClockDateTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('events')]
final class EventWallClockDateTimeTest extends TestCase
{
    public function test_parses_datetime_local_in_event_timezone_to_utc(): void
    {
        $stored = EventWallClockDateTime::parseToAppStorage('2026-07-19T11:00', 'Asia/Riyadh');

        self::assertNotNull($stored);
        self::assertSame('2026-07-19 08:00:00', $stored->format('Y-m-d H:i:s'));
    }

    public function test_formats_stored_utc_back_to_event_timezone_input(): void
    {
        $stored = CarbonImmutable::parse('2026-07-19 08:00:00', 'UTC');
        $input = EventWallClockDateTime::toInput($stored, 'Asia/Riyadh');

        self::assertSame('2026-07-19T11:00', $input);
    }

    public function test_formats_iso8601_with_event_offset(): void
    {
        $stored = CarbonImmutable::parse('2026-07-19 08:00:00', 'UTC');
        $iso = EventWallClockDateTime::toIso8601($stored, 'Asia/Riyadh');

        self::assertNotNull($iso);
        self::assertStringContainsString('11:00:00', $iso);
        self::assertStringContainsString('+03:00', $iso);
    }
}
