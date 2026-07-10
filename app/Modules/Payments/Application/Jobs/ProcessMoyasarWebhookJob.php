<?php

namespace App\Modules\Payments\Application\Jobs;

use App\Modules\Payments\Application\Webhooks\ProcessMoyasarWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessMoyasarWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $receiptId,
        public readonly string $providerPaymentId,
    ) {}

    public function handle(ProcessMoyasarWebhook $processor): void
    {
        $processor->execute($this->receiptId, $this->providerPaymentId);
    }
}
