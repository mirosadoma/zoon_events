<?php

namespace App\Modules\Credentials\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Credentials\Contracts\CredentialIssuer;
use App\Modules\Credentials\Domain\Events\CredentialLifecycleChanged;
use App\Modules\Credentials\Domain\IssuedCredential;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityReasonCode;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Shared\Http\Problems\Phase5Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class ReissueCredential
{
    public function __construct(
        private CredentialIssuer $issuer,
        private AuditWriter $audit,
    ) {}

    public function execute(TenantContext $context, string $eventId, string $credentialId, string $reason): IssuedCredential
    {
        return DB::transaction(function () use ($context, $eventId, $credentialId, $reason): IssuedCredential {
            $credential = Credential::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->findOrFail($credentialId);
            Credential::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->where('attendee_id', $credential->attendee_id)
                ->lockForUpdate()
                ->get();
            if (! in_array($credential->status, ['active', 'revoked'], true)) {
                throw Phase1Problem::make('credential_superseded');
            }
            $credential->forceFill(['status' => 'superseded'])->save();
            $replacement = $this->issuer->issue(
                $credential->tenant_id,
                $credential->event_id,
                $credential->attendee_id,
                $credential->ticket_type_id,
                $credential->expires_at,
            );
            if ($replacement === null) {
                throw Phase5Problem::make(IdentityReasonCode::NOT_VERIFIED);
            }
            $credential->forceFill(['superseded_by_id' => $replacement->id])->save();
            $this->audit->writeTenant(
                'credential.reissued',
                'succeeded',
                $context,
                targetType: 'credential',
                targetId: $credential->id,
                metadata: ['event_id' => $eventId, 'replacement_id' => $replacement->id, 'reason' => $reason],
            );
            event(new CredentialLifecycleChanged(
                $context->tenant->id,
                $eventId,
                $credential->id,
                'reissued',
                $replacement->id,
            ));

            return $replacement;
        });
    }
}
