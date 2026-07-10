<?php

namespace App\Modules\IdentityVerification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class IdentityVerification extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'attendee_id',
        'consent_id',
        'method',
        'status',
        'provider',
        'provider_reference',
        'verified_name',
        'verified_nationality',
        'verified_at',
        'manual_review_by',
        'manual_review_at',
        'rejection_reason',
        'retention_until',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
            'manual_review_at' => 'immutable_datetime',
            'retention_until' => 'immutable_datetime',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Attendees\\Infrastructure\\Persistence\\Models\\Attendee', 'attendee_id');
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(IdentityConsent::class, 'consent_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(IdentityBiometricArtifact::class, 'verification_id');
    }
}
