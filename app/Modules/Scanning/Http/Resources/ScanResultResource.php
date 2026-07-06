<?php

namespace App\Modules\Scanning\Http\Resources;

use App\Modules\Scanning\Application\Results\ScanSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScanSubmission */
final class ScanResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ScanSubmission $submission */
        $submission = $this->resource;

        return [
            'scan_event_id' => $submission->scanEventId,
            'result' => $submission->decision->result,
            'reason_code' => $submission->decision->reasonCode,
            'attendee_display_name' => $submission->attendeeDisplayName,
            'ticket_type_label' => $submission->ticketTypeLabel,
        ];
    }
}
