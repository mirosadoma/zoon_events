<?php

namespace App\Modules\AdminConsole\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Http\Controllers\Concerns\ResolvesRouteParam;
use App\Modules\AdminConsole\ViewModels\Kiosk\KioskModeViewModel;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class KioskModeController extends Controller
{
    use ResolvesRouteParam;

    /** @var list<string> */
    private const STEPS = ['unlock', 'confirm', 'scan', 'lookup', 'result'];

    public function __construct(
        private readonly KioskModeViewModel $viewModel,
    ) {}

    public function show(?string $deviceCode = null, ?string $step = null): Response|RedirectResponse
    {
        $resolvedCode = $this->routeParamOrNull('device_code') ?? $deviceCode;
        abort_unless(is_string($resolvedCode) && $resolvedCode !== '', 404);

        $resolvedStep = $this->routeParamOrNull('step') ?? $step;

        if ($resolvedStep === null || $resolvedStep === '') {
            $locale = $this->routeParamOrNull('locale');
            $prefix = is_string($locale) && $locale !== '' ? '/'.$locale : '';

            return redirect("{$prefix}/kiosk/{$resolvedCode}/unlock");
        }

        abort_unless(in_array($resolvedStep, self::STEPS, true), 404);

        $kiosk = Kiosk::query()
            ->where('device_code', $resolvedCode)
            ->where('status', '!=', 'retired')
            ->firstOrFail();

        $event = Event::query()
            ->where('tenant_id', $kiosk->tenant_id)
            ->findOrFail($kiosk->event_id);

        return Inertia::render('kiosk/Mode', array_merge(
            $this->viewModel->make($kiosk, $event),
            ['step' => $resolvedStep],
        ));
    }
}
