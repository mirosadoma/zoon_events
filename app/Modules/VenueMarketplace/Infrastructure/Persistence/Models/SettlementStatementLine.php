<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Persistence\Models;

use App\Modules\Tenancy\Contracts\TenantOwned;
use App\Modules\Tenancy\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SettlementStatementLine extends Model implements TenantOwned
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'tenant_id', 'organizer_tenant_id', 'settlement_statement_id'];

    protected $hidden = [
        'id', 'tenant_id', 'organizer_tenant_id', 'settlement_statement_id', 'rental_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'publication_version' => 'integer',
            'unit_price_minor' => 'integer',
            'billable_units' => 'integer',
            'line_total_minor' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(SettlementStatement::class, 'settlement_statement_id');
    }
}
