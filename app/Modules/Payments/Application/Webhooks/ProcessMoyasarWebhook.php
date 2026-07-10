<?php

namespace App\Modules\Payments\Application\Webhooks;

use App\Modules\Payments\Application\Actions\ReconcilePaymentAttempt;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentWebhookReceipt;

final readonly class ProcessMoyasarWebhook
{
    public function __construct(private ReconcilePaymentAttempt $reconcile) {}

    public function execute(string $receiptId, string $providerPaymentId): void
    {
        $receipt = PaymentWebhookReceipt::query()->findOrFail($receiptId);
        if ($receipt->status === 'processed') {
            return;
        }
        $attempt = PaymentAttempt::query()
            ->where('payment_account_id', $receipt->payment_account_id)
            ->where('provider_payment_id_hash', hash('sha256', $providerPaymentId))
            ->first();
        if ($attempt === null) {
            $receipt->forceFill([
                'status' => 'ignored',
                'reason_code' => 'payment_not_found',
                'processed_at' => now(),
            ])->save();

            return;
        }
        $this->reconcile->execute($attempt);
        $receipt->forceFill(['status' => 'processed', 'processed_at' => now()])->save();
    }
}
