<?php

namespace App\Modules\Ticketing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class PriceTier extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'event_id', 'ticket_type_id', 'name', 'price_minor', 'currency',
        'starts_at', 'ends_at', 'remaining_at_most', 'priority', 'status',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'remaining_at_most' => 'integer',
            'priority' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
