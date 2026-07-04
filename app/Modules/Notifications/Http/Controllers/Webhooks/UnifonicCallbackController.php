<?php

namespace App\Modules\Notifications\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Notifications\Application\Actions\ProcessNotificationCallback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UnifonicCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        string $route_token,
        ProcessNotificationCallback $processor,
        AuditWriter $audit,
    ): JsonResponse {
        $expected = (string) config('notifications.unifonic.callback_route_token');
        if ($expected === '' || ! hash_equals($expected, $route_token)) {
            $audit->write('platform', null, 'notification.callback_denied', 'denied', reasonCode: 'route_unknown');
            throw new NotFoundHttpException;
        }
        $validated = $request->validate([
            'MessageID' => ['required', 'string', 'max:160'],
            'Status' => ['required', 'string', 'max:40'],
        ]);
        if (! $processor->handle($validated['MessageID'], $validated['Status'])) {
            $audit->write('platform', null, 'notification.callback_denied', 'denied', reasonCode: 'message_unknown');
        }

        return response()->json(['accepted' => true], 202);
    }
}
