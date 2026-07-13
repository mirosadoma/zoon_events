<?php

namespace App\Modules\AdminConsole\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Support\PublicRegistrationEventPresenter;
use App\Modules\Events\Application\Support\ShareablePublicEventResolver;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Notifications\Application\Jobs\DeliverNotificationJob;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Orders\Application\Actions\CompleteFreeRegistration;
use App\Modules\Orders\Application\Actions\StartPaidRegistration;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Registration\Application\Queries\ResolvePublishedRegistrationForm;
use App\Modules\Registration\Application\Support\RegistrationFieldPresenter;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Ticketing\Contracts\TicketPriceReader;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class PublicEventRegistrationController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly ShareablePublicEventResolver $shareableEvents,
        private readonly TicketPriceReader $prices,
        private readonly PublicRegistrationEventPresenter $eventPages,
        private readonly RegistrationFieldPresenter $registrationFields,
        private readonly ResolvePublishedRegistrationForm $publishedForms,
    ) {}

    public function show(string $locale, string $eventSlug): Response
    {
        return $this->renderRegistrationPage($locale, $this->shareableEvents->findBySlug($eventSlug));
    }

    public function store(Request $request, string $locale, string $eventSlug): JsonResponse
    {
        return $this->storeRegistration($request, $this->shareableEvents->findBySlug($eventSlug));
    }

    private function renderRegistrationPage(string $locale, Event $event): Response
    {
        $formVersion = $this->publishedForms->forEvent($event);

        $fields = collect(is_array($formVersion->fields) ? $formVersion->fields : [])
            ->filter(fn (mixed $field): bool => is_array($field)
                && ($field['visibility'] ?? 'public') === 'public'
                && ($field['type'] ?? '') !== 'hidden')
            ->map(fn (array $field, int $index): array => $this->registrationFields->clientField($field, $index))
            ->values()
            ->all();

        $ticketTypes = TicketType::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketType $ticket): array => [
                'id' => (string) $ticket->id,
                'code' => $ticket->code,
                'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
                'price_minor' => $ticket->base_price_minor,
                'currency' => $ticket->currency,
            ])
            ->values()
            ->all();

        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        return Inertia::render('public/registration/Event', [
            'locale' => $resolvedLocale,
            'event' => $this->eventPages->heroEvent($event, true),
            'form' => [
                'version_id' => (string) $formVersion->id,
                'fields' => $fields,
                'privacy_notice_version' => (string) ($formVersion->privacy_notice_version ?? 'v1'),
                'terms_version' => (string) ($formVersion->terms_version ?? 'v1'),
            ],
            'ticketTypes' => $ticketTypes,
            'isPreview' => false,
            'submitUrl' => "/{$resolvedLocale}/events/{$event->slug}/register",
        ]);
    }

    private function storeRegistration(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'form_version_id' => ['required'],
            'ticket_type_id' => ['required'],
            'event_venue_id' => ['nullable', 'string', 'max:64'],
            'buyer' => ['required', 'array'],
            'buyer.first_name' => ['required', 'string', 'max:120'],
            'buyer.last_name' => ['required', 'string', 'max:120'],
            'buyer.email' => ['required', 'email', 'max:254'],
            'buyer.phone' => ['nullable', 'string', 'max:30'],
            'attendee' => ['required', 'array'],
            'attendee.first_name' => ['required', 'string', 'max:120'],
            'attendee.last_name' => ['required', 'string', 'max:120'],
            'attendee.email' => ['required', 'email', 'max:254'],
            'attendee.phone' => ['nullable', 'string', 'max:30'],
            'answers' => ['required', 'array', 'max:100'],
            'consents' => ['required', 'array'],
            'consents.terms' => ['required', 'accepted'],
            'consents.privacy' => ['required', 'accepted'],
            'consents.marketing' => ['sometimes', 'boolean'],
        ]);

        $answers = (array) $validated['answers'];
        unset($answers['event_venue_id'], $answers['event_venue']);

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            $idempotencyKey = 'public-'.bin2hex(random_bytes(16));
        }

        $expiresAt = $event->end_at === null
            ? CarbonImmutable::now()->addDay()
            : CarbonImmutable::parse($event->end_at);

        $input = new FreeRegistrationInput(
            $event->tenant_id,
            $event->id,
            (string) $validated['form_version_id'],
            (string) $validated['ticket_type_id'],
            $idempotencyKey,
            $answers,
            [
                'terms' => (bool) ($validated['consents']['terms'] ?? false),
                'privacy' => (bool) ($validated['consents']['privacy'] ?? false),
                'marketing' => (bool) ($validated['consents']['marketing'] ?? false),
            ],
            $this->sanitizePerson($validated['buyer']),
            $this->sanitizePerson($validated['attendee']),
            app()->getLocale(),
            $expiresAt,
        );

        $price = $this->prices->price($event->tenant_id, $event->id, (string) $validated['ticket_type_id']);
        $result = ($price->minor === 0 ? app(CompleteFreeRegistration::class) : app(StartPaidRegistration::class))->execute($input);
        $order = Order::query()->findOrFail($result->orderId);

        $this->deliverPendingNotifications((string) $event->tenant_id, (string) $result->orderId);

        $resolvedLocale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return $this->success([
            'public_reference' => $order->public_reference,
            'access_token' => $result->accessToken,
            'credential_id' => $result->credentialId,
            'credential_token' => $result->accessToken !== null ? $result->credentialToken : null,
            'credential_expires_at' => $result->credentialExpiresAt?->toIso8601String(),
            'credential' => $result->credentialId === null ? null : array_filter([
                'id' => $result->credentialId,
                'status' => 'active',
                'qr_payload' => $result->accessToken !== null ? $order->public_reference : null,
                'expires_at' => $result->credentialExpiresAt?->toIso8601String(),
            ], fn ($value, $key) => ! ($value === null && $key === 'qr_payload'), ARRAY_FILTER_USE_BOTH),
            'identity_verify_url' => $result->accessToken !== null
                ? url("/{$resolvedLocale}/identity/{$event->slug}/{$result->accessToken}")
                : null,
            'credential_status' => $result->credentialToken !== null
                ? 'issued'
                : ($result->accessToken !== null ? 'pending_identity' : 'unavailable'),
            'replayed' => $result->replayed,
        ], $result->replayed ? 200 : 201);
    }

    /** @param array<string, mixed> $person */
    private function sanitizePerson(array $person): array
    {
        return array_filter([
            'first_name' => trim(strip_tags((string) ($person['first_name'] ?? ''))),
            'last_name' => trim(strip_tags((string) ($person['last_name'] ?? ''))),
            'email' => mb_strtolower(trim((string) ($person['email'] ?? ''))),
            'phone' => isset($person['phone']) ? trim(strip_tags((string) $person['phone'])) : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function deliverPendingNotifications(string $tenantId, string $orderId): void
    {
        Notification::query()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->whereIn('status', ['pending', 'temporary_failure'])
            ->pluck('id')
            ->each(function (string $notificationId): void {
                try {
                    DeliverNotificationJob::dispatchSync($notificationId);
                } catch (Throwable $exception) {
                    Log::warning('public_registration.notification_delivery_deferred', [
                        'notification_id' => $notificationId,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            });
    }
}
