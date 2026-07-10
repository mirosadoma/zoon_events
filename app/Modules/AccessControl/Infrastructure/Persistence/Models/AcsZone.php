<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence\Models;

use Database\Factories\AcsZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AcsZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'external_acs_zone_id',
        'anti_passback_enabled',
        'unavailability_mode',
        'emergency_egress_mode',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'anti_passback_enabled' => 'boolean',
        ];
    }

    public function lanes(): HasMany
    {
        return $this->hasMany(AcsLane::class, 'zone_id');
    }

    protected static function newFactory(): AcsZoneFactory
    {
        return AcsZoneFactory::new();
    }
}
