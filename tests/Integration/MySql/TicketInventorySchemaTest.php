<?php

namespace Tests\Integration\MySql;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsTenantSchema;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('ticket-inventory')]
final class TicketInventorySchemaTest extends Phase1MySqlTestCase
{
    use AssertsTenantSchema;

    public function test_inventory_hold_and_price_tables_are_scoped_and_constrained(): void
    {
        $this->assertTenantOwnedTablesRequireTenantId(['ticket_inventories', 'inventory_holds', 'price_tiers']);
        $this->assertUniqueConstraintStartsWithTenantId('ticket_inventories', 'PRIMARY');
        $this->assertUniqueConstraintStartsWithTenantId('price_tiers', 'price_tiers_priority_unique');
        $this->assertUniqueConstraintStartsWithTenantId('inventory_holds', 'inventory_holds_scope_unique');
        foreach ([
            ['ticket_inventories', 'ticket_inventory_counters_chk'],
            ['price_tiers', 'price_tiers_selector_chk'],
            ['price_tiers', 'price_tiers_window_chk'],
            ['inventory_holds', 'inventory_holds_quantity_chk'],
        ] as [$table, $constraint]) {
            $this->assertCheckConstraintExists($table, $constraint);
        }
        $this->assertIndexExists('inventory_holds', 'inventory_holds_expiry_idx');
        $this->assertIndexExists('price_tiers', 'price_tiers_evaluation_idx');
    }
}
