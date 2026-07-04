<?php

namespace App\Modules\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class PaymentAttempt extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'event_id', 'order_id', 'payment_account_id', 'attempt_number',
        'provider_payment_id', 'provider_payment_id_hash', 'idempotency_key_hash',
        'status', 'requested_minor', 'captured_minor', 'refunded_minor', 'currency',
        'provider_reason_code', 'last_reconciled_at', 'next_reconcile_at',
    ];

    protected function casts(): array
    {
        return ['last_reconciled_at' => 'immutable_datetime', 'next_reconcile_at' => 'immutable_datetime'];
    }
}
