<?php

namespace App\Modules\WalletPasses\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\WalletPasses\Application\Actions\GenerateWalletPassAction;
use App\Modules\WalletPasses\Application\Support\PublicOrderWalletContext;
use App\Modules\WalletPasses\Http\Requests\GenerateWalletPassRequest;
use App\Modules\WalletPasses\Http\Resources\WalletPassStatusResource;

final class GoogleWalletPassController extends Controller
{
    use RespondsWithApi;

    public function show(
        GenerateWalletPassRequest $request,
        string $publicReference,
        PublicOrderWalletContext $context,
        GenerateWalletPassAction $action,
    ) {
        $resolved = $context->resolve($request, $publicReference);
        $pass = $action->execute(
            $resolved['order']->tenant_id,
            $resolved['order']->event_id,
            $resolved['attendee']->id,
            $resolved['credential']->id,
            'google',
            app()->getLocale(),
        );

        return $this->success(new WalletPassStatusResource($pass));
    }
}
