<?php

namespace App\Modules\WalletPasses\Http\Controllers\AppleWebService;

use App\Http\Controllers\Controller;
use App\Modules\WalletPasses\Application\Support\ApplePassAuthenticator;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPassAppleDeviceRegistration;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UnregisterDeviceController extends Controller
{
    public function __construct(private readonly ApplePassAuthenticator $auth) {}

    public function destroy(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier, string $serialNumber): Response
    {
        $pass = $this->auth->pass($request, $serialNumber);
        WalletPassAppleDeviceRegistration::query()
            ->where('wallet_pass_id', $pass->id)
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->update(['unregistered_at' => now()]);

        return response()->noContent(200);
    }
}
