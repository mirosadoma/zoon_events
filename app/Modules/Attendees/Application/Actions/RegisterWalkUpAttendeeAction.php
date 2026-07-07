<?php

namespace App\Modules\Attendees\Application\Actions;

use App\Modules\Attendees\Domain\Events\WalkUpAttendeeRegistered;
use App\Modules\Attendees\Domain\WalkUpRegistrationResult;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Orders\Application\Actions\CompleteFreeRegistration;
use App\Modules\Orders\Application\Actions\StartPaidRegistration;
use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RegisterWalkUpAttendeeAction
{
    public function __construct(
        private CompleteFreeRegistration $free,
        private StartPaidRegistration $paidStart,
        private OrderPaymentPort $paymentPort,
    ) {}

    public function execute(
        string $tenantId,
        string $eventId,
        string $ticketTypeId,
        string $name,
        string $email,
        ?string $phone,
    ): WalkUpRegistrationResult {
        $settings = EventCheckInSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        if ($settings === null || ! $settings->walk_up_registration_enabled) {
            throw Phase3Problem::make('walk_up_registration_disabled');
        }

        $ticketType = TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->findOrFail($ticketTypeId);

        [$firstName, $lastName] = $this->splitName($name);
        $person = array_filter([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
        ], fn ($value) => $value !== null);

        $formVersion = RegistrationFormVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'published')
            ->latest('version')
            ->firstOrFail();

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($eventId);

        $input = new FreeRegistrationInput(
            tenantId: $tenantId,
            eventId: $eventId,
            formVersionId: $formVersion->id,
            ticketTypeId: $ticketTypeId,
            idempotencyKey: 'walkup_'.(string) Str::ulid(),
            answers: [],
            consent: [],
            buyer: $person,
            attendee: $person,
            locale: app()->getLocale(),
            credentialExpiresAt: CarbonImmutable::instance($event->end_at ?? now()->addYear()),
        );

        if ($ticketType->base_price_minor === 0) {
            $result = $this->free->execute($input);

            $attendee = $this->markWalkUp($tenantId, $eventId, $result->orderId);

            return new WalkUpRegistrationResult(
                attendeeId: $attendee->id,
                credentialId: $result->credentialId,
                origin: 'walk_up',
                paymentStatus: 'not_required',
            );
        }

        if (! $settings->walk_up_payment_method_enabled) {
            throw Phase3Problem::make('walk_up_payment_not_collectible');
        }

        $started = $this->paidStart->execute($input);
        $order = Order::query()->findOrFail($started->orderId);

        $paid = $this->paymentPort->completeCaptured(
            $order->id,
            'on_site',
            $order->total_minor,
            $order->currency,
            false,
        );

        $attendee = $this->markWalkUp($tenantId, $eventId, $order->id);

        return new WalkUpRegistrationResult(
            attendeeId: $attendee->id,
            credentialId: $paid->credentialId,
            origin: 'walk_up',
            paymentStatus: 'paid',
        );
    }

    private function markWalkUp(string $tenantId, string $eventId, string $orderId): Attendee
    {
        return DB::transaction(function () use ($tenantId, $eventId, $orderId): Attendee {
            $attendee = Attendee::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('order_id', $orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $attendee->forceFill(['origin' => 'walk_up'])->save();

            event(new WalkUpAttendeeRegistered($tenantId, $eventId, $attendee->id));

            return $attendee;
        });
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $name): array
    {
        $trimmed = trim($name);
        $parts = preg_split('/\s+/', $trimmed, 2);

        return [$parts[0] ?? $trimmed, $parts[1] ?? ''];
    }
}
