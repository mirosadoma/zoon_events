<?php

namespace App\Modules\Shared\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class IdempotencyRecord extends Model
{
    protected $fillable = [
        'scope',
        'tenant_id',
        'scope_identifier',
        'actor_id',
        'operation',
        'key_hash',
        'request_hash',
        'state',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return ['response_body' => 'array', 'expires_at' => 'immutable_datetime'];
    }
}
