<?php

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Ticketing\Contracts\TicketHoldReleaser;
use Illuminate\Support\Facades\DB;

final readonly class CancelPendingRegistration
{
    public function __construct(
        private TicketHoldReleaser $holds,
        private AuditWriter $audit,
    ) {}

    public function execute(Order $order, string $reason = 'payment_failed'): void
    {
        if ($order->status !== 'pending_payment') {
            return;
        }

        DB::transaction(function () use ($order, $reason): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'pending_payment') {
                return;
            }

            if ($locked->inventory_hold_id !== null) {
                $this->holds->release($locked->tenant_id, $locked->inventory_hold_id, $reason);
            }

            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'fulfillment_payload_ciphertext' => null,
                'fulfillment_encryption_key_id' => null,
            ])->save();

            $this->audit->write(
                'tenant',
                $locked->tenant_id,
                'registration.paid_cancelled',
                'succeeded',
                targetType: 'order',
                targetId: $locked->id,
                metadata: ['event_id' => $locked->event_id, 'reason' => $reason],
            );
        }, 3);
    }
}
