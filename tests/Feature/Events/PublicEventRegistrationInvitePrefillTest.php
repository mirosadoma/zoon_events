<?php

namespace Tests\Feature\Events;

use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class PublicEventRegistrationInvitePrefillTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    #[Test]
    public function registration_page_prefills_invite_name_and_locks_email(): void
    {
        $fixture = $this->createRegistrationFixture();

        EventRegistrationInvite::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'email' => 'guest@example.test',
            'name' => 'Amr Mohamed',
            'code' => '3624356920',
            'is_active' => true,
            'invite_status' => 'not_registered',
            'sent_at' => now(),
        ]);

        $this->get("/en/events/{$fixture['event']->slug}/register?invite=3624356920")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration/Event')
                ->where('inviteCode', '3624356920')
                ->where('lockedEmail', 'guest@example.test')
                ->where('prefillName', 'Amr Mohamed'));
    }
}
