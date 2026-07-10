<?php

namespace Tests\Feature\AdminConsole;

use App\Modules\AdminConsole\ViewModels\Events\OrganizerOperationsViewModel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
#[Group('phase-1-organizer')]
final class Phase1OrganizerAuthorizationTest extends TestCase
{
    public function test_view_models_never_expose_encrypted_or_indexed_personal_fields(): void
    {
        $view = new OrganizerOperationsViewModel;
        $props = $view->attendees([(object) [
            'id' => 'attendee-safe',
            'registration_status' => 'registered',
            'preferred_locale' => 'ar',
            'email_ciphertext' => 'forbidden',
            'email_index' => 'forbidden',
        ]]);
        $serialized = json_encode($props, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('ciphertext', $serialized);
        self::assertStringNotContainsString('email_index', $serialized);
        self::assertStringNotContainsString('forbidden', $serialized);
    }
}
