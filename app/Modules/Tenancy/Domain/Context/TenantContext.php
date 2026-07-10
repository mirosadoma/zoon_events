<?php

namespace App\Modules\Tenancy\Domain\Context;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;

final readonly class TenantContext
{
    public function __construct(
        public Tenant $tenant,
        public TenantMembership $membership,
        public User $actor,
    ) {}
}
