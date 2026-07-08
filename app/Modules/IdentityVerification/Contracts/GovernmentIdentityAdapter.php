<?php

namespace App\Modules\IdentityVerification\Contracts;

use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityAttributes;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityCallbackResult;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityStartResult;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityVerificationResult;
use App\Modules\IdentityVerification\Domain\ValueObjects\GovernmentIdentityContext;

interface GovernmentIdentityAdapter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function startVerification(GovernmentIdentityContext $context): GovernmentIdentityStartResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): GovernmentIdentityCallbackResult;

    public function fetchResult(string $reference): GovernmentIdentityVerificationResult;

    /**
     * @param  array<string, mixed>  $raw
     */
    public function mapAttributes(array $raw): GovernmentIdentityAttributes;
}
