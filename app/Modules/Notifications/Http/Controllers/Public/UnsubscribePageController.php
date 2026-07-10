<?php

namespace App\Modules\Notifications\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UnsubscribePageController extends Controller
{
    public function show(Request $request): View
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return view('public.unsubscribe', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'alternateLocale' => $locale === 'ar' ? 'en' : 'ar',
            'unsubscribed' => $request->session()->get('unsubscribed', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()
            ->route('public.notifications.unsubscribe', ['locale' => app()->getLocale() === 'ar' ? 'ar' : 'en'])
            ->with('unsubscribed', true);
    }
}
