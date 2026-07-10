<?php

namespace App\Modules\Tenancy\Contracts\Events;

use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;

interface TenantAwareEvent
{
    public function tenantEventContext(): TenantJobContext;
}
