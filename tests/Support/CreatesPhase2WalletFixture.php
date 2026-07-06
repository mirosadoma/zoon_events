<?php

namespace Tests\Support;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Illuminate\Support\Str;

trait CreatesPhase2WalletFixture
{
    /** @param array{fixture:array<string,mixed>,credential:Credential} $scanFixture */
    protected function createWalletPassForCredential(
        array $scanFixture,
        string $provider = 'apple',
        WalletPassStatus $status = WalletPassStatus::Active,
        ?string $serial = null,
    ): WalletPass {
        $credential = $scanFixture['credential'];
        $attendee = Attendee::query()
            ->where('tenant_id', $credential->tenant_id)
            ->where('event_id', $credential->event_id)
            ->where('id', $credential->attendee_id)
            ->firstOrFail();

        return WalletPass::factory()->forCredential($credential, $attendee)->create([
            'provider' => $provider,
            'status' => $status,
            'pass_serial_number' => $serial ?? (string) Str::ulid(),
        ]);
    }
}
