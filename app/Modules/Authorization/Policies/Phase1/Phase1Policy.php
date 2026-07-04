<?php

namespace App\Modules\Authorization\Policies\Phase1;

use App\Models\User;
use App\Modules\Authorization\Contracts\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final readonly class Phase1Policy
{
    public const ABILITIES = [
        'viewEvent' => 'event.view',
        'manageEvent' => 'event.manage',
        'publishEvent' => 'event.publish',
        'cancelEvent' => 'event.cancel',
        'reopenEvent' => 'event.reopen',
        'archiveEvent' => 'event.archive',
        'manageRegistration' => 'registration.manage',
        'manageTicketing' => 'ticketing.manage',
        'viewOrder' => 'order.view',
        'manageOrder' => 'order.manage',
        'refundPayment' => 'payment.refund',
        'viewAttendee' => 'attendee.view',
        'manageAttendee' => 'attendee.manage',
        'viewCredential' => 'credential.view',
        'validateCredential' => 'credential.validate',
        'revokeCredential' => 'credential.revoke',
        'reissueCredential' => 'credential.reissue',
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
