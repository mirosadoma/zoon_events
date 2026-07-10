<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Contracts\Clock;
use App\Modules\Shared\Domain\Context\CorrelationId;
use App\Modules\Shared\Domain\Context\RequestContext;
use App\Modules\Shared\Domain\Context\RequestId;
use App\Modules\Shared\Domain\DeploymentMode;
use App\Modules\Shared\Domain\Identifiers\Ulid;
use App\Modules\Shared\Domain\Locale;
use App\Modules\Shared\Support\Clock\FrozenClock;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FoundationPrimitivesTest extends TestCase
{
    #[Test]
    public function ulid_value_object_accepts_valid_ulids(): void
    {
        $id = Ulid::generate();

        $this->assertSame(26, strlen((string) $id));
        $this->assertTrue(Ulid::isValid((string) $id));
    }

    #[Test]
    public function ulid_value_object_rejects_invalid_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Ulid::fromString('not-a-ulid');
    }

    #[Test]
    public function deployment_mode_only_allows_supported_values(): void
    {
        $this->assertSame('saas', DeploymentMode::from('saas')->value);
        $this->assertSame('on_premise', DeploymentMode::from('on_premise')->value);
    }

    #[Test]
    public function locale_only_allows_english_and_arabic(): void
    {
        $this->assertSame('en', Locale::from('en')->value);
        $this->assertSame('ar', Locale::from('ar')->value);
    }

    #[Test]
    public function correlation_and_request_identifiers_are_bounded(): void
    {
        $correlationId = CorrelationId::fromHeader('tenant-flow-123');
        $requestId = RequestId::generate();

        $this->assertSame('tenant-flow-123', $correlationId->value);
        $this->assertLessThanOrEqual(64, strlen($requestId->value));
    }

    #[Test]
    public function invalid_correlation_id_is_replaced(): void
    {
        $correlationId = CorrelationId::fromHeader(str_repeat('*', 10));

        $this->assertNotSame(str_repeat('*', 10), $correlationId->value);
        $this->assertLessThanOrEqual(64, strlen($correlationId->value));
    }

    #[Test]
    public function frozen_clock_is_deterministic(): void
    {
        $frozenAt = CarbonImmutable::parse('2026-07-02T18:00:00Z');
        $clock = new FrozenClock($frozenAt);

        $this->assertInstanceOf(Clock::class, $clock);
        $this->assertTrue($frozenAt->equalTo($clock->now()));
    }

    #[Test]
    public function request_context_carries_ids_and_locale(): void
    {
        $context = new RequestContext(
            CorrelationId::generate(),
            RequestId::generate(),
            Locale::from('ar'),
        );

        $this->assertSame('ar', $context->locale->value);
        $this->assertLessThanOrEqual(64, strlen($context->correlationId->value));
    }
}
