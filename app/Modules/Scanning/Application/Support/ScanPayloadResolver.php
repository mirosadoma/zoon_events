<?php

namespace App\Modules\Scanning\Application\Support;

use App\Modules\Orders\Application\Support\CompletedRegistrationResolver;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;

final readonly class ScanPayloadResolver
{
    public function __construct(private CompletedRegistrationResolver $completedRegistrations) {}

    /**
     * Accept signed QR tokens and staff-friendly order references (`ord_...`).
     *
     * @return array{qr_payload:string,credential_id:?string}
     */
    public function resolveQrPayload(string $payload, string $tenantId, string $eventId): array
    {
        $payload = trim($payload);

        if ($payload === '' || ! str_starts_with($payload, 'ord_')) {
            return [
                'qr_payload' => $payload,
                'credential_id' => null,
            ];
        }

        $order = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('public_reference', $payload)
            ->first();

        if ($order === null) {
            return [
                'qr_payload' => $payload,
                'credential_id' => null,
            ];
        }

        $completed = $this->completedRegistrations->fromExistingOrder($order);

        if ($completed->credentialToken !== null && $completed->credentialToken !== '') {
            return [
                'qr_payload' => $completed->credentialToken,
                'credential_id' => null,
            ];
        }

        if ($completed->credentialId !== null) {
            return [
                'qr_payload' => '',
                'credential_id' => $completed->credentialId,
            ];
        }

        return [
            'qr_payload' => $payload,
            'credential_id' => null,
        ];
    }
}
