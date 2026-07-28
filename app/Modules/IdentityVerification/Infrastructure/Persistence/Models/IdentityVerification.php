<?php

namespace App\Modules\IdentityVerification\Infrastructure\Persistence\Models;

use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
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
        'verified_name_ciphertext',
        'verified_nationality',
        'verified_nationality_ciphertext',
        'encryption_key_id',
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

    public function resolvedVerifiedName(PersonalDataGuard $guard): ?string
    {
        if (filled($this->verified_name_ciphertext) && filled($this->encryption_key_id)) {
            $name = trim($guard->decryptString(
                [
                    'key_id' => (string) $this->encryption_key_id,
                    'ciphertext' => (string) $this->verified_name_ciphertext,
                ],
                $this->personalDataScope(),
            ));

            return $name !== '' ? $name : null;
        }

        return is_string($this->verified_name) && $this->verified_name !== ''
            ? $this->verified_name
            : null;
    }

    public function resolvedVerifiedNationality(PersonalDataGuard $guard): ?string
    {
        if (filled($this->verified_nationality_ciphertext) && filled($this->encryption_key_id)) {
            $value = trim($guard->decryptString(
                [
                    'key_id' => (string) $this->encryption_key_id,
                    'ciphertext' => (string) $this->verified_nationality_ciphertext,
                ],
                $this->personalDataScope(),
            ));

            return $value !== '' ? $value : null;
        }

        return is_string($this->verified_nationality) && $this->verified_nationality !== ''
            ? $this->verified_nationality
            : null;
    }

    private function personalDataScope(): string
    {
        return "{$this->tenant_id}:{$this->event_id}:identity-verification";
    }
}
