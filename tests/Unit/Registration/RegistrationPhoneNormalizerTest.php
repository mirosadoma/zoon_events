<?php

namespace Tests\Unit\Registration;

use App\Modules\Registration\Application\Support\RegistrationPhoneNormalizer;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class RegistrationPhoneNormalizerTest extends TestCase
{
    public function test_accepts_local_egyptian_mobile_numbers(): void
    {
        self::assertTrue(RegistrationPhoneNormalizer::isValid('01276069689'));
        self::assertSame('01276069689', RegistrationPhoneNormalizer::normalize('01276069689'));
    }

    public function test_strips_spaces_and_parentheses(): void
    {
        self::assertTrue(RegistrationPhoneNormalizer::isValid('(012) 760-69689'));
        self::assertSame('01276069689', RegistrationPhoneNormalizer::normalize('(012) 760-69689'));
    }

    public function test_accepts_international_prefixes(): void
    {
        self::assertTrue(RegistrationPhoneNormalizer::isValid('+966 50 123 4567'));
        self::assertSame('+966501234567', RegistrationPhoneNormalizer::normalize('+966 50 123 4567'));
    }
}
