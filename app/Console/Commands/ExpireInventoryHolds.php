<?php

namespace App\Console\Commands;

use App\Modules\Ticketing\Application\Inventory\InventoryService;
use App\Modules\Ticketing\Application\Jobs\ExpireInventoryHoldsJob;
use Illuminate\Console\Command;

final class ExpireInventoryHolds extends Command
{
    protected $signature = 'zonetec:ticketing:expire-holds';

    protected $description = 'Release bounded batches of expired ticket inventory holds';

    public function handle(ExpireInventoryHoldsJob $job): int
    {
        $job->handle(app(InventoryService::class));
        $this->components->info('Expired inventory holds processed.');

        return self::SUCCESS;
    }
}
