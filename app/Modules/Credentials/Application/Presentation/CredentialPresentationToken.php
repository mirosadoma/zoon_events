<?php

namespace App\Modules\Credentials\Application\Presentation;

use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use InvalidArgumentException;

final readonly class CredentialPresentationToken
{
    public function __construct(private PersonalDataCipher $cipher) {}

    public function store(Credential $credential, string $token): void
    {
        $credential->forceFill([
            'presentation_token_ciphertext' => json_encode(
                $this->cipher->encrypt($token, "{$credential->tenant_id}:{$credential->event_id}:credential-presentation"),
                JSON_THROW_ON_ERROR,
            ),
        ])->save();
    }

    public function resolve(Credential $credential): string
    {
        if ($credential->presentation_token_ciphertext === null) {
            throw new InvalidArgumentException('Credential presentation token is unavailable.');
        }

        $encrypted = json_decode($credential->presentation_token_ciphertext, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($encrypted)) {
            throw new InvalidArgumentException('Credential presentation token is unavailable.');
        }

        return $this->cipher->decrypt($encrypted, "{$credential->tenant_id}:{$credential->event_id}:credential-presentation");
    }
}
