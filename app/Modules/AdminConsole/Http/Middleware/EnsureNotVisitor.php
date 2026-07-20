<?php

namespace App\Modules\AdminConsole\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureNotVisitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->isVisitor()) {
            $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

            return redirect("/{$locale}/visitor");
        }

        return $next($request);
    }
}
