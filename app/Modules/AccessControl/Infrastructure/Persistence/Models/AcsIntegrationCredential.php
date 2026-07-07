<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class AcsIntegrationCredential extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'secret_hash',
        'capabilities',
        'status',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
