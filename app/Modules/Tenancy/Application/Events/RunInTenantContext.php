<?php

namespace App\Modules\Tenancy\Application\Events;

use App\Modules\Tenancy\Application\Queue\RestoreTenantContext;
use App\Modules\Tenancy\Contracts\Events\TenantAwareEvent;
use App\Modules\Tenancy\Contracts\Queue\TenantAwareJob;
use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;
use Closure;

final class RunInTenantContext
{
    public function __construct(private readonly RestoreTenantContext $restore) {}

    public function run(TenantAwareEvent $event, Closure $listener): mixed
    {
        $job = new readonly class($event) implements TenantAwareJob
        {
            public function __construct(private TenantAwareEvent $event) {}

            public function tenantJobContext(): TenantJobContext
            {
                return $this->event->tenantEventContext();
            }
        };

        return $this->restore->handle($job, fn () => $listener($event));
    }
}
