<?php

namespace App\Modules\IdentityVerification\Contracts;

use App\Modules\IdentityVerification\Domain\Results\FaceCaptureResult;
use App\Modules\IdentityVerification\Domain\Results\FaceLivenessResult;
use App\Modules\IdentityVerification\Domain\ValueObjects\FaceCaptureContext;

interface FaceCaptureAdapter
{
    public function submitCapture(FaceCaptureContext $context, string $capture): FaceCaptureResult;

    public function liveness(string $capture): ?FaceLivenessResult;
}
