<?php

namespace App\Modules\IdentityVerification\Infrastructure\Adapters;

use App\Modules\IdentityVerification\Contracts\GovernmentIdentityAdapter;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityAttributes;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityCallbackResult;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityStartResult;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityVerificationResult;
use App\Modules\IdentityVerification\Domain\ValueObjects\GovernmentIdentityContext;

final class MockGovernmentIdentityAdapter implements GovernmentIdentityAdapter
{
    public function startVerification(GovernmentIdentityContext $context): GovernmentIdentityStartResult
    {
        return new GovernmentIdentityStartResult(
            status: 'started',
            reference: sprintf('gov-%s', $context->attendeeId),
            redirectUrl: sprintf('/mock/identity/%s', $context->attendeeId),
        );
    }

    public function handleCallback(array $payload): GovernmentIdentityCallbackResult
    {
        return new GovernmentIdentityCallbackResult(
            status: (string) ($payload['status'] ?? 'verified'),
            reference: isset($payload['reference']) ? (string) $payload['reference'] : null,
            raw: $payload,
        );
    }

    public function fetchResult(string $reference): GovernmentIdentityVerificationResult
    {
        return new GovernmentIdentityVerificationResult(
            status: 'verified',
            reference: $reference,
            attributes: new GovernmentIdentityAttributes(
                verifiedName: 'Mock Verified Attendee',
                verifiedNationality: 'SA',
            ),
        );
    }

    public function mapAttributes(array $raw): GovernmentIdentityAttributes
    {
        return new GovernmentIdentityAttributes(
            verifiedName: isset($raw['verified_name']) ? (string) $raw['verified_name'] : 'Mock Verified Attendee',
            verifiedNationality: isset($raw['verified_nationality']) ? (string) $raw['verified_nationality'] : 'SA',
        );
    }
}
