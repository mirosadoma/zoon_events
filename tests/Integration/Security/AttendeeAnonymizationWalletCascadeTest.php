<?php

namespace Tests\Integration\Security;

use App\Modules\Attendees\Application\Jobs\AnonymizeEligibleAttendees;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPassAppleDeviceRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase2WalletFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('wallet-passes')]
#[Group('phase-2-privacy')]
final class AttendeeAnonymizationWalletCascadeTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase2WalletFixture;
    use DatabaseTransactions;

    public function test_attendee_anonymization_revokes_credentials_and_scrubs_wallet_passes(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $pass = $this->createWalletPassForCredential($scan, status: WalletPassStatus::Active);
        $pass->forceFill(['apple_authentication_token' => 'apple-auth-secret'])->save();

        WalletPassAppleDeviceRegistration::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $pass->tenant_id,
            'wallet_pass_id' => $pass->id,
            'device_library_identifier' => 'device-under-test',
            'push_token' => 'push-token',
            'registered_at' => now(),
        ]);

        Attendee::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->whereKey($scan['credential']->attendee_id)
            ->update(['registered_at' => now()->subYears(2)]);

        $result = app(AnonymizeEligibleAttendees::class)->handle(
            $scan['fixture']['tenant']->id,
            Carbon::now()->subYear(),
            false,
        );

        self::assertSame(1, $result['anonymized']);

        $credential = Credential::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->findOrFail($scan['credential']->id);
        self::assertSame('revoked', $credential->status);

        $pass->refresh();
        self::assertSame(WalletPassStatus::Revoked, $pass->status);
        self::assertNull($pass->pass_url);
        self::assertNull($pass->apple_authentication_token);

        self::assertSame(
            0,
            WalletPassAppleDeviceRegistration::query()->where('wallet_pass_id', $pass->id)->count(),
        );
    }
}
