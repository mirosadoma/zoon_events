<?php

namespace Tests\Integration\Queue;

use App\Exceptions\FoundationException;
use App\Modules\Tenancy\Application\Queue\RestoreTenantContext;
use App\Modules\Tenancy\Contracts\Queue\TenantAwareJob;
use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class TenantJobContextTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_job_restores_trusted_context_and_always_clears_it(): void
    {
        $fixture = $this->createTenantMember();
        $serialized = new TenantJobContext($fixture['tenant']->id, $fixture['membership']->id, $fixture['user']->id, 'test');
        $job = new class($serialized) implements TenantAwareJob
        {
            public function __construct(private readonly TenantJobContext $context) {}

            public function tenantJobContext(): TenantJobContext
            {
                return $this->context;
            }
        };
        $store = app(TenantContextStore::class);

        app(RestoreTenantContext::class)->handle($job, function () use ($store, $fixture): void {
            self::assertSame($fixture['tenant']->id, $store->current()->tenant->id);
        });

        self::assertNull($store->currentOrNull());
    }

    public function test_inactive_tenant_is_rejected_and_context_remains_clear(): void
    {
        $fixture = $this->createTenantMember();
        $fixture['tenant']->update(['status' => 'suspended', 'suspended_at' => now()]);
        $job = new class(new TenantJobContext($fixture['tenant']->id, $fixture['membership']->id, $fixture['user']->id)) implements TenantAwareJob
        {
            public function __construct(private readonly TenantJobContext $context) {}

            public function tenantJobContext(): TenantJobContext
            {
                return $this->context;
            }
        };

        try {
            app(RestoreTenantContext::class)->handle($job, static fn (): null => null);
            self::fail('Inactive tenant context was accepted.');
        } catch (FoundationException $exception) {
            self::assertSame('tenant_context_invalid', $exception->problemCode);
        } finally {
            self::assertNull(app(TenantContextStore::class)->currentOrNull());
        }
    }
}
