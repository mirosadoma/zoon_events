<?php

namespace Tests\Integration\Security;

use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('price-tiers')]
final class TicketPricingIsolationTest extends TestCase
{
    public function test_price_tier_model_does_not_allow_scope_reassignment_via_guarded_fill(): void
    {
        $tier = new PriceTier;
        $tier->fill([
            'tenant_id' => '01SYNTHETICTENANT000000000',
            'event_id' => '01SYNTHETICEVENT0000000000',
            'ticket_type_id' => '01SYNTHETICTICKET000000000',
            'name' => 'Early',
            'price_minor' => 100,
            'currency' => 'SAR',
            'priority' => 1,
        ]);

        self::assertSame('01SYNTHETICTENANT000000000', $tier->tenant_id);
        self::assertSame('01SYNTHETICEVENT0000000000', $tier->event_id);
        self::assertSame('01SYNTHETICTICKET000000000', $tier->ticket_type_id);
    }
}
