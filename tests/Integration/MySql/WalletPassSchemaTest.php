<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsTenantSchema;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class WalletPassSchemaTest extends Phase2MySqlTestCase
{
    use AssertsTenantSchema;

    public function test_wallet_pass_tables_define_required_columns_and_unique_indexes(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->assertTableHasColumns('wallet_passes', [
            'id',
            'tenant_id',
            'event_id',
            'attendee_id',
            'credential_id',
            'provider',
            'pass_serial_number',
            'pass_url',
            'apple_authentication_token',
            'pass_content_updated_at',
            'status',
            'last_pushed_at',
            'last_push_reason_code',
            'superseded_by_id',
            'created_at',
            'updated_at',
        ]);
        $this->assertUniqueIndexOnColumns('wallet_passes', 'wallet_passes_tenant_id_provider_pass_serial_number_unique', [
            'tenant_id',
            'provider',
            'pass_serial_number',
        ]);

        $this->assertTableHasColumns('wallet_pass_apple_device_registrations', [
            'id',
            'tenant_id',
            'wallet_pass_id',
            'device_library_identifier',
            'push_token',
            'registered_at',
            'unregistered_at',
        ]);
        $this->assertUniqueIndexOnColumns(
            'wallet_pass_apple_device_registrations',
            'wallet_pass_apple_regs_pass_device_unique',
            ['wallet_pass_id', 'device_library_identifier'],
        );
    }

    /** @param list<string> $columns */
    private function assertTableHasColumns(string $table, array $columns): void
    {
        $schema = (string) config('database.connections.mysql.database');
        $existing = collect(DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$schema, $table],
        ))->pluck('COLUMN_NAME')->all();

        self::assertNotEmpty($existing, "Expected table {$table} to exist.");
        self::assertSame($columns, $existing, "{$table} columns must match the Phase 2 contract.");
    }

    /** @param list<string> $expectedColumns */
    private function assertUniqueIndexOnColumns(string $table, string $indexName, array $expectedColumns): void
    {
        $schema = (string) config('database.connections.mysql.database');
        $columns = collect(DB::select(
            'SELECT COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0
             ORDER BY SEQ_IN_INDEX',
            [$schema, $table, $indexName],
        ))->pluck('COLUMN_NAME')->all();

        self::assertSame($expectedColumns, $columns, "{$indexName} on {$table} must match the Phase 2 contract.");
    }
}
