<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence\Models;

use Database\Factories\AcsLaneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AcsLane extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'zone_id',
        'name',
        'external_acs_lane_id',
        'gate_type',
        'access_direction',
        'is_admission_lane',
        'status',
        'health_status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_admission_lane' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(AcsZone::class, 'zone_id');
    }

    protected static function newFactory(): AcsLaneFactory
    {
        return AcsLaneFactory::new();
    }
}
