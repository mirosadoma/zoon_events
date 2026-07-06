<?php

namespace App\Modules\Audit\Application\Listeners\Phase2;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\WalletPasses\Domain\Events\WalletPassRevocationFailed;
use App\Modules\WalletPasses\Domain\Events\WalletPassRevoked;
use App\Modules\WalletPasses\Domain\Events\WalletPassUpdated;
use App\Modules\WalletPasses\Domain\Events\WalletPassUpdateFailed;

final readonly class WalletPassAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handleUpdated(WalletPassUpdated $event): void
    {
        $this->write('wallet_pass.updated', $event->tenantId, $event->walletPassId, $event->eventId, $event->provider);
    }

    public function handleUpdateFailed(WalletPassUpdateFailed $event): void
    {
        $this->write(
            'wallet_pass.update_failed',
            $event->tenantId,
            $event->walletPassId,
            $event->eventId,
            $event->provider,
            $event->reasonCode,
            'failed',
        );
    }

    public function handleRevoked(WalletPassRevoked $event): void
    {
        $this->write('wallet_pass.revoked', $event->tenantId, $event->walletPassId, $event->eventId, $event->provider);
    }

    public function handleRevocationFailed(WalletPassRevocationFailed $event): void
    {
        $this->write(
            'wallet_pass.revocation_failed',
            $event->tenantId,
            $event->walletPassId,
            $event->eventId,
            $event->provider,
            $event->reasonCode,
            'failed',
        );
    }

    private function write(
        string $action,
        string $tenantId,
        string $walletPassId,
        string $eventId,
        string $provider,
        ?string $reasonCode = null,
        string $outcome = 'succeeded',
    ): void {
        $this->audit->write(
            'tenant',
            $tenantId,
            $action,
            $outcome,
            reasonCode: $reasonCode,
            targetType: 'wallet_pass',
            targetId: $walletPassId,
            metadata: ['event_id' => $eventId, 'provider' => $provider],
        );
    }
}
