<?php

namespace App\Modules\IdentityVerification\Infrastructure\Adapters;

use App\Modules\IdentityVerification\Contracts\FaceCaptureAdapter;
use App\Modules\IdentityVerification\Domain\Results\FaceCaptureResult;
use App\Modules\IdentityVerification\Domain\Results\FaceLivenessResult;
use App\Modules\IdentityVerification\Domain\ValueObjects\FaceCaptureContext;

final class MockFaceCaptureAdapter implements FaceCaptureAdapter
{
    public function submitCapture(FaceCaptureContext $context, string $capture): FaceCaptureResult
    {
        return new FaceCaptureResult(
            status: 'submitted',
            reference: sprintf('face-%s', $context->attendeeId),
            artifactType: 'template',
            liveness: $this->liveness($capture),
        );
    }

    public function liveness(string $capture): ?FaceLivenessResult
    {
        return match (trim($capture)) {
            'liveness-failed' => new FaceLivenessResult('failed', 'identity_provider_unavailable'),
            'liveness-unavailable' => new FaceLivenessResult('unavailable', 'identity_provider_unavailable'),
            default => new FaceLivenessResult('passed'),
        };
    }
}
