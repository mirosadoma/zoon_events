<?php

namespace App\Modules\Credentials\Application\Validation;

use App\Modules\Credentials\Application\Signing\CanonicalCredentialToken;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Problems\Phase1Problem;

final readonly class CredentialValidator
{
    public function __construct(private CanonicalCredentialToken $tokens) {}

    /** @return array{credential_id:string,status:string,event_id:string} */
    public function validate(string $token, ?string $expectedTenantId = null, ?string $expectedEventId = null): array
    {
        try {
            $claims = $this->tokens->verify($token);
        } catch (\Throwable) {
            throw Phase1Problem::make('credential_invalid');
        }
        if (($expectedTenantId !== null && (string) $claims['tid'] !== (string) $expectedTenantId)
            || ($expectedEventId !== null && (string) $claims['eid'] !== (string) $expectedEventId)) {
            throw Phase1Problem::make('credential_invalid');
        }
        $credential = Credential::query()
            ->where('tenant_id', $claims['tid'])
            ->where('event_id', $claims['eid'])
            ->where('token_digest', hash('sha256', $token))
            ->find($claims['cid']);
        if ($credential === null || ! hash_equals($credential->nonce_hash, hash('sha256', $claims['nonce']))) {
            throw Phase1Problem::make('credential_invalid');
        }
        $this->assertCredentialStillValid($credential, (int) $claims['exp']);
        if ($credential->status !== 'active') {
            throw Phase1Problem::make('credential_'.$credential->status);
        }

        return ['credential_id' => $credential->id, 'status' => 'active', 'event_id' => $credential->event_id];
    }

    /**
     * Validate a credential directly by its ID (no signed token required).
     * Used for look-up-based check-ins where no raw QR payload is available.
     * Returns the same shape as validate() and throws the same Phase1Problem codes.
     *
     * @return array{credential_id:string,status:string,event_id:string}
     */
    public function validateById(string $credentialId, string $tenantId, string $eventId): array
    {
        $credential = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($credentialId);

        if ($credential === null) {
            throw Phase1Problem::make('credential_invalid');
        }

        $this->assertCredentialStillValid($credential);
        if ($credential->status !== 'active') {
            throw Phase1Problem::make('credential_'.$credential->status);
        }

        return ['credential_id' => $credential->id, 'status' => 'active', 'event_id' => $credential->event_id];
    }

    private function assertCredentialStillValid(Credential $credential, ?int $tokenExp = null): void
    {
        $event = Event::query()
            ->where('tenant_id', $credential->tenant_id)
            ->find($credential->event_id);

        $effectiveExpiry = $credential->expires_at->getTimestamp();

        if ($tokenExp !== null) {
            $effectiveExpiry = max($effectiveExpiry, $tokenExp);
        }

        if ($event?->end_at !== null) {
            $effectiveExpiry = max($effectiveExpiry, $event->end_at->getTimestamp());
        }

        if ($effectiveExpiry <= time()) {
            throw Phase1Problem::make('credential_expired');
        }
    }
}
