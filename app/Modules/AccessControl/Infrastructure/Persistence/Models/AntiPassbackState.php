<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence\Models;

use Database\Factories\AntiPassbackStateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class AntiPassbackState extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'credential_id',
        'zone_id',
        'state',
        'last_access_event_id',
        'last_transition_at',
    ];

    protected function casts(): array
    {
        return [
            'last_transition_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AntiPassbackStateFactory
    {
        return AntiPassbackStateFactory::new();
    }
}
