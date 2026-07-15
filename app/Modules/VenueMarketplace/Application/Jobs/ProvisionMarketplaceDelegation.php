<?php

namespace App\Modules\VenueMarketplace\Application\Jobs;

use App\Modules\VenueMarketplace\Application\Actions\ProvisionDelegatedAssetsAction;
use App\Modules\VenueMarketplace\Domain\Events\DelegationProvisioned;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProvisionMarketplaceDelegation implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        public readonly int $ownerTenantId,
        public readonly string $delegationPublicId,
        public readonly string $correlationId,
    ) {
        $this->afterCommit = true;
        $this->onQueue('marketplace-delegation');
    }

    public function uniqueId(): string
    {
        return "provision:{$this->ownerTenantId}:{$this->delegationPublicId}";
    }

    public function handle(ProvisionDelegatedAssetsAction $action): void
    {
        $delegation = $action->execute(
            $this->ownerTenantId,
            $this->delegationPublicId,
            $this->correlationId,
        );

        event(new DelegationProvisioned(
            delegationPublicId: $delegation->public_id,
            status: $delegation->status,
            ownerTenantId: (int) $delegation->tenant_id,
            organizerTenantId: (int) $delegation->organizer_tenant_id,
            correlationId: $this->correlationId,
        ));
    }
}
