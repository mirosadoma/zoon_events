<?php

namespace App\Modules\Ticketing\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Facades\DB;

final readonly class CreatePriceTier
{
    public function __construct(private AuditWriter $audit) {}

    /** @param array<string,mixed> $attributes */
    public function execute(TenantContext $context, TicketType $ticket, array $attributes): PriceTier
    {
        if ($attributes['currency'] !== $ticket->currency) {
            throw Phase1Problem::make('price_changed');
        }

        return DB::transaction(function () use ($context, $ticket, $attributes): PriceTier {
            $tier = PriceTier::query()->create([
                ...$attributes,
                'tenant_id' => $context->tenant->id,
                'event_id' => $ticket->event_id,
                'ticket_type_id' => $ticket->id,
                'status' => 'active',
            ]);
            $this->audit->writeTenant('price_tier.created', 'succeeded', $context, targetType: 'price_tier', targetId: $tier->id, metadata: ['event_id' => $ticket->event_id, 'ticket_type_id' => $ticket->id]);

            return $tier;
        });
    }
}
