<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SettlementStatement extends Model implements TenantOwned
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'tenant_id', 'organizer_tenant_id'];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'rental_request_id',
        'supersedes_statement_id', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'agreed_total_minor' => 'integer',
            'agreed_start_at' => 'immutable_datetime',
            'agreed_end_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SettlementStatementLine::class, 'settlement_statement_id')
            ->withoutGlobalScopes();
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(RentalRequest::class, 'rental_request_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_statement_id');
    }

    public function revision(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_statement_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(MarketplaceDispute::class, 'settlement_statement_id')
            ->withoutGlobalScopes();
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

    public function scopeLatestRevision(Builder $query): Builder
    {
        return $query->where('status', 'issued');
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }
}
