<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Support\LocaleDetector;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,ar'],
        ]);

        $locale = $validated['locale'];
        Cookie::queue('locale', $locale, 60 * 24 * 365);

        $user = $request->user();

        if ($user instanceof User && $user->preferred_locale !== $locale) {
            $user->forceFill(['preferred_locale' => $locale])->save();
        }

        $path = $request->headers->get('referer')
            ? parse_url((string) $request->headers->get('referer'), PHP_URL_PATH)
            : null;
        $path = is_string($path) ? $path : '/'.LocaleDetector::detect($request).'/dashboard';
        $path = preg_replace('#^/(en|ar)(?=/|$)#', "/{$locale}", $path) ?? "/{$locale}";

        return redirect($path);
    }
}
