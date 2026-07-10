<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\Redaction\SafeMetadata;
use App\Modules\Shared\Support\Redaction\SecretRedactor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecretRedactorTest extends TestCase
{
    #[Test]
    public function redactor_removes_secret_like_values_recursively(): void
    {
        $redacted = SecretRedactor::redact([
            'password' => 'super-secret',
            'nested' => [
                'token' => 'abc123',
                'api_key' => 'xyz789',
                'authorization_header' => 'Bearer very-secret-token',
            ],
        ]);

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['nested']['token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['api_key']);
        $this->assertSame('[REDACTED]', $redacted['nested']['authorization_header']);
    }

    #[Test]
    public function safe_metadata_limits_large_nested_values(): void
    {
        $metadata = SafeMetadata::from([
            'connection_string' => 'mysql://secret@localhost',
            'payload' => [
                'notes' => str_repeat('a', 300),
                'deep' => [
                    'level_2' => [
                        'level_3' => [
                            'level_4' => [
                                'level_5' => 'too-deep',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('[REDACTED]', $metadata['connection_string']);
        $this->assertSame('[TRUNCATED]', $metadata['payload']['notes']);
        $this->assertSame('[TRUNCATED]', $metadata['payload']['deep']['level_2']['level_3']['truncated']);
    }
}
