<?php

namespace Tests\Unit\Scanning;

use App\Modules\Scanning\Application\Queries\LookupAttendeesQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('manual-desk')]
#[Group('phase-3')]
final class LookupAttendeesQueryTest extends TestCase
{
    public function test_class_exists_and_has_required_method(): void
    {
        self::assertTrue(class_exists(LookupAttendeesQuery::class), 'LookupAttendeesQuery class must exist');
        self::assertTrue(method_exists(LookupAttendeesQuery::class, 'search'), 'LookupAttendeesQuery must have search() method');
    }

    public function test_search_method_signature_is_correct(): void
    {
        $reflection = new \ReflectionMethod(LookupAttendeesQuery::class, 'search');
        $params = $reflection->getParameters();

        self::assertCount(4, $params, 'search() must have 4 parameters');
        self::assertSame('tenantId', $params[0]->getName());
        self::assertSame('eventId', $params[1]->getName());
        self::assertSame('fragment', $params[2]->getName());
        self::assertSame('maxMatches', $params[3]->getName());
        self::assertTrue($params[3]->isOptional(), 'maxMatches should have a default');
        self::assertSame(8, $params[3]->getDefaultValue(), 'maxMatches default should be 8');
    }
}
