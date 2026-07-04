<?php

namespace App\Modules\Payments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class PaymentAccount extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'adapter_key', 'secret_reference', 'account_reference',
        'webhook_route_token_hash', 'mode', 'currency', 'status',
    ];
}
