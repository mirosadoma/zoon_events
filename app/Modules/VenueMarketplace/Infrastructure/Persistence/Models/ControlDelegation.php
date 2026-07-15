<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ControlDelegation extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = [
        'id', 'tenant_id', 'organizer_tenant_id', 'rental_request_id',
        'event_id', 'public_id', 'version',
    ];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'rental_request_id',
        'event_id', 'revoked_by_user_id', 'revocation_reason', 'idempotency_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'last_provision_attempt_at' => 'immutable_datetime',
            'provision_attempts' => 'integer',
            'version' => 'integer',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(RentalRequest::class, 'rental_request_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(DelegatedAssetResource::class, 'control_delegation_id');
    }

    public function scopeForParticipant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScopes()->where(
            fn (Builder $participant): Builder => $participant
                ->where('tenant_id', $tenantId)
                ->orWhere('organizer_tenant_id', $tenantId),
        );
    }

    public function isPending(): bool
    {
        return $this->status === 'pending'
            && $this->revoked_at === null;
    }
}
