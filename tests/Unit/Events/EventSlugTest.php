<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Application\Support\EventSlug;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class EventSlugTest extends TestCase
{
    public function test_slugifies_english_title_with_hyphens(): void
    {
        self::assertSame('summer-tech-summit', EventSlug::fromTitle('Summer Tech Summit'));
    }

    public function test_slugifies_arabic_title_keeping_letters(): void
    {
        self::assertSame('قمة-التقنية', EventSlug::fromTitle('قمة التقنية'));
    }

    public function test_prefers_english_name_when_present(): void
    {
        self::assertSame('english-title', EventSlug::fromNames('English Title', 'عنوان عربي'));
    }

    public function test_falls_back_to_arabic_when_english_empty(): void
    {
        self::assertSame('عنوان-عربي', EventSlug::fromNames('', 'عنوان عربي'));
    }

    public function test_falls_back_to_event_when_empty(): void
    {
        self::assertSame('event', EventSlug::fromTitle('   '));
    }
}
