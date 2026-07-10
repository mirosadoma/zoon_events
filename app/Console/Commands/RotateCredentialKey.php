<?php

namespace App\Console\Commands;

use App\Modules\Credentials\Infrastructure\Persistence\Models\CredentialSigningKey;
use Illuminate\Console\Command;

final class RotateCredentialKey extends Command
{
    protected $signature = 'zonetec:credentials:rotate-key
        {key-id}
        {--public-key=}
        {--private-key-reference=}
        {--compromise= : Existing key ID to mark compromised}
        {--force}';

    protected $description = 'Stage credential signing-key metadata using a secret reference';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Apply credential key metadata transition?')) {
            return self::FAILURE;
        }
        if ($compromised = $this->option('compromise')) {
            CredentialSigningKey::query()->where('key_id', $compromised)->update(['status' => 'compromised']);
        }
        $publicKey = (string) $this->option('public-key');
        $reference = (string) $this->option('private-key-reference');
        if ($publicKey === '' || $reference === '') {
            $this->components->error('Public key and private-key reference are required.');

            return self::FAILURE;
        }
        CredentialSigningKey::query()->updateOrCreate(
            ['key_id' => (string) $this->argument('key-id')],
            [
                'public_key' => $publicKey,
                'private_key_reference' => $reference,
                'status' => 'pending',
                'not_before' => now(),
            ],
        );
        $this->components->info('Credential key metadata staged. Deploy the matching reference configuration before activation.');

        return self::SUCCESS;
    }
}
