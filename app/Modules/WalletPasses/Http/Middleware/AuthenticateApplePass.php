<?php

namespace App\Modules\WalletPasses\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApplePass
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'ApplePass ')) {
            abort(401);
        }

        $passTypeIdentifier = $request->route('pass_type_identifier');
        if ($passTypeIdentifier !== null) {
            $expected = (string) config('wallet.apple.pass_type_identifier');
            if ($expected !== '' && $passTypeIdentifier !== $expected) {
                abort(401);
            }
        }

        return $next($request);
    }
}
