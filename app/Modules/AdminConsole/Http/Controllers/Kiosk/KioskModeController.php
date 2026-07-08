<?php

namespace App\Modules\AdminConsole\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\ViewModels\Kiosk\KioskModeViewModel;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use Inertia\Inertia;
use Inertia\Response;

final class KioskModeController extends Controller
{
    public function __construct(private readonly KioskModeViewModel $viewModel) {}

    public function show(string $deviceCode): Response
    {
        $kiosk = Kiosk::query()
            ->where('device_code', $deviceCode)
            ->where('status', '!=', 'retired')
            ->firstOrFail();

        $event = Event::query()
            ->where('tenant_id', $kiosk->tenant_id)
            ->findOrFail($kiosk->event_id);

        return Inertia::render('kiosk/Mode', $this->viewModel->make($kiosk, $event));
    }
}
