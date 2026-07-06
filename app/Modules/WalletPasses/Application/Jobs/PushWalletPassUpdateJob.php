<?php

namespace App\Modules\WalletPasses\Application\Jobs;

use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\WalletPasses\Contracts\WalletAdapter;
use App\Modules\WalletPasses\Domain\Events\WalletPassRevocationFailed;
use App\Modules\WalletPasses\Domain\Events\WalletPassRevoked;
use App\Modules\WalletPasses\Domain\Events\WalletPassUpdated;
use App\Modules\WalletPasses\Domain\Events\WalletPassUpdateFailed;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassRevocationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassUpdateRequest;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Adapters\Apple\AppleWalletAdapter;
use App\Modules\WalletPasses\Infrastructure\Adapters\Google\GoogleWalletAdapter;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use App\Modules\WalletPasses\Testing\FakeWalletAdapter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

final class PushWalletPassUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120, 240, 480];
    }

    public function __construct(
        public readonly string $walletPassId,
        public readonly string $operation,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $pass = WalletPass::query()->find($this->walletPassId);
        if ($pass === null || $pass->superseded_by_id !== null) {
            return;
        }

        $credential = Credential::query()
            ->where('tenant_id', $pass->tenant_id)
            ->where('id', $pass->credential_id)
            ->first();

        $credentialStatus = $credential?->status ?? 'revoked';
        $adapter = $this->resolveAdapter($pass->provider);

        $result = $this->operation === 'revoke'
            ? $adapter->revoke(new WalletPassRevocationRequest(
                $pass->tenant_id,
                $pass->event_id,
                $pass->pass_serial_number,
                $pass->provider,
                'credential_revoked',
            ))
            : $adapter->update(new WalletPassUpdateRequest(
                $pass->tenant_id,
                $pass->event_id,
                $pass->pass_serial_number,
                $pass->provider,
                $credentialStatus,
            ));

        if ($result->status === 'unavailable') {
            $pass->forceFill([
                'last_push_reason_code' => $result->reasonCode ?? 'wallet_provider_unavailable',
                'last_pushed_at' => now(),
            ])->save();

            throw new RuntimeException('Wallet provider unavailable; retry required.');
        }

        if ($this->operation === 'revoke') {
            $this->finishRevoke($pass, $result->status, $result->reasonCode);
        } else {
            $this->finishUpdate($pass, $result->status, $result->reasonCode);
        }
    }

    private function finishUpdate(WalletPass $pass, string $status, ?string $reasonCode): void
    {
        if ($status === 'updated') {
            $pass->forceFill([
                'status' => WalletPassStatus::Updated,
                'last_pushed_at' => now(),
                'last_push_reason_code' => null,
                'pass_content_updated_at' => now(),
            ])->save();
            event(new WalletPassUpdated($pass->tenant_id, $pass->event_id, $pass->id, $pass->provider));

            return;
        }

        $pass->forceFill([
            'last_pushed_at' => now(),
            'last_push_reason_code' => $reasonCode ?? 'wallet_update_failed',
        ])->save();
        event(new WalletPassUpdateFailed(
            $pass->tenant_id,
            $pass->event_id,
            $pass->id,
            $pass->provider,
            $reasonCode ?? 'wallet_update_failed',
        ));
    }

    private function finishRevoke(WalletPass $pass, string $status, ?string $reasonCode): void
    {
        if ($status === 'revoked') {
            $pass->forceFill([
                'status' => WalletPassStatus::Revoked,
                'last_pushed_at' => now(),
                'last_push_reason_code' => null,
            ])->save();
            event(new WalletPassRevoked($pass->tenant_id, $pass->event_id, $pass->id, $pass->provider));

            return;
        }

        $pass->forceFill([
            'last_pushed_at' => now(),
            'last_push_reason_code' => $reasonCode ?? 'wallet_revocation_failed',
        ])->save();
        event(new WalletPassRevocationFailed(
            $pass->tenant_id,
            $pass->event_id,
            $pass->id,
            $pass->provider,
            $reasonCode ?? 'wallet_revocation_failed',
        ));
    }

    private function resolveAdapter(string $provider): WalletAdapter
    {
        $binding = $provider === 'apple'
            ? config('wallet.default_apple_adapter')
            : config('wallet.default_google_adapter');

        if ($binding === 'fake') {
            return app(FakeWalletAdapter::class);
        }

        return $provider === 'apple'
            ? app(AppleWalletAdapter::class)
            : app(GoogleWalletAdapter::class);
    }
}
