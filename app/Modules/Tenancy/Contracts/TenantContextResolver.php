<?php

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Domain\Context\TenantContext;

interface TenantContextResolver
{
    public function current(): TenantContext;

    public function currentOrNull(): ?TenantContext;
}
