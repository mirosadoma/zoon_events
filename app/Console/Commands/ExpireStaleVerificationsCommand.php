<?php

namespace App\Console\Commands;

use App\Modules\IdentityVerification\Application\Actions\ExpireStaleVerifications;
use Illuminate\Console\Command;

final class ExpireStaleVerificationsCommand extends Command
{
    protected $signature = 'zonetec:identity:expire-stale';

    protected $description = 'Expire identity verifications that are past the validity window';

    public function handle(ExpireStaleVerifications $action): int
    {
        $result = $action->execute();

        $this->info(sprintf('Expired %d verification(s).', $result['expired_count']));

        return self::SUCCESS;
    }
}
