<?php

namespace App\Modules\AdminConsole\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\ViewModels\Kiosk\KioskModeViewModel;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use Inertia\Inertia;
use Inertia\Response;

final class KioskModeController extends Controller
{
    public function __construct(
        private readonly KioskModeViewModel $viewModel,
        private readonly KioskSessionContextStore $kioskSessions,
    ) {}

    public function show(string $deviceCode): Response
    {
        $session = $this->kioskSessions->current();

        $kiosk = Kiosk::query()
            ->where('device_code', $deviceCode)
            ->where('status', '!=', 'retired')
            ->firstOrFail();

        if ($kiosk->id !== $session->kioskId || $kiosk->event_id !== $session->eventId || $kiosk->tenant_id !== $session->tenantId) {
            throw Phase3Problem::make('kiosk_session_invalid');
        }

        $event = Event::query()
            ->where('tenant_id', $kiosk->tenant_id)
            ->findOrFail($kiosk->event_id);

        return Inertia::render('kiosk/Mode', $this->viewModel->make($kiosk, $event));
    }
}
