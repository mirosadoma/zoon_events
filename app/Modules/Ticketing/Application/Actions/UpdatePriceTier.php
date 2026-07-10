<?php

namespace App\Modules\Ticketing\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePriceTier
{
    public function __construct(private AuditWriter $audit) {}

    /** @param array<string,mixed> $attributes */
    public function execute(TenantContext $context, TicketType $ticket, PriceTier $tier, array $attributes): PriceTier
    {
        if (isset($attributes['currency']) && $attributes['currency'] !== $ticket->currency) {
            throw Phase1Problem::make('price_changed');
        }

        return DB::transaction(function () use ($context, $ticket, $tier, $attributes): PriceTier {
            $tier->fill($attributes)->save();
            $this->audit->writeTenant('price_tier.updated', 'succeeded', $context, targetType: 'price_tier', targetId: $tier->id, metadata: ['event_id' => $ticket->event_id, 'ticket_type_id' => $ticket->id]);

            return $tier->refresh();
        });
    }
}
