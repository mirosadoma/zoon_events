<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use Inertia\Inertia;
use Inertia\Response;

final class LandingController extends Controller
{
    public function __invoke(SiteSettingsRepository $settings): Response
    {
        return Inertia::render('Landing', $settings->toPublicArray());
    }
}
