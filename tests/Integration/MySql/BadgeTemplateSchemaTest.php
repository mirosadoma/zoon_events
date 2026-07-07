<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MySqlTestCase;

#[Group('badge-printing')]
#[Group('phase-3')]
final class BadgeTemplateSchemaTest extends MySqlTestCase
{
    public function test_badge_templates_table_has_required_columns(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $columns = Schema::getColumnListing('badge_templates');

        foreach (['id', 'tenant_id', 'event_id', 'name', 'layout', 'paper_size', 'printer_type', 'status', 'created_at', 'updated_at'] as $column) {
            self::assertContains($column, $columns, "Missing column: {$column}");
        }
    }

    public function test_badge_templates_has_status_index(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $indexes = Schema::getIndexes('badge_templates');
        $indexedColumns = collect($indexes)->flatMap(fn ($idx) => $idx['columns'])->unique()->values()->all();

        self::assertContains('status', $indexedColumns, 'badge_templates should have status indexed');
    }
}
