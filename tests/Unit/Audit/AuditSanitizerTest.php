<?php

namespace Tests\Unit\Audit;

use App\Modules\Audit\Application\Sanitization\AuditSanitizer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('audit')]
class AuditSanitizerTest extends TestCase
{
    public function test_secrets_and_sensitive_changes_never_persist_verbatim(): void
    {
        $sanitizer = new AuditSanitizer;
        $metadata = $sanitizer->metadata(['password' => 'raw', 'nested' => ['api_token' => 'raw'], 'ip' => 'fingerprint-only']);
        $changes = $sanitizer->changes(['password' => 'old', 'name' => 'A'], ['password' => 'new', 'name' => 'B'], ['password', 'name']);

        self::assertSame('[REDACTED]', $metadata['password']);
        self::assertSame('[REDACTED]', $metadata['nested']['api_token']);
        self::assertSame(['changed' => true, 'redacted' => true], $changes['password']);
        self::assertSame(['from' => 'A', 'to' => 'B'], $changes['name']);
        self::assertStringNotContainsString('raw', json_encode([$metadata, $changes]));
    }
}
