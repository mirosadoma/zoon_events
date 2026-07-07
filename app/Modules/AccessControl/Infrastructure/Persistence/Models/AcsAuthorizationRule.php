<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence\Models;

use Database\Factories\AcsAuthorizationRuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class AcsAuthorizationRule extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'ticket_type_id',
        'attendee_type',
        'zone_id',
        'lane_id',
        'access_direction',
        'anti_passback_exempt',
        'valid_from',
        'valid_until',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'anti_passback_exempt' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    protected static function newFactory(): AcsAuthorizationRuleFactory
    {
        return AcsAuthorizationRuleFactory::new();
    }
}
