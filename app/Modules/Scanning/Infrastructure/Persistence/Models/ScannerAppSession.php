<?php

namespace App\Modules\Scanning\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;

final class ScannerAppSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_zone_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EventZone::class, 'event_zone_id');
    }
}
