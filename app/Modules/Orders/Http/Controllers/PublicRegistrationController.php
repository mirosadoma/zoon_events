<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Contracts\PublicOrderHostAuthorizer;
use App\Modules\Events\Domain\Context\PublicEventContextStore;
use App\Modules\Orders\Application\Actions\CompleteFreeRegistration;
use App\Modules\Orders\Application\Actions\StartPaidRegistration;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Orders\Http\Requests\CreateRegistrationRequest;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Ticketing\Contracts\TicketPriceReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class PublicRegistrationController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly PublicEventContextStore $eventContexts,
        private readonly PublicOrderHostAuthorizer $hosts,
        private readonly TicketPriceReader $prices,
    ) {}

    public function store(
        CreateRegistrationRequest $request,
        CompleteFreeRegistration $free,
        StartPaidRegistration $paid,
    ) {
        $context = $this->eventContexts->current();
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw Phase1Problem::make('inventory_conflict');
        }
        $expiresAt = $context->eventEndsAt === null
            ? CarbonImmutable::now()->addDay()
            : CarbonImmutable::parse($context->eventEndsAt);
        $input = new FreeRegistrationInput(
            $context->tenantId,
            $context->eventId,
            $request->validated('form_version_id'),
            $request->validated('ticket_type_id'),
            $idempotencyKey,
            $request->validated('answers'),
            $request->validated('consents'),
            $request->safePerson('buyer'),
            $request->safePerson('attendee'),
            app()->getLocale(),
            $expiresAt,
        );
        $price = $this->prices->price($context->tenantId, $context->eventId, $request->validated('ticket_type_id'));
        $result = ($price->minor === 0 ? $free : $paid)->execute($input);
        $order = Order::query()->findOrFail($result->orderId);

        return $this->success($this->map($order, $result->accessToken, $result->credentialId, $result->credentialToken, $result->credentialExpiresAt), $result->replayed ? 200 : 201);
    }

    public function show(Request $request, string $publicReference)
    {
        $token = (string) $request->header('X-Order-Access-Token');
        $order = Order::query()->where('public_reference', $publicReference)->first();
        if ($order === null
            || ! hash_equals($order->access_token_hash, hash('sha256', $token))
            || ! $this->hosts->allows($request->getHost(), $order->tenant_id, $order->event_id)) {
            abort(404);
        }

        return $this->success($this->map($order));
    }

    private function map(Order $order, ?string $accessToken = null, ?string $credentialId = null, ?string $credentialToken = null, ?CarbonImmutable $credentialExpiresAt = null): array
    {
        return array_filter([
            'public_reference' => $order->public_reference,
            'access_token' => $accessToken,
            'status' => $order->status,
            'payment_status' => 'not_required',
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'credential' => $credentialId === null ? null : [
                'id' => $credentialId,
                'status' => 'active',
                'qr_payload' => $credentialToken,
                'issued_at' => now()->toIso8601String(),
                'expires_at' => $credentialExpiresAt?->toIso8601String(),
                'revoked_at' => null,
            ],
            'expires_at' => $order->created_at?->addDay()->toIso8601String(),
        ], fn ($value, $key) => ! ($value === null && in_array($key, ['access_token'], true)), ARRAY_FILTER_USE_BOTH);
    }
}
