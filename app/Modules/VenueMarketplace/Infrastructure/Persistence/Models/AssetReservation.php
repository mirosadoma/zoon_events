<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetReservation extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = [
        'id', 'tenant_id', 'organizer_tenant_id', 'rental_request_id',
        'rental_asset_id', 'venue_asset_id',
    ];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'rental_request_id',
        'rental_asset_id', 'venue_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'reserved_from' => 'immutable_datetime',
            'reserved_until' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(RentalRequest::class, 'rental_request_id');
    }

    public function rentalAsset(): BelongsTo
    {
        return $this->belongsTo(RentalAsset::class, 'rental_asset_id');
    }

    public function venueAsset(): BelongsTo
    {
        return $this->belongsTo(VenueAsset::class, 'venue_asset_id');
    }

    public function scopeForRental(Builder $query, int $tenantId, int $rentalRequestId): Builder
    {
        return $query->forTenant((string) $tenantId)
            ->where('rental_request_id', $rentalRequestId);
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', ['reserved', 'active']);
    }

    public function isBlocking(): bool
    {
        return in_array($this->status, ['reserved', 'active'], true);
    }
}
