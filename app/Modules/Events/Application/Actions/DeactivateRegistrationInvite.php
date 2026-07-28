<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class DeactivateRegistrationInvite
{
    public function __construct(
        private AuditWriter $audit,
        private PersonalDataGuard $guard,
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

    public function markConsumed(string $eventId, string $email, ?string $inviteCode = null): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }

        $query = EventRegistrationInvite::query()
            ->where('event_id', $eventId)
            ->where(function ($builder) use ($email): void {
                $builder->where('email_index', $this->guard->emailIndex($email))
                    ->orWhere('email', $email);
            });

        if (is_string($inviteCode) && $inviteCode !== '') {
            $query->where('code', $inviteCode);
        }

        $query->update([
            'is_active' => false,
            'used_at' => now(),
            'invite_status' => 'registered',
        ]);
    }
}
