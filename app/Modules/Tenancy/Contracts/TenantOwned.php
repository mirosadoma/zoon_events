<?php

namespace App\Modules\Tenancy\Contracts;

interface TenantOwned
{
    public function tenantId(): string;
}
