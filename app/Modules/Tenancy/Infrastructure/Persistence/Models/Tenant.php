<?php

namespace App\Modules\Tenancy\Infrastructure\Persistence\Models;

use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Domain\OrganizationType;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'is_active',
        'organization_type',
        'default_locale',
        'timezone',
        'data_residency_region',
        'policy_profile',
        'created_by_user_id',
        'suspended_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'policy_profile' => 'array',
            'status' => LifecycleStatus::class,
            'organization_type' => OrganizationType::class,
            'is_active' => 'boolean',
            'suspended_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active && $this->status === LifecycleStatus::Active;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
