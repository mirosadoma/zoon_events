<?php

namespace App\Modules\Authorization\Contracts;

use App\Models\User;
use App\Modules\Tenancy\Domain\Context\TenantContext;

interface PermissionEvaluator
{
    public function hasPlatformPermission(User $user, string $permission): bool;

    public function hasTenantPermission(TenantContext $context, string $permission): bool;
}
