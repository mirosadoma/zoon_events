<?php

namespace App\Modules\VenueMarketplace\Application\Jobs;

use App\Modules\VenueMarketplace\Application\Actions\GenerateSettlementStatementAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class GenerateRentalSettlementStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $ownerTenantId,
        public readonly string $rentalPublicId,
        public readonly string $correlationId,
    ) {
        $this->afterCommit = true;
        $this->onQueue('marketplace');
    }

    public function handle(GenerateSettlementStatementAction $action): void
    {
        $action->execute(
            $this->ownerTenantId,
            $this->rentalPublicId,
            $this->correlationId,
        );
    }

    public function uniqueId(): string
    {
        return "marketplace:settlement:{$this->ownerTenantId}:{$this->rentalPublicId}";
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Settlement statement generation failed', [
            'rental_public_id' => $this->rentalPublicId,
            'owner_tenant_id' => $this->ownerTenantId,
            'exception' => $e->getMessage(),
        ]);
    }
}
