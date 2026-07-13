<?php

namespace App\Console\Commands;

use App\Modules\Credentials\Application\Signing\CredentialKeyRing;
use Illuminate\Console\Command;

final class CredentialKeysCheck extends Command
{
    protected $signature = 'zonetec:credentials:keys-check';

    protected $description = 'Check credential signing readiness without displaying key material';

    public function handle(CredentialKeyRing $keys): int
    {
        $failure = $keys->readinessFailure();
        if ($failure !== null) {
            $this->components->error('Credential signing is not ready.');
            $this->components->warn($failure);

            return self::FAILURE;
        }
        $this->components->info('Credential signing is ready for key '.$keys->currentKeyId().'.');

        return self::SUCCESS;
    }
}
