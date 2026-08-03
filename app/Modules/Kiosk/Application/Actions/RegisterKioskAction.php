<?php

namespace App\Modules\Kiosk\Application\Actions;

use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RegisterKioskAction
{
    public function execute(
        string $tenantId,
        string $eventId,
        string $deviceName,
        ?string $locationLabel,
        string $plainConfirmationCode,
    ): Kiosk {
        $code = trim($plainConfirmationCode);

        if ($code === '') {
            throw ValidationException::withMessages([
                'confirmation_code' => 'A confirmation code is required.',
            ]);
        }

        $deviceCode = $this->generateUniqueDeviceCode($tenantId, $eventId);

        return Kiosk::create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'device_name' => $deviceName,
            'device_code' => $deviceCode,
            'location_label' => $locationLabel,
            'status' => 'registered',
            'printer_status' => 'unknown',
            'confirmation_required' => true,
            'confirmation_code_hash' => hash('sha256', $code),
        ]);
    }

    private function generateUniqueDeviceCode(string $tenantId, string $eventId, int $attempts = 5): string
    {
        for ($i = 0; $i < $attempts; $i++) {
            $code = strtoupper(Str::random(8));
            if (! Kiosk::query()->where('tenant_id', $tenantId)->where('event_id', $eventId)->where('device_code', $code)->exists()) {
                return $code;
            }
        }

        return strtoupper(Str::random(16));
    }
}
