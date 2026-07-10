<?php

namespace App\Modules\AccessControl\Infrastructure\Adapters;

use App\Modules\AccessControl\Contracts\AcsAdapter;
use App\Modules\AccessControl\Domain\Results\AcsHealthResult;

/**
 * Provider-neutral mock ACS used while the real Runa transport is an open
 * integration question (plan.md §Summary). It reports online and available so
 * gate authorization proceeds on the organizer-configured rules; a real adapter
 * replaces this binding later without changing AccessControl domain logic.
 */
final class MockAcsAdapter implements AcsAdapter
{
    public function health(): AcsHealthResult
    {
        return new AcsHealthResult('online');
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
