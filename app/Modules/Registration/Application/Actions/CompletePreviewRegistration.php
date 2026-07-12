<?php

namespace App\Modules\Registration\Application\Actions;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Application\CredentialIssuerService;
use App\Modules\Credentials\Application\Presentation\CredentialPresentationToken;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Notifications\Contracts\ConfirmationIntentCreator;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Orders\Application\Actions\CompleteFreeRegistration;
use App\Modules\Orders\Application\Actions\CompletePaidRegistration;
use App\Modules\Orders\Application\Actions\StartPaidRegistration;
use App\Modules\Orders\Domain\CompletedRegistration;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Ticketing\Contracts\TicketPriceReader;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Carbon\CarbonImmutable;

final readonly class CompletePreviewRegistration
{
    public function __construct(
        private TicketPriceReader $prices,
        private CompleteFreeRegistration $free,
        private StartPaidRegistration $paid,
        private CompletePaidRegistration $completePaid,
        private CredentialIssuerService $directIssuer,
        private ConfirmationIntentCreator $notifications,
        private CredentialPresentationToken $presentationTokens,
    ) {}

    public function execute(FreeRegistrationInput $input, TicketType $ticket): CompletedRegistration
    {
        $price = $this->prices->price($input->tenantId, $input->eventId, $ticket->id);

        if ($price->minor === 0) {
            return $this->free->execute($input);
        }

        $started = $this->paid->execute($input);
        $order = Order::query()->findOrFail($started->orderId);

        if ($order->status === 'pending_payment') {
            $completed = $this->completePaid->completeCaptured(
                $started->orderId,
                'preview-registration',
                $price->minor,
                $ticket->currency,
                false,
            );

            if ($completed->credentialToken !== null) {
                return new CompletedRegistration(
                    $started->orderId,
                    $started->publicReference,
                    $started->accessToken,
                    $completed->credentialId,
                    $completed->credentialToken,
                    $input->credentialExpiresAt,
                    $started->replayed,
                );
            }
        }

        return $this->resolvePaidPreviewResult($started, $input);
    }

    private function resolvePaidPreviewResult(CompletedRegistration $started, FreeRegistrationInput $input): CompletedRegistration
    {
        $order = Order::query()->findOrFail($started->orderId);
        $attendee = Attendee::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('order_id', $order->id)
            ->first();

        if (! $attendee instanceof Attendee) {
            return $started;
        }

        $credential = Credential::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('attendee_id', $attendee->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $credential instanceof Credential) {
            $item = $order->items()->first();
            $ticketTypeId = $item?->ticket_type_id;

            if (! is_string($ticketTypeId) || $ticketTypeId === '') {
                return $started;
            }

            $expiresAt = $order->credential_expires_at !== null
                ? CarbonImmutable::parse($order->credential_expires_at)
                : $input->credentialExpiresAt;

            $issued = $this->directIssuer->issue(
                $order->tenant_id,
                $order->event_id,
                (string) $attendee->id,
                $ticketTypeId,
                $expiresAt,
            );
            $credential = Credential::query()->findOrFail($issued->id);
        }

        $this->ensureConfirmationNotification($order, $attendee, $credential, $input);

        return new CompletedRegistration(
            $order->id,
            $order->public_reference,
            $started->accessToken,
            (string) $credential->id,
            $this->presentationTokens->resolve($credential),
            $input->credentialExpiresAt,
            $started->replayed,
        );
    }

    private function ensureConfirmationNotification(
        Order $order,
        Attendee $attendee,
        Credential $credential,
        FreeRegistrationInput $input,
    ): void {
        $exists = Notification::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->exists();

        if ($exists) {
            return;
        }

        $this->notifications->create(
            $order->tenant_id,
            $order->event_id,
            (string) $attendee->id,
            $order->id,
            (string) $credential->id,
            $input->attendee['email'],
            $input->locale,
            $input->attendee['phone'] ?? null,
        );
    }
}
