<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class DeactivateRegistrationInvite
{
    public function __construct(
        private AuditWriter $audit,
    ) {}

    public function execute(TenantContext $context, string $eventId, string $inviteId): EventRegistrationInvite
    {
        return DB::transaction(function () use ($context, $eventId, $inviteId): EventRegistrationInvite {
            $invite = EventRegistrationInvite::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->findOrFail($inviteId);

            $invite->forceFill([
                'is_active' => false,
            ])->save();

            $this->audit->writeTenant(
                'event.invite.deactivated',
                'succeeded',
                $context,
                targetType: 'event_registration_invite',
                targetId: $invite->id,
                metadata: ['event_id' => $eventId],
            );

            return $invite->refresh();
        });
    }
}
