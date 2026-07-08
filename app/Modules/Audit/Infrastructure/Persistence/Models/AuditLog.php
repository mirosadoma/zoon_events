<?php

namespace App\Modules\Audit\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Audit records are append-only and cannot be updated.');
        });

        static::deleting(static function (): never {
            throw new \LogicException('Audit records are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'change_summary' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return array<string, mixed> */
    public function integrityPayload(): array
    {
        return [
            'scope' => $this->scope,
            'tenant_id' => $this->tenant_id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'outcome' => $this->outcome,
            'reason_code' => $this->reason_code,
            'channel' => $this->channel,
            'correlation_id' => $this->correlation_id,
            'request_id' => $this->request_id,
            'source_fingerprint' => $this->source_fingerprint,
            'client_fingerprint' => $this->client_fingerprint,
            'change_summary' => $this->change_summary,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at,
            'integrity_algorithm' => $this->integrity_algorithm,
            'integrity_key_id' => $this->integrity_key_id,
        ];
    }
}
