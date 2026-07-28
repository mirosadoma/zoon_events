<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistrationInvite extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'email',
        'email_ciphertext',
        'email_index',
        'name',
        'name_ciphertext',
        'encryption_key_id',
        'code',
        'is_active',
        'invite_status',
        'sent_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sent_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function resolvedEmail(PersonalDataGuard $guard): string
    {
        if (filled($this->email_ciphertext) && filled($this->encryption_key_id)) {
            return strtolower(trim($guard->decryptString(
                [
                    'key_id' => (string) $this->encryption_key_id,
                    'ciphertext' => (string) $this->email_ciphertext,
                ],
                $this->personalDataScope(),
            )));
        }

        return strtolower(trim((string) ($this->email ?? '')));
    }

    public function resolvedName(PersonalDataGuard $guard): ?string
    {
        if (filled($this->name_ciphertext) && filled($this->encryption_key_id)) {
            $name = trim($guard->decryptString(
                [
                    'key_id' => (string) $this->encryption_key_id,
                    'ciphertext' => (string) $this->name_ciphertext,
                ],
                $this->personalDataScope(),
            ));

            return $name !== '' ? $name : null;
        }

        $legacy = $this->name;

        return is_string($legacy) && trim($legacy) !== '' ? trim($legacy) : null;
    }

    private function personalDataScope(): string
    {
        return "{$this->tenant_id}:{$this->event_id}:invite";
    }
}
