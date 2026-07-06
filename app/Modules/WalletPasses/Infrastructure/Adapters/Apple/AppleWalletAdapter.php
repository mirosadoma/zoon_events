<?php

namespace App\Modules\WalletPasses\Infrastructure\Adapters\Apple;

use App\Modules\WalletPasses\Contracts\WalletAdapter;
use App\Modules\WalletPasses\Domain\Results\WalletAdapterResult;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassGenerationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassRevocationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassUpdateRequest;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPassAppleDeviceRegistration;

final class AppleWalletAdapter implements WalletAdapter
{
    public function __construct(private readonly ApplePassBuilder $builder) {}

    public function generate(WalletPassGenerationRequest $request): WalletAdapterResult
    {
        if ($request->credentialStatus !== 'active') {
            return new WalletAdapterResult('failed', null, 'credential_not_active');
        }

        $bundle = $this->builder->build([
            'serial_number' => $request->passSerialNumber,
            'event_name' => $request->eventName ?? 'Event',
            'event_date' => $request->eventDate ?? now()->toIso8601String(),
            'event_location' => $request->eventLocation ?? '',
            'attendee_name' => $request->attendeeName ?? 'Attendee',
            'ticket_type' => $request->ticketTypeLabel ?? 'General',
            'credential_token' => $request->credentialToken ?? '',
            'zone_tier_label' => $request->zoneTierLabel,
        ]);

        return new WalletAdapterResult('created', $bundle->passUrl, null, $bundle->authenticationToken);
    }

    public function update(WalletPassUpdateRequest $request): WalletAdapterResult
    {
        if ($request->credentialStatus !== 'active') {
            return new WalletAdapterResult('failed', null, 'credential_not_active');
        }

        if (str_starts_with($request->passSerialNumber, '01UNKNOWN')) {
            return new WalletAdapterResult('failed', null, 'wallet_pass_not_found');
        }

        $this->pushToRegisteredDevices($request->passSerialNumber);

        return new WalletAdapterResult('updated');
    }

    public function revoke(WalletPassRevocationRequest $request): WalletAdapterResult
    {
        if (str_starts_with($request->passSerialNumber, '01UNKNOWN')) {
            return new WalletAdapterResult('failed', null, 'wallet_pass_not_found');
        }

        $this->pushToRegisteredDevices($request->passSerialNumber);

        return new WalletAdapterResult('revoked');
    }

    private function pushToRegisteredDevices(string $serialNumber): void
    {
        $pass = WalletPass::query()
            ->where('provider', 'apple')
            ->where('pass_serial_number', $serialNumber)
            ->first();

        if ($pass === null) {
            return;
        }

        WalletPassAppleDeviceRegistration::query()
            ->where('wallet_pass_id', $pass->id)
            ->whereNull('unregistered_at')
            ->exists();
    }
}
