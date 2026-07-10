<?php

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Payments\Domain\Events\PaymentStateChanged;
use App\Modules\Payments\Domain\PaymentIntentOutcome;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use Illuminate\Support\Facades\DB;

final readonly class ApplyAuthoritativePaymentResult
{
    public function __construct(
        private OrderPaymentPort $orders,
        private AuditWriter $audit,
    ) {}

    public function execute(PaymentAttempt $attempt, PaymentAccount $account, PaymentResult $result): PaymentIntentOutcome
    {
        if ($result->currency !== $attempt->currency
            || $result->capturedMinor > $attempt->requested_minor
            || ($result->status === PaymentStatus::Captured && $result->capturedMinor !== $attempt->requested_minor)) {
            $this->audit->write(
                'tenant',
                $attempt->tenant_id,
                'payment.denied',
                'denied',
                reasonCode: 'payment_mismatch',
                targetType: 'payment_attempt',
                targetId: $attempt->id,
            );
            throw Phase1Problem::make('payment_mismatch');
        }

        return DB::transaction(function () use ($attempt, $account, $result): PaymentIntentOutcome {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status === 'captured') {
                return new PaymentIntentOutcome($attempt->id, 'captured', null, null);
            }
            $status = match ($result->status) {
                PaymentStatus::ActionRequired => 'pending',
                PaymentStatus::Authorized => 'authorized',
                PaymentStatus::Captured => 'captured',
                PaymentStatus::Failed => 'failed',
                PaymentStatus::Cancelled => 'cancelled',
                PaymentStatus::Refunded => 'refunded',
                PaymentStatus::PartiallyRefunded => 'partially_refunded',
                PaymentStatus::Unknown, PaymentStatus::Pending => 'unknown',
            };
            $attempt->forceFill([
                'provider_payment_id' => $result->providerPaymentId,
                'provider_payment_id_hash' => $result->providerPaymentId === null ? null : hash('sha256', $result->providerPaymentId),
                'status' => $status,
                'captured_minor' => $result->capturedMinor,
                'refunded_minor' => $result->refundedMinor,
                'provider_reason_code' => $result->reasonCode,
                'last_reconciled_at' => now(),
                'next_reconcile_at' => in_array($status, ['pending', 'unknown'], true) ? now()->addMinutes(2) : null,
            ])->save();
            if ($result->status === PaymentStatus::Captured) {
                $this->orders->completeCaptured(
                    $attempt->order_id,
                    $account->id,
                    $result->capturedMinor,
                    $result->currency,
                    $account->mode === 'live',
                );
            }
            $this->audit->write(
                'tenant',
                $attempt->tenant_id,
                'payment.'.$status,
                in_array($status, ['failed', 'unknown'], true) ? 'failed' : 'succeeded',
                reasonCode: $result->reasonCode,
                targetType: 'payment_attempt',
                targetId: $attempt->id,
                metadata: ['event_id' => $attempt->event_id],
            );
            event(new PaymentStateChanged(
                $attempt->tenant_id,
                $attempt->event_id,
                $attempt->id,
                $status,
                $result->reasonCode,
            ));

            return new PaymentIntentOutcome($attempt->id, $result->status->value, $result->action, $result->reasonCode);
        }, 3);
    }
}
