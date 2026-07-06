<?php

namespace App\Modules\Authorization\Policies\Phase2;

use App\Models\User;
use App\Modules\Authorization\Contracts\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final readonly class Phase2Policy
{
    public const ABILITIES = [
        'viewWalletPass' => 'wallet.pass.view',
        'generateWalletPass' => 'wallet.pass.generate',
        'manageWalletPass' => 'wallet.pass.manage',
        'submitScan' => 'checkin.scan.submit',
        'overrideScan' => 'checkin.scan.override',
        'viewCheckInDashboard' => 'checkin.dashboard.view',
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
