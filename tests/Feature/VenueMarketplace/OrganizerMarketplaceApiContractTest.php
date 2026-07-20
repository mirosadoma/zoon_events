<?php

namespace Tests\Feature\VenueMarketplace;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class OrganizerMarketplaceApiContractTest extends TestCase
{
    public function test_catalog_quote_and_rental_routes_match_the_v1_contract(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        foreach ([
            'api.v1.tenant.marketplace.catalog.index' => ['GET', 'api/v1/tenant/marketplace/catalog'],
            'api.v1.tenant.marketplace.catalog.show' => ['GET', 'api/v1/tenant/marketplace/catalog/{publication_public_id}'],
            'api.v1.tenant.marketplace.quotes.store' => ['POST', 'api/v1/tenant/marketplace/quotes'],
            'api.v1.tenant.marketplace.rentals.index' => ['GET', 'api/v1/tenant/marketplace/rentals'],
            'api.v1.tenant.marketplace.rentals.store' => ['POST', 'api/v1/tenant/marketplace/rentals'],
            'api.v1.tenant.marketplace.rentals.show' => ['GET', 'api/v1/tenant/marketplace/rentals/{rental_public_id}'],
        ] as $name => [$method, $uri]) {
            self::assertTrue($routes->has($name), "Missing route {$name}");
            self::assertContains($method, $routes[$name]->methods());
            self::assertSame($uri, $routes[$name]->uri());
            self::assertContains('auth:sanctum', $routes[$name]->gatherMiddleware());
            self::assertContains('permission:marketplace.manage,tenant', $routes[$name]->gatherMiddleware());
        }
        self::assertNotContains(
            'idempotency',
            $routes['api.v1.tenant.marketplace.quotes.store']->gatherMiddleware(),
        );
        self::assertContains(
            'idempotency',
            $routes['api.v1.tenant.marketplace.rentals.store']->gatherMiddleware(),
        );
    }
}
