<?php

namespace Tests\Integration\Queue;

use App\Modules\Tenancy\Application\Events\RunInTenantContext;
use App\Modules\Tenancy\Contracts\Events\TenantAwareEvent;
use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class TenantEventContextTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_listener_runs_inside_restored_context_and_cleanup_follows(): void
    {
        $fixture = $this->createTenantMember();
        $event = new readonly class(new TenantJobContext($fixture['tenant']->id, $fixture['membership']->id, $fixture['user']->id)) implements TenantAwareEvent
        {
            public function __construct(private TenantJobContext $context) {}

            public function tenantEventContext(): TenantJobContext
            {
                return $this->context;
            }
        };
        $store = app(TenantContextStore::class);

        app(RunInTenantContext::class)->run($event, function () use ($store, $fixture): void {
            self::assertSame($fixture['tenant']->id, $store->current()->tenant->id);
        });

        self::assertNull($store->currentOrNull());
    }
}
