<?php

namespace Tests\Feature\Events;

use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class PublicEventAgendaPageTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_guest_can_open_agenda_page_by_event_slug(): void
    {
        $fixture = $this->createRegistrationFixture();

        EventAgendaItem::query()->create([
            'tenant_id' => $fixture['event']->tenant_id,
            'event_id' => $fixture['event']->id,
            'title_en' => 'Opening speech',
            'title_ar' => 'كلمة افتتاحية',
            'start_at' => $fixture['event']->start_at,
            'end_at' => $fixture['event']->start_at?->addMinutes(15),
            'sort_order' => 0,
        ]);

        $response = $this->get("/en/events/{$fixture['event']->slug}/agenda");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration/Agenda')
                ->where('registerUrl', "/events/{$fixture['event']->slug}/register")
                ->has('items', 1)
                ->has('event.name'));
    }

    public function test_draft_event_agenda_page_is_not_found(): void
    {
        $fixture = $this->createRegistrationFixture();
        $fixture['event']->update(['status' => 'draft']);

        $this->get("/en/events/{$fixture['event']->slug}/agenda")->assertNotFound();
    }
}
