<?php

namespace App\Modules\Tenancy\Infrastructure\Persistence\Models;

use App\Modules\Shared\Domain\LifecycleStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'status',
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
            'suspended_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
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
