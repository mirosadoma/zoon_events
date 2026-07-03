<?php

namespace App\Modules\Shared\Application\Idempotency;

use App\Exceptions\FoundationException;
use App\Modules\Shared\Infrastructure\Persistence\Models\IdempotencyRecord;
use Illuminate\Database\QueryException;

final class IdempotencyService
{
    public function acquire(string $scope, ?string $tenantId, string $actorId, string $operation, string $key, string $requestHash): IdempotencyRecord
    {
        $keyHash = hash('sha256', $key);
        $attributes = [
            'scope' => $scope,
            'scope_identifier' => $tenantId ?? 'platform',
            'actor_id' => $actorId,
            'operation' => $operation,
            'key_hash' => $keyHash,
        ];

        $record = IdempotencyRecord::query()->where($attributes)->first();
        if ($record instanceof IdempotencyRecord && $record->expires_at->isPast()) {
            $record->delete();
            $record = null;
        }

        if (! $record instanceof IdempotencyRecord) {
            try {
                return IdempotencyRecord::query()->create($attributes + [
                    'tenant_id' => $tenantId,
                    'request_hash' => $requestHash,
                    'state' => 'processing',
                    'expires_at' => now()->addHours((int) config('zonetec.idempotency_hours', 24)),
                ]);
            } catch (QueryException) {
                $record = IdempotencyRecord::query()->where($attributes)->firstOrFail();
            }
        }

        if (! hash_equals($record->request_hash, $requestHash)) {
            throw FoundationException::conflict('idempotency_conflict', 'The idempotency key was already used for a different request.');
        }

        if ($record->state === 'processing') {
            throw FoundationException::conflict('idempotency_in_progress', 'An equivalent request is still processing.');
        }

        return $record;
    }

    public function complete(IdempotencyRecord $record, int $status, mixed $body): void
    {
        $encoded = json_encode($body, JSON_THROW_ON_ERROR);
        $record->update([
            'state' => 'completed',
            'response_status' => $status,
            'response_body' => strlen($encoded) <= 65535 ? $body : ['resource_reference' => true],
        ]);
    }

    public function fail(IdempotencyRecord $record): void
    {
        $record->update(['state' => 'failed']);
    }
}
