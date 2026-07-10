<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Application\AdapterInvocationContext;
use App\Modules\Integrations\Domain\AdapterResult;
use App\Modules\Integrations\Domain\AdapterRetryPolicy;
use App\Modules\Integrations\Domain\AdapterStatus;
use App\Modules\Integrations\Testing\FakeCapabilityAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('adapter')]
class AdapterContractTest extends TestCase
{
    public function test_fake_drives_success_timeout_unknown_rejection_and_offline_states(): void
    {
        $context = new AdapterInvocationContext('tenant', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'actor', 'correlation', 'idempotency', 'en', 1000, 'internal');
        $fake = new FakeCapabilityAdapter;

        self::assertSame(AdapterStatus::Succeeded, $fake->execute($context, ['safe' => true])->status);
        self::assertSame(AdapterRetryPolicy::Safe, $fake->scenario('timeout_before_send')->execute($context, [])->retryPolicy);
        self::assertSame(AdapterRetryPolicy::ReconcileFirst, $fake->scenario('timeout_unknown')->execute($context, [])->retryPolicy);
        self::assertSame(AdapterStatus::Rejected, $fake->scenario('rejected')->execute($context, [])->status);
        self::assertSame(AdapterStatus::Unavailable, $fake->scenario('offline')->execute($context, [])->status);
    }

    public function test_unknown_outcome_cannot_be_blindly_retried(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AdapterResult(AdapterStatus::Unknown, AdapterRetryPolicy::Safe, 'timeout_unknown_outcome');
    }
}
