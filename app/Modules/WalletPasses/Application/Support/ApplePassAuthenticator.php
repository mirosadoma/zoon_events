<?php

namespace App\Modules\WalletPasses\Application\Support;

use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Illuminate\Http\Request;

final class ApplePassAuthenticator
{
    public function pass(Request $request, string $serialNumber): WalletPass
    {
        $token = $this->token($request);
        $pass = WalletPass::query()
            ->where('provider', 'apple')
            ->where('pass_serial_number', $serialNumber)
            ->first();

        if ($pass === null
            || $pass->apple_authentication_token === null
            || ! hash_equals($pass->apple_authentication_token, $token)) {
            abort(401);
        }

        return $pass;
    }

    public function token(Request $request): string
    {
        $header = (string) $request->header('Authorization');
        if (! str_starts_with($header, 'ApplePass ')) {
            abort(401);
        }

        return substr($header, strlen('ApplePass '));
    }
}
