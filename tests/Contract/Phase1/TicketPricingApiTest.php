<?php

namespace Tests\Contract\Phase1;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('price-tiers')]
final class TicketPricingApiTest extends TestCase
{
    public function test_documented_price_tier_operation_is_routed_and_protected(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/tenant/events/{event_id}/ticket-types/{ticket_type_id}/price-tiers'
                && in_array('POST', $route->methods(), true),
        );

        self::assertNotNull($route);
        self::assertContains('permission:ticketing.manage,tenant', $route->gatherMiddleware());
        self::assertContains('idempotency', $route->gatherMiddleware());
    }
}
