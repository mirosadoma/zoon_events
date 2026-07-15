<?php

namespace App\Modules\VenueMarketplace\Console\Commands;

use App\Modules\VenueMarketplace\Application\Jobs\GenerateRentalSettlementStatement;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class FinalizeMarketplaceStatements extends Command
{
    protected $signature = 'marketplace:finalize-statements {--chunk=100 : Records per chunk}';

    protected $description = 'Dispatch settlement statement generation for terminal rentals without a statement.';

    public function handle(): int
    {
        $chunkSize = max(1, min((int) $this->option('chunk'), 500));
        $dispatched = 0;
        $skipped = 0;
        $failed = 0;

        $statedRentalIds = SettlementStatement::query()
            ->withoutGlobalScopes()
            ->where('revision', 1)
            ->pluck('rental_request_id');

        RentalRequest::query()
            ->withoutGlobalScopes()
            ->whereIn('status', ['completed', 'cancelled', 'revoked'])
            ->whereNotIn('id', $statedRentalIds)
            ->orderBy('id')
            ->chunk($chunkSize, function ($rentals) use (&$dispatched, &$skipped, &$failed): void {
                foreach ($rentals as $rental) {
                    try {
                        GenerateRentalSettlementStatement::dispatch(
                            (int) $rental->tenant_id,
                            $rental->public_id,
                            'marketplace-finalize-'.Str::ulid(),
                        );
                        $dispatched++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->error("Failed to dispatch for rental {$rental->public_id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Dispatched: {$dispatched}, Skipped: {$skipped}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
