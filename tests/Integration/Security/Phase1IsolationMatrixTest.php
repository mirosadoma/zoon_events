<?php

namespace Tests\Integration\Security;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('phase-1-isolation')]
final class Phase1IsolationMatrixTest extends TestCase
{
    public function test_every_organizer_route_requires_trusted_tenant_context_and_permission(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/tenant/events/'));
        self::assertNotEmpty($routes);
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            self::assertContains('tenant.context', $middleware, $route->uri());
            self::assertTrue(collect($middleware)->contains(fn ($item): bool => str_starts_with($item, 'permission:')), $route->uri());
        }
    }

    public function test_public_and_callback_routes_never_accept_tenant_header_as_context(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/public/')
                || str_starts_with($route->uri(), 'api/v1/webhooks/'));
        foreach ($routes as $route) {
            self::assertNotContains('tenant.context', $route->gatherMiddleware(), $route->uri());
        }
    }
}
