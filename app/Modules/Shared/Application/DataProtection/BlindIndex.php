<?php

namespace App\Modules\Shared\Application\DataProtection;

use InvalidArgumentException;

final readonly class BlindIndex
{
    /** @param array<string,string> $keyRing */
    public function __construct(
        private string $currentKeyId,
        private array $keyRing,
    ) {
        if (! isset($keyRing[$currentKeyId]) || strlen($keyRing[$currentKeyId]) < 16) {
            throw new InvalidArgumentException('Blind-index key is unavailable.');
        }
    }

    public function email(string $value): string
    {
        return $this->digest(mb_strtolower(trim($value)));
    }

    public function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $this->digest($digits);
    }

    public function keyId(): string
    {
        return $this->currentKeyId;
    }

    private function digest(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, $this->keyRing[$this->currentKeyId]);
    }
}
