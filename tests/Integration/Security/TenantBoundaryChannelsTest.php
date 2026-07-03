<?php

namespace Tests\Integration\Security;

use App\Modules\Tenancy\Application\Boundaries\TenantCacheKey;
use App\Modules\Tenancy\Application\Boundaries\TenantLogContext;
use App\Modules\Tenancy\Application\Boundaries\TenantStoragePath;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class TenantBoundaryChannelsTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_paths_keys_and_logs_are_derived_from_trusted_context(): void
    {
        $fixture = $this->createTenantMember();
        app(TenantContextStore::class)->bind($fixture['tenant'], $fixture['membership'], $fixture['user']);

        self::assertSame(
            "tenants/{$fixture['tenant']->id}/audit-exports/result.csv",
            app(TenantStoragePath::class)->make('audit-exports/result.csv'),
        );
        self::assertSame(
            "tenant:{$fixture['tenant']->id}:feature.flags",
            app(TenantCacheKey::class)->make('feature.flags'),
        );
        self::assertSame($fixture['tenant']->id, app(TenantLogContext::class)->current()['tenant_id']);
        self::assertArrayNotHasKey('email', app(TenantLogContext::class)->current());
    }

    public function test_storage_traversal_is_rejected(): void
    {
        $fixture = $this->createTenantMember();
        app(TenantContextStore::class)->bind($fixture['tenant'], $fixture['membership'], $fixture['user']);

        $this->expectException(InvalidArgumentException::class);
        app(TenantStoragePath::class)->make('../other-tenant/export.csv');
    }
}
