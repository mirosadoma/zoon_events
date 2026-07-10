<?php

namespace App\Modules\AccessControl\Http\Resources;

use App\Modules\AccessControl\Domain\ValueObjects\AcsDecisionResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AcsDecisionResult */
final class AuthorizationDecisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'decision' => $this->decision,
            'reason_code' => $this->reasonCode,
            'access_event_id' => $this->accessEventId,
            'scan_event_id' => $this->scanEventId,
        ];
    }
}
