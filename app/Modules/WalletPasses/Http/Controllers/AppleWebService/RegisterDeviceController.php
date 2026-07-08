<?php

namespace App\Modules\WalletPasses\Http\Controllers\AppleWebService;

use App\Http\Controllers\Controller;
use App\Modules\WalletPasses\Application\Support\ApplePassAuthenticator;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPassAppleDeviceRegistration;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RegisterDeviceController extends Controller
{
    public function __construct(private readonly ApplePassAuthenticator $auth) {}

    public function store(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier, string $serialNumber): Response
    {
        $pass = $this->auth->pass($request, $serialNumber);
        $pushToken = (string) $request->input('pushToken');
        $existing = WalletPassAppleDeviceRegistration::query()
            ->where('wallet_pass_id', $pass->id)
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->first();

        if ($existing !== null && $existing->unregistered_at === null) {
            $existing->forceFill(['push_token' => $pushToken, 'registered_at' => now()])->save();

            return response()->noContent(200);
        }

        WalletPassAppleDeviceRegistration::query()->updateOrCreate(
            ['wallet_pass_id' => $pass->id, 'device_library_identifier' => $deviceLibraryIdentifier],
            [
                'tenant_id' => $pass->tenant_id,
                'push_token' => $pushToken,
                'registered_at' => now(),
                'unregistered_at' => null,
            ],
        );

        return response()->noContent(201);
    }
}
