<?php

namespace Tests\Integration\MySql;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsTenantSchema;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('free-registration')]
final class FreeRegistrationSchemaTest extends Phase1MySqlTestCase
{
    use AssertsTenantSchema;

    public function test_free_registration_aggregate_tables_are_scoped_and_constrained(): void
    {
        $this->assertTenantOwnedTablesRequireTenantId([
            'registration_submissions', 'orders', 'order_items', 'attendees', 'credentials', 'notifications',
        ]);
        foreach ([
            ['orders', 'orders_money_chk'],
            ['order_items', 'order_items_money_chk'],
            ['attendees', 'attendees_status_chk'],
            ['credentials', 'credentials_expiry_chk'],
            ['notifications', 'notifications_status_chk'],
        ] as [$table, $constraint]) {
            $this->assertCheckConstraintExists($table, $constraint);
        }
        $this->assertUniqueConstraintStartsWithTenantId('orders', 'orders_tenant_id_public_reference_unique');
        $this->assertUniqueConstraintStartsWithTenantId('credentials', 'credentials_one_active_unique');
        $this->assertUniqueConstraintStartsWithTenantId('notifications', 'notifications_intent_unique');
    }
}
