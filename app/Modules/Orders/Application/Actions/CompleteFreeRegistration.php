<?php

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Attendees\Contracts\AttendeeCreator;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Credentials\Application\CredentialIssuerService;
use App\Modules\Credentials\Contracts\CredentialIssuer;
use App\Modules\Notifications\Contracts\ConfirmationIntentCreator;
use App\Modules\Orders\Application\Support\CompletedRegistrationResolver;
use App\Modules\Orders\Domain\CompletedRegistration;
use App\Modules\Orders\Domain\Events\FreeRegistrationCompleted;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use App\Modules\Registration\Contracts\SubmissionCreator;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Ticketing\Contracts\FreeTicketAllocator;
use Illuminate\Support\Facades\DB;

final readonly class CompleteFreeRegistration
{
    public function __construct(
        private SubmissionCreator $submissions,
        private FreeTicketAllocator $tickets,
        private AttendeeCreator $attendees,
        private CredentialIssuer $credentials,
        private CredentialIssuerService $directCredentials,
        private ConfirmationIntentCreator $notifications,
        private PersonalDataCipher $cipher,
        private BlindIndex $indexes,
        private AuditWriter $audit,
        private CompletedRegistrationResolver $completedRegistrations,
    ) {}

    public function execute(FreeRegistrationInput $input): CompletedRegistration
    {
        $reference = 'ord_'.substr(hash_hmac('sha256', $input->idempotencyKey, (string) config('app.key')), 0, 32);
        $existing = Order::query()->where('tenant_id', $input->tenantId)->where('event_id', $input->eventId)->where('public_reference', $reference)->first();
        if ($existing !== null) {
            return $this->completedRegistrations->fromExistingOrder($existing);
        }

        return DB::transaction(function () use ($input, $reference): CompletedRegistration {
            $submission = $this->submissions->create(
                $input->tenantId, $input->eventId, $input->formVersionId,
                $input->idempotencyKey, $input->answers, $input->consent, $input->locale,
            );
            $allocation = $this->tickets->reserve($input->tenantId, $input->eventId, $input->ticketTypeId);
            $accessToken = sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $scope = "{$input->tenantId}:{$input->eventId}:order";
            $encrypt = fn (string $value): string => $this->cipher->encrypt($value, $scope)['ciphertext'];
            $order = Order::query()->create([
                'tenant_id' => $input->tenantId,
                'event_id' => $input->eventId,
                'public_reference' => $reference,
                'access_token_hash' => hash('sha256', $accessToken),
                'status' => 'paid',
                'buyer_name_ciphertext' => $encrypt($input->buyer['first_name'].' '.$input->buyer['last_name']),
                'buyer_email_ciphertext' => $encrypt($input->buyer['email']),
                'buyer_phone_ciphertext' => isset($input->buyer['phone']) ? $encrypt($input->buyer['phone']) : null,
                'buyer_email_index' => $this->indexes->email($input->buyer['email']),
                'buyer_phone_index' => isset($input->buyer['phone']) ? $this->indexes->phone($input->buyer['phone']) : null,
                'encryption_key_id' => $submission->encryptionKeyId,
                'subtotal_minor' => 0, 'tax_minor' => 0, 'fees_minor' => 0, 'total_minor' => 0,
                'currency' => $allocation->currency,
                'inventory_hold_id' => $allocation->holdId,
                'submission_id' => $submission->id,
                'credential_expires_at' => $input->credentialExpiresAt,
                'locale' => $input->locale,
                'paid_at' => now(),
            ]);
            $item = OrderItem::query()->create([
                'tenant_id' => $input->tenantId, 'event_id' => $input->eventId,
                'order_id' => $order->id, 'ticket_type_id' => $allocation->ticketTypeId,
                'quantity' => 1, 'unit_price_minor' => 0, 'tax_minor' => 0, 'fees_minor' => 0,
                'total_minor' => 0, 'currency' => $allocation->currency,
                'ticket_name_snapshot' => $allocation->ticketName,
            ]);
            $attendee = $this->attendees->create(
                $input->tenantId, $input->eventId, $order->id, $item->id,
                $allocation->ticketTypeId, $submission->id, $input->attendee, $input->locale,
            );
            $item->forceFill(['attendee_id' => $attendee->id])->save();
            $issuer = $input->bypassIdentityGateForCredential ? $this->directCredentials : $this->credentials;
            $credential = $issuer->issue(
                $input->tenantId, $input->eventId, $attendee->id,
                $allocation->ticketTypeId, $input->credentialExpiresAt,
            );
            $this->tickets->linkAndConvert($input->tenantId, $allocation->holdId, $order->id);
            $this->notifications->create(
                $input->tenantId,
                $input->eventId,
                $attendee->id,
                $order->id,
                $credential?->id,
                $input->attendee['email'],
                $input->locale,
                $input->attendee['phone'] ?? null,
            );
            $this->audit->write(
                'tenant', $input->tenantId, 'registration.free_completed', 'succeeded',
                targetType: 'order', targetId: $order->id,
                metadata: ['event_id' => $input->eventId],
            );
            if ($credential !== null) {
                event(new FreeRegistrationCompleted(
                    $input->tenantId,
                    $input->eventId,
                    $order->id,
                    $attendee->id,
                    $credential->id,
                ));
            }

            return new CompletedRegistration(
                $order->id,
                $reference,
                $accessToken,
                $credential?->id,
                $credential?->token,
                $credential?->expiresAt,
                false,
            );
        }, 3);
    }
}
