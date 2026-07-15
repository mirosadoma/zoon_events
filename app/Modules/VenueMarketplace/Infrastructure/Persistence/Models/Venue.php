<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Venue extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'public_id', 'version'];

    protected $hidden = [
        'id', 'tenant_id', 'created_by_user_id', 'updated_by_user_id',
        'activated_at', 'suspended_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'publish_contact' => 'boolean',
            'version' => 'integer',
            'activated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(VenueAsset::class);
    }

    public function scopeForTenantPublicId(Builder $query, int|string $tenantId, string $publicId): Builder
    {
        return $query->forTenant((string) $tenantId)->where('venues.public_id', $publicId);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}
