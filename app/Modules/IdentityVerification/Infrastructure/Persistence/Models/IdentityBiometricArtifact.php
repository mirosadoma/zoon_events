<?php

namespace App\Modules\IdentityVerification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdentityBiometricArtifact extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'verification_id',
        'artifact_type',
        'storage_reference',
        'liveness_result',
        'retention_until',
        'created_at',
        'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'retention_until' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
        ];
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(IdentityVerification::class, 'verification_id');
    }
}
