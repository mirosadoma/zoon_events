<?php

namespace Tests\Integration\MySql;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsTenantSchema;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('payments')]
final class PaymentSchemaTest extends Phase1MySqlTestCase
{
    use AssertsTenantSchema;

    public function test_payment_tables_enforce_scope_deduplication_money_and_reconciliation_indexes(): void
    {
        $this->assertTenantOwnedTablesRequireTenantId(['payment_accounts', 'payment_attempts', 'refunds']);
        $this->assertUniqueConstraintStartsWithTenantId('payment_accounts', 'payment_accounts_routing_unique');
        $this->assertUniqueConstraintStartsWithTenantId('payment_attempts', 'payment_attempts_number_unique');
        $this->assertUniqueConstraintStartsWithTenantId('refunds', 'refunds_scope_unique');
        $this->assertCheckConstraintExists('payment_attempts', 'payment_attempts_money_chk');
        $this->assertCheckConstraintExists('refunds', 'refunds_amount_chk');
        $this->assertIndexExists('payment_attempts', 'payment_attempts_reconcile_index');
        $this->assertIndexExists('payment_webhook_receipts', 'payment_webhooks_processing_index');
    }
}
