<?php

namespace App\Modules\Credentials\Application;

use App\Modules\Credentials\Application\Presentation\CredentialPresentationToken;
use App\Modules\Credentials\Application\Signing\CanonicalCredentialToken;
use App\Modules\Credentials\Application\Signing\CredentialKeyRing;
use App\Modules\Credentials\Contracts\CredentialIssuer;
use App\Modules\Credentials\Domain\IssuedCredential;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Credentials\Infrastructure\Persistence\Models\CredentialSigningKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class CredentialIssuerService implements CredentialIssuer
{
    public function __construct(
        private CanonicalCredentialToken $tokens,
        private CredentialKeyRing $keys,
        private CredentialPresentationToken $presentationTokens,
    ) {}

    public function issue(string $tenantId, string $eventId, string $attendeeId, string $ticketTypeId, CarbonImmutable $expiresAt): IssuedCredential
    {
        $issuedAt = CarbonImmutable::now();
        $nonce = sodium_bin2base64(random_bytes(16), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $key = config('credentials.key_ring.'.$this->keys->currentKeyId());
        CredentialSigningKey::query()->firstOrCreate(
            ['key_id' => $this->keys->currentKeyId()],
            [
                'public_key' => $key['public_key'],
                'private_key_reference' => $key['private_key_reference'] ?? null,
                'status' => $key['status'],
                'not_before' => now(),
            ],
        );
        $credential = Credential::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'attendee_id' => $attendeeId,
            'ticket_type_id' => $ticketTypeId,
            'status' => 'active',
            'token_version' => 'zt1',
            'key_id' => $this->keys->currentKeyId(),
            'nonce_hash' => hash('sha256', $nonce),
            'token_digest' => hash('sha256', (string) Str::ulid()),
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ]);

        $id = (string) $credential->id;
        $token = $this->tokens->issue([
            'cid' => $id,
            'eid' => $eventId,
            'exp' => $expiresAt->getTimestamp(),
            'iat' => $issuedAt->getTimestamp(),
            'nonce' => $nonce,
            'tid' => $tenantId,
        ]);

        $credential->forceFill(['token_digest' => hash('sha256', $token)])->save();
        $this->presentationTokens->store($credential, $token);

        return new IssuedCredential($id, $token, $expiresAt);
    }
}
