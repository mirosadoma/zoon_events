<?php

namespace App\Modules\Registration\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class RegistrationFormVersion extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'registration_form_id', 'version', 'status', 'fields',
        'schema_hash', 'privacy_notice_version', 'terms_version', 'published_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return ['fields' => 'array', 'published_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            if ($version->getOriginal('status') === 'published') {
                throw new LogicException('Published registration form versions are immutable.');
            }
        });
        self::deleting(function (self $version): void {
            if ($version->status === 'published') {
                throw new LogicException('Published registration form versions are immutable.');
            }
        });
    }
}
