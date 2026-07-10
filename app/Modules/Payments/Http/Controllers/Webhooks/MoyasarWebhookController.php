<?php

namespace App\Modules\Payments\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Payments\Application\Jobs\ProcessMoyasarWebhookJob;
use App\Modules\Payments\Contracts\PaymentSecretLoader;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentWebhookReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MoyasarWebhookController extends Controller
{
    public function __invoke(Request $request, string $routeToken, PaymentSecretLoader $secrets, AuditWriter $audit): JsonResponse
    {
        $account = PaymentAccount::query()
            ->where('adapter_key', 'moyasar')
            ->where('status', 'active')
            ->where('webhook_route_token_hash', hash('sha256', $routeToken))
            ->first();
        if (! $account) {
            $audit->write('platform', null, 'payment.callback_denied', 'denied', reasonCode: 'route_unknown');
            throw new NotFoundHttpException;
        }
        $raw = $request->getContent();
        $reference = (string) config('payments.moyasar.webhook_secret_reference');
        $expected = hash_hmac('sha256', $raw, $secrets->load($reference));
        if (! hash_equals($expected, (string) $request->header('X-Moyasar-Signature'))) {
            $audit->write(
                'tenant',
                $account->tenant_id,
                'payment.callback_denied',
                'denied',
                reasonCode: 'signature_invalid',
                targetType: 'payment_account',
                targetId: $account->id,
            );
            throw new NotFoundHttpException;
        }
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:160'],
            'payment_id' => ['required', 'string', 'max:160'],
        ]);
        $receipt = PaymentWebhookReceipt::query()->firstOrCreate(
            ['payment_account_id' => $account->id, 'provider_event_id' => $validated['id']],
            [
                'payload_digest' => hash('sha256', $raw),
                'status' => 'received',
                'received_at' => now(),
            ],
        );
        if ($receipt->wasRecentlyCreated) {
            ProcessMoyasarWebhookJob::dispatchAfterResponse($receipt->id, $validated['payment_id']);
        }

        return response()->json(['status' => 'accepted'], 202);
    }
}
