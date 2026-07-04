<?php

namespace App\Modules\Shared\Application\DataProtection;

use InvalidArgumentException;

final readonly class PersonalDataCipher
{
    /** @param array<string,string> $keyRing */
    public function __construct(
        private string $currentKeyId,
        private array $keyRing,
    ) {
        $this->key($currentKeyId);
    }

    /** @return array{key_id:string,ciphertext:string} */
    public function encrypt(string $plaintext, string $scope): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $scope,
            $nonce,
            $this->key($this->currentKeyId),
        );

        return [
            'key_id' => $this->currentKeyId,
            'ciphertext' => sodium_bin2base64($nonce.$ciphertext, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
        ];
    }

    /** @param array{key_id:string,ciphertext:string} $encrypted */
    public function decrypt(array $encrypted, string $scope): string
    {
        try {
            $packed = sodium_base642bin($encrypted['ciphertext'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr($packed, $nonceLength),
                $scope,
                substr($packed, 0, $nonceLength),
                $this->key($encrypted['key_id']),
            );
        } catch (\Throwable) {
            throw new InvalidArgumentException('Encrypted personal data is invalid.');
        }

        if (! is_string($plaintext)) {
            throw new InvalidArgumentException('Encrypted personal data is invalid.');
        }

        return $plaintext;
    }

    private function key(string $keyId): string
    {
        $encoded = $this->keyRing[$keyId] ?? null;
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (! is_string($key) || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new InvalidArgumentException('Personal data key is unavailable.');
        }

        return $key;
    }
}
