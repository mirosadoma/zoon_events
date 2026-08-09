<?php

namespace Tests\Unit\Ai;

use App\Modules\Ai\Application\Retrieval\KnowledgeChunker;
use App\Modules\Ai\Application\Retrieval\Sources\OrganizerFaqSource;
use App\Modules\Ai\Application\Support\OrganizerFaqMatcher;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantFaq;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

final class OrganizerFaqSupportTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    #[Test]
    public function matcher_matches_near_exact_questions_and_ignores_inactive(): void
    {
        $fixture = $this->createRegistrationFixture();
        $tenantId = (int) $fixture['tenant']->id;
        $eventId = (int) $fixture['event']->id;

        EventAssistantFaq::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'question_en' => 'Where can I park?',
            'question_ar' => 'أين يمكنني ركن السيارة؟',
            'answer_en' => 'Lot A',
            'answer_ar' => 'الموقف أ',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        EventAssistantFaq::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'question_en' => 'Is lunch included?',
            'question_ar' => 'هل الغداء مشمول؟',
            'answer_en' => 'Yes',
            'answer_ar' => 'نعم',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $matcher = new OrganizerFaqMatcher;

        $match = $matcher->match($tenantId, $eventId, 'Where can I park?', 'en');
        self::assertNotNull($match);
        self::assertSame('Lot A', $match->answer_en);

        $inactive = $matcher->match($tenantId, $eventId, 'Is lunch included?', 'en');
        self::assertNull($inactive);

        self::assertGreaterThanOrEqual(0.82, $matcher->similarity(
            $matcher->normalize('Where can I park'),
            $matcher->normalize('Where can I park?'),
        ));
    }

    #[Test]
    public function organizer_faq_source_emits_bilingual_chunks(): void
    {
        $fixture = $this->createRegistrationFixture();
        $tenantId = (int) $fixture['tenant']->id;
        $eventId = (int) $fixture['event']->id;

        $faq = EventAssistantFaq::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'question_en' => 'Dress code?',
            'question_ar' => 'قواعد اللباس؟',
            'answer_en' => 'Business casual.',
            'answer_ar' => 'غير رسمي أنيق.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        EventAssistantFaq::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'question_en' => 'Inactive?',
            'question_ar' => 'غير نشط؟',
            'answer_en' => 'Hidden',
            'answer_ar' => 'مخفي',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $source = new OrganizerFaqSource(new KnowledgeChunker);
        $chunks = $source->extract($tenantId, $eventId);

        self::assertSame('organizer_faq', $source->sourceType());
        self::assertCount(2, $chunks);
        self::assertSame('faq:'.$faq->id, $chunks[0]->sourceId);
        self::assertSame('en', $chunks[0]->locale);
        self::assertSame('ar', $chunks[1]->locale);
        self::assertStringContainsString('Q: Dress code?', $chunks[0]->content);
        self::assertStringContainsString('A: Business casual.', $chunks[0]->content);
    }
}
