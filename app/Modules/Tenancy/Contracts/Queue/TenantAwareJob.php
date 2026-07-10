<?php

namespace App\Modules\Tenancy\Contracts\Queue;

interface TenantAwareJob
{
    public function tenantJobContext(): TenantJobContext;
}
