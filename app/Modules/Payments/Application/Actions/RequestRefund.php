<?php

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Orders\Contracts\OrderRefundPort;
use App\Modules\Payments\Application\PaymentGatewayRegistry;
use App\Modules\Payments\Domain\Events\RefundStateChanged;
use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;
use App\Modules\Payments\Infrastructure\Persistence\Models\Refund;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class RequestRefund
{
    public function __construct(
        private OrderRefundPort $orders,
        private PaymentGatewayRegistry $gateways,
        private AuditWriter $audit,
    ) {}

    public function execute(TenantContext $context, string $eventId, string $orderId, int $amountMinor, string $reason, string $idempotencyKey): Refund
    {
        $order = $this->orders->refundable($context->tenant->id, $eventId, $orderId);
        $attempt = PaymentAttempt::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->where('order_id', $orderId)
            ->latest('attempt_number')
            ->firstOrFail();
        if ($attempt->captured_minor < 1 || ! in_array($attempt->status, ['captured', 'partially_refunded', 'refunded'], true)) {
            throw Phase1Problem::make('refund_not_allowed');
        }
        $committed = (int) Refund::query()
            ->where('payment_attempt_id', $attempt->id)
            ->whereIn('status', ['pending', 'succeeded', 'unknown'])
            ->sum('amount_minor');
        if ($amountMinor < 1 || $committed + $amountMinor > $attempt->captured_minor) {
            throw Phase1Problem::make('refund_not_allowed');
        }
        $hash = hash('sha256', $idempotencyKey);
        $existing = Refund::query()
            ->where('payment_attempt_id', $attempt->id)
            ->where('idempotency_key_hash', $hash)
            ->first();
        if ($existing !== null) {
            return $existing;
        }
        $refund = DB::transaction(fn () => Refund::query()->create([
            'tenant_id' => $context->tenant->id,
            'event_id' => $eventId,
            'order_id' => $orderId,
            'payment_attempt_id' => $attempt->id,
            'amount_minor' => $amountMinor,
            'currency' => $order->currency,
            'status' => 'pending',
            'reason' => $reason,
            'requested_by_user_id' => $context->actor->id,
            'idempotency_key_hash' => $hash,
        ]));
        $account = PaymentAccount::query()->findOrFail($attempt->payment_account_id);
        try {
            $result = $this->gateways->get($account->adapter_key)->refund(
                new PaymentContext(
                    $context->tenant->id,
                    $account->id,
                    '',
                    $idempotencyKey,
                    $account->mode === 'live',
                    (int) config('payments.timeout_ms'),
                ),
                (string) $attempt->provider_payment_id,
                $amountMinor,
                $order->currency,
            );
        } catch (\Throwable) {
            $result = new PaymentResult(PaymentStatus::Unknown, null, $attempt->captured_minor, $attempt->refunded_minor, $order->currency, 'unknown_outcome');
        }

        return DB::transaction(function () use ($context, $refund, $attempt, $result): Refund {
            $status = match ($result->status) {
                PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded => 'succeeded',
                PaymentStatus::Failed, PaymentStatus::Cancelled => 'failed',
                default => 'unknown',
            };
            $refund->forceFill([
                'status' => $status,
                'provider_refund_id' => $result->providerPaymentId,
                'last_reconciled_at' => now(),
            ])->save();
            if ($status === 'succeeded') {
                $attempt->forceFill([
                    'refunded_minor' => $result->refundedMinor,
                    'status' => $result->refundedMinor >= $attempt->captured_minor ? 'refunded' : 'partially_refunded',
                ])->save();
                $this->orders->applyRefund($attempt->order_id, $result->refundedMinor);
            }
            $this->audit->writeTenant(
                'refund.'.$status,
                $status === 'failed' ? 'failed' : 'succeeded',
                $context,
                $result->reasonCode,
                'refund',
                $refund->id,
                ['event_id' => $attempt->event_id],
            );
            event(new RefundStateChanged($attempt->tenant_id, $attempt->event_id, $refund->id, $status));

            return $refund->refresh();
        });
    }
}
