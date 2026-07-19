<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use Inertia\Inertia;
use Inertia\Response;

final class MarketingSiteController extends Controller
{
    public function about(SiteSettingsRepository $settings): Response
    {
        return Inertia::render('marketing/About', $settings->toPublicArray());
    }

    public function solutions(SiteSettingsRepository $settings): Response
    {
        return Inertia::render('marketing/Solutions', $settings->toPublicArray());
    }

    public function contact(SiteSettingsRepository $settings): Response
    {
        return Inertia::render('marketing/Contact', $settings->toPublicArray());
    }

    public function privacy(SiteSettingsRepository $settings): Response
    {
        return Inertia::render('marketing/Privacy', $settings->toPublicArray());
    }

    public function terms(SiteSettingsRepository $settings): Response
    {
        return Inertia::render('marketing/Terms', $settings->toPublicArray());
    }
}
