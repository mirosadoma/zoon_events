<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-authorization')]
final class AcsZoneLaneRuleSchemaTest extends Phase4MySqlTestCase
{
    public function test_acs_zones_table_matches_contract(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTableHasColumns('acs_zones', [
            'id', 'tenant_id', 'event_id', 'name', 'external_acs_zone_id',
            'anti_passback_enabled', 'unavailability_mode', 'emergency_egress_mode',
            'status', 'created_at', 'updated_at',
        ]);

        $this->assertTableHasIndex('acs_zones', 'acs_zones_external_uq');
        $this->assertCheckConstraintContains('acs_zones', 'acs_zones_unavailability_chk', 'fail_open');
        $this->assertCheckConstraintContains('acs_zones', 'acs_zones_emergency_chk', 'fail_open');
        $this->assertCheckConstraintContains('acs_zones', 'acs_zones_status_chk', 'active');
    }

    public function test_acs_lanes_table_matches_contract(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTableHasColumns('acs_lanes', [
            'id', 'tenant_id', 'event_id', 'zone_id', 'name', 'external_acs_lane_id',
            'gate_type', 'access_direction', 'is_admission_lane', 'status', 'health_status',
            'last_seen_at', 'created_at', 'updated_at',
        ]);

        $this->assertTableHasIndex('acs_lanes', 'acs_lanes_external_uq');
        $this->assertCheckConstraintContains('acs_lanes', 'acs_lanes_gate_type_chk', 'turnstile');
        $this->assertCheckConstraintContains('acs_lanes', 'acs_lanes_access_direction_chk', 'entry');
        $this->assertCheckConstraintContains('acs_lanes', 'acs_lanes_status_chk', 'active');
        $this->assertCheckConstraintContains('acs_lanes', 'acs_lanes_health_status_chk', 'online');
    }

    public function test_acs_authorization_rules_table_matches_contract(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTableHasColumns('acs_authorization_rules', [
            'id', 'tenant_id', 'event_id', 'ticket_type_id', 'attendee_type', 'zone_id',
            'lane_id', 'access_direction', 'anti_passback_exempt', 'valid_from', 'valid_until',
            'status', 'created_at', 'updated_at',
        ]);

        $this->assertTableHasIndex('acs_authorization_rules', 'acs_authorization_rules_zone_status_index');
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
