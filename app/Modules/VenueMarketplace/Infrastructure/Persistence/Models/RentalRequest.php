<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RentalRequest extends Model implements TenantOwned
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'organizer_tenant_id'];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'venue_id', 'event_id',
        'idempotency_key_hash', 'idempotency_payload_hash', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'event_snapshot' => 'array',
            'quote_version' => 'integer',
            'total_minor' => 'integer',
            'version' => 'integer',
            'requested_start_at' => 'immutable_datetime',
            'requested_end_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(RentalAsset::class)
            ->withoutGlobalScopes()
            ->orderBy('line_order');
    }

    public function scopeForOrganizer(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScopes()->where('organizer_tenant_id', $tenantId);
    }

    public function scopeForParticipant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScopes()->where(
            fn (Builder $participant): Builder => $participant
                ->where('tenant_id', $tenantId)
                ->orWhere('organizer_tenant_id', $tenantId),
        );
    }

    public function scopeForParticipantPublicId(Builder $query, int $tenantId, string $publicId): Builder
    {
        return $query->forParticipant($tenantId)->where('public_id', $publicId);
    }
}
