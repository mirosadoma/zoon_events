<?php

namespace App\Modules\IdentityVerification\Testing;

use App\Modules\IdentityVerification\Contracts\FaceCaptureAdapter;
use App\Modules\IdentityVerification\Domain\Results\FaceCaptureResult;
use App\Modules\IdentityVerification\Domain\Results\FaceLivenessResult;
use App\Modules\IdentityVerification\Domain\ValueObjects\FaceCaptureContext;

final class FakeFaceCaptureAdapter implements FaceCaptureAdapter
{
    /** @var list<array{operation:string,payload:mixed}> */
    private array $calls = [];

    public function submitCapture(FaceCaptureContext $context, string $capture): FaceCaptureResult
    {
        $this->calls[] = [
            'operation' => 'submitCapture',
            'payload' => ['context' => $context, 'capture' => $capture],
        ];

        return new FaceCaptureResult(
            status: 'submitted',
            reference: sprintf('fake-face-%s', $context->attendeeId),
            artifactType: 'template',
            liveness: $this->liveness($capture),
        );
    }

    public function liveness(string $capture): ?FaceLivenessResult
    {
        $this->calls[] = ['operation' => 'liveness', 'payload' => $capture];

        return match (trim($capture)) {
            'failed' => new FaceLivenessResult('failed', 'identity_provider_unavailable'),
            'unavailable' => new FaceLivenessResult('unavailable', 'identity_provider_unavailable'),
            default => new FaceLivenessResult('passed'),
        };
    }

    /** @return list<array{operation:string,payload:mixed}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
