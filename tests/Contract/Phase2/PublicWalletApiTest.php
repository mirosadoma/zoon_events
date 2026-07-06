<?php

namespace Tests\Contract\Phase2;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class PublicWalletApiTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_public_wallet_routes_match_the_review_contract(): void
    {
        foreach ([
            ['GET', 'api/v1/public/orders/{public_reference}/wallet-passes/apple', 'getApplePass'],
            ['GET', 'api/v1/public/orders/{public_reference}/wallet-passes/google', 'getGoogleWalletSaveLink'],
        ] as [$method, $uri, $operationId]) {
            $route = collect(app('router')->getRoutes()->getRoutes())->first(
                fn (Route $route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true),
            );
            self::assertNotNull($route, "{$method} {$uri}");
            self::assertNotContains('auth:sanctum', $route->gatherMiddleware());
            self::assertTrue(collect($route->gatherMiddleware())->contains(fn (string $middleware): bool => str_starts_with($middleware, 'throttle:')));
        }

        $contract = file_get_contents(base_path('specs/001-project-foundation/contracts/openapi.yaml'));
        $review = file_get_contents(base_path('specs/003-wallet-passes-scanning/contracts/openapi.yaml'));
        self::assertStringContainsString('/public/orders/{public_reference}/wallet-passes/apple:', $contract);
        self::assertStringContainsString('/public/orders/{public_reference}/wallet-passes/google:', $contract);
        self::assertStringContainsString('operationId: getApplePass', $review);
        self::assertStringContainsString('operationId: getGoogleWalletSaveLink', $review);
    }

    public function test_wallet_pass_endpoints_return_documented_problem_responses_for_invalid_context(): void
    {
        $fixture = $this->createRegistrationFixture();
        $created = $this->withHeader('Idempotency-Key', 'wallet-api-contract')
            ->postJson("http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations", $this->registrationPayload($fixture))
            ->assertCreated();
        $reference = $created->json('data.public_reference');
        $token = $created->json('data.access_token');

        $this->withHeader('X-Order-Access-Token', $token)
            ->get("http://register.example.test/api/v1/public/orders/{$reference}/wallet-passes/apple")
            ->assertOk();

        $this->withHeader('X-Order-Access-Token', 'wrong')
            ->get("http://register.example.test/api/v1/public/orders/{$reference}/wallet-passes/google")
            ->assertNotFound()
            ->assertJsonPath('code', 'resource_not_found');

        $this->withHeader('X-Order-Access-Token', $token)
            ->get('http://register.example.test/api/v1/public/orders/ord_missing/wallet-passes/apple')
            ->assertNotFound()
            ->assertJsonPath('code', 'resource_not_found');
    }
}
