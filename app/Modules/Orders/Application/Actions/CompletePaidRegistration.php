<?php

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Attendees\Contracts\AttendeeCreator;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Credentials\Contracts\CredentialIssuer;
use App\Modules\Events\Contracts\PublicOrderHostAuthorizer;
use App\Modules\Notifications\Contracts\ConfirmationIntentCreator;
use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Orders\Domain\PaidOrderResult;
use App\Modules\Orders\Domain\PayableOrder;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Ticketing\Contracts\PaidTicketAllocator;
use Illuminate\Support\Facades\DB;

final readonly class CompletePaidRegistration implements OrderPaymentPort
{
    public function __construct(
        private PublicOrderHostAuthorizer $hosts,
        private PersonalDataCipher $cipher,
        private AttendeeCreator $attendees,
        private CredentialIssuer $credentials,
        private ConfirmationIntentCreator $notifications,
        private PaidTicketAllocator $tickets,
        private AuditWriter $audit,
    ) {}

    public function payable(string $publicReference, string $accessToken, string $host): PayableOrder
    {
        $order = Order::query()->where('public_reference', $publicReference)->first();
        if ($order === null
            || ! hash_equals($order->access_token_hash, hash('sha256', $accessToken))
            || ! $this->hosts->allows($host, $order->tenant_id, $order->event_id)) {
            abort(404);
        }

        return new PayableOrder(
            $order->id,
            $order->tenant_id,
            $order->event_id,
            $order->public_reference,
            $order->total_minor,
            $order->currency,
            $order->status,
        );
    }

    public function completeCaptured(string $orderId, string $paymentAccountId, int $capturedMinor, string $currency, bool $live): PaidOrderResult
    {
        return DB::transaction(function () use ($orderId, $capturedMinor, $currency): PaidOrderResult {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            if ($order->status === 'paid') {
                return new PaidOrderResult($order->id, 'paid', null, null);
            }
            if ($order->status !== 'pending_payment'
                || $capturedMinor !== $order->total_minor
                || $currency !== $order->currency
                || $order->fulfillment_payload_ciphertext === null
                || $order->credential_expires_at === null) {
                throw Phase1Problem::make('payment_mismatch');
            }
            $identity = json_decode(
                $this->cipher->decrypt(
                    [
                        'key_id' => $order->fulfillment_encryption_key_id,
                        'ciphertext' => $order->fulfillment_payload_ciphertext,
                    ],
                    "{$order->tenant_id}:{$order->event_id}:paid-fulfillment",
                ),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $item = OrderItem::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $attendee = $this->attendees->create(
                $order->tenant_id,
                $order->event_id,
                $order->id,
                $item->id,
                $item->ticket_type_id,
                $order->submission_id,
                $identity,
                $order->locale,
                $order->event_venue_id !== null ? (string) $order->event_venue_id : null,
            );
            $item->forceFill(['attendee_id' => $attendee->id])->save();
            $credential = $this->credentials->issue(
                $order->tenant_id,
                $order->event_id,
                $attendee->id,
                $item->ticket_type_id,
                $order->credential_expires_at,
            );
            $this->tickets->linkAndConvert($order->tenant_id, $order->inventory_hold_id, $order->id);
            if ($credential !== null) {
                $this->notifications->create(
                    $order->tenant_id,
                    $order->event_id,
                    $attendee->id,
                    $order->id,
                    $credential->id,
                    $identity['email'],
                    $order->locale,
                    $identity['phone'] ?? null,
                );
            }
            $order->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'fulfillment_payload_ciphertext' => null,
                'fulfillment_encryption_key_id' => null,
            ])->save();
            $this->audit->write(
                'tenant',
                $order->tenant_id,
                'registration.paid_completed',
                'succeeded',
                targetType: 'order',
                targetId: $order->id,
                metadata: [
                    'event_id' => $order->event_id,
                    'attendee_id' => $attendee->id,
                ],
            );

            return new PaidOrderResult($order->id, 'paid', $credential?->id, $credential?->token);
        }, 3);
    }
}
