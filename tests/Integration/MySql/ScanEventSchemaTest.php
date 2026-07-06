<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class ScanEventSchemaTest extends Phase2MySqlTestCase
{
    public function test_scan_tables_define_required_columns_and_unique_indexes(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTableHasColumns('event_check_in_settings', [
            'tenant_id',
            'event_id',
            'single_entry_enabled',
            'single_entry_scope',
            'created_at',
            'updated_at',
        ]);
        $this->assertPrimaryKeyOnColumns('event_check_in_settings', ['tenant_id', 'event_id']);

        $this->assertTableHasColumns('scan_events', [
            'id',
            'tenant_id',
            'event_id',
            'attendee_id',
            'credential_id',
            'scanner_type',
            'scanner_id',
            'gate_id',
            'zone_id',
            'direction',
            'result',
            'reason',
            'attendee_display_name_ciphertext',
            'offline_mode',
            'scanned_at',
            'synced_at',
            'created_at',
        ]);
        self::assertNotContains('updated_at', $this->columnNames('scan_events'));

        $this->assertTableHasColumns('event_check_in_summaries', [
            'tenant_id',
            'event_id',
            'registered_count',
            'checked_in_count',
            'rejected_count',
            'duplicate_count',
            'last_scan_at',
            'updated_at',
        ]);
        $this->assertPrimaryKeyOnColumns('event_check_in_summaries', ['tenant_id', 'event_id']);
    }

    public function test_scan_events_table_defines_required_indexes(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTableHasIndex('scan_events', 'scan_events_credential_timeline_index');
        $this->assertTableHasIndex('scan_events', 'scan_events_result_timeline_index');
        $this->assertTableHasIndex('wallet_passes', 'wallet_passes_attendee_provider_index');
        $this->assertTableHasIndex('wallet_passes', 'wallet_passes_credential_index');
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
        self::assertTrue($exists, "Expected index {$indexName} to exist on {$table}.");
    }

    /** @return list<string> */
    private function columnNames(string $table): array
    {
        $schema = (string) config('database.connections.mysql.database');

        return collect(DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$schema, $table],
        ))->pluck('COLUMN_NAME')->all();
    }

    /** @param list<string> $columns */
    private function assertTableHasColumns(string $table, array $columns): void
    {
        $existing = $this->columnNames($table);
        self::assertNotEmpty($existing, "Expected table {$table} to exist.");
        self::assertSame($columns, $existing, "{$table} columns must match the Phase 2 contract.");
    }

    /** @param list<string> $expectedColumns */
    private function assertPrimaryKeyOnColumns(string $table, array $expectedColumns): void
    {
        $schema = (string) config('database.connections.mysql.database');
        $columns = collect(DB::select(
            'SELECT COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = "PRIMARY"
             ORDER BY ORDINAL_POSITION',
            [$schema, $table],
        ))->pluck('COLUMN_NAME')->all();

        self::assertSame($expectedColumns, $columns, "PRIMARY key on {$table} must match the Phase 2 contract.");
    }
}
