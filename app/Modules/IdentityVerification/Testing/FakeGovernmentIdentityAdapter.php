<?php

namespace App\Modules\IdentityVerification\Testing;

use App\Modules\IdentityVerification\Contracts\GovernmentIdentityAdapter;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityAttributes;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityCallbackResult;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityStartResult;
use App\Modules\IdentityVerification\Domain\Results\GovernmentIdentityVerificationResult;
use App\Modules\IdentityVerification\Domain\ValueObjects\GovernmentIdentityContext;

final class FakeGovernmentIdentityAdapter implements GovernmentIdentityAdapter
{
    /** @var list<array{operation:string,payload:mixed}> */
    private array $calls = [];

    public function startVerification(GovernmentIdentityContext $context): GovernmentIdentityStartResult
    {
        $this->calls[] = ['operation' => 'startVerification', 'payload' => $context];

        return new GovernmentIdentityStartResult('started', sprintf('fake-gov-%s', $context->attendeeId));
    }

    public function handleCallback(array $payload): GovernmentIdentityCallbackResult
    {
        $this->calls[] = ['operation' => 'handleCallback', 'payload' => $payload];

        return new GovernmentIdentityCallbackResult(
            status: (string) ($payload['status'] ?? 'verified'),
            reference: isset($payload['reference']) ? (string) $payload['reference'] : null,
            raw: $payload,
        );
    }

    public function fetchResult(string $reference): GovernmentIdentityVerificationResult
    {
        $this->calls[] = ['operation' => 'fetchResult', 'payload' => $reference];

        return new GovernmentIdentityVerificationResult(
            'verified',
            $reference,
            new GovernmentIdentityAttributes('Fake Verified Attendee', 'SA'),
        );
    }

    public function mapAttributes(array $raw): GovernmentIdentityAttributes
    {
        $this->calls[] = ['operation' => 'mapAttributes', 'payload' => $raw];

        return new GovernmentIdentityAttributes(
            isset($raw['verified_name']) ? (string) $raw['verified_name'] : 'Fake Verified Attendee',
            isset($raw['verified_nationality']) ? (string) $raw['verified_nationality'] : 'SA',
        );
    }

    /** @return list<array{operation:string,payload:mixed}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
