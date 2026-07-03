<?php

namespace App\Modules\Tenancy\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TenantConfiguration extends Model
{
    use HasUlids;

    protected $fillable = ['tenant_id', 'key', 'schema_version', 'value', 'status', 'created_by_user_id', 'activated_by_user_id', 'activated_at'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'activated_at' => 'datetime',
        ];
    }
}
