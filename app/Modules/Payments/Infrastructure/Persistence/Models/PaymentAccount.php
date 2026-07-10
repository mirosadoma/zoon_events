<?php

namespace App\Modules\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentAccount extends Model
{
    protected $fillable = [
        'tenant_id', 'adapter_key', 'secret_reference', 'account_reference',
        'webhook_route_token_hash', 'mode', 'currency', 'status',
    ];
}
