<?php

namespace App\Modules\WalletPasses\Application\Listeners;

use App\Modules\Credentials\Domain\Events\CredentialLifecycleChanged;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Illuminate\Support\Str;

final readonly class CredentialReissuedWalletSyncListener
{
    public function handle(CredentialLifecycleChanged $event): void
    {
        if ($event->transition !== 'reissued' || $event->replacementId === null) {
            return;
        }

        $credential = Credential::query()
            ->where('tenant_id', $event->tenantId)
            ->where('event_id', $event->eventId)
            ->find($event->credentialId);

        if ($credential === null) {
            return;
        }

        WalletPass::query()
            ->where('tenant_id', $event->tenantId)
            ->where('event_id', $event->eventId)
            ->where('credential_id', $event->credentialId)
            ->whereNull('superseded_by_id')
            ->get()
            ->each(function (WalletPass $pass) use ($event, $credential): void {
                $replacementPass = WalletPass::query()->create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $pass->tenant_id,
                    'event_id' => $pass->event_id,
                    'attendee_id' => $credential->attendee_id,
                    'credential_id' => $event->replacementId,
                    'provider' => $pass->provider,
                    'pass_serial_number' => (string) Str::ulid(),
                    'status' => WalletPassStatus::Created,
                ]);

                $pass->forceFill(['superseded_by_id' => $replacementPass->id])->save();
            });
    }
}
