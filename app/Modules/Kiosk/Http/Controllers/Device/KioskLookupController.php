<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Credentials\Application\Validation\CredentialValidator;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Scanning\Application\Queries\LookupAttendeesQuery;
use App\Modules\Scanning\Http\Requests\AttendeeLookupRequest;
use App\Modules\Scanning\Http\Resources\AttendeeLookupResource;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;

final class KioskLookupController extends \App\Http\Controllers\Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly KioskSessionContextStore $kioskContexts,
        private readonly CredentialValidator $credentials,
        private readonly LookupAttendeesQuery $lookup,
    ) {}

    public function store(AttendeeLookupRequest $request): \Illuminate\Http\JsonResponse
    {
        $context  = $this->kioskContexts->current();
        $tenantId = $context->tenantId;
        $eventId  = $context->eventId;

        if ($request->filled('qr_payload')) {
            try {
                $validated = $this->credentials->validate(
                    $request->string('qr_payload')->toString(),
                    $tenantId,
                    $eventId,
                );
                $result = [
                    'too_many' => false,
                    'matches'  => [[
                        'attendee_id'       => null,
                        'credential_id'     => $validated['credential_id'],
                        'display_name'      => null,
                        'ticket_type_label' => null,
                        'checkin_status'    => 'not_checked_in',
                    ]],
                ];
            } catch (\Throwable $e) {
                throw $e;
            }
        } else {
            $fragment   = $request->string('query')->toString();
            $maxMatches = (int) config('printing.lookup.max_matches', 8);
            $result = $this->lookup->search($tenantId, $eventId, $fragment, $maxMatches);

            if ($result['too_many']) {
                throw Phase3Problem::make('lookup_too_many_matches');
            }
        }

        return $this->success((new AttendeeLookupResource($result))->resolve());
    }
}
