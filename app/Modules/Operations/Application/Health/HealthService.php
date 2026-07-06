<?php

namespace App\Modules\Operations\Application\Health;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;
use App\Modules\Operations\Application\Health\Checks\AppleWalletHealthCheck;
use App\Modules\Operations\Application\Health\Checks\CredentialSigningHealthCheck;
use App\Modules\Operations\Application\Health\Checks\DataProtectionHealthCheck;
use App\Modules\Operations\Application\Health\Checks\GoogleWalletHealthCheck;
use App\Modules\Operations\Application\Health\Checks\NotificationConfigurationHealthCheck;
use App\Modules\Operations\Application\Health\Checks\PaymentCheck;
use App\Modules\Operations\Contracts\HealthCheck;
use App\Modules\Shared\Contracts\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\QueueManager;
use Throwable;

class HealthService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly QueueManager $queue,
        private readonly FilesystemFactory $filesystem,
        private readonly Clock $clock,
        private readonly ConfigurationValidator $configuration,
    ) {}

    public function readiness(): HealthReport
    {
        $checks = [];

        foreach ((array) config('health.checks', []) as $category) {
            $checks[] = $this->runCheck((string) $category);
        }

        $status = collect($checks)->contains(fn (HealthCheckResult $check): bool => $check->status !== 'ok')
            ? 'unavailable'
            : 'ok';

        return new HealthReport($status, CarbonImmutable::instance($this->clock->now()), $checks);
    }

    private function runCheck(string $category): HealthCheckResult
    {
        $started = microtime(true);

        try {
            match ($category) {
                'database' => $this->database->connection()->select('select 1 as ready'),
                'queue' => $this->queue->connection()->size(config('queue.connections.database.queue', 'default')),
                'storage' => $this->filesystem->disk(config('filesystems.default'))->exists('.'),
                'audit_key' => $this->assertAuditKey(),
                'config' => $this->assertConfiguration(),
                'data_protection' => $this->runExtensionCheck(DataProtectionHealthCheck::class),
                'credential_signing' => $this->runExtensionCheck(CredentialSigningHealthCheck::class),
                'payments' => $this->runExtensionCheck(PaymentCheck::class),
                'notifications' => $this->runExtensionCheck(NotificationConfigurationHealthCheck::class),
                'apple_wallet' => $this->runExtensionCheck(AppleWalletHealthCheck::class),
                'google_wallet' => $this->runExtensionCheck(GoogleWalletHealthCheck::class),
                default => null,
            };

            return new HealthCheckResult($category, 'ok', $this->elapsedMs($started));
        } catch (Throwable) {
            return new HealthCheckResult($category, 'unavailable', $this->elapsedMs($started), $category.'_check_failed');
        }
    }

    private function assertAuditKey(): void
    {
        $currentKeyId = (string) config('audit.current_key_id');
        $keyRing = (array) config('audit.key_ring', []);

        if ($currentKeyId === '' || ! array_key_exists($currentKeyId, $keyRing) || ! is_string($keyRing[$currentKeyId]) || strlen($keyRing[$currentKeyId]) < 16) {
            throw new \RuntimeException('Audit key is unavailable.');
        }
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function assertConfiguration(): void
    {
        if (! $this->configuration->isValid()) {
            throw new \RuntimeException('Configuration validation failed.');
        }
    }

    /** @param class-string<HealthCheck> $check */
    private function runExtensionCheck(string $check): void
    {
        if (app($check)->run()->status !== 'ok') {
            throw new \RuntimeException('Phase 1 configuration health check failed.');
        }
    }
}
