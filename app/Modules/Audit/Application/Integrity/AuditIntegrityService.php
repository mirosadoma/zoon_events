<?php

namespace App\Modules\Audit\Application\Integrity;

use RuntimeException;

final class AuditIntegrityService
{
    public function __construct(private readonly CanonicalAuditPayload $canonical) {}

    /** @param array<string, mixed> $payload */
    public function sign(array $payload, ?string $keyId = null): string
    {
        $id = $keyId ?? (string) config('audit.current_key_id');
        $key = $this->key($id);

        return hash_hmac('sha256', $this->canonical->serialize($payload), $key);
    }

    /** @param array<string, mixed> $payload */
    public function verify(array $payload, string $keyId, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->sign($payload, $keyId));
    }

    private function key(string $keyId): string
    {
        $key = config("audit.key_ring.{$keyId}");

        if (! is_string($key) || strlen($key) < 16) {
            throw new RuntimeException("Audit integrity key [{$keyId}] is unavailable or invalid.");
        }

        return $key;
    }
}
