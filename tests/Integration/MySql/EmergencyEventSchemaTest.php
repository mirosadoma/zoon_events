<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-emergency')]
final class EmergencyEventSchemaTest extends Phase4MySqlTestCase
{
    public function test_emergency_events_table_matches_contract(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTableHasColumns('emergency_events', [
            'id', 'tenant_id', 'event_id', 'zone_id', 'signal_source', 'behavior_applied',
            'raised_at', 'cleared_at', 'created_at', 'updated_at',
        ]);

        $this->assertTableHasIndex('emergency_events', 'emergency_events_active_index');
        $this->assertCheckConstraintContains('emergency_events', 'emergency_events_source_chk', 'operator');
        $this->assertCheckConstraintContains('emergency_events', 'emergency_events_behavior_chk', 'fail_open');
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
