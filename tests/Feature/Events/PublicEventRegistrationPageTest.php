<?php

namespace Tests\Feature\Events;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class PublicEventRegistrationPageTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_guest_can_open_registration_page_by_event_slug(): void
    {
        $fixture = $this->createRegistrationFixture();

        $response = $this->get("/ar/events/{$fixture['event']->slug}/register");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration/Event')
                ->where('isPreview', false)
                ->where('submitUrl', "/ar/events/{$fixture['event']->slug}/register")
                ->has('form.fields')
                ->has('categories'));
    }

    public function test_draft_event_registration_page_is_not_found(): void
    {
        $fixture = $this->createRegistrationFixture();
        $fixture['event']->update(['status' => 'draft']);

        $this->get("/ar/events/{$fixture['event']->slug}/register")->assertNotFound();
    }
}
