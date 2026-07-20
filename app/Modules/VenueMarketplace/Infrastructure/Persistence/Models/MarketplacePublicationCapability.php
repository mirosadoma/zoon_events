<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MarketplacePublicationCapability extends Model implements TenantOwned
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = ['id', 'tenant_id', 'catalog_publication_id'];

    protected $hidden = ['id', 'tenant_id', 'catalog_publication_id'];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCatalogPublication::class, 'catalog_publication_id');
    }
}
