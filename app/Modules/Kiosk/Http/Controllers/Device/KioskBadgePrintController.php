<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Application\Actions\BuildBadgePrintDocumentAction;
use App\Modules\BadgePrinting\Application\Actions\CreateBadgePrintJobAction;
use App\Modules\BadgePrinting\Http\Resources\BadgePrintJobResource;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Http\Requests\KioskBadgePrintRequest;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;

final class KioskBadgePrintController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly KioskSessionContextStore $kioskContexts,
        private readonly BuildBadgePrintDocumentAction $printDocuments,
    ) {}

    public function preview(KioskBadgePrintRequest $request): JsonResponse
    {
        [$attendee, $credential] = $this->resolveCheckedInAttendee($request);

        $context = $this->kioskContexts->current();
        $document = $this->buildDocument(
            (string) $context->tenantId,
            (string) $context->eventId,
            (string) $attendee->id,
            (string) $credential->id,
            null,
            $request->fieldOverrides(),
            false,
        );

        if ($document === null) {
            throw Phase3Problem::make('badge_template_not_active');
        }

        return $this->success([
            'print_html' => $document['html'],
            'fields' => $document['fields'],
            'editable_fields' => $document['editable_fields'],
        ]);
    }

    public function store(KioskBadgePrintRequest $request, CreateBadgePrintJobAction $action): JsonResponse
    {
        [$attendee, $credential] = $this->resolveCheckedInAttendee($request);
        $context = $this->kioskContexts->current();
        $fieldOverrides = $request->fieldOverrides();

        $job = $action->execute(
            tenantId: $context->tenantId,
            eventId: $context->eventId,
            attendeeId: (string) $attendee->id,
            credentialId: (string) $credential->id,
            kioskId: $context->kioskId,
            printedByUserId: null,
        );

        $payload = (new BadgePrintJobResource($job))->resolve();
        $document = $this->buildDocument(
            (string) $context->tenantId,
            (string) $context->eventId,
            (string) $attendee->id,
            (string) $credential->id,
            $job->badge_template_id !== null ? (string) $job->badge_template_id : null,
            $fieldOverrides,
            true,
        );
        $payload['print_html'] = $document['html'] ?? null;

        return $this->success($payload, 201);
    }

    /**
     * @return array{0: Attendee, 1: Credential}
     */
    private function resolveCheckedInAttendee(KioskBadgePrintRequest $request): array
    {
        $context = $this->kioskContexts->current();
        $attendeeId = $request->string('attendee_id')->toString();
        $credentialId = $request->string('credential_id')->toString();

        $attendee = Attendee::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->find($attendeeId);

        if ($attendee === null) {
            throw Phase3Problem::make('badge_print_checkin_required');
        }

        $checkedIn = $attendee->checkin_status === 'checked_in'
            || $attendee->first_checked_in_at !== null;

        if (! $checkedIn) {
            throw Phase3Problem::make('badge_print_checkin_required');
        }

        $credential = Credential::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->where('attendee_id', $attendee->id)
            ->find($credentialId);

        if ($credential === null) {
            throw Phase3Problem::make('badge_print_checkin_required');
        }

        return [$attendee, $credential];
    }

    /**
     * @param  array<string, string|null>  $fieldOverrides
     * @return array{html: string, fields: array<string, string|null>, editable_fields: list<string>}|null
     */
    private function buildDocument(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $credentialId,
        ?string $templateId,
        array $fieldOverrides = [],
        bool $autoPrint = true,
    ): ?array {
        $template = $templateId !== null
            ? BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->find($templateId)
            : null;

        if ($template === null) {
            $template = BadgeTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();
        }

        if ($template === null) {
            return null;
        }

        return $this->printDocuments->build(
            $tenantId,
            $eventId,
            $attendeeId,
            $credentialId,
            $template,
            $fieldOverrides,
            $autoPrint,
        );
    }
}
