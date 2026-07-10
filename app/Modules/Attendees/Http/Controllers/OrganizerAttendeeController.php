<?php

namespace App\Modules\Attendees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendees\Application\Actions\CorrectAttendee;
use App\Modules\Attendees\Application\Queries\OrganizerAttendeeQuery;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class OrganizerAttendeeController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly TenantContextStore $contexts) {}

    public function index(Request $request, string $eventId, OrganizerAttendeeQuery $query)
    {
        $attendees = $query->execute(
            $this->contexts->current()->tenant->id,
            $eventId,
            $request->string('email')->toString() ?: null,
            $request->integer('page_size', 50),
        );

        return $this->success($attendees->map(fn ($attendee): array => [
            'id' => $attendee->id,
            'order_id' => $attendee->order_id,
            'ticket_type_id' => $attendee->ticket_type_id,
            'registration_status' => $attendee->registration_status,
            'preferred_locale' => $attendee->preferred_locale,
            'registered_at' => $attendee->registered_at?->toIso8601String(),
        ])->all());
    }

    public function update(Request $request, string $eventId, string $attendeeId, CorrectAttendee $action)
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:254'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $reason = $validated['reason'];
        unset($validated['reason']);
        $attendee = $action->execute($this->contexts->current(), $eventId, $attendeeId, $validated, $reason);

        return $this->success([
            'id' => $attendee->id,
            'registration_status' => $attendee->registration_status,
            'preferred_locale' => $attendee->preferred_locale,
        ]);
    }
}
