<?php

namespace Tests\Contract\Phase1;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('payments')]
final class PaidRegistrationApiTest extends TestCase
{
    public function test_public_payment_intent_route_matches_review_contract(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/public/orders/{public_reference}/payment-intents'
                && in_array('POST', $route->methods(), true),
        );

        self::assertNotNull($route);
        self::assertContains('throttle:public-checkout', $route->gatherMiddleware());
        self::assertNotContains('auth:sanctum', $route->gatherMiddleware());
        $review = file_get_contents(base_path('specs/002-registration-ticketing-credentials/contracts/openapi.yaml'));
        self::assertStringContainsString('operationId: createPublicPaymentIntent', $review);
    }
}
