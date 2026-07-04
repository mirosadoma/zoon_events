<?php

namespace App\Modules\Credentials\Application\Validation;

use App\Modules\Credentials\Application\Signing\CanonicalCredentialToken;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
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
        if ($claims['exp'] <= time()) {
            throw Phase1Problem::make('credential_expired');
        }
        if (($expectedTenantId !== null && $claims['tid'] !== $expectedTenantId)
            || ($expectedEventId !== null && $claims['eid'] !== $expectedEventId)) {
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
        if ($credential->expires_at->isPast()) {
            throw Phase1Problem::make('credential_expired');
        }
        if ($credential->status !== 'active') {
            throw Phase1Problem::make('credential_'.$credential->status);
        }

        return ['credential_id' => $credential->id, 'status' => 'active', 'event_id' => $credential->event_id];
    }
}
