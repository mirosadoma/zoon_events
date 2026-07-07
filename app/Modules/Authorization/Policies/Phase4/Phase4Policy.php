<?php

namespace App\Modules\Authorization\Policies\Phase4;

use App\Models\User;
use App\Modules\Authorization\Contracts\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final readonly class Phase4Policy
{
    public const ABILITIES = [
        'configureAcs' => 'acs.configure',
        'viewGateEvents' => 'acs.events.view',
        'viewAcsHealth' => 'acs.health.view',
        'manageEmergency' => 'acs.emergency.manage',
    ];

    public function __construct(
        private PermissionEvaluator $permissions,
        private TenantContextStore $contextStore,
    ) {}

    public function allows(User $user, string $ability): bool
    {
        $permission = self::ABILITIES[$ability] ?? null;
        $context = $this->contextStore->currentOrNull();

        return $permission !== null
            && $context !== null
            && $context->actor->is($user)
            && $this->permissions->hasTenantPermission($context, $permission);
    }
}
