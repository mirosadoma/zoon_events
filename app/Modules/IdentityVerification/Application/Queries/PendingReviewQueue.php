<?php

namespace App\Modules\IdentityVerification\Application\Queries;

use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Illuminate\Support\Collection;

final readonly class PendingReviewQueue
{
    /** @return Collection<int, IdentityVerification> */
    public function forEvent(string $tenantId, string $eventId): Collection
    {
        return IdentityVerification::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', IdentityVerificationStatus::PENDING)
            ->whereIn('method', [
                IdentityVerificationMethod::FACE_CAPTURE,
                IdentityVerificationMethod::MANUAL_REVIEW,
            ])
            ->orderBy('updated_at')
            ->get();
    }
}
