<?php

namespace App\Modules\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentWebhookReceipt extends Model
{
    protected $fillable = [
        'payment_account_id', 'provider_event_id', 'payload_digest', 'status',
        'reason_code', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
