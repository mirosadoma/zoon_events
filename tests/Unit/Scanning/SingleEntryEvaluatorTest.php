<?php

namespace Tests\Unit\Scanning;

use App\Modules\Scanning\Domain\SingleEntryEvaluator;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class SingleEntryEvaluatorTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_disabled_single_entry_never_marks_duplicate(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        EventCheckInSetting::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
            'single_entry_enabled' => false,
            'single_entry_scope' => 'event',
        ]);
        ScanEvent::factory()->forCredential(
            $scan['fixture']['tenant'],
            $scan['fixture']['event'],
            $scan['credential'],
        )->create(['result' => 'accepted']);

        self::assertFalse(app(SingleEntryEvaluator::class)->isDuplicate(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            $scan['credential']->id,
            $scan['credential']->ticket_type_id,
        ));
    }

    public function test_event_scope_treats_any_prior_accepted_scan_as_duplicate(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        ScanEvent::factory()->forCredential(
            $scan['fixture']['tenant'],
            $scan['fixture']['event'],
            $scan['credential'],
        )->create(['result' => 'accepted']);

        self::assertTrue(app(SingleEntryEvaluator::class)->isDuplicate(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            $scan['credential']->id,
            $scan['credential']->ticket_type_id,
        ));
    }

    public function test_ticket_type_scope_only_duplicates_same_ticket_type(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        EventCheckInSetting::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
            'single_entry_enabled' => true,
            'single_entry_scope' => 'ticket_type',
        ]);
        ScanEvent::factory()->forCredential(
            $scan['fixture']['tenant'],
            $scan['fixture']['event'],
            $scan['credential'],
        )->create(['result' => 'accepted']);

        self::assertTrue(app(SingleEntryEvaluator::class)->isDuplicate(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            $scan['credential']->id,
            $scan['credential']->ticket_type_id,
        ));

        self::assertFalse(app(SingleEntryEvaluator::class)->isDuplicate(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            $scan['credential']->id,
            '01OTHERTICKETTYPE00000000',
        ));
    }
}
