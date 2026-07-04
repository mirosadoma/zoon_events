<?php

namespace Tests\Integration\MySql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsTenantSchema;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class EventSetupSchemaTest extends Phase1MySqlTestCase
{
    use AssertsTenantSchema;

    public function test_event_setup_tables_are_tenant_scoped_and_constrained(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $this->assertTenantOwnedTablesRequireTenantId([
            'events',
            'event_branding',
            'registration_forms',
            'registration_form_versions',
            'ticket_types',
        ]);

        $this->assertUniqueConstraintStartsWithTenantId('events', 'events_tenant_id_slug_unique');
        $this->assertUniqueConstraintStartsWithTenantId('registration_forms', 'registration_forms_tenant_id_event_id_name_unique');
        $this->assertUniqueConstraintStartsWithTenantId('registration_form_versions', 'registration_form_versions_number_unique');
        $this->assertUniqueConstraintStartsWithTenantId('ticket_types', 'ticket_types_tenant_id_event_id_code_unique');

        foreach ([
            ['events', 'events_schedule_chk'],
            ['events', 'events_status_chk'],
            ['registration_form_versions', 'registration_form_versions_publish_chk'],
            ['ticket_types', 'ticket_types_sale_window_chk'],
            ['ticket_types', 'ticket_types_currency_chk'],
        ] as [$table, $constraint]) {
            $this->assertCheckConstraintExists($table, $constraint);
        }
    }

    public function test_form_and_ticket_foreign_keys_include_tenant_and_event_scope(): void
    {
        $schema = (string) config('database.connections.mysql.database');
        foreach ([
            ['registration_form_versions', 'registration_form_versions_form_fk', 3],
            ['ticket_types', 'ticket_types_tenant_id_event_id_foreign', 2],
            ['event_branding', 'event_branding_tenant_id_event_id_foreign', 2],
        ] as [$table, $constraint, $expectedColumns]) {
            $columns = DB::select(
                'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
                 ORDER BY ORDINAL_POSITION',
                [$schema, $table, $constraint],
            );
            self::assertCount($expectedColumns, $columns);
            self::assertSame('tenant_id', $columns[0]->COLUMN_NAME);
        }
    }
}
