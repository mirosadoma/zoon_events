<?php

namespace Tests\Contract;

use Tests\TestCase;

class OpenApiDocumentTest extends TestCase
{
    public function test_openapi_is_versioned_synchronized_and_has_unique_operation_ids(): void
    {
        $source = file_get_contents(base_path('specs/001-project-foundation/contracts/openapi.yaml'));
        $copy = file_get_contents(base_path('docs/api/openapi.yaml'));
        preg_match_all('/operationId:\\s*([A-Za-z0-9_]+)/', $source, $matches);

        self::assertMatchesRegularExpression('/openapi:\\s+3\\.1\\.[01]/', $source);
        self::assertSame(hash('sha256', $source), hash('sha256', $copy));
        self::assertNotEmpty($matches[1]);
        self::assertSame($matches[1], array_values(array_unique($matches[1])));
        self::assertStringNotContainsString('example.com@example.com', $source);
    }
}
