<?php

namespace App\Modules\Tenancy\Infrastructure\Persistence\Models;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRoleAssignment;
use App\Modules\Shared\Domain\LifecycleStatus;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantMembership extends Model
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'created_by_user_id',
        'suspended_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LifecycleStatus::class,
            'suspended_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TenantRoleAssignment::class);
    }

    protected static function newFactory(): TenantMembershipFactory
    {
        return TenantMembershipFactory::new();
    }
}
