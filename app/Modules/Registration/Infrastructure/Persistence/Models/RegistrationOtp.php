<?php

namespace App\Modules\Registration\Infrastructure\Persistence\Models;

use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
use Illuminate\Database\Eloquent\Model;

class RegistrationOtp extends Model
{
    protected $fillable = [
        'token',
        'tenant_id',
        'event_id',
        'email',
        'email_ciphertext',
        'email_index',
        'payload',
        'payload_ciphertext',
        'encryption_key_id',
        'code_hash',
        'expires_at',
        'attempts',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function resolvedEmail(PersonalDataGuard $guard): string
    {
        if (filled($this->email_ciphertext) && filled($this->encryption_key_id)) {
            return $guard->decryptString(
                [
                    'key_id' => (string) $this->encryption_key_id,
                    'ciphertext' => (string) $this->email_ciphertext,
                ],
                $this->personalDataScope(),
            );
        }

        return (string) ($this->email ?? '');
    }

    /** @return array<string, mixed> */
    public function resolvedPayload(PersonalDataGuard $guard): array
    {
        if (filled($this->payload_ciphertext) && filled($this->encryption_key_id)) {
            return $guard->decryptJson(
                [
                    'key_id' => (string) $this->encryption_key_id,
                    'ciphertext' => (string) $this->payload_ciphertext,
                ],
                $this->personalDataScope(),
            );
        }

        return is_array($this->payload) ? $this->payload : [];
    }

    private function personalDataScope(): string
    {
        return "{$this->tenant_id}:{$this->event_id}:registration-otp";
    }
}
