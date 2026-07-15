<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MarketplaceDispute extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'organizer_tenant_id'];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'rental_request_id',
        'settlement_statement_id', 'reported_by_tenant_id', 'reported_by_user_id',
        'assigned_platform_user_id', 'active_statement_id', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'immutable_datetime',
            'review_started_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceDisputeEvent::class, 'marketplace_dispute_id')
            ->withoutGlobalScopes()
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function participantEvents(): HasMany
    {
        return $this->events()->where('visibility', 'participants');
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(SettlementStatement::class, 'settlement_statement_id');
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(RentalRequest::class, 'rental_request_id');
    }

    public function scopeForParticipant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScopes()->where(
            fn (Builder $participant): Builder => $participant
                ->where('tenant_id', $tenantId)
                ->orWhere('organizer_tenant_id', $tenantId),
        );
    }

    public function scopeForPlatform(Builder $query): Builder
    {
        return $query->withoutGlobalScopes();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'under_review'], true);
    }
}
