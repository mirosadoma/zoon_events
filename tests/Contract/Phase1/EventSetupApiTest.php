<?php

namespace Tests\Contract\Phase1;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class EventSetupApiTest extends TestCase
{
    public function test_all_documented_event_setup_operations_are_routed_with_explicit_permissions(): void
    {
        $expected = [
            'GET' => [
                'api/v1/tenant/events',
                'api/v1/tenant/events/{event_id}',
                'api/v1/tenant/events/{event_id}/ticket-types',
            ],
            'POST' => [
                'api/v1/tenant/events',
                'api/v1/tenant/events/{event_id}/publish',
                'api/v1/tenant/events/{event_id}/cancel',
                'api/v1/tenant/events/{event_id}/reopen',
                'api/v1/tenant/events/{event_id}/archive',
                'api/v1/tenant/events/{event_id}/registration-form/publish',
                'api/v1/tenant/events/{event_id}/ticket-types',
            ],
            'PATCH' => [
                'api/v1/tenant/events/{event_id}',
                'api/v1/tenant/events/{event_id}/ticket-types/{ticket_type_id}',
            ],
            'PUT' => ['api/v1/tenant/events/{event_id}/registration-form'],
        ];

        foreach ($expected as $method => $uris) {
            foreach ($uris as $uri) {
                $route = collect(app('router')->getRoutes()->getRoutes())
                    ->first(fn (Route $route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true));
                self::assertNotNull($route, "{$method} {$uri} is not routed.");
                self::assertContains('auth:sanctum', $route->gatherMiddleware());
                self::assertTrue(
                    collect($route->gatherMiddleware())->contains(fn (string $middleware): bool => str_starts_with($middleware, 'permission:')),
                    "{$method} {$uri} lacks an explicit permission.",
                );
            }
        }
    }

    public function test_public_event_contract_routes_are_anonymous_and_host_context_bound(): void
    {
        foreach ([
            'api/v1/public/events/{event_slug}',
            'api/v1/public/events/{event_slug}/registration-form',
        ] as $uri) {
            $route = collect(app('router')->getRoutes()->getRoutes())->first(fn (Route $route): bool => $route->uri() === $uri);
            self::assertNotNull($route);
            self::assertContains('public.event.context', $route->gatherMiddleware());
            self::assertNotContains('auth:sanctum', $route->gatherMiddleware());
        }
    }
}
