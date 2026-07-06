<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class AttendeeCheckInColumnsTest extends Phase2MySqlTestCase
{
    public function test_attendees_table_gains_check_in_columns(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $schema = (string) config('database.connections.mysql.database');
        $columns = collect(DB::select(
            'SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN (?, ?, ?)',
            [$schema, 'attendees', 'checkin_status', 'first_checked_in_at', 'last_scan_event_id'],
        ))->keyBy('COLUMN_NAME');

        self::assertSame('not_checked_in', $columns['checkin_status']->COLUMN_DEFAULT);
        self::assertSame('YES', $columns['first_checked_in_at']->IS_NULLABLE);
        self::assertSame('YES', $columns['last_scan_event_id']->IS_NULLABLE);
    }
}
