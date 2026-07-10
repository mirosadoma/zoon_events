<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MySqlTestCase;

#[Group('kiosk')]
#[Group('phase-3')]
final class KioskSchemaTest extends MySqlTestCase
{
    public function test_kiosks_table_has_required_columns(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $columns = Schema::getColumnListing('kiosks');

        $expected = [
            'id', 'tenant_id', 'event_id', 'device_name', 'device_code',
            'location_label', 'status', 'printer_status', 'last_heartbeat_at',
            'confirmation_required', 'confirmation_code_hash', 'retired_at',
            'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            self::assertContains($column, $columns, "Missing column: {$column}");
        }
    }

    public function test_kiosk_sessions_table_has_required_columns(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $columns = Schema::getColumnListing('kiosk_sessions');

        $expected = ['id', 'tenant_id', 'kiosk_id', 'secret_hash', 'confirmed_at', 'expires_at', 'revoked_at', 'created_at'];

        foreach ($expected as $column) {
            self::assertContains($column, $columns, "Missing column: {$column}");
        }
    }
}
