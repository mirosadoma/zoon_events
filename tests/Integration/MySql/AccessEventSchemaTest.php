<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-authorization')]
final class AccessEventSchemaTest extends Phase4MySqlTestCase
{
    public function test_access_events_table_matches_contract(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTableHasColumns('access_events', [
            'id', 'tenant_id', 'event_id', 'event_type', 'credential_id', 'zone_id', 'lane_id',
            'direction', 'decision', 'reason_code', 'source', 'external_event_id', 'scan_event_id',
            'occurred_at', 'created_at',
        ]);

        self::assertNotContains('updated_at', Schema::getColumnListing('access_events'));
        $this->assertTableHasIndex('access_events', 'access_events_external_uq');
        $this->assertTableHasIndex('access_events', 'access_events_timeline_index');
        $this->assertTableHasIndex('access_events', 'access_events_credential_zone_timeline_index');
        $this->assertCheckConstraintContains('access_events', 'access_events_type_chk', 'decision');
        $this->assertCheckConstraintContains('access_events', 'access_events_decision_chk', 'allow');
    }

    /** @param list<string> $columns */
    private function assertTableHasColumns(string $table, array $columns): void
    {
        $existing = Schema::getColumnListing($table);
        self::assertNotEmpty($existing, "Expected table {$table} to exist.");

        foreach ($columns as $column) {
            self::assertContains($column, $existing, "Missing column {$column} on {$table}.");
        }
    }

    private function assertTableHasIndex(string $table, string $indexName): void
    {
        $schema = (string) config('database.connections.mysql.database');
        $exists = (bool) DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             LIMIT 1',
            [$schema, $table, $indexName],
        );

        self::assertTrue($exists, "Expected index {$indexName} on {$table}.");
    }

    private function assertCheckConstraintContains(string $table, string $constraintName, string $needle): void
    {
        $schema = (string) config('database.connections.mysql.database');
        $row = DB::selectOne(
            'SELECT cc.CHECK_CLAUSE
             FROM information_schema.TABLE_CONSTRAINTS tc
             JOIN information_schema.CHECK_CONSTRAINTS cc
               ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA
              AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
             WHERE tc.TABLE_SCHEMA = ? AND tc.TABLE_NAME = ? AND tc.CONSTRAINT_NAME = ?
             LIMIT 1',
            [$schema, $table, $constraintName],
        );

        self::assertNotNull($row, "Expected check constraint {$constraintName} on {$table}.");
        self::assertStringContainsString($needle, (string) $row->CHECK_CLAUSE);
    }
}
