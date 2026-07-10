<?php

namespace App\Modules\Credentials\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Credentials\Domain\Events\CredentialLifecycleChanged;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class RevokeCredential
{
    public function __construct(private AuditWriter $audit) {}

    public function execute(TenantContext $context, string $eventId, string $credentialId, string $reason): Credential
    {
        return DB::transaction(function () use ($context, $eventId, $credentialId, $reason): Credential {
            $credential = Credential::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->findOrFail($credentialId);
            if ($credential->status === 'revoked') {
                return $credential;
            }
            $credential->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by_user_id' => $context->actor->id,
                'revocation_reason' => $reason,
            ])->save();
            $this->audit->writeTenant(
                'credential.revoked',
                'succeeded',
                $context,
                targetType: 'credential',
                targetId: $credential->id,
                metadata: ['event_id' => $eventId, 'reason' => $reason],
            );
            event(new CredentialLifecycleChanged(
                $context->tenant->id,
                $eventId,
                $credential->id,
                'revoked',
            ));

            return $credential->refresh();
        });
    }
}
