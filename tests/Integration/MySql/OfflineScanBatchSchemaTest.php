<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('offline-scanning')]
final class OfflineScanBatchSchemaTest extends Phase2MySqlTestCase
{
    public function test_offline_scan_reconciliation_batches_table_matches_contract(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $columns = collect(DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [(string) config('database.connections.mysql.database'), 'offline_scan_reconciliation_batches'],
        ))->pluck('COLUMN_NAME')->all();

        self::assertSame([
            'id',
            'tenant_id',
            'event_id',
            'device_reference',
            'allowlist_issued_at',
            'allowlist_expires_at',
            'submitted_scan_count',
            'accepted_count',
            'duplicate_count',
            'conflict_count',
            'status',
            'created_at',
            'processed_at',
        ], $columns);
    }
}
