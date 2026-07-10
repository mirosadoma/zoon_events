<?php

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Application\PaymentGatewayRegistry;
use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentIntentOutcome;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;

final readonly class ReconcilePaymentAttempt
{
    public function __construct(
        private PaymentGatewayRegistry $gateways,
        private ApplyAuthoritativePaymentResult $apply,
    ) {}

    public function execute(PaymentAttempt $attempt): PaymentIntentOutcome
    {
        $account = PaymentAccount::query()
            ->where('tenant_id', $attempt->tenant_id)
            ->where('status', 'active')
            ->findOrFail($attempt->payment_account_id);
        if ($attempt->provider_payment_id === null) {
            $attempt->forceFill([
                'status' => 'unknown',
                'provider_reason_code' => 'unknown_outcome',
                'last_reconciled_at' => now(),
                'next_reconcile_at' => now()->addMinutes(5),
            ])->save();

            return new PaymentIntentOutcome($attempt->id, 'unknown', null, 'unknown_outcome');
        }
        $result = $this->gateways->get($account->adapter_key)->fetch(
            new PaymentContext(
                $attempt->tenant_id,
                $account->id,
                '',
                'reconcile-'.$attempt->id,
                $account->mode === 'live',
                (int) config('payments.timeout_ms'),
            ),
            $attempt->provider_payment_id,
        );

        return $this->apply->execute($attempt, $account, $result);
    }
}
