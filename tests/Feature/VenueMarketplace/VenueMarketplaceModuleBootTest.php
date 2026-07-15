<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\VenueMarketplace\Providers\VenueMarketplaceServiceProvider;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-6')]
final class VenueMarketplaceModuleBootTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $this->assertArrayHasKey(
            VenueMarketplaceServiceProvider::class,
            $this->app->getLoadedProviders(),
        );
    }

    public function test_configuration_defaults_are_local_safe_and_disabled_by_default(): void
    {
        $this->assertFalse(config('marketplace.catalog.cache_enabled'));
        $this->assertSame(300, config('marketplace.catalog.cache_ttl_seconds'));
        $this->assertSame(100, config('marketplace.activation.batch_size'));
        $this->assertSame(100, config('marketplace.statement.batch_size'));
        $this->assertSame(500, config('marketplace.export.chunk_size'));
        $this->assertFalse(config('marketplace.observability.catalog_queries_enabled'));
        $this->assertFalse(config('marketplace.observability.provisioning_enabled'));
        $this->assertFalse(config('marketplace.observability.lifecycle_commands_enabled'));
    }

    public function test_phase6_route_group_is_registered_in_testing(): void
    {
        $this->assertTrue(Route::has('api.v1.tenant.marketplace.__probe.boot'));
    }

    public function test_unauthenticated_marketplace_request_is_rejected(): void
    {
        $this->getJson('/api/v1/tenant/marketplace/__probe/boot')
            ->assertUnauthorized();
    }
}
