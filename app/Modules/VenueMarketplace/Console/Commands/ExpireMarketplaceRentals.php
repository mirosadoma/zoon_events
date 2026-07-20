<?php

namespace App\Modules\VenueMarketplace\Console\Commands;

use App\Modules\VenueMarketplace\Application\Actions\ExpireRentalAction;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ExpireMarketplaceRentals extends Command
{
    protected $signature = 'marketplace:expire-rentals {--chunk=100}';

    protected $description = 'Expire active/approved rentals whose end time has passed';

    public function handle(ExpireRentalAction $action): int
    {
        $chunkSize = (int) $this->option('chunk');
        $expired = 0;
        $failed = 0;

        RentalRequest::query()
            ->withoutGlobalScopes()
            ->whereIn('status', ['active', 'approved'])
            ->where('requested_end_at', '<=', now())
            ->orderBy('id')
            ->chunk($chunkSize, function ($rentals) use ($action, &$expired, &$failed): void {
                foreach ($rentals as $rental) {
                    try {
                        $action->execute(
                            (int) $rental->tenant_id,
                            (int) $rental->id,
                            'scheduler:expire-rentals:' . Str::ulid(),
                        );
                        $expired++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        $this->components->info("Expired: {$expired}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
