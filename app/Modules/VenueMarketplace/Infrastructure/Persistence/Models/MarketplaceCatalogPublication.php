<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MarketplaceCatalogPublication extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = [
        'id', 'tenant_id', 'venue_id', 'venue_asset_id', 'active_venue_asset_id',
        'venue_version', 'asset_version', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'publication_version' => 'integer',
            'venue_version' => 'integer',
            'asset_version' => 'integer',
            'capacity_per_minute' => 'integer',
            'price_minor' => 'integer',
            'availability_windows' => 'array',
            'public_contact' => 'array',
            'published_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(MarketplacePublicationCapability::class, 'catalog_publication_id')
            ->withoutGlobalScopes()
            ->orderBy('capability_code');
    }

    public function availabilityWindows(): HasMany
    {
        return $this->hasMany(MarketplacePublicationAvailabilityWindow::class, 'catalog_publication_id')
            ->withoutGlobalScopes()
            ->orderBy('available_from')
            ->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->withdrawn_at === null;
    }
}
