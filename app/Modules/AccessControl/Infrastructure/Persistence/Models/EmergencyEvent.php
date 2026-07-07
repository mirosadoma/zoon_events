<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence\Models;

use Database\Factories\EmergencyEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EmergencyEvent extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'zone_id',
        'signal_source',
        'behavior_applied',
        'raised_at',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'raised_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EmergencyEventFactory
    {
        return EmergencyEventFactory::new();
    }
}
