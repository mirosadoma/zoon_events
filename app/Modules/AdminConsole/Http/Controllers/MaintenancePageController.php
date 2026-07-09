<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use Inertia\Inertia;
use Inertia\Response;

final class MaintenancePageController extends Controller
{
    public function __invoke(SiteSettingsRepository $settings): Response
    {
        $config = $settings->current();

        return Inertia::render('Maintenance', [
            'messageEn' => $config->maintenance_message_en,
            'messageAr' => $config->maintenance_message_ar,
            'appNameEn' => $config->app_name_en,
            'appNameAr' => $config->app_name_ar,
        ]);
    }
}
