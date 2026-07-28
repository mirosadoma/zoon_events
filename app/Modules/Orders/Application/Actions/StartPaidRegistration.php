<?php

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Orders\Domain\CompletedRegistration;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use App\Modules\Registration\Contracts\SubmissionCreator;
use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
use App\Modules\Ticketing\Contracts\PaidTicketAllocator;
use Illuminate\Support\Facades\DB;

final readonly class StartPaidRegistration
{
    public function __construct(
        private SubmissionCreator $submissions,
        private PaidTicketAllocator $tickets,
        private PersonalDataGuard $guard,
        private AuditWriter $audit,
    ) {}

    public function execute(FreeRegistrationInput $input): CompletedRegistration
    {
        $reference = 'ord_'.substr(hash_hmac('sha256', $input->idempotencyKey, (string) config('app.key')), 0, 32);
        $existing = Order::query()
            ->where('tenant_id', $input->tenantId)
            ->where('event_id', $input->eventId)
            ->where('public_reference', $reference)
            ->first();
        if ($existing !== null) {
            return new CompletedRegistration($existing->id, $reference, null, null, null, null, true);
        }

        return DB::transaction(function () use ($input, $reference): CompletedRegistration {
            $submission = $this->submissions->create(
                $input->tenantId,
                $input->eventId,
                $input->formVersionId,
                $input->idempotencyKey,
                $input->answers,
                $input->consent,
                $input->locale,
            );
            $allocation = $this->tickets->reserve($input->tenantId, $input->eventId, $input->ticketTypeId);
            $priceMinor = $input->priceMinorOverride ?? $allocation->priceMinor;
            $currency = $input->currencyOverride ?? $allocation->currency;
            $accessToken = sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $orderScope = "{$input->tenantId}:{$input->eventId}:order";
            $fulfillmentScope = "{$input->tenantId}:{$input->eventId}:paid-fulfillment";
            $encrypt = fn (string $value): array => $this->guard->encryptString($value, $orderScope);
            $buyerName = $encrypt($input->buyer['first_name'].' '.$input->buyer['last_name']);
            $buyerEmail = $encrypt($input->buyer['email']);
            $buyerPhone = isset($input->buyer['phone']) ? $encrypt($input->buyer['phone']) : null;
            $fulfillment = $this->guard->encryptJson($input->attendee, $fulfillmentScope);
            $order = Order::query()->create([
                'tenant_id' => $input->tenantId,
                'event_id' => $input->eventId,
                'public_reference' => $reference,
                'access_token_hash' => hash('sha256', $accessToken),
                'status' => 'pending_payment',
                'buyer_name_ciphertext' => $buyerName['ciphertext'],
                'buyer_email_ciphertext' => $buyerEmail['ciphertext'],
                'buyer_phone_ciphertext' => $buyerPhone['ciphertext'] ?? null,
                'buyer_email_index' => $this->guard->emailIndex($input->buyer['email']),
                'buyer_phone_index' => isset($input->buyer['phone']) ? $this->guard->phoneIndex($input->buyer['phone']) : null,
                'encryption_key_id' => $buyerName['key_id'],
                'subtotal_minor' => $priceMinor,
                'tax_minor' => 0,
                'fees_minor' => 0,
                'total_minor' => $priceMinor,
                'currency' => $currency,
                'inventory_hold_id' => $allocation->holdId,
                'submission_id' => $submission->id,
                'event_category_id' => $input->eventCategoryId,
                'event_venue_id' => $input->eventVenueId !== null && $input->eventVenueId !== ''
                    ? (int) $input->eventVenueId
                    : null,
                'fulfillment_payload_ciphertext' => $fulfillment['ciphertext'],
                'fulfillment_encryption_key_id' => $fulfillment['key_id'],
                'credential_expires_at' => $input->credentialExpiresAt,
                'locale' => $input->locale,
            ]);
            OrderItem::query()->create([
                'tenant_id' => $input->tenantId,
                'event_id' => $input->eventId,
                'order_id' => $order->id,
                'ticket_type_id' => $allocation->ticketTypeId,
                'quantity' => 1,
                'unit_price_minor' => $priceMinor,
                'tax_minor' => 0,
                'fees_minor' => 0,
                'total_minor' => $priceMinor,
                'currency' => $currency,
                'price_tier_id' => $input->priceMinorOverride !== null ? null : $allocation->priceTierId,
                'ticket_name_snapshot' => $allocation->ticketName,
            ]);
            $this->audit->write(
                'tenant',
                $input->tenantId,
                'registration.paid_started',
                'succeeded',
                targetType: 'order',
                targetId: $order->id,
                metadata: ['event_id' => $input->eventId],
            );

            return new CompletedRegistration($order->id, $reference, $accessToken, null, null, null, false);
        }, 3);
    }
}
