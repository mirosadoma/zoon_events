<?php

namespace Database\Factories;

use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPassAppleDeviceRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WalletPassAppleDeviceRegistration> */
final class WalletPassAppleDeviceRegistrationFactory extends Factory
{
    protected $model = WalletPassAppleDeviceRegistration::class;

    public function definition(): array
    {
        return [
            'device_library_identifier' => 'device-'.Str::lower((string) Str::ulid()),
            'push_token' => 'apns-'.Str::lower((string) Str::ulid()),
            'registered_at' => now(),
            'unregistered_at' => null,
        ];
    }

    public function forWalletPass(WalletPass $walletPass): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $walletPass->tenant_id,
            'wallet_pass_id' => $walletPass->id,
        ]);
    }
}
