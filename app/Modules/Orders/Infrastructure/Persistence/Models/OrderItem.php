<?php

namespace App\Modules\Orders\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class OrderItem extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'order_id', 'ticket_type_id', 'attendee_id',
        'quantity', 'unit_price_minor', 'tax_minor', 'fees_minor', 'total_minor',
        'currency', 'price_tier_id', 'ticket_name_snapshot',
    ];

    protected function casts(): array
    {
        return ['ticket_name_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $item): void {
            if ($item->isDirty(array_diff($item->getFillable(), ['attendee_id']))) {
                throw new LogicException('Order item snapshots are immutable.');
            }
        });
    }
}
