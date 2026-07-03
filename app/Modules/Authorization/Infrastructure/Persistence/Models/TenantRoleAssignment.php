<?php

namespace App\Modules\Authorization\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantRoleAssignment extends Model implements TenantOwned
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['tenant_id', 'tenant_membership_id', 'tenant_role_id', 'granted_by_user_id', 'expires_at', 'revoked_at', 'revoked_by_user_id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'tenant_membership_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(TenantRole::class, 'tenant_role_id');
    }
}
