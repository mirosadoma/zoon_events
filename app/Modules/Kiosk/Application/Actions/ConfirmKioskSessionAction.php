<?php

namespace App\Modules\Kiosk\Application\Actions;

use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\KioskSession;
use App\Modules\Shared\Http\Problems\Phase3Problem;

final readonly class ConfirmKioskSessionAction
{
    public function execute(KioskSession $session, Kiosk $kiosk, string $submittedCode): void
    {
        if (! hash_equals($kiosk->confirmation_code_hash ?? '', hash('sha256', $submittedCode))) {
            throw Phase3Problem::make('kiosk_confirmation_invalid');
        }

        $session->forceFill(['confirmed_at' => now()])->save();
    }
}
