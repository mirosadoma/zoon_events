<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MarketplaceDisputeEvent extends Model implements TenantOwned
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'tenant_id', 'organizer_tenant_id', 'marketplace_dispute_id'];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'marketplace_dispute_id',
        'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(MarketplaceDispute::class, 'marketplace_dispute_id');
    }

    public function isParticipantVisible(): bool
    {
        return $this->visibility === 'participants';
    }
}
