<?php

namespace Tests\Unit\Credentials;

use App\Modules\Credentials\Application\Validation\CredentialValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-3')]
final class CredentialValidatorValidateByIdTest extends TestCase
{
    public function test_class_has_validate_by_id_method(): void
    {
        self::assertTrue(method_exists(CredentialValidator::class, 'validateById'), 'CredentialValidator must have validateById() method');
    }

    public function test_validate_by_id_method_signature(): void
    {
        $reflection = new \ReflectionMethod(CredentialValidator::class, 'validateById');
        $params = $reflection->getParameters();

        self::assertCount(3, $params, 'validateById() must have 3 parameters: credentialId, tenantId, eventId');
        self::assertSame('credentialId', $params[0]->getName());
        self::assertSame('tenantId', $params[1]->getName());
        self::assertSame('eventId', $params[2]->getName());
    }
}
