<?php

namespace App\Modules\AdminConsole\ViewModels\Kiosk;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;

final readonly class KioskModeViewModel
{
    /**
     * @return array{
     *     deviceCode: string,
     *     kiosk: array<string, mixed>,
     *     event: array<string, mixed>,
     *     branding: array<string, mixed>|null
     * }
     */
    public function make(Kiosk $kiosk, Event $event): array
    {
        return [
            'deviceCode' => $kiosk->device_code,
            'kiosk' => [
                'id' => $kiosk->id,
                'device_name' => $kiosk->device_name,
                'confirmation_required' => $kiosk->confirmation_required,
            ],
            'event' => [
                'id' => $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'branding' => null,
        ];
    }
}
