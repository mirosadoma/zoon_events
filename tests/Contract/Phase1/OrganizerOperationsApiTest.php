<?php

namespace Tests\Contract\Phase1;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('phase-1-organizer')]
final class OrganizerOperationsApiTest extends TestCase
{
    public function test_order_attendee_and_refund_routes_have_explicit_permissions(): void
    {
        $expected = [
            ['GET', 'api/v1/tenant/events/{event_id}/orders', 'permission:order.view,tenant'],
            ['GET', 'api/v1/tenant/events/{event_id}/attendees', 'permission:attendee.view,tenant'],
            ['PATCH', 'api/v1/tenant/events/{event_id}/attendees/{attendee_id}', 'permission:attendee.manage,tenant'],
            ['POST', 'api/v1/tenant/events/{event_id}/orders/{order_id}/refunds', 'permission:payment.refund,tenant'],
            ['POST', 'api/v1/tenant/events/{event_id}/orders/{order_id}/cancel', 'permission:order.manage,tenant'],
        ];
        foreach ($expected as [$method, $uri, $permission]) {
            $route = collect(app('router')->getRoutes()->getRoutes())->first(
                fn (Route $route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true),
            );
            self::assertNotNull($route);
            self::assertContains($permission, $route->gatherMiddleware());
            self::assertContains('tenant.context', $route->gatherMiddleware());
        }
    }
}
