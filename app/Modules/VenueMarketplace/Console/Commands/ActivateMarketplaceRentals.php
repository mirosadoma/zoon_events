<?php

namespace App\Modules\VenueMarketplace\Console\Commands;

use App\Modules\VenueMarketplace\Application\Actions\ActivateRentalAction;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ActivateMarketplaceRentals extends Command
{
    protected $signature = 'marketplace:activate-rentals {--chunk=100}';

    protected $description = 'Activate approved rentals whose start time has arrived';

    public function handle(ActivateRentalAction $action): int
    {
        $chunkSize = (int) $this->option('chunk');
        $activated = 0;
        $failed = 0;

        RentalRequest::query()
            ->withoutGlobalScopes()
            ->where('status', 'approved')
            ->where('requested_start_at', '<=', now())
            ->orderBy('id')
            ->chunk($chunkSize, function ($rentals) use ($action, &$activated, &$failed): void {
                foreach ($rentals as $rental) {
                    try {
                        $action->execute(
                            (int) $rental->tenant_id,
                            (int) $rental->id,
                            'scheduler:activate-rentals:' . Str::ulid(),
                        );
                        $activated++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        $this->components->info("Activated: {$activated}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
