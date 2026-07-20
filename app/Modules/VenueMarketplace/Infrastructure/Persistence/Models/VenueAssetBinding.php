<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VenueAssetBinding extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = [
        'id', 'tenant_id', 'venue_asset_id', 'secret_reference',
        'external_reference', 'external_reference_ciphertext',
        'password', 'credential', 'token',
    ];

    protected $hidden = [
        'id', 'tenant_id', 'venue_asset_id', 'adapter_key', 'opaque_reference', 'binding_metadata',
        'secret_reference', 'external_reference', 'external_reference_ciphertext',
        'password', 'credential', 'token',
    ];

    protected function casts(): array
    {
        return [
            'opaque_reference' => 'encrypted',
            'binding_metadata' => 'encrypted:array',
            'verified_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(VenueAsset::class, 'venue_asset_id');
    }
}
