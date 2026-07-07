<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence\Models;

use Database\Factories\AccessEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class AccessEvent extends Model
{
    use HasFactory;
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_type',
        'credential_id',
        'zone_id',
        'lane_id',
        'direction',
        'decision',
        'reason_code',
        'source',
        'external_event_id',
        'scan_event_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AccessEventFactory
    {
        return AccessEventFactory::new();
    }
}
