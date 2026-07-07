<?php

namespace App\Modules\Authorization\Policies\Phase3;

use App\Models\User;
use App\Modules\Authorization\Contracts\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final readonly class Phase3Policy
{
    public const ABILITIES = [
        'manageKiosk'            => 'kiosk.manage',
        'viewKioskHealth'        => 'kiosk.health.view',
        'performDeskCheckIn'     => 'checkin.desk.perform',
        'printBadge'             => 'badge.print',
        'reprintBadge'           => 'badge.reprint',
        'manageBadgeTemplate'    => 'badge.template.manage',
        'registerWalkUpAttendee' => 'attendee.walkup.register',
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
