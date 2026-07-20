<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DelegatedAssetResource extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = [
        'id', 'tenant_id', 'organizer_tenant_id', 'control_delegation_id',
        'rental_request_id', 'rental_asset_id', 'venue_asset_id',
    ];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'control_delegation_id',
        'rental_request_id', 'rental_asset_id', 'venue_asset_id',
        'idempotency_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'granted_capabilities' => 'array',
            'provisioned_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(ControlDelegation::class, 'control_delegation_id');
    }

    public function rentalAsset(): BelongsTo
    {
        return $this->belongsTo(RentalAsset::class, 'rental_asset_id');
    }

    public function isProvisioned(): bool
    {
        return $this->provisioning_status === 'provisioned'
            && $this->provisioned_at !== null
            && $this->released_at === null;
    }
}
