<?php

namespace App\Modules\Kiosk\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Kiosk extends Model
{
    protected $fillable = [
        'id', 'tenant_id', 'event_id', 'device_name', 'device_code',
        'location_label', 'status', 'printer_status', 'last_heartbeat_at',
        'confirmation_required', 'confirmation_code_hash', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'retired_at' => 'datetime',
            'confirmation_required' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(KioskSession::class);
    }
}
