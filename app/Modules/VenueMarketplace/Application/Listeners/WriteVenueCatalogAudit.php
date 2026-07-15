<?php

namespace App\Modules\VenueMarketplace\Application\Listeners;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Application\Services\MarketplaceCatalogCache;
use App\Modules\VenueMarketplace\Domain\Events\VenueCatalogEvents;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class WriteVenueCatalogAudit implements MarketplaceAuditWriter
{
    public function __construct(
        private Dispatcher $events,
        private AuditWriter $audit,
        private MarketplaceCatalogCache $catalogCache,
    ) {}

    public function write(MarketplaceAuditEvent $event): void
    {
        $this->events->dispatch(new VenueCatalogEvents($event));
    }

    public function handle(VenueCatalogEvents $event): void
    {
        $record = $event->audit;
        $tenantId = match ($record->scope) {
            'platform' => null,
            'organizer' => $record->organizerTenantId,
            default => $record->ownerTenantId,
        };
        $actor = $record->actorUserId === null
            ? null
            : User::query()->find($record->actorUserId);

        $this->audit->write(
            $record->scope === 'platform' ? 'platform' : 'tenant',
            $tenantId === null ? null : (string) $tenantId,
            $record->action,
            $record->outcome,
            $actor,
            $record->reasonCode,
            'marketplace_resource',
            $record->targetPublicId,
            [
                ...$record->payload,
                'marketplace_scope' => $record->scope,
                'correlation_id' => $record->correlationId,
                'organizer_tenant_id' => $record->organizerTenantId,
            ],
        );
        if (in_array($record->action, [
            'venue_asset.published',
            'venue_asset.publication_withdrawn',
            'venue_asset.retired',
            'venue.status_changed',
        ], true)) {
            $this->catalogCache->invalidate();
        }
    }
}
