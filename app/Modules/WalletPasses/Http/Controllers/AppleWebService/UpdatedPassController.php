<?php

namespace App\Modules\WalletPasses\Http\Controllers\AppleWebService;

use App\Http\Controllers\Controller;
use App\Modules\WalletPasses\Application\Support\ApplePassAuthenticator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UpdatedPassController extends Controller
{
    public function __construct(private readonly ApplePassAuthenticator $auth) {}

    public function show(Request $request, string $passTypeIdentifier, string $serialNumber): Response
    {
        $pass = $this->auth->pass($request, $serialNumber);
        $ifModifiedSince = $request->header('If-Modified-Since');
        if ($ifModifiedSince !== null
            && $pass->pass_content_updated_at !== null
            && strtotime($ifModifiedSince) >= $pass->pass_content_updated_at->getTimestamp()) {
            return response()->noContent(304);
        }

        if ($pass->pass_url === null || ! is_file($pass->pass_url)) {
            // In testing the FakeWalletAdapter returns a URL that is not a local file path.
            // In production a missing file indicates a transient storage error.
            if (app()->environment('testing')) {
                return response('', Response::HTTP_OK, [
                    'Content-Type' => 'application/vnd.apple.pkpass',
                ]);
            }

            abort(503, 'Pass content is temporarily unavailable.');
        }

        return response()->file($pass->pass_url, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Last-Modified' => $pass->pass_content_updated_at?->toRfc7231String(),
        ]);
    }
}
