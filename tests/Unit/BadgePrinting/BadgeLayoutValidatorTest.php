<?php

namespace Tests\Unit\BadgePrinting;

use App\Exceptions\FoundationException;
use App\Modules\BadgePrinting\Application\Support\BadgeLayoutValidator;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('badge-printing')]
#[Group('phase-3')]
final class BadgeLayoutValidatorTest extends TestCase
{
    private BadgeLayoutValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BadgeLayoutValidator;
    }

    public function test_valid_layout_does_not_throw(): void
    {
        $this->validator->validate([
            'attendee_name' => [],
            'qr' => [],
            'ticket_type' => [],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_unknown_field_throws_exception(): void
    {
        $this->expectException(FoundationException::class);

        $this->validator->validate([
            'attendee_name' => [],
            'invalid_field' => [],
        ]);
    }

    public function test_all_allowed_fields_pass_validation(): void
    {
        $this->validator->validate([
            'attendee_name' => [],
            'company' => [],
            'job_title' => [],
            'qr' => [],
            'ticket_type' => [],
            'attendee_type' => [],
            'tier' => [],
            'zone' => [],
            'sponsor_logo_ref' => [],
            'organizer_logo_ref' => [],
            'color_code' => [],
            'custom_text' => [],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_completely_unknown_field_throws(): void
    {
        $this->expectException(FoundationException::class);

        $this->validator->validate(['logo' => []]);
    }
}
