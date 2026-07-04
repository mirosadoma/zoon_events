<?php

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Payments\Application\PaymentGatewayRegistry;
use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentIntentOutcome;
use App\Modules\Payments\Domain\PaymentRequest;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

final readonly class CreatePaymentIntent
{
    public function __construct(
        private OrderPaymentPort $orders,
        private PaymentGatewayRegistry $gateways,
        private ApplyAuthoritativePaymentResult $apply,
    ) {}

    public function execute(string $publicReference, string $accessToken, string $host, string $idempotencyKey, string $returnUrl): PaymentIntentOutcome
    {
        $order = $this->orders->payable($publicReference, $accessToken, $host);
        $account = PaymentAccount::query()
            ->where('tenant_id', $order->tenantId)
            ->where('currency', $order->currency)
            ->where('status', 'active')
            ->firstOrFail();
        $hash = hash('sha256', $idempotencyKey);
        $existing = PaymentAttempt::query()
            ->where('payment_account_id', $account->id)
            ->where('idempotency_key_hash', $hash)
            ->first();
        if ($existing !== null) {
            return new PaymentIntentOutcome($existing->id, $existing->status, null, $existing->provider_reason_code);
        }
        $attempt = DB::transaction(function () use ($order, $account, $hash): PaymentAttempt {
            return PaymentAttempt::query()->create([
                'tenant_id' => $order->tenantId,
                'event_id' => $order->eventId,
                'order_id' => $order->id,
                'payment_account_id' => $account->id,
                'attempt_number' => (int) PaymentAttempt::query()->where('order_id', $order->id)->max('attempt_number') + 1,
                'idempotency_key_hash' => $hash,
                'status' => 'pending',
                'requested_minor' => $order->totalMinor,
                'captured_minor' => 0,
                'refunded_minor' => 0,
                'currency' => $order->currency,
                'next_reconcile_at' => now()->addMinutes(2),
            ]);
        });
        $context = new PaymentContext(
            $order->tenantId,
            $account->id,
            request()->header('X-Correlation-ID', ''),
            $idempotencyKey,
            $account->mode === 'live',
            (int) config('payments.timeout_ms'),
        );
        try {
            $result = $this->gateways->get($account->adapter_key)->create(
                $context,
                new PaymentRequest($order->publicReference, $order->totalMinor, $order->currency, $returnUrl),
            );
        } catch (\Throwable) {
            $result = new PaymentResult(PaymentStatus::Unknown, null, 0, 0, $order->currency, 'unknown_outcome');
        }

        return $this->apply->execute($attempt, $account, $result);
    }
}
