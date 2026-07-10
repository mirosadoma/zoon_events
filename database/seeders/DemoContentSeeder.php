<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\AccessControl\Application\Actions\RegisterAcsIntegrationCredentialAction;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\OrganizerRegistrationRequest;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\FeatureFlags\Infrastructure\Persistence\Models\FeatureFlag;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use App\Modules\Orders\Application\Actions\CompleteFreeRegistration;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\AccessEventFactory;
use Database\Factories\BadgePrintJobFactory;
use Database\Factories\BadgeTemplateFactory;
use Database\Factories\EventCheckInSettingFactory;
use Database\Factories\EventCheckInSummaryFactory;
use Database\Factories\KioskFactory;
use Database\Factories\KioskSessionFactory;
use Database\Factories\ScanEventFactory;
use Database\Factories\WalletPassFactory;
use Database\Seeders\Concerns\BuildsDemoEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds rich demo content across events, registrations, check-in, on-site, ACS, identity, and platform pages.
 */
final class DemoContentSeeder extends Seeder
{
    use BuildsDemoEvent;

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        config([
            'notifications.dispatch_sync' => false,
            'notifications.email_adapter' => 'log',
        ]);

        $tenant = Tenant::query()->where('slug', 'fixture-alpha')->first();
        $actor = User::query()->where('email', DemoAccounts::PRIMARY_DEMO_EMAIL)->first()
            ?? User::query()->orderBy('created_at')->first();

        if ($tenant === null || ! $actor instanceof User) {
            return;
        }

        $summit = $this->ensurePublishedEvent(
            $tenant,
            $actor,
            'zonetec-summit-2026',
            'Zonetec Summit 2026',
            'قمة زونتك 2026',
            500,
        );

        $this->ensurePublishedEvent(
            $tenant,
            $actor,
            'gala-night-2026',
            'Gala Night 2026',
            'أمسية جالا 2026',
            200,
        );

        $this->ensureDraftEvent($tenant, $actor, 'workshop-draft', 'Leadership Workshop', 'ورشة القيادة');

        $bravo = Tenant::query()->where('slug', 'fixture-bravo')->first();
        $bravoActor = User::query()->where('email', DemoAccounts::FIXTURE_BRAVO_EMAIL)->first();

        if ($bravo instanceof Tenant && $bravoActor instanceof User) {
            $this->ensureDraftEvent($bravo, $bravoActor, 'bravo-planning-event', 'Bravo Planning Session', 'جلسة تخطيط برافو');
        }

        $credentials = $this->seedRegistrations($tenant, $summit);
        $this->seedCheckIn($tenant, $summit['event'], $credentials);
        $this->seedOnSite($tenant, $summit['event'], $credentials, $actor);
        $this->seedAcs($summit['event'], $credentials);
        $this->seedIdentity($tenant, $summit['event'], $summit['ticket_type_id'], $credentials, $actor);
        $this->seedPlatformExtras($actor);
    }

    /**
     * @param  array{event: Event, form_version_id: string, ticket_type_id: string}  $fixture
     * @return list<Credential>
     */
    private function seedRegistrations(Tenant $tenant, array $fixture): array
    {
        $existingCount = Order::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_id', $fixture['event']->id)
            ->count();

        if ($existingCount >= 10) {
            return Credential::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $fixture['event']->id)
                ->limit(12)
                ->get()
                ->all();
        }

        $action = app(CompleteFreeRegistration::class);
        $credentials = [];
        $expiresAt = CarbonImmutable::parse($fixture['event']->end_at)->addDay();

        for ($i = 1; $i <= 12; $i++) {
            $result = $action->execute(new FreeRegistrationInput(
                tenantId: (string) $tenant->id,
                eventId: (string) $fixture['event']->id,
                formVersionId: $fixture['form_version_id'],
                ticketTypeId: $fixture['ticket_type_id'],
                idempotencyKey: "demo-summit-reg-{$i}",
                answers: ['email' => "attendee{$i}@demo.zonetec.test", 'company' => "Company {$i}"],
                consent: ['terms' => true, 'privacy' => true, 'marketing' => $i % 3 === 0],
                buyer: ['first_name' => 'Demo', 'last_name' => "Buyer {$i}", 'email' => "buyer{$i}@demo.zonetec.test"],
                attendee: ['first_name' => 'Demo', 'last_name' => "Attendee {$i}", 'email' => "attendee{$i}@demo.zonetec.test"],
                locale: 'en',
                credentialExpiresAt: $expiresAt,
            ));

            if ($result->credentialId !== null) {
                $credential = Credential::query()->find($result->credentialId);

                if ($credential instanceof Credential) {
                    $credentials[] = $credential;
                }
            }
        }

        return $credentials;
    }

    /**
     * @param  list<Credential>  $credentials
     */
    private function seedCheckIn(Tenant $tenant, Event $event, array $credentials): void
    {
        EventCheckInSettingFactory::new()->forEvent($tenant, $event)->create();
        EventCheckInSummaryFactory::new()->forEvent($tenant, $event)->create([
            'registered_count' => count($credentials),
            'checked_in_count' => min(5, count($credentials)),
            'rejected_count' => 2,
            'duplicate_count' => 1,
            'last_scan_at' => now()->subMinutes(10),
        ]);

        foreach (array_slice($credentials, 0, 6) as $index => $credential) {
            ScanEventFactory::new()->forCredential($tenant, $event, $credential)->create([
                'result' => $index === 5 ? 'rejected' : 'accepted',
                'reason' => $index === 5 ? 'credential_revoked' : 'entry_granted',
                'scanned_at' => now()->subMinutes(30 - ($index * 3)),
            ]);
        }
    }

    /**
     * @param  list<Credential>  $credentials
     */
    private function seedOnSite(Tenant $tenant, Event $event, array $credentials, User $actor): void
    {
        $kiosks = KioskFactory::new()->count(2)->online()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
        ]);

        foreach ($kiosks as $kiosk) {
            KioskSessionFactory::new()->create([
                'tenant_id' => $tenant->id,
                'kiosk_id' => $kiosk->id,
            ]);
        }

        $template = BadgeTemplateFactory::new()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'name' => 'Summit badge',
        ]);

        foreach (array_slice($credentials, 0, 4) as $index => $credential) {
            $attendee = Attendee::query()->find($credential->attendee_id);

            if (! $attendee instanceof Attendee) {
                continue;
            }

            BadgePrintJobFactory::new()
                ->forCredential($credential, $attendee, $template)
                ->create($index < 2 ? ['status' => 'printed', 'printed_at' => now()->subHour()] : []);

            if ($index < 3) {
                WalletPassFactory::new()->forCredential($credential, $attendee)->create();
            }
        }
    }

    /**
     * @param  list<Credential>  $credentials
     */
    private function seedAcs(Event $event, array $credentials): void
    {
        if ($credentials === []) {
            return;
        }

        $credential = $credentials[0];

        $zone = AcsZone::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Main hall',
            'status' => 'active',
        ]);

        $lane = AcsLane::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'name' => 'Gate A',
            'status' => 'active',
            'is_admission_lane' => true,
        ]);

        AcsAuthorizationRule::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'ticket_type_id' => $credential->ticket_type_id,
            'status' => 'active',
        ]);

        app(RegisterAcsIntegrationCredentialAction::class)->execute(
            (string) $event->tenant_id,
            (string) $event->id,
            'Demo ACS Integration',
            ['authorize', 'event.ingest', 'emergency.ingest'],
        );

        AccessEventFactory::new()->count(5)->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'lane_id' => $lane->id,
            'zone_id' => $zone->id,
            'credential_id' => $credential->id,
        ]);
    }

    /**
     * @param  list<Credential>  $credentials
     */
    private function seedIdentity(Tenant $tenant, Event $event, string $ticketTypeId, array $credentials, User $actor): void
    {
        IdentityVerificationRequirement::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'ticket_type_id' => $ticketTypeId],
            [
                'level' => 'required_before_credential',
                'face_fallback_enabled' => true,
            ],
        );

        $statuses = ['pending', 'manually_approved', 'rejected'];

        foreach (array_slice($credentials, 0, 3) as $index => $credential) {
            IdentityVerification::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'attendee_id' => $credential->attendee_id],
                [
                    'method' => 'manual_review',
                    'status' => $statuses[$index] ?? 'pending_review',
                    'provider' => 'demo',
                    'provider_reference' => "demo-ref-{$index}",
                    'verified_name' => $index === 1 ? 'Demo Attendee Verified' : null,
                    'verified_at' => $index === 1 ? now()->subDay() : null,
                    'rejection_reason' => $index === 2 ? 'Document image was unreadable.' : null,
                    'manual_review_by' => $index > 0 ? $actor->id : null,
                    'manual_review_at' => $index > 0 ? now()->subHours(4) : null,
                ],
            );
        }
    }

    private function seedPlatformExtras(User $actor): void
    {
        OrganizerRegistrationRequest::query()->updateOrCreate(
            ['email' => 'pending.organizer@demo.zonetec.test', 'status' => 'pending'],
            [
                'name' => 'Pending Organizer',
                'password_hash' => Hash::make('PendingDemo2026!'),
                'organization_name' => 'Future Events Co.',
                'phone' => '+20 111 222 3333',
                'message' => 'We run 10+ corporate events per year and would like to onboard.',
            ],
        );

        OrganizerRegistrationRequest::query()->updateOrCreate(
            ['email' => 'rejected.organizer@demo.zonetec.test', 'status' => 'rejected'],
            [
                'name' => 'Rejected Organizer',
                'password_hash' => Hash::make('RejectedDemo2026!'),
                'organization_name' => 'Incomplete Org',
                'rejection_reason' => 'Missing business registration documents.',
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now()->subDays(2),
            ],
        );

        FeatureFlag::query()->updateOrCreate(
            ['key' => 'demo.wallet_passes'],
            [
                'name' => 'Wallet passes demo',
                'description' => 'Enables wallet pass generation in demo environments.',
                'owner' => 'platform',
                'value_type' => 'boolean',
                'default_value' => true,
                'status' => 'active',
                'security_class' => 'standard',
                'created_by_user_id' => $actor->id,
            ],
        );
    }
}
