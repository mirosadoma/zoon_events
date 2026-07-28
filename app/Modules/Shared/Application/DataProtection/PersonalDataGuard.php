<?php

namespace App\Modules\Shared\Application\DataProtection;

use JsonException;

/**
 * Toggle-aware wrapper around PersonalDataCipher + BlindIndex.
 *
 * When PERSONAL_DATA_ENCRYPTION_ENABLED=false (local/dev only), values are
 * stored with a plaintext marker so reads remain dual-compatible.
 */
final readonly class PersonalDataGuard
{
    public const PLAIN_KEY_ID = 'plain';

    private const PLAIN_PREFIX = 'plain:';

    public function __construct(
        private PersonalDataCipher $cipher,
        private BlindIndex $indexes,
        private bool $enabled,
        private string $currentKeyId,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function keyId(): string
    {
        return $this->enabled ? $this->currentKeyId : self::PLAIN_KEY_ID;
    }

    public function blindIndexKeyId(): string
    {
        return $this->indexes->keyId();
    }

    /** @return array{key_id:string,ciphertext:string} */
    public function encryptString(string $plaintext, string $scope): array
    {
        if (! $this->enabled) {
            return [
                'key_id' => self::PLAIN_KEY_ID,
                'ciphertext' => self::PLAIN_PREFIX.base64_encode($plaintext),
            ];
        }

        return $this->cipher->encrypt($plaintext, $scope);
    }

    /** @param array{key_id?:string,ciphertext:string} $encrypted */
    public function decryptString(array $encrypted, string $scope): string
    {
        $ciphertext = (string) ($encrypted['ciphertext'] ?? '');
        $keyId = (string) ($encrypted['key_id'] ?? '');

        if ($keyId === self::PLAIN_KEY_ID || str_starts_with($ciphertext, self::PLAIN_PREFIX)) {
            if (str_starts_with($ciphertext, self::PLAIN_PREFIX)) {
                $decoded = base64_decode(substr($ciphertext, strlen(self::PLAIN_PREFIX)), true);

                return is_string($decoded) ? $decoded : '';
            }

            return $ciphertext;
        }

        return $this->cipher->decrypt([
            'key_id' => $keyId,
            'ciphertext' => $ciphertext,
        ], $scope);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key_id:string,ciphertext:string}
     *
     * @throws JsonException
     */
    public function encryptJson(array $data, string $scope): array
    {
        return $this->encryptString(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $scope,
        );
    }

    /**
     * @param  array{key_id?:string,ciphertext:string}  $encrypted
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function decryptJson(array $encrypted, string $scope): array
    {
        $decoded = json_decode($this->decryptString($encrypted, $scope), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    public function emailIndex(string $value): string
    {
        return $this->indexes->email($value);
    }

    public function phoneIndex(string $value): string
    {
        return $this->indexes->phone($value);
    }
}
