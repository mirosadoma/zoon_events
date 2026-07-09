<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Notifications\Application\Jobs\DeliverNotificationJob;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Orders\Application\Actions\CompleteFreeRegistration;
use App\Modules\Orders\Application\Actions\StartPaidRegistration;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Contracts\TicketPriceReader;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class PreviewRegistrationController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly TicketPriceReader $prices,
    ) {}

    public function store(
        Request $request,
        string $event_id,
        CompleteFreeRegistration $free,
        StartPaidRegistration $paid,
    ) {
        $context = $this->authorizeTenant('registration.manage');
        $event = $this->event($context, $event_id);

        $validated = $request->validate([
            'form_version_id' => ['required'],
            'ticket_type_id' => ['required'],
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

        $ticket = TicketType::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail((string) $validated['ticket_type_id']);

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if ($idempotencyKey === '') {
            $idempotencyKey = 'preview-'.bin2hex(random_bytes(16));
        }

        $expiresAt = $event->end_at === null
            ? CarbonImmutable::now()->addDay()
            : CarbonImmutable::parse($event->end_at);

        $buyer = $this->sanitizePerson($validated['buyer']);
        $attendee = $this->sanitizePerson($validated['attendee']);

        $input = new FreeRegistrationInput(
            $context->tenant->id,
            $event->id,
            (string) $validated['form_version_id'],
            (string) $validated['ticket_type_id'],
            $idempotencyKey,
            $validated['answers'],
            [
                'terms' => (bool) ($validated['consents']['terms'] ?? false),
                'privacy' => (bool) ($validated['consents']['privacy'] ?? false),
                'marketing' => (bool) ($validated['consents']['marketing'] ?? false),
            ],
            $buyer,
            $attendee,
            app()->getLocale(),
            $expiresAt,
            bypassIdentityGateForCredential: true,
        );

        $price = $this->prices->price($context->tenant->id, $event->id, $ticket->id);
        $result = ($price->minor === 0 ? $free : $paid)->execute($input);
        $this->deliverPendingNotifications((string) $context->tenant->id, (string) $result->orderId);

        return $this->success([
            'order_id' => $result->orderId,
            'public_reference' => $result->publicReference,
            'access_token' => $result->accessToken,
            'credential_id' => $result->credentialId,
            'credential_token' => $result->credentialToken,
            'credential_expires_at' => $result->credentialExpiresAt?->toIso8601String(),
            'replayed' => $result->replayed,
            'ticket_name' => [
                'en' => $ticket->name_en,
                'ar' => $ticket->name_ar,
            ],
        ], $result->replayed ? 200 : 201);
    }

    private function authorizeTenant(string $permission): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, $permission), 403);

        return $context;
    }

    private function event(TenantContext $context, ?string $eventId = null): Event
    {
        $resolved = request()->route('event_id');

        if (! is_string($resolved) || $resolved === '') {
            $resolved = $eventId;
        }

        abort_unless(is_string($resolved) && $resolved !== '', 404);

        return Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($resolved);
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
            ->each(fn (string $notificationId) => DeliverNotificationJob::dispatchSync($notificationId));
    }
}
