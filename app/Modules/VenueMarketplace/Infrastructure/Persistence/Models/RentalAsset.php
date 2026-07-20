<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RentalAsset extends Model implements TenantOwned
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = ['id', 'tenant_id', 'organizer_tenant_id', 'rental_request_id'];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'rental_request_id',
        'venue_asset_id', 'catalog_publication_id',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'selected_capabilities' => 'array',
            'publication_version' => 'integer',
            'unit_price_minor' => 'integer',
            'quantity' => 'integer',
            'billable_units' => 'integer',
            'line_total_minor' => 'integer',
            'line_order' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new \LogicException('Rental asset snapshots are immutable.');
        });
        self::deleting(static function (): never {
            throw new \LogicException('Rental asset snapshots are immutable.');
        });
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(RentalRequest::class, 'rental_request_id')->withoutGlobalScopes();
    }
}
