<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\Actions\CreatePaymentIntent;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\Request;

final class PublicPaymentIntentController extends Controller
{
    use RespondsWithApi;

    public function store(Request $request, string $publicReference, CreatePaymentIntent $action)
    {
        $validated = $request->validate(['return_url' => ['required', 'url', 'max:2048']]);
        $outcome = $action->execute(
            $publicReference,
            (string) $request->header('X-Order-Access-Token'),
            $request->getHost(),
            (string) $request->header('Idempotency-Key'),
            $validated['return_url'],
        );

        return $this->success([
            'attempt_id' => $outcome->attemptId,
            'status' => $outcome->status,
            'action' => $outcome->action,
            'reason_code' => $outcome->reasonCode,
        ], 201);
    }
}
