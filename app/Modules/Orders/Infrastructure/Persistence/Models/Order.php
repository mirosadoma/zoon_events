<?php

namespace App\Modules\Orders\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Order extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'event_id', 'public_reference', 'access_token_hash', 'status',
        'buyer_name_ciphertext', 'buyer_email_ciphertext', 'buyer_phone_ciphertext',
        'buyer_email_index', 'buyer_phone_index', 'encryption_key_id', 'subtotal_minor',
        'tax_minor', 'fees_minor', 'total_minor', 'currency', 'inventory_hold_id',
        'submission_id', 'fulfillment_payload_ciphertext', 'fulfillment_encryption_key_id',
        'credential_expires_at', 'locale', 'paid_at', 'cancelled_at', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'credential_expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $order): void {
            foreach (['subtotal_minor', 'tax_minor', 'fees_minor', 'total_minor', 'currency'] as $field) {
                if ($order->isDirty($field)) {
                    throw new LogicException('Order money snapshots are immutable.');
                }
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
