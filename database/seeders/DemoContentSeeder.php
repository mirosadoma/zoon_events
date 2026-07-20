<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Application\Registration\EnsureDefaultRegistrationSlot;
use App\Modules\Events\Application\Support\InviteCodeGenerator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Orders\Application\Actions\CompleteFreeRegistration;
use App\Modules\Orders\Domain\FreeRegistrationInput;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class DemoContentSeeder extends Seeder
{
    public function run(): void
    {

        $tenant = Tenant::query()->where('slug', DemoAccounts::TENANT_SLUG)->first();
        if (! $tenant) {
            return;
        }

        $event = Event::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'zonetec-summit-2026')
            ->first();

        if (! $event) {
            return;
        }

        $organizer = User::query()->where('email', DemoAccounts::TENANT_EMAIL)->first();
        $primaryVenue = $event->venues()->orderBy('sort_order')->first();

        $event->forceFill([
            'created_by_user_id' => $organizer?->id ?? $event->created_by_user_id,
            'status' => in_array((string) $event->status, ['draft', 'published'], true)
                ? 'registration_open'
                : $event->status,
        ])->save();

        $this->seedAgenda($tenant, $event, $primaryVenue);
        $ticket = app(EnsureDefaultRegistrationSlot::class)->execute($event, $organizer?->id);
        if ($ticket === null) {
            return;
        }

        $formVersion = RegistrationFormVersion::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_id', $event->id)
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first();

        if ($formVersion === null) {
            return;
        }

        $categories = EventCategory::query()
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->get()
            ->values();

        $attendeeIds = $this->seedRegistrations($tenant, $event, $formVersion->id, (string) $ticket->id, $categories);
        $this->seedVisitorAccount($attendeeIds[0] ?? null);
        $this->seedPrivateInvites($tenant);
        $kiosk = $this->seedKiosk($tenant, $event);
        $this->seedAcs($tenant, $event, (string) $ticket->id);
        $this->seedCheckInAndBadges($tenant, $event, $attendeeIds, $kiosk, $organizer);
    }

    private function seedAgenda(Tenant $tenant, Event $event, mixed $primaryVenue): void
    {
        $anchor = $primaryVenue?->start_at ?? $event->start_at;
        if ($anchor === null) {
            return;
        }

        $day = CarbonImmutable::parse($anchor)->timezone($event->timezone)->startOfDay();
        $items = [
            ['title_en' => 'Registration & Networking', 'title_ar' => 'التسجيل والتواصل', 'start' => 8, 'end' => 9],
            ['title_en' => 'Opening Keynote', 'title_ar' => 'الكلمة الافتتاحية', 'start' => 9, 'end' => 10],
            ['title_en' => 'AI & Cloud Workshops', 'title_ar' => 'ورش الذكاء الاصطناعي والسحابة', 'start' => 10, 'end' => 12],
            ['title_en' => 'Partner Showcase', 'title_ar' => 'عرض الشركاء', 'start' => 13, 'end' => 15],
            ['title_en' => 'Closing Panel', 'title_ar' => 'جلسة ختامية', 'start' => 15, 'end' => 17],
        ];

        foreach ($items as $index => $item) {
            EventAgendaItem::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'event_id' => $event->id,
                    'title_en' => $item['title_en'],
                ],
                [
                    'title_ar' => $item['title_ar'],
                    'start_at' => $day->copy()->addHours($item['start']),
                    'end_at' => $day->copy()->addHours($item['end']),
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventCategory>  $categories
     * @return list<string>
     */
    private function seedRegistrations(
        Tenant $tenant,
        Event $event,
        string $formVersionId,
        string $ticketTypeId,
        $categories,
    ): array {
        Mail::fake();

        $complete = app(CompleteFreeRegistration::class);
        $attendeeIds = [];
        $primaryVenue = $event->venues()->orderBy('sort_order')->first();
        $venueId = $primaryVenue?->id;
        $expiresAt = CarbonImmutable::parse($primaryVenue?->end_at ?? $event->end_at ?? now()->addMonths(2))->addDay();

        $people = [
            ['first' => 'Sara', 'last' => 'AlHarbi', 'email' => 'attendee1@demo.zonetec.test', 'category' => 'normal'],
            ['first' => 'Omar', 'last' => 'Hassan', 'email' => 'attendee2@demo.zonetec.test', 'category' => 'normal'],
            ['first' => 'Layla', 'last' => 'Mansour', 'email' => 'attendee3@demo.zonetec.test', 'category' => 'vip'],
            ['first' => 'Yousef', 'last' => 'Nasser', 'email' => 'attendee4@demo.zonetec.test', 'category' => 'vip'],
            ['first' => 'Noura', 'last' => 'Fahad', 'email' => 'attendee5@demo.zonetec.test', 'category' => 'vvip'],
            ['first' => 'Karim', 'last' => 'Saleh', 'email' => 'attendee6@demo.zonetec.test', 'category' => 'speaker'],
            ['first' => 'Huda', 'last' => 'Ibrahim', 'email' => 'attendee7@demo.zonetec.test', 'category' => 'normal'],
            ['first' => 'Faisal', 'last' => 'Ahmad', 'email' => 'attendee8@demo.zonetec.test', 'category' => 'normal'],
            ['first' => 'Maya', 'last' => 'Rashid', 'email' => 'attendee9@demo.zonetec.test', 'category' => 'vip'],
            ['first' => 'Tariq', 'last' => 'Zaid', 'email' => 'attendee10@demo.zonetec.test', 'category' => 'normal'],
            ['first' => 'Rania', 'last' => 'Omar', 'email' => 'attendee11@demo.zonetec.test', 'category' => 'speaker'],
            ['first' => 'Samir', 'last' => 'Khalil', 'email' => 'attendee12@demo.zonetec.test', 'category' => 'normal'],
        ];

        foreach ($people as $index => $person) {
            $existing = Attendee::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->where('email_index', app(\App\Modules\Shared\Application\DataProtection\BlindIndex::class)->email($person['email']))
                ->first();

            if ($existing) {
                $attendeeIds[] = (string) $existing->id;

                continue;
            }

            $category = $categories->firstWhere('slug', $person['category']) ?? $categories->first();
            $identity = [
                'first_name' => $person['first'],
                'last_name' => $person['last'],
                'email' => $person['email'],
                'phone' => '+9665'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
            ];

            $completed = $complete->execute(new FreeRegistrationInput(
                tenantId: (string) $tenant->id,
                eventId: (string) $event->id,
                formVersionId: $formVersionId,
                ticketTypeId: $ticketTypeId,
                idempotencyKey: 'demo-seed-'.Str::slug($person['email']),
                answers: [
                    'full_name' => $person['first'].' '.$person['last'],
                    'email' => $person['email'],
                    'phone' => $identity['phone'],
                    'company' => 'Zonetec Demo',
                    'job_title' => 'Attendee',
                    'country' => 'sa',
                ],
                consent: ['terms' => true, 'privacy' => true, 'marketing' => false],
                buyer: $identity,
                attendee: $identity,
                locale: 'en',
                credentialExpiresAt: $expiresAt,
                bypassIdentityGateForCredential: true,
                eventCategoryId: $category !== null ? (string) $category->id : null,
                eventVenueId: $venueId !== null ? (string) $venueId : null,
            ));

            $attendeeId = Attendee::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->where('order_id', $completed->orderId)
                ->value('id');

            if ($attendeeId !== null) {
                $attendeeIds[] = (string) $attendeeId;
            }
        }

        return $attendeeIds;
    }

    private function seedVisitorAccount(?string $attendeeId): void
    {
        if ($attendeeId === null) {
            return;
        }

        $visitor = User::query()->updateOrCreate(
            ['email' => DemoAccounts::VISITOR_EMAIL],
            [
                'name' => 'Demo Visitor',
                'password' => Hash::make(DemoAccounts::VISITOR_PASSWORD),
                'type' => 'visitor',
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => 'en',
            ],
        );

        Attendee::query()->whereKey($attendeeId)->update(['user_id' => $visitor->id]);
    }

    private function seedPrivateInvites(Tenant $tenant): void
    {
        $private = Event::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'leadership-workshop')
            ->first();

        if (! $private) {
            return;
        }

        $generator = app(InviteCodeGenerator::class);
        $emails = [
            'invite1@demo.zonetec.test',
            'invite2@demo.zonetec.test',
            'invite3@demo.zonetec.test',
        ];

        foreach ($emails as $email) {
            $existing = EventRegistrationInvite::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $private->id)
                ->where('email', $email)
                ->first();

            if ($existing) {
                continue;
            }

            EventRegistrationInvite::query()->create([
                'tenant_id' => $tenant->id,
                'event_id' => $private->id,
                'email' => $email,
                'code' => $generator->generateUnique($private->id),
                'is_active' => true,
                'invite_status' => 'not_registered',
                'sent_at' => now(),
            ]);
        }
    }

    private function seedKiosk(Tenant $tenant, Event $event): Kiosk
    {
        return Kiosk::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'device_code' => 'DEMO-KIOSK-01',
            ],
            [
                'device_name' => 'Main Lobby Kiosk',
                'location_label' => 'Main Entrance',
                'status' => 'online',
                'printer_status' => 'ready',
                'last_heartbeat_at' => now(),
                'confirmation_required' => false,
            ],
        );
    }

    private function seedAcs(Tenant $tenant, Event $event, string $ticketTypeId): void
    {
        $primaryVenue = $event->venues()->orderBy('sort_order')->first();
        $validFrom = $primaryVenue?->start_at ?? $event->start_at;
        $validUntil = $primaryVenue?->end_at ?? $event->end_at;

        $zone = AcsZone::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'name' => 'Main Hall',
            ],
            [
                'external_acs_zone_id' => 'zone-main',
                'anti_passback_enabled' => true,
                'unavailability_mode' => 'fail_closed',
                'emergency_egress_mode' => 'fail_open',
                'status' => 'active',
            ],
        );

        $lane = AcsLane::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'zone_id' => $zone->id,
                'name' => 'Entrance Lane A',
            ],
            [
                'external_acs_lane_id' => 'lane-a',
                'gate_type' => 'turnstile',
                'access_direction' => 'entry',
                'is_admission_lane' => true,
                'status' => 'active',
                'health_status' => 'online',
                'last_seen_at' => now(),
            ],
        );

        AcsAuthorizationRule::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'zone_id' => $zone->id,
                'lane_id' => $lane->id,
                'ticket_type_id' => $ticketTypeId,
            ],
            [
                'attendee_type' => 'general',
                'access_direction' => 'entry',
                'anti_passback_exempt' => false,
                'valid_from' => $validFrom !== null ? CarbonImmutable::parse($validFrom)->subDay() : null,
                'valid_until' => $validUntil !== null ? CarbonImmutable::parse($validUntil)->addDay() : null,
                'status' => 'active',
            ],
        );
    }

    /**
     * @param  list<string>  $attendeeIds
     */
    private function seedCheckInAndBadges(
        Tenant $tenant,
        Event $event,
        array $attendeeIds,
        Kiosk $kiosk,
        ?User $organizer,
    ): void {
        $template = BadgeTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_id', $event->id)
            ->first();

        $checkedIn = 0;
        $toCheckIn = array_slice($attendeeIds, 0, 5);

        foreach ($toCheckIn as $attendeeId) {
            $credential = Credential::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->where('attendee_id', $attendeeId)
                ->first();

            if ($credential === null) {
                continue;
            }

            $scan = ScanEvent::query()->create([
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'attendee_id' => $attendeeId,
                'credential_id' => $credential->id,
                'scanner_type' => 'manual_desk',
                'scanner_id' => 'demo-desk-1',
                'gate_id' => 'main-gate',
                'zone_id' => 'main-hall',
                'direction' => 'in',
                'result' => 'accepted',
                'reason' => null,
                'offline_mode' => false,
                'scanned_at' => now()->subMinutes(30 - $checkedIn),
                'synced_at' => now(),
            ]);

            Attendee::query()->whereKey($attendeeId)->update([
                'checkin_status' => 'checked_in',
                'first_checked_in_at' => $scan->scanned_at,
                'last_scan_event_id' => $scan->id,
                'invite_status' => 'attended',
            ]);

            if ($template !== null) {
                BadgePrintJob::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'event_id' => $event->id,
                        'attendee_id' => $attendeeId,
                        'is_reprint' => false,
                    ],
                    [
                        'credential_id' => $credential->id,
                        'badge_template_id' => $template->id,
                        'kiosk_id' => $kiosk->id,
                        'printed_by_user_id' => $organizer?->id,
                        'status' => 'printed',
                        'printed_at' => now()->subMinutes(25 - $checkedIn),
                    ],
                );
            }

            $checkedIn++;
        }

        $summary = DB::table('event_check_in_summaries')
            ->where('tenant_id', $tenant->id)
            ->where('event_id', $event->id)
            ->first();

        $payload = [
            'registered_count' => count($attendeeIds),
            'checked_in_count' => $checkedIn,
            'rejected_count' => 0,
            'duplicate_count' => 0,
            'last_scan_at' => now(),
            'updated_at' => now(),
        ];

        if ($summary) {
            DB::table('event_check_in_summaries')->where('id', $summary->id)->update($payload);
        } else {
            DB::table('event_check_in_summaries')->insert([
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                ...$payload,
            ]);
        }
    }
}
