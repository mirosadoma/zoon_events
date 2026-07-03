<?php

namespace Tests\Unit\Audit;

use App\Modules\Audit\Application\Integrity\AuditIntegrityService;
use App\Modules\Audit\Application\Integrity\CanonicalAuditPayload;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('audit')]
class AuditIntegrityTest extends TestCase
{
    public function test_canonical_payload_and_key_rotation_detect_mutation(): void
    {
        config(['audit.current_key_id' => 'current', 'audit.key_ring' => ['current' => 'current-synthetic-key', 'old' => 'old-synthetic-key-value']]);
        $service = new AuditIntegrityService(new CanonicalAuditPayload);
        $left = ['z' => ['b' => 2, 'a' => 1], 'a' => 'value'];
        $right = ['a' => 'value', 'z' => ['a' => 1, 'b' => 2]];

        self::assertSame($service->sign($left), $service->sign($right));
        self::assertTrue($service->verify($left, 'current', $service->sign($left)));
        self::assertFalse($service->verify(['a' => 'changed', 'z' => ['a' => 1, 'b' => 2]], 'current', $service->sign($left)));
        self::assertNotSame($service->sign($left, 'current'), $service->sign($left, 'old'));
    }

    public function test_unknown_key_fails_closed(): void
    {
        config(['audit.key_ring' => []]);
        $this->expectException(RuntimeException::class);
        (new AuditIntegrityService(new CanonicalAuditPayload))->sign(['a' => 1], 'missing');
    }
}
