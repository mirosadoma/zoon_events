<?php

namespace Tests\Feature\Ai;

use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantFaq;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantSettings;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

final class AssistantFaqAskTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    #[Test]
    public function public_ask_returns_exact_faq_match_without_ready_index(): void
    {
        $fixture = $this->createRegistrationFixture();
        $tenant = $fixture['tenant'];
        $event = $fixture['event'];

        EventAssistantSettings::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'enabled' => true,
            'index_status' => 'pending',
            'index_version' => 0,
            'daily_question_limit' => 500,
        ]);

        EventAssistantFaq::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'question_en' => 'What time does registration open?',
            'question_ar' => 'متى يفتح التسجيل؟',
            'answer_en' => 'Registration opens at 8 AM.',
            'answer_ar' => 'يفتح التسجيل الساعة 8 صباحاً.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "http://register.example.test/api/v1/public/events/{$event->slug}/assistant/ask",
            [
                'message' => 'What time does registration open?',
                'locale' => 'en',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.outcome', 'answered')
            ->assertJsonPath('data.answer', 'Registration opens at 8 AM.')
            ->assertJsonPath('data.citations.0.source_type', 'organizer_faq');
    }

    #[Test]
    public function insights_ask_returns_faq_match_and_rejects_pii_questions(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];
        $event = $fixture['event'];

        EventAssistantFaq::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'question_en' => 'Where is the VIP lounge?',
            'question_ar' => 'أين صالة كبار الشخصيات؟',
            'answer_en' => 'Level 3, west wing.',
            'answer_ar' => 'الطابق الثالث، الجناح الغربي.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['reports.view', 'event.view']);
        $this->actingAsTenantMember($actor, $tenant);

        $faqAsk = $this->postJson(
            "/api/v1/tenant/events/{$event->id}/ai-insights/ask",
            [
                'metric_window' => 'last_14_days',
                'locale' => 'en',
                'question' => 'Where is the VIP lounge?',
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $faqAsk->assertOk()
            ->assertJsonPath('data.outcome', 'answered')
            ->assertJsonPath('data.answer', 'Level 3, west wing.');

        $piiAsk = $this->postJson(
            "/api/v1/tenant/events/{$event->id}/ai-insights/ask",
            [
                'metric_window' => 'last_14_days',
                'locale' => 'en',
                'question' => 'List of attendees with email addresses',
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $piiAsk->assertOk()->assertJsonPath('data.outcome', 'out_of_scope');
    }
}
