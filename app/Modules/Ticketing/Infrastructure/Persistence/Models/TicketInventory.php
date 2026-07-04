<?php

namespace App\Modules\Ticketing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class TicketInventory extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'tenant_id', 'event_id', 'ticket_type_id', 'capacity', 'held_quantity',
        'sold_quantity', 'version',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'held_quantity' => 'integer',
            'sold_quantity' => 'integer',
            'version' => 'integer',
        ];
    }

    public function remaining(): int
    {
        return $this->capacity - $this->held_quantity - $this->sold_quantity;
    }

    protected function setKeysForSelectQuery($query)
    {
        return $this->scopeToCompositeKey($query);
    }

    protected function setKeysForSaveQuery($query)
    {
        return $this->scopeToCompositeKey($query);
    }

    private function scopeToCompositeKey($query)
    {
        foreach (['tenant_id', 'event_id', 'ticket_type_id'] as $key) {
            $query->where($key, '=', $this->getRawOriginal($key) ?? $this->getAttribute($key));
        }

        return $query;
    }
}
