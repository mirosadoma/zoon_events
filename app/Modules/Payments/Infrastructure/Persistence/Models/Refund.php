<?php

namespace App\Modules\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class Refund extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'order_id', 'payment_attempt_id', 'amount_minor',
        'currency', 'status', 'reason', 'requested_by_user_id', 'provider_refund_id',
        'idempotency_key_hash', 'last_reconciled_at',
    ];
}
