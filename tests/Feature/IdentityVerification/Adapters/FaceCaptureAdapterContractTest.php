<?php

namespace Tests\Feature\IdentityVerification\Adapters;

use App\Modules\IdentityVerification\Domain\ValueObjects\FaceCaptureContext;
use App\Modules\IdentityVerification\Infrastructure\Adapters\MockFaceCaptureAdapter;
use App\Modules\IdentityVerification\Testing\FakeFaceCaptureAdapter;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-5')]
#[Group('identity-adapters')]
final class FaceCaptureAdapterContractTest extends TestCase
{
    public function test_mock_face_capture_adapter_returns_liveness_variants(): void
    {
        $adapter = new MockFaceCaptureAdapter;
        $context = new FaceCaptureContext('1', '10', '100', 'idem-1');

        $submitted = $adapter->submitCapture($context, 'ok');
        self::assertSame('submitted', $submitted->status);
        self::assertSame('template', $submitted->artifactType);
        self::assertSame('passed', $submitted->liveness?->status);

        self::assertSame('failed', $adapter->liveness('liveness-failed')?->status);
        self::assertSame('unavailable', $adapter->liveness('liveness-unavailable')?->status);
    }

    public function test_fake_face_capture_adapter_records_calls_and_is_deterministic(): void
    {
        $adapter = new FakeFaceCaptureAdapter;
        $context = new FaceCaptureContext('2', '20', '200', 'idem-2');

        $adapter->submitCapture($context, 'failed');

        $calls = $adapter->calls();
        self::assertCount(2, $calls);
        self::assertSame('submitCapture', $calls[0]['operation']);
        self::assertSame('liveness', $calls[1]['operation']);
        self::assertSame('failed', $calls[1]['payload']);
    }
}
