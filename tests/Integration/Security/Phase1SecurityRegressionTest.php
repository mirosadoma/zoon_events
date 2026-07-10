<?php

namespace Tests\Integration\Security;

use App\Modules\Attendees\Application\Jobs\AnonymizeEligibleAttendees;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Notifications\Application\Rendering\ConfirmationRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('phase-1-privacy')]
final class Phase1SecurityRegressionTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_retention_anonymizes_contact_data_but_preserves_financial_tombstones_and_legal_holds(): void
    {
        $fixture = $this->createRegistrationFixture(domainReference: 'privacy-retention.example.test');
        $eligible = $this->registered('eligible-retention', $fixture);
        $held = $this->registered('held-retention', $fixture);
        $held->forceFill(['legal_hold_at' => now(), 'legal_hold_reference' => 'CASE-SAFE'])->save();
        Attendee::query()->whereIn('id', [$eligible->id, $held->id])->update(['registered_at' => now()->subYears(2)]);
        $total = DB::table('orders')->where('id', $eligible->order_id)->value('total_minor');

        $preview = app(AnonymizeEligibleAttendees::class)->handle($eligible->tenant_id, Carbon::now()->subYear(), true);
        self::assertSame(1, $preview['eligible']);
        self::assertSame(0, $preview['anonymized']);
        $result = app(AnonymizeEligibleAttendees::class)->handle($eligible->tenant_id, Carbon::now()->subYear(), false);

        self::assertSame(1, $result['anonymized']);
        self::assertSame('anonymized', $eligible->refresh()->registration_status);
        self::assertSame('registered', $held->refresh()->registration_status);
        self::assertSame($total, DB::table('orders')->where('id', $eligible->order_id)->value('total_minor'));
        self::assertSame('anonymized', DB::table('notifications')->where('attendee_id', $eligible->id)->value('destination_ciphertext'));
        self::assertSame('anonymized', DB::table('registration_submissions')->where('id', $eligible->submission_id)->value('answers_ciphertext'));
    }

    public function test_templates_escape_xss_and_models_reject_forbidden_mass_assignment_fields(): void
    {
        $rendered = app(ConfirmationRenderer::class)->render('en', [
            'event_name' => '<script>alert(1)</script>',
            'order_reference' => "' OR 1=1 --",
            'credential_url' => 'https://example.test/public/orders/safe',
        ]);
        self::assertStringNotContainsString('<script>', $rendered['body']);
        self::assertStringContainsString('&lt;script&gt;', $rendered['body']);

        $attendee = new Attendee;
        $attendee->fill(['registration_status' => 'anonymized', 'legal_hold_reference' => 'forged']);
        self::assertArrayNotHasKey('registration_status', $attendee->getAttributes());
    }

    /** @param array<string,mixed>|null $fixture */
    private function registered(string $idempotency, ?array $fixture = null): Attendee
    {
        $fixture ??= $this->createRegistrationFixture();
        $host = $fixture['event']->branding()->value('domain_reference');
        $this->withHeader('Idempotency-Key', $idempotency)->postJson(
            "http://{$host}/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();

        return Attendee::query()->where('tenant_id', $fixture['tenant']->id)->latest('id')->firstOrFail();
    }
}
