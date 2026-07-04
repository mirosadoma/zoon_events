<?php

namespace App\Modules\Credentials\Application\Signing;

use App\Modules\Credentials\Contracts\SecretReferenceLoader;
use App\Modules\Credentials\Domain\CredentialKeyStatus;
use InvalidArgumentException;

final readonly class CredentialKeyRing
{
    /**
     * @param  array<string,array{status:string,public_key:string,private_key_reference?:string}>  $keys
     */
    public function __construct(
        private string $currentKeyId,
        private array $keys,
        private SecretReferenceLoader $secrets,
    ) {}

    /** @return array{key_id:string,signature:string} */
    public function sign(string $message): array
    {
        $key = $this->key($this->currentKeyId);
        if (CredentialKeyStatus::from($key['status']) !== CredentialKeyStatus::Active) {
            throw new InvalidArgumentException('Current credential signing key is not active.');
        }
        $reference = $key['private_key_reference'] ?? '';
        $secret = $this->decode($this->secrets->load($reference), SODIUM_CRYPTO_SIGN_SECRETKEYBYTES);

        return [
            'key_id' => $this->currentKeyId,
            'signature' => sodium_bin2base64(
                sodium_crypto_sign_detached($message, $secret),
                SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
            ),
        ];
    }

    public function verify(string $keyId, string $message, string $signature): bool
    {
        try {
            $key = $this->key($keyId);
            $status = CredentialKeyStatus::from($key['status']);
            if (! in_array($status, [CredentialKeyStatus::Active, CredentialKeyStatus::VerifyOnly], true)) {
                return false;
            }

            return sodium_crypto_sign_verify_detached(
                sodium_base642bin($signature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
                $message,
                $this->decode($key['public_key'], SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES),
            );
        } catch (\Throwable) {
            return false;
        }
    }

    public function isReady(): bool
    {
        try {
            $this->sign('readiness');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function currentKeyId(): string
    {
        return $this->currentKeyId;
    }

    /** @return array{status:string,public_key:string,private_key_reference?:string} */
    private function key(string $keyId): array
    {
        return $this->keys[$keyId] ?? throw new InvalidArgumentException('Credential signing key is unavailable.');
    }

    private function decode(string $encoded, int $length): string
    {
        $decoded = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        if (strlen($decoded) !== $length) {
            throw new InvalidArgumentException('Credential signing key is invalid.');
        }

        return $decoded;
    }
}
