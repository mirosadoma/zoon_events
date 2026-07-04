<?php

namespace App\Modules\Registration\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class RegistrationSubmission extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'event_id', 'form_version_id', 'submission_key_hash',
        'answers_ciphertext', 'encryption_key_id', 'consent_evidence', 'locale',
        'submitted_at', 'created_at',
    ];

    protected function casts(): array
    {
        return ['consent_evidence' => 'array', 'submitted_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Registration submissions are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Registration submissions are immutable.'));
    }
}
