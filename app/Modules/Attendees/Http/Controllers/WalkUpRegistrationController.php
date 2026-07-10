<?php

namespace App\Modules\Attendees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendees\Application\Actions\RegisterWalkUpAttendeeAction;
use App\Modules\Attendees\Http\Requests\RegisterWalkUpAttendeeRequest;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class WalkUpRegistrationController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly RegisterWalkUpAttendeeAction $action,
    ) {}

    public function store(RegisterWalkUpAttendeeRequest $request, string $eventId): JsonResponse
    {
        $context = $this->contexts->current();
        $tenantId = $context->tenant->id;

        $event = $context->event ?? null;
        if ($event === null) {
            throw Phase3Problem::make('walk_up_registration_disabled');
        }

        $input = new FreeRegistrationInput(
            tenantId: $tenantId,
            eventId: $eventId,
            formVersionId: $request->string('form_version_id')->toString(),
            ticketTypeId: $request->string('ticket_type_id')->toString(),
            idempotencyKey: $request->string('idempotency_key')->toString(),
            answers: (array) $request->input('answers', []),
            consent: (array) $request->input('consent', []),
            buyer: (array) $request->input('buyer'),
            attendee: (array) $request->input('attendee'),
            locale: $request->string('locale')->toString(),
            credentialExpiresAt: CarbonImmutable::parse($event->ends_at ?? now()->addYear()),
        );

        $result = $this->action->execute($input);

        return $this->success([
            'order_id' => $result->orderId,
        ], 201);
    }
}
