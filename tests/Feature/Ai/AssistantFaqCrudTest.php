<?php

namespace Tests\Feature\Ai;

use App\Modules\Ai\Application\Jobs\RebuildEventKnowledgeIndexJob;
use App\Modules\Ai\Infrastructure\Persistence\Models\EventAssistantFaq;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

final class AssistantFaqCrudTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    #[Test]
    public function it_creates_lists_updates_and_deletes_faqs_for_scoped_event(): void
    {
        Queue::fake();
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];
        $event = $fixture['event'];

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['event.view', 'event.manage']);
        $this->actingAsTenantMember($actor, $tenant);

        $create = $this->postJson(
            "/api/v1/tenant/events/{$event->id}/assistant/faqs",
            [
                'question_en' => 'Where is parking?',
                'question_ar' => 'أين موقف السيارات؟',
                'answer_en' => 'Lot B next to Gate 2.',
                'answer_ar' => 'الموقف ب بجانب البوابة 2.',
                'is_active' => true,
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $create->assertCreated()
            ->assertJsonPath('data.question_en', 'Where is parking?')
            ->assertJsonPath('data.is_active', true);

        $faqId = $create->json('data.id');
        Queue::assertPushed(RebuildEventKnowledgeIndexJob::class);

        $list = $this->getJson(
            "/api/v1/tenant/events/{$event->id}/assistant/faqs",
            ['X-Tenant-ID' => (string) $tenant->id],
        );

        $list->assertOk()
            ->assertJsonCount(1, 'data.faqs')
            ->assertJsonPath('data.faqs.0.id', $faqId);

        $update = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/assistant/faqs/{$faqId}",
            [
                'answer_en' => 'Lot C near Gate 1.',
                'is_active' => false,
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $update->assertOk()
            ->assertJsonPath('data.answer_en', 'Lot C near Gate 1.')
            ->assertJsonPath('data.is_active', false);

        $delete = $this->deleteJson(
            "/api/v1/tenant/events/{$event->id}/assistant/faqs/{$faqId}",
            [],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $delete->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('event_assistant_faqs', ['id' => $faqId]);
    }

    #[Test]
    public function it_returns_not_found_for_foreign_tenant_event(): void
    {
        Queue::fake();
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $other = $this->createRegistrationFixture();

        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $fixture['actor']->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['actor']->id,
        ]);
        $this->grantTenantPermissions($fixture['tenant'], $membership, ['event.view', 'event.manage']);
        $this->actingAsTenantMember($fixture['actor'], $fixture['tenant']);

        $this->getJson(
            "/api/v1/tenant/events/{$other['event']->id}/assistant/faqs",
            ['X-Tenant-ID' => (string) $fixture['tenant']->id],
        )->assertNotFound();

        $this->postJson(
            "/api/v1/tenant/events/{$other['event']->id}/assistant/faqs",
            [
                'question_en' => 'Foreign?',
                'question_ar' => 'أجنبي؟',
                'answer_en' => 'No',
                'answer_ar' => 'لا',
            ],
            [
                'X-Tenant-ID' => (string) $fixture['tenant']->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        )->assertNotFound();
    }

    #[Test]
    public function it_returns_not_found_for_faq_from_another_event(): void
    {
        Queue::fake();
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];
        $event = $fixture['event'];

        $otherEvent = Event::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'other-'.Str::lower((string) Str::ulid()),
            'name_en' => 'Other',
            'name_ar' => 'آخر',
            'tier' => 'public',
            'status' => 'draft',
            'timezone' => 'Africa/Cairo',
            'start_at' => '2027-02-10 12:00:00',
            'end_at' => '2027-02-10 18:00:00',
            'registration_opens_at' => '2026-01-01 00:00:00',
            'registration_closes_at' => '2027-02-10 11:00:00',
            'created_by_user_id' => $actor->id,
        ]);

        $faq = EventAssistantFaq::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $otherEvent->id,
            'question_en' => 'Hidden?',
            'question_ar' => 'مخفي؟',
            'answer_en' => 'Yes',
            'answer_ar' => 'نعم',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['event.view', 'event.manage']);
        $this->actingAsTenantMember($actor, $tenant);

        $this->putJson(
            "/api/v1/tenant/events/{$event->id}/assistant/faqs/{$faq->id}",
            ['answer_en' => 'Nope'],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        )->assertNotFound();
    }
}
