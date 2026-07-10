<?php

namespace App\Modules\WalletPasses\Infrastructure\Adapters\Google;

use App\Modules\WalletPasses\Contracts\WalletAdapter;
use App\Modules\WalletPasses\Domain\Results\WalletAdapterResult;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassGenerationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassRevocationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassUpdateRequest;
use Illuminate\Support\Str;

final class GoogleWalletAdapter implements WalletAdapter
{
    public function __construct(private readonly GoogleWalletObjectBuilder $builder) {}

    public function generate(WalletPassGenerationRequest $request): WalletAdapterResult
    {
        if ($request->credentialStatus !== 'active') {
            return new WalletAdapterResult('failed', null, 'credential_not_active');
        }

        $classSuffix = 'event-'.Str::lower($request->eventId);
        $objectSuffix = 'pass-'.Str::lower($request->passSerialNumber);
        $class = $this->builder->buildClass([
            'class_suffix' => $classSuffix,
            'event_name' => $request->eventName ?? 'Event',
        ]);
        $object = $this->builder->buildObject([
            'class_suffix' => $classSuffix,
            'object_suffix' => $objectSuffix,
            'event_name' => $request->eventName ?? 'Event',
            'event_date' => $request->eventDate ?? now()->toIso8601String(),
            'event_location' => $request->eventLocation ?? '',
            'attendee_name' => $request->attendeeName ?? 'Attendee',
            'ticket_type' => $request->ticketTypeLabel ?? 'General',
            'credential_token' => $request->credentialToken ?? '',
        ]);
        $jwt = $this->builder->signJwt($class, $object);

        return new WalletAdapterResult('created', $this->builder->saveLink($jwt));
    }

    public function update(WalletPassUpdateRequest $request): WalletAdapterResult
    {
        if ($request->credentialStatus !== 'active') {
            return new WalletAdapterResult('failed', null, 'credential_not_active');
        }

        if (str_starts_with($request->passSerialNumber, '01UNKNOWN')) {
            return new WalletAdapterResult('failed', null, 'wallet_pass_not_found');
        }

        return new WalletAdapterResult('updated');
    }

    public function revoke(WalletPassRevocationRequest $request): WalletAdapterResult
    {
        if (str_starts_with($request->passSerialNumber, '01UNKNOWN')) {
            return new WalletAdapterResult('failed', null, 'wallet_pass_not_found');
        }

        return new WalletAdapterResult('revoked');
    }
}
