<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MySqlTestCase;

#[Group('badge-printing')]
#[Group('phase-3')]
final class BadgePrintJobSchemaTest extends MySqlTestCase
{
    public function test_badge_print_jobs_table_has_required_columns(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $columns = Schema::getColumnListing('badge_print_jobs');

        $expected = [
            'id', 'tenant_id', 'event_id', 'attendee_id', 'credential_id',
            'badge_template_id', 'kiosk_id', 'printed_by_user_id', 'status',
            'failure_reason', 'is_reprint', 'reprint_reason', 'original_print_job_id',
            'printed_at', 'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            self::assertContains($column, $columns, "Missing column: {$column}");
        }
    }

    public function test_badge_print_jobs_has_status_and_attendee_indexes(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $indexes = Schema::getIndexes('badge_print_jobs');
        $indexNames = collect($indexes)->pluck('name')->all();

        self::assertNotEmpty($indexNames, 'badge_print_jobs should have at least one index');
    }
}
