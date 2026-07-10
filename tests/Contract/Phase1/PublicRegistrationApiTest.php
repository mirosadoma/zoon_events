<?php

namespace Tests\Contract\Phase1;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('free-registration')]
final class PublicRegistrationApiTest extends TestCase
{
    public function test_public_registration_and_order_operations_match_review_contract(): void
    {
        foreach ([
            ['POST', 'api/v1/public/events/{event_slug}/registrations'],
            ['GET', 'api/v1/public/orders/{public_reference}'],
        ] as [$method, $uri]) {
            $route = collect(app('router')->getRoutes()->getRoutes())->first(
                fn (Route $route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true),
            );
            self::assertNotNull($route);
            self::assertNotContains('auth:sanctum', $route->gatherMiddleware());
            self::assertTrue(collect($route->gatherMiddleware())->contains(fn (string $middleware): bool => str_starts_with($middleware, 'throttle:')));
        }
    }

    public function test_authoritative_contract_contains_both_operation_ids(): void
    {
        $contract = file_get_contents(base_path('specs/001-project-foundation/contracts/openapi.yaml'));
        $review = file_get_contents(base_path('specs/002-registration-ticketing-credentials/contracts/openapi.yaml'));
        self::assertStringContainsString('/public/events/{event_slug}/registrations:', $contract);
        self::assertStringContainsString('/public/orders/{public_reference}:', $contract);
        self::assertStringContainsString('operationId: createPublicRegistration', $review);
        self::assertStringContainsString('operationId: getPublicOrder', $review);
    }
}
