<?php

namespace App\Console\Commands;

use App\Modules\IdentityVerification\Application\Actions\PurgeExpiredIdentityArtifacts;
use Illuminate\Console\Command;

final class PurgeExpiredIdentityArtifactsCommand extends Command
{
    protected $signature = 'zonetec:identity:purge-expired';

    protected $description = 'Purge expired identity biometric artifacts and provider payloads';

    public function handle(PurgeExpiredIdentityArtifacts $action): int
    {
        $result = $action->execute();

        $this->info(sprintf(
            'Purged %d artifact(s) across %d verification(s); cleared %d provider reference(s).',
            $result['artifact_count'],
            $result['verification_count'],
            $result['provider_references_cleared'],
        ));

        return self::SUCCESS;
    }
}
