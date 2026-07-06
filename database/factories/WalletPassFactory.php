<?php

namespace Database\Factories;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WalletPass> */
final class WalletPassFactory extends Factory
{
    protected $model = WalletPass::class;

    public function definition(): array
    {
        return [
            'provider' => 'apple',
            'pass_serial_number' => (string) Str::ulid(),
            'pass_url' => 'https://wallet.test/apple/'.Str::lower((string) Str::ulid()),
            'status' => WalletPassStatus::Active,
            'last_pushed_at' => now(),
            'last_push_reason_code' => null,
            'superseded_by_id' => null,
        ];
    }

    public function forCredential(Credential $credential, Attendee $attendee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $credential->tenant_id,
            'event_id' => $credential->event_id,
            'attendee_id' => $attendee->id,
            'credential_id' => $credential->id,
        ]);
    }
}
