<?php

namespace App\Console\Commands;

use App\Modules\Payments\Application\Actions\ConfigurePaymentAccount as ConfigureAction;
use Illuminate\Console\Command;

final class ConfigurePaymentAccount extends Command
{
    protected $signature = 'zonetec:payments:configure
        {tenant : Tenant ULID}
        {--adapter=moyasar}
        {--secret-reference=}
        {--account-reference=}
        {--route-token=}
        {--mode=test}
        {--currency=SAR}
        {--reason= : Required audit reason}
        {--force : Skip confirmation}';

    protected $description = 'Configure a tenant payment account using secret references only';

    public function handle(ConfigureAction $action): int
    {
        foreach (['secret-reference', 'account-reference', 'route-token', 'reason'] as $required) {
            if (trim((string) $this->option($required)) === '') {
                $this->components->error("Missing required --{$required} option.");

                return self::FAILURE;
            }
        }
        if (! $this->option('force') && ! $this->confirm('Replace the active payment routing configuration?')) {
            return self::FAILURE;
        }
        $account = $action->execute(
            (string) $this->argument('tenant'),
            (string) $this->option('adapter'),
            (string) $this->option('secret-reference'),
            (string) $this->option('account-reference'),
            (string) $this->option('route-token'),
            (string) $this->option('mode'),
            strtoupper((string) $this->option('currency')),
            (string) $this->option('reason'),
        );
        $this->components->info("Payment account {$account->id} configured.");

        return self::SUCCESS;
    }
}
