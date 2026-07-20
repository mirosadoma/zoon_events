<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetAvailabilityWindow extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'venue_asset_id', 'public_id'];

    protected $hidden = ['id', 'tenant_id', 'venue_asset_id', 'created_by_user_id', 'updated_by_user_id'];

    protected function casts(): array
    {
        return [
            'available_from' => 'immutable_datetime',
            'available_until' => 'immutable_datetime',
            'local_from' => 'immutable_datetime',
            'local_until' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(VenueAsset::class, 'venue_asset_id');
    }

    public function isBookable(): bool
    {
        return $this->status === 'available';
    }
}
