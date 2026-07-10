<?php

namespace App\Modules\Ticketing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class InventoryHold extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'ticket_type_id', 'order_id', 'quantity',
        'quoted_price_minor', 'currency', 'price_tier_id', 'status', 'expires_at',
        'released_reason_code',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quoted_price_minor' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
