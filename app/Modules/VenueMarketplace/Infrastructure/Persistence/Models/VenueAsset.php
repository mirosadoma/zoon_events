<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class VenueAsset extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'venue_id', 'public_id', 'version'];

    protected $hidden = ['id', 'tenant_id', 'venue_id', 'created_by_user_id', 'updated_by_user_id'];

    protected function casts(): array
    {
        return [
            'asset_type' => 'string',
            'capabilities' => 'array',
            'capacity_per_minute' => 'integer',
            'operational_status' => 'string',
            'pricing_model' => 'string',
            'price_minor' => 'integer',
            'currency' => 'string',
            'version' => 'integer',
            'retired_at' => 'immutable_datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function binding(): HasOne
    {
        return $this->hasOne(VenueAssetBinding::class);
    }

    public function availabilityWindows(): HasMany
    {
        return $this->hasMany(AssetAvailabilityWindow::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(MarketplaceCatalogPublication::class)->withoutGlobalScopes();
    }

    public function scopeForTenantPublicId(Builder $query, int|string $tenantId, string $publicId): Builder
    {
        return $query->forTenant((string) $tenantId)->where('venue_assets.public_id', $publicId);
    }

    public function scopeForTenantVenue(Builder $query, int|string $tenantId, int|string $venueId): Builder
    {
        return $query->forTenant((string) $tenantId)->where('venue_assets.venue_id', $venueId);
    }

    public function isActive(): bool
    {
        return $this->operational_status === 'active';
    }

    public function isRetired(): bool
    {
        return $this->operational_status === 'retired';
    }
}
