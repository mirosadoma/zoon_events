<?php

namespace App\Modules\WalletPasses\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\WalletPasses\Application\Actions\GenerateWalletPassAction;
use App\Modules\WalletPasses\Application\Support\PublicOrderWalletContext;
use App\Modules\WalletPasses\Http\Requests\GenerateWalletPassRequest;
use Symfony\Component\HttpFoundation\Response;

final class ApplePassController extends Controller
{
    public function show(
        GenerateWalletPassRequest $request,
        string $publicReference,
        PublicOrderWalletContext $context,
        GenerateWalletPassAction $action,
    ): Response {
        $resolved = $context->resolve($request, $publicReference);
        $pass = $action->execute(
            $resolved['order']->tenant_id,
            $resolved['order']->event_id,
            $resolved['attendee']->id,
            $resolved['credential']->id,
            'apple',
            app()->getLocale(),
        );

        if ($pass->pass_url === null || ! is_file($pass->pass_url)) {
            return response('', Response::HTTP_OK, [
                'Content-Type' => 'application/vnd.apple.pkpass',
            ]);
        }

        return response()->file($pass->pass_url, [
            'Content-Type' => 'application/vnd.apple.pkpass',
        ]);
    }
}
