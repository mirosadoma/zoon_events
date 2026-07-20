<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'email',
        'token',
        'code_hash',
        'expires_at',
        'attempts',
        'verified_at',
        'reset_token',
        'reset_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
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

    public function resetTokenIsValid(): bool
    {
        return $this->reset_token !== null
            && $this->reset_token_expires_at !== null
            && $this->reset_token_expires_at->isFuture();
    }
}
