<?php

namespace Tests\Integration\Security;

use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('credential-security')]
final class CredentialLeakageTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_raw_qr_never_appears_in_database_audit_or_logs(): void
    {
        $fixture = $this->createRegistrationFixture();
        $created = $this->withHeader('Idempotency-Key', 'credential-leakage')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();
        $token = $created->json('data.credential.qr_payload');
        $credential = Credential::query()->where('event_id', $fixture['event']->id)->firstOrFail();

        self::assertNotSame($token, $credential->token_digest);
        self::assertFalse(DB::table('audit_logs')->where('metadata', 'like', '%'.$token.'%')->exists());
        $log = storage_path('logs/laravel.log');
        if (is_file($log)) {
            self::assertStringNotContainsString($token, file_get_contents($log));
        } else {
            self::assertTrue(true);
        }
    }
}
