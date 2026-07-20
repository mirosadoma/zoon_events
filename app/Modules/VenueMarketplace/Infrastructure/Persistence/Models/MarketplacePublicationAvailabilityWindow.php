<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class MarketplacePublicationAvailabilityWindow extends Model implements TenantOwned
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'marketplace_publication_availability_windows';

    protected $guarded = ['id', 'tenant_id', 'catalog_publication_id'];

    protected $hidden = ['id', 'tenant_id', 'catalog_publication_id'];

    protected function casts(): array
    {
        return [
            'available_from' => 'immutable_datetime',
            'available_until' => 'immutable_datetime',
        ];
    }
}
