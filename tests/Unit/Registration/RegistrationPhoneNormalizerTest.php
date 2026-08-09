<?php

namespace Tests\Unit\Registration;

use App\Modules\Registration\Application\Support\RegistrationPhoneNormalizer;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class RegistrationPhoneNormalizerTest extends TestCase
{
    public function test_accepts_local_saudi_mobile_numbers(): void
    {
        self::assertTrue(RegistrationPhoneNormalizer::isValid('0512312312'));
        self::assertSame('0512312312', RegistrationPhoneNormalizer::normalize('0512312312'));
    }

    public function test_strips_spaces_and_punctuation(): void
    {
        self::assertTrue(RegistrationPhoneNormalizer::isValid('(05) 123-12312'));
        self::assertSame('0512312312', RegistrationPhoneNormalizer::normalize('(05) 123-12312'));
    }

    public function test_converts_saudi_international_prefix_to_local(): void
    {
        self::assertTrue(RegistrationPhoneNormalizer::isValid('+966 51 231 2312'));
        self::assertSame('0512312312', RegistrationPhoneNormalizer::normalize('+966 51 231 2312'));
    }

    public function test_rejects_numbers_that_do_not_start_with_05_or_wrong_length(): void
    {
        self::assertFalse(RegistrationPhoneNormalizer::isValid('01276069689'));
        self::assertFalse(RegistrationPhoneNormalizer::isValid('05123'));
        self::assertFalse(RegistrationPhoneNormalizer::isValid('0612312312'));
        self::assertFalse(RegistrationPhoneNormalizer::isValid('05123123123'));
    }
}
