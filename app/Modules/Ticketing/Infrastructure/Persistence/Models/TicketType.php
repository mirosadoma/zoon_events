<?php

namespace App\Modules\Ticketing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class TicketType extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'event_id', 'code', 'name_en', 'name_ar', 'description_en',
        'description_ar', 'attendee_type', 'base_price_minor', 'currency',
        'sale_starts_at', 'sale_ends_at', 'status', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'base_price_minor' => 'integer',
            'sale_starts_at' => 'immutable_datetime',
            'sale_ends_at' => 'immutable_datetime',
        ];
    }
}
