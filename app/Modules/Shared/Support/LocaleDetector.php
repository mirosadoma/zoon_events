<?php

namespace App\Modules\Shared\Support;

use App\Models\User;
use App\Modules\Shared\Domain\Locale;
use Illuminate\Http\Request;

final class LocaleDetector
{
    public static function fromPath(Request $request): ?string
    {
        $first = explode('/', trim($request->path(), '/'))[0] ?? '';

        return in_array($first, ['en', 'ar'], true) ? $first : null;
    }

    public static function fromReferer(Request $request): ?string
    {
        $referer = $request->headers->get('Referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $first = explode('/', trim($path, '/'))[0] ?? '';

        return in_array($first, ['en', 'ar'], true) ? $first : null;
    }

    public static function detect(Request $request): string
    {
        $routeLocale = $request->route('locale');

        if (is_string($routeLocale) && in_array($routeLocale, ['en', 'ar'], true)) {
            return $routeLocale;
        }

        $pathLocale = self::fromPath($request);

        if ($pathLocale !== null) {
            return $pathLocale;
        }

        $refererLocale = self::fromReferer($request);

        if ($refererLocale !== null) {
            return $refererLocale;
        }

        $cookie = $request->cookie('locale');

        if (is_string($cookie)) {
            $normalized = strtolower(substr($cookie, 0, 2));

            if (in_array($normalized, ['en', 'ar'], true)) {
                return $normalized;
            }
        }

        $user = $request->user();

        if ($user instanceof User && in_array($user->preferred_locale, ['en', 'ar'], true)) {
            return $user->preferred_locale;
        }

        foreach ($request->getLanguages() as $candidate) {
            $normalized = strtolower(substr($candidate, 0, 2));

            if ($normalized === Locale::Arabic->value) {
                return Locale::Arabic->value;
            }

            if ($normalized === Locale::English->value) {
                return Locale::English->value;
            }
        }

        return Locale::default()->value;
    }
}
