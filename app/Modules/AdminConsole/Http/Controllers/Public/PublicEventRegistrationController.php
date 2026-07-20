<?php

namespace App\Modules\AdminConsole\Http\Controllers\Public;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Application\Actions\DeactivateRegistrationInvite;
use App\Modules\Events\Application\Actions\SendRegistrationBadgeEmail;
use App\Modules\Events\Application\Registration\EnsureDefaultRegistrationSlot;
use App\Modules\Events\Application\Support\EvaluateEventCategoryCapacity;
use App\Modules\Events\Application\Support\EvaluatePublicRegistrationWindow;
use App\Modules\Events\Application\Support\PublicRegistrationEventPresenter;
use App\Modules\Events\Application\Support\RenderRegistrationInviteUnavailablePage;
use App\Modules\Events\Application\Support\RenderRegistrationSoldOutPage;
use App\Modules\Events\Application\Support\RenderRegistrationWindowUnavailablePage;
use App\Modules\Events\Application\Support\ResolveActiveRegistrationInvite;
use App\Modules\Events\Application\Support\ShareablePublicEventResolver;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Notifications\Application\Jobs\DeliverNotificationJob;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Orders\Application\Actions\CancelPendingRegistration;
use App\Modules\Orders\Application\Actions\CompleteFreeRegistration;
use App\Modules\Orders\Application\Actions\CompletePaidRegistration;
use App\Modules\Orders\Application\Actions\StartPaidRegistration;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Registration\Application\Queries\ResolvePublishedRegistrationForm;
use App\Modules\Registration\Application\Support\RegistrationFieldPresenter;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationOtp;
use App\Modules\Registration\Mail\RegistrationOtpMail;
use App\Modules\Identity\Application\Actions\CreateOrLinkVisitorAccount;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Ticketing\Application\Queries\PublicTicketTypeCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class PublicEventRegistrationController extends Controller
{
    use RespondsWithApi;

    private const DEMO_SUCCESS_CARD = '4242424242424242';

    private const DEMO_FAIL_CARD = '4000000000000002';

    public function __construct(
        private readonly ShareablePublicEventResolver $shareableEvents,
        private readonly PublicTicketTypeCatalog $publicTickets,
        private readonly PublicRegistrationEventPresenter $eventPages,
        private readonly RegistrationFieldPresenter $registrationFields,
        private readonly ResolvePublishedRegistrationForm $publishedForms,
        private readonly EnsureDefaultRegistrationSlot $defaultSlot,
        private readonly PersonalDataCipher $cipher,
        private readonly BlindIndex $indexes,
        private readonly ResolveActiveRegistrationInvite $invites,
        private readonly DeactivateRegistrationInvite $deactivateInvite,
        private readonly SendRegistrationBadgeEmail $badgeEmail,
        private readonly CreateOrLinkVisitorAccount $visitorAccounts,
        private readonly RenderRegistrationInviteUnavailablePage $inviteUnavailablePages,
        private readonly EvaluatePublicRegistrationWindow $registrationWindows,
        private readonly RenderRegistrationWindowUnavailablePage $registrationWindowPages,
        private readonly EvaluateEventCategoryCapacity $categoryCapacity,
        private readonly RenderRegistrationSoldOutPage $registrationSoldOutPages,
    ) {}

    public function show(Request $request, string $locale, string $eventSlug): Response
    {
        $event = $this->shareableEvents->findBySlug($eventSlug);

        try {
            $invite = $this->invites->requireForPrivateEvent($event, $request->query('invite'));
        } catch (FoundationException $exception) {
            if (str_starts_with($exception->problemCode, 'invite_')) {
                return $this->inviteUnavailablePages->execute($locale, $event, $exception->problemCode);
            }

            throw $exception;
        }

        $window = $this->registrationWindows->status($event);
        if ($window !== EvaluatePublicRegistrationWindow::OPEN) {
            return $this->registrationWindowPages->execute($locale, $event, $window);
        }

        // Public URLs only — private invites skip category capacity gates.
        if ($invite === null && $this->categoryCapacity->isEventFullyBooked($event)) {
            return $this->registrationSoldOutPages->execute($locale, $event);
        }

        return $this->renderRegistrationPage($locale, $event, $invite);
    }

    public function store(Request $request, string $locale, string $eventSlug): JsonResponse
    {
        $event = $this->shareableEvents->findBySlug($eventSlug);
        $invite = $this->invites->requireForPrivateEvent($event, $request->input('invite_code') ?? $request->query('invite'));

        $window = $this->registrationWindows->status($event);
        if ($window !== EvaluatePublicRegistrationWindow::OPEN) {
            throw ValidationException::withMessages([
                'registration' => [
                    $window === EvaluatePublicRegistrationWindow::CLOSED
                        ? 'Registration for this event is closed.'
                        : 'Registration for this event has not opened yet.',
                ],
            ]);
        }

        return $this->storeRegistrationDraft($request, $locale, $event, $invite);
    }

    public function showOtp(string $locale, string $eventSlug, string $token): Response
    {
        $event = $this->shareableEvents->findBySlug($eventSlug);
        $otp = $this->findActiveOtp($event, $token);
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        return Inertia::render('public/registration/Otp', [
            'locale' => $resolvedLocale,
            'event' => [
                'id' => (string) $event->id,
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'email' => $this->maskEmail((string) $otp->email),
            'token' => $otp->token,
            'submitUrl' => "/{$resolvedLocale}/events/{$event->slug}/register/otp/{$otp->token}",
            'registerUrl' => "/{$resolvedLocale}/events/{$event->slug}/register",
        ]);
    }

    public function verifyOtp(Request $request, string $locale, string $eventSlug, string $token): JsonResponse
    {
        $event = $this->shareableEvents->findBySlug($eventSlug);
        $otp = $this->findActiveOtp($event, $token);
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if ($otp->attempts >= 5) {
            throw ValidationException::withMessages([
                'code' => ['Too many attempts. Please start registration again.'],
            ]);
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, hash('sha256', $validated['code']))) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.'],
            ]);
        }

        if ($otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.'],
            ]);
        }

        $otp->forceFill(['verified_at' => now()])->save();

        /** @var array<string, mixed> $payload */
        $payload = $otp->payload;
        $attendeePerson = $this->sanitizePerson((array) ($payload['attendee'] ?? []));
        $this->assertEmailNotAlreadyRegistered(
            $event,
            (string) (($attendeePerson['email'] ?? '') ?: ($payload['invite_email'] ?? '')),
        );

        $categoryId = isset($payload['event_category_id']) && $payload['event_category_id'] !== null && $payload['event_category_id'] !== ''
            ? (string) $payload['event_category_id']
            : null;
        $category = $categoryId !== null
            ? EventCategory::query()->where('event_id', $event->id)->find($categoryId)
            : null;

        if ($categoryId !== null && $category === null) {
            throw ValidationException::withMessages([
                'code' => ['Selected category is not available for this event.'],
            ]);
        }

        if ($category !== null) {
            try {
                $this->categoryCapacity->assertCategoryAvailable($event, $category);
            } catch (ValidationException) {
                throw ValidationException::withMessages([
                    'code' => ['This category is full.'],
                ]);
            }
        }

        $ticketTypeId = (string) ($payload['ticket_type_id'] ?? '');
        if ($ticketTypeId === '') {
            $ticket = $this->defaultSlot->execute($event)
                ?? $this->publicTickets->forEvent($event)->first();
            $ticketTypeId = (string) ($ticket?->id ?? '');
        }

        if ($ticketTypeId === '') {
            throw ValidationException::withMessages([
                'code' => ['Registration is not available for this event.'],
            ]);
        }

        $expiresAt = $event->end_at === null
            ? CarbonImmutable::now()->addDay()
            : CarbonImmutable::parse($event->end_at);

        $input = new FreeRegistrationInput(
            $event->tenant_id,
            $event->id,
            (string) $payload['form_version_id'],
            $ticketTypeId,
            (string) $payload['idempotency_key'],
            (array) ($payload['answers'] ?? []),
            (array) ($payload['consents'] ?? []),
            $this->sanitizePerson((array) ($payload['buyer'] ?? [])),
            $attendeePerson,
            $resolvedLocale,
            $expiresAt,
            eventCategoryId: $category !== null ? (string) $category->id : null,
            priceMinorOverride: $category !== null && $category->isPayable() ? (int) $category->price_minor : null,
            currencyOverride: $category !== null && $category->isPayable() ? (string) ($category->currency ?: 'SAR') : null,
            eventVenueId: isset($payload['event_venue_id']) && $payload['event_venue_id'] !== null && $payload['event_venue_id'] !== ''
                ? (string) $payload['event_venue_id']
                : null,
        );

        if ($category !== null && $category->isPayable()) {
            $result = app(StartPaidRegistration::class)->execute($input);
            $order = Order::query()->findOrFail($result->orderId);

            return $this->success([
                'next' => 'payment',
                'payment_url' => "/{$resolvedLocale}/events/{$event->slug}/register/payment/{$order->public_reference}?access_token=".urlencode((string) $result->accessToken),
                'public_reference' => $order->public_reference,
                'access_token' => $result->accessToken,
            ]);
        }

        $result = app(CompleteFreeRegistration::class)->execute($input);
        $order = Order::query()->findOrFail($result->orderId);
        $this->deliverPendingNotifications((string) $event->tenant_id, (string) $result->orderId);
        $attendeeId = Attendee::query()->where('order_id', $order->id)->value('id');
        $this->finalizeInviteRegistration(
            $event,
            $result->orderId,
            $result->credentialId,
            (string) (($payload['attendee']['email'] ?? '') ?: ($payload['invite_email'] ?? '')),
            isset($payload['invite_code']) ? (string) $payload['invite_code'] : null,
            $resolvedLocale,
            $attendeeId !== null ? (string) $attendeeId : null,
        );

        return $this->success([
            'next' => 'confirmation',
            'confirmation_url' => "/{$resolvedLocale}/events/{$event->slug}/register/confirmation/{$order->public_reference}?access_token=".urlencode((string) $result->accessToken),
            'public_reference' => $order->public_reference,
            'access_token' => $result->accessToken,
            'credential_token' => $result->credentialToken,
        ]);
    }

    public function showPayment(Request $request, string $locale, string $eventSlug, string $publicReference): Response
    {
        $event = $this->shareableEvents->findBySlug($eventSlug);
        $order = $this->findPayableOrder($event, $publicReference, (string) $request->query('access_token', ''));
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $accessToken = (string) $request->query('access_token', '');

        return Inertia::render('public/registration/Payment', [
            'locale' => $resolvedLocale,
            'event' => [
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'publicReference' => $order->public_reference,
            'accessToken' => $accessToken,
            'totalMinor' => (int) $order->total_minor,
            'currency' => (string) $order->currency,
            'submitUrl' => "/{$resolvedLocale}/events/{$event->slug}/register/payment/{$order->public_reference}",
        ]);
    }

    public function processPayment(Request $request, string $locale, string $eventSlug, string $publicReference): JsonResponse
    {
        $event = $this->shareableEvents->findBySlug($eventSlug);
        $accessToken = (string) $request->input('access_token', '');
        $order = $this->findPayableOrder($event, $publicReference, $accessToken);
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        $validated = $request->validate([
            'access_token' => ['required', 'string'],
            'card_number' => ['required', 'string', 'max:32'],
            'card_expiry' => ['required', 'string', 'max:7'],
            'card_cvv' => ['required', 'string', 'max:4'],
            'card_name' => ['required', 'string', 'max:120'],
        ]);

        $digits = preg_replace('/\D+/', '', $validated['card_number']) ?? '';

        if ($digits === self::DEMO_FAIL_CARD) {
            app(CancelPendingRegistration::class)->execute($order, 'payment_failed');

            return $this->success([
                'next' => 'failed',
                'failed_url' => "/{$resolvedLocale}/events/{$event->slug}/register/payment-failed",
            ]);
        }

        if ($digits !== self::DEMO_SUCCESS_CARD) {
            throw ValidationException::withMessages([
                'card_number' => ['Use a demo card number to continue.'],
            ]);
        }

        if (! $this->expiryIsFuture((string) $validated['card_expiry'])) {
            throw ValidationException::withMessages([
                'card_expiry' => ['Card expiry must be in the future.'],
            ]);
        }

        $paid = app(CompletePaidRegistration::class)->completeCaptured(
            (string) $order->id,
            'fake-visa',
            (int) $order->total_minor,
            (string) $order->currency,
            false,
        );

        $this->deliverPendingNotifications((string) $event->tenant_id, (string) $order->id);

        $order->refresh();
        $attendee = Attendee::query()->where('order_id', $order->id)->first();
        $email = $this->resolveOrderEmail($order);
        $this->finalizeInviteRegistration(
            $event,
            (string) $order->id,
            $paid->credentialId,
            $email,
            null,
            $resolvedLocale,
            $attendee?->id !== null ? (string) $attendee->id : null,
        );

        return $this->success([
            'next' => 'confirmation',
            'confirmation_url' => "/{$resolvedLocale}/events/{$event->slug}/register/confirmation/{$order->public_reference}?access_token=".urlencode($accessToken),
            'public_reference' => $order->public_reference,
            'access_token' => $accessToken,
            'credential_id' => $paid->credentialId,
            'credential_token' => $paid->credentialToken,
        ]);
    }

    public function showPaymentFailed(string $locale, string $eventSlug): Response
    {
        $event = $this->shareableEvents->findBySlug($eventSlug);
        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        return Inertia::render('public/registration/PaymentFailed', [
            'locale' => $resolvedLocale,
            'event' => [
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'registerUrl' => "/{$resolvedLocale}/events/{$event->slug}/register",
        ]);
    }

    public function showConfirmation(Request $request, string $locale, string $eventSlug, string $publicReference): Response
    {
        $event = $this->shareableEvents->findBySlug($eventSlug);
        $order = Order::query()
            ->where('event_id', $event->id)
            ->where('public_reference', $publicReference)
            ->where('status', 'paid')
            ->firstOrFail();

        $accessToken = (string) $request->query('access_token', '');
        if ($accessToken !== '' && ! hash_equals($order->access_token_hash, hash('sha256', $accessToken))) {
            abort(404);
        }

        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $attendee = Attendee::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('order_id', $order->id)
            ->first();

        return Inertia::render('public/registration/Confirmation', [
            'locale' => $resolvedLocale,
            'reference' => $order->public_reference,
            'eventName' => $resolvedLocale === 'ar' ? ($event->name_ar ?: $event->name_en) : $event->name_en,
            'attendeeName' => $this->resolveAttendeeName($attendee),
            'qrPayload' => $order->public_reference,
            'accessToken' => $accessToken !== '' ? $accessToken : null,
            'credentialStatus' => 'active',
        ]);
    }

    private function resolveAttendeeName(?Attendee $attendee): string
    {
        if ($attendee === null) {
            return 'Participant';
        }

        try {
            $scope = "{$attendee->tenant_id}:{$attendee->event_id}:attendee";
            $firstName = $this->cipher->decrypt([
                'key_id' => $attendee->encryption_key_id,
                'ciphertext' => $attendee->first_name_ciphertext,
            ], $scope);

            return trim($firstName) !== '' ? trim($firstName) : 'Participant';
        } catch (Throwable) {
            return 'Participant';
        }
    }

    private function renderRegistrationPage(string $locale, Event $event, ?EventRegistrationInvite $invite = null): Response
    {
        $formVersion = $this->publishedForms->forEvent($event);
        $this->defaultSlot->execute($event);

        $fields = collect(is_array($formVersion->fields) ? $formVersion->fields : [])
            ->filter(fn (mixed $field): bool => is_array($field)
                && ($field['visibility'] ?? 'public') === 'public'
                && ($field['type'] ?? '') !== 'hidden'
                && ($field['type'] ?? '') !== 'consent')
            ->map(fn (array $field, int $index): array => $this->registrationFields->clientField($field, $index))
            ->values()
            ->all();

        $categories = EventCategory::query()
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->get();

        $capacityById = collect($this->categoryCapacity->forEvent($event))
            ->keyBy('id');

        $categoryPayload = $categories
            ->map(function (EventCategory $category) use ($capacityById): array {
                $capacity = $capacityById->get((string) $category->id);

                return [
                    'id' => (string) $category->id,
                    'name' => ['en' => $category->name, 'ar' => $category->name_ar ?: $category->name],
                    'color' => $category->color,
                    'is_paid' => (bool) $category->is_paid,
                    'price_minor' => (int) $category->price_minor,
                    'currency' => (string) ($category->currency ?: 'SAR'),
                    'capacity' => $capacity['capacity'] ?? null,
                    'remaining' => $capacity['remaining'] ?? null,
                    'is_full' => (bool) ($capacity['is_full'] ?? false),
                ];
            })
            ->values()
            ->all();

        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';

        $branding = EventBranding::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->first();

        $submitUrl = "/{$resolvedLocale}/events/{$event->slug}/register";
        if ($invite !== null) {
            $submitUrl .= '?invite='.$invite->code;
        }

        return Inertia::render('public/registration/Event', [
            'locale' => $resolvedLocale,
            'event' => $this->eventPages->heroEvent($event, true),
            'form' => [
                'version_id' => (string) $formVersion->id,
                'fields' => $fields,
                'privacy_notice_version' => (string) ($formVersion->privacy_notice_version ?? 'v1'),
                'terms_version' => (string) ($formVersion->terms_version ?? 'v1'),
            ],
            'categories' => $invite !== null ? [] : $categoryPayload,
            'requiresCategorySelection' => $invite === null,
            'ticketTypes' => [],
            'requiresTicketSelection' => false,
            'isPreview' => false,
            'submitUrl' => $submitUrl,
            'theme' => $branding?->theme_config,
            'inviteCode' => $invite?->code,
            'lockedEmail' => $invite?->email,
        ]);
    }

    private function storeRegistrationDraft(Request $request, string $locale, Event $event, ?EventRegistrationInvite $invite = null): JsonResponse
    {
        $categoryRequired = $invite === null;
        $validated = $request->validate([
            'form_version_id' => ['required'],
            'event_category_id' => [$categoryRequired ? 'required' : 'nullable', 'integer', 'exists:event_categories,id'],
            'event_venue_id' => ['nullable', 'string', 'max:64'],
            'invite_code' => ['nullable', 'string', 'size:10'],
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

        $category = null;
        if ($categoryRequired || ! empty($validated['event_category_id'])) {
            $category = EventCategory::query()
                ->where('event_id', $event->id)
                ->find($validated['event_category_id'] ?? null);

            if ($category === null) {
                throw ValidationException::withMessages([
                    'event_category_id' => ['Selected category is not available for this event.'],
                ]);
            }

            $this->categoryCapacity->assertCategoryAvailable($event, $category);
        }

        if (EventVenue::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->exists()
            && (($validated['event_venue_id'] ?? '') === '')) {
            throw ValidationException::withMessages([
                'event_venue_id' => ['Please select a location and date.'],
            ]);
        }

        $answers = (array) $validated['answers'];
        unset($answers['event_venue_id'], $answers['event_venue']);

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            $idempotencyKey = 'public-'.bin2hex(random_bytes(16));
        }

        $ticket = $this->defaultSlot->execute($event)
            ?? $this->publicTickets->forEvent($event)->first();

        if ($ticket === null) {
            throw ValidationException::withMessages([
                'event_category_id' => ['Registration is not available for this event.'],
            ]);
        }

        $buyer = $this->sanitizePerson($validated['buyer']);
        $attendee = $this->sanitizePerson($validated['attendee']);

        if ($invite !== null) {
            $lockedEmail = strtolower($invite->email);
            $submittedEmails = array_values(array_filter([
                $buyer['email'],
                $attendee['email'],
                isset($answers['email']) ? mb_strtolower(trim((string) $answers['email'])) : '',
            ]));

            foreach ($submittedEmails as $submittedEmail) {
                if ($submittedEmail !== '' && $submittedEmail !== $lockedEmail) {
                    throw ValidationException::withMessages([
                        'attendee.email' => ['This invite is locked to a specific email address.'],
                        'answers.email' => ['This invite is locked to a specific email address.'],
                    ]);
                }
            }

            $buyer['email'] = $lockedEmail;
            $attendee['email'] = $lockedEmail;
            $answers['email'] = $lockedEmail;
        }

        $this->assertEmailNotAlreadyRegistered($event, (string) ($attendee['email'] ?? ''));

        $resolvedLocale = $locale === 'ar' ? 'ar' : 'en';
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(48);

        RegistrationOtp::query()->create([
            'token' => $token,
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'email' => $attendee['email'],
            'payload' => [
                'form_version_id' => (string) $validated['form_version_id'],
                'event_category_id' => $category !== null ? (string) $category->id : null,
                'ticket_type_id' => (string) $ticket->id,
                'event_venue_id' => $validated['event_venue_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'answers' => $answers,
                'consents' => [
                    'terms' => (bool) ($validated['consents']['terms'] ?? false),
                    'privacy' => (bool) ($validated['consents']['privacy'] ?? false),
                    'marketing' => (bool) ($validated['consents']['marketing'] ?? false),
                ],
                'buyer' => $buyer,
                'attendee' => $attendee,
                'invite_code' => $invite?->code,
                'invite_email' => $invite?->email,
            ],
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(15),
            'attempts' => 0,
        ]);

        $eventName = $resolvedLocale === 'ar'
            ? ($event->name_ar ?: $event->name_en)
            : $event->name_en;

        try {
            Mail::to($attendee['email'])->send(new RegistrationOtpMail($code, $eventName, $resolvedLocale));
        } catch (Throwable $exception) {
            Log::warning('public_registration.otp_mail_failed', [
                'event_id' => $event->id,
                'reason' => $exception->getMessage(),
            ]);
            Log::info('public_registration.otp_code', [
                'token' => $token,
                'code' => $code,
                'email' => $attendee['email'],
            ]);
        }

        if (app()->environment('local')) {
            Log::info('public_registration.otp_code', [
                'token' => $token,
                'code' => $code,
                'email' => $attendee['email'],
            ]);
        }

        return $this->success([
            'next' => 'otp',
            'otp_url' => "/{$resolvedLocale}/events/{$event->slug}/register/otp/{$token}",
            'token' => $token,
        ], 201);
    }

    private function finalizeInviteRegistration(
        Event $event,
        string $orderId,
        ?string $credentialId,
        string $email,
        ?string $inviteCode,
        string $locale,
        ?string $attendeeId = null,
    ): void {
        $email = strtolower(trim($email));
        if ($email !== '') {
            $this->deactivateInvite->execute($event->id, $email, $inviteCode);
        }

        $resolvedAttendeeId = $attendeeId;
        if ($resolvedAttendeeId === null) {
            $resolvedAttendeeId = Attendee::query()
                ->where('order_id', $orderId)
                ->value('id');
            $resolvedAttendeeId = $resolvedAttendeeId !== null ? (string) $resolvedAttendeeId : null;
        }

        if ($resolvedAttendeeId !== null && $email !== '') {
            $this->provisionVisitorAccount($event, $resolvedAttendeeId, $email, $locale);
        }

        // Badge email after QR confirmation and visitor credentials/reminder mail.
        $this->sendBadgeTemplateEmail(
            $event,
            $orderId,
            $credentialId,
            $email,
            $locale,
            $resolvedAttendeeId,
        );
    }

    private function provisionVisitorAccount(
        Event $event,
        string $attendeeId,
        string $email,
        string $locale,
    ): void {
        try {
            $attendee = Attendee::query()->find($attendeeId);
            $name = 'Visitor';
            if ($attendee !== null) {
                try {
                    $scope = "{$attendee->tenant_id}:{$attendee->event_id}:attendee";
                    $first = $this->cipher->decrypt([
                        'key_id' => $attendee->encryption_key_id,
                        'ciphertext' => $attendee->first_name_ciphertext,
                    ], $scope);
                    $last = $this->cipher->decrypt([
                        'key_id' => $attendee->encryption_key_id,
                        'ciphertext' => $attendee->last_name_ciphertext,
                    ], $scope);
                    $combined = trim($first.' '.$last);
                    if ($combined !== '') {
                        $name = $combined;
                    }
                } catch (Throwable) {
                    // Keep default visitor name.
                }
            }

            $this->visitorAccounts->execute($attendeeId, $email, $name, $locale);
        } catch (Throwable $exception) {
            Log::warning('public_registration.visitor_account_failed', [
                'event_id' => $event->id,
                'attendee_id' => $attendeeId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    private function assertEmailNotAlreadyRegistered(Event $event, string $email): void
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return;
        }

        $exists = Attendee::query()
            ->where('event_id', $event->id)
            ->where('email_index', $this->indexes->email($email))
            ->whereNull('anonymized_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'attendee.email' => ['You are already registered for this event.'],
                'answers.email' => ['You are already registered for this event.'],
            ]);
        }
    }

    private function sendBadgeTemplateEmail(
        Event $event,
        string $orderId,
        ?string $credentialId,
        string $email,
        string $locale,
        ?string $attendeeId = null,
    ): void {
        if ($credentialId === null || $email === '') {
            return;
        }

        $resolvedAttendeeId = $attendeeId;
        if ($resolvedAttendeeId === null) {
            $resolvedAttendeeId = Attendee::query()
                ->where('order_id', $orderId)
                ->value('id');
            $resolvedAttendeeId = $resolvedAttendeeId !== null ? (string) $resolvedAttendeeId : null;
        }

        if ($resolvedAttendeeId === null) {
            return;
        }

        try {
            $this->badgeEmail->execute(
                $event,
                $resolvedAttendeeId,
                $credentialId,
                $email,
                $locale,
            );
        } catch (Throwable $exception) {
            Log::warning('public_registration.badge_mail_failed', [
                'event_id' => $event->id,
                'order_id' => $orderId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveOrderEmail(Order $order): string
    {
        try {
            return strtolower(trim($this->cipher->decrypt([
                'key_id' => $order->encryption_key_id,
                'ciphertext' => $order->buyer_email_ciphertext,
            ], "{$order->tenant_id}:{$order->event_id}:order")));
        } catch (Throwable) {
            return '';
        }
    }

    private function findActiveOtp(Event $event, string $token): RegistrationOtp
    {
        $otp = RegistrationOtp::query()
            ->where('event_id', $event->id)
            ->where('token', $token)
            ->first();

        if ($otp === null || $otp->isVerified() || $otp->isExpired()) {
            abort(404);
        }

        return $otp;
    }

    private function findPayableOrder(Event $event, string $publicReference, string $accessToken): Order
    {
        $order = Order::query()
            ->where('event_id', $event->id)
            ->where('public_reference', $publicReference)
            ->where('status', 'pending_payment')
            ->first();

        if ($order === null
            || $accessToken === ''
            || ! hash_equals($order->access_token_hash, hash('sha256', $accessToken))) {
            abort(404);
        }

        return $order;
    }

    private function expiryIsFuture(string $expiry): bool
    {
        if (! preg_match('/^(\d{2})\s*\/\s*(\d{2})$/', trim($expiry), $matches)) {
            return false;
        }

        $month = (int) $matches[1];
        $year = 2000 + (int) $matches[2];
        if ($month < 1 || $month > 12) {
            return false;
        }

        $end = CarbonImmutable::create($year, $month, 1)->endOfMonth();

        return $end->greaterThanOrEqualTo(CarbonImmutable::now()->startOfDay());
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$parts[1];
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
