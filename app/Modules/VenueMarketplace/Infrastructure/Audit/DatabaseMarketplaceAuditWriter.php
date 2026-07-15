<?php

namespace App\Modules\VenueMarketplace\Infrastructure\Audit;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;

final readonly class DatabaseMarketplaceAuditWriter implements MarketplaceAuditWriter
{
    public function __construct(private AuditWriter $audit) {}

    public function write(MarketplaceAuditEvent $event): void
    {
        $tenantId = match ($event->scope) {
            'owner' => $event->ownerTenantId,
            'organizer' => $event->organizerTenantId,
            'platform' => null,
        };
        $actor = $event->actorUserId === null
            ? null
            : User::query()->find($event->actorUserId);

        $this->audit->write(
            $event->scope === 'platform' ? 'platform' : 'tenant',
            $tenantId === null ? null : (string) $tenantId,
            $event->action,
            $event->outcome,
            $actor,
            $event->reasonCode,
            'marketplace_resource',
            $event->targetPublicId,
            [
                ...$event->payload,
                'marketplace_scope' => $event->scope,
                'correlation_id' => $event->correlationId,
                'counterparty_tenant_id' => match ($event->scope) {
                    'owner' => $event->organizerTenantId,
                    'organizer' => $event->ownerTenantId,
                    'platform' => null,
                },
            ],
        );
    }
}
