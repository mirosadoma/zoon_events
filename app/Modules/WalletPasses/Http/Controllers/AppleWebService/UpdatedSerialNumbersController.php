<?php

namespace App\Modules\WalletPasses\Http\Controllers\AppleWebService;

use App\Http\Controllers\Controller;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPassAppleDeviceRegistration;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UpdatedSerialNumbersController extends Controller
{
    public function index(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier): Response
    {
        $expectedPassTypeId = (string) config('wallet.apple.pass_type_identifier');
        if ($expectedPassTypeId !== '' && $passTypeIdentifier !== $expectedPassTypeId) {
            abort(401);
        }

        $registrations = WalletPassAppleDeviceRegistration::query()
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->whereNull('unregistered_at')
            ->pluck('wallet_pass_id');

        if ($registrations->isEmpty()) {
            return response()->noContent(204);
        }

        $since = $request->query('passesUpdatedSince');
        $query = WalletPass::query()
            ->whereIn('id', $registrations)
            ->where('provider', 'apple')
            ->whereNull('superseded_by_id');

        if (is_string($since) && $since !== '') {
            $query->where('pass_content_updated_at', '>', $since);
        }

        $serialNumbers = $query->pluck('pass_serial_number')->values()->all();
        if ($serialNumbers === []) {
            return response()->noContent(204);
        }

        return response()->json([
            'serialNumbers' => $serialNumbers,
            'lastUpdated' => now()->toIso8601String(),
        ]);
    }
}
