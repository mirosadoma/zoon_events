<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use App\Modules\Events\Application\Registration\EnsureDefaultRegistrationSlot;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

trait BuildsDemoEvent
{
    /**
     * @param  array{
     *   tier?:string,
     *   event_type?:string,
     *   registration_mode?:string,
     *   with_paid_tickets?:bool,
     *   with_price_tiers?:bool
     * }  $options
     * @return array{event: Event, form_version_id: string, ticket_type_id: string}
     */
    protected function ensurePublishedEvent(
        Tenant $tenant,
        User $actor,
        string $slug,
        string $nameEn,
        string $nameAr,
        int $capacity = 500,
        array $options = [],
    ): array {
        $tier = $options['tier'] ?? 'public';
        $eventType = $options['event_type'] ?? 'conference';
        $registrationMode = $options['registration_mode'] ?? 'free_registration';
        $withPaidTickets = (bool) ($options['with_paid_tickets'] ?? false);
        $withPriceTiers = (bool) ($options['with_price_tiers'] ?? false);

        if ($withPaidTickets) {
            $tier = 'public';
            $registrationMode = 'paid_ticketing';
        }

        $existing = Event::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->first();

        if ($existing instanceof Event && $existing->active_form_version_id !== null) {
            $ticket = $this->resolveRegistrationTicket($tenant, $existing, $withPaidTickets);

            if ($ticket instanceof TicketType) {
                return [
                    'event' => $existing,
                    'form_version_id' => (string) $existing->active_form_version_id,
                    'ticket_type_id' => (string) $ticket->id,
                ];
            }
        }

        $event = Event::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => $slug],
            [
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'description_en' => 'Demo event for full platform walkthrough.',
                'description_ar' => 'فعالية تجريبية لاستعراض المنصة بالكامل.',
                'tier' => $tier,
                'event_type' => $eventType,
                'registration_mode' => $registrationMode,
                'status' => 'published',
                'timezone' => 'Africa/Cairo',
                'start_at' => now()->addMonths(2)->setTime(10, 0),
                'end_at' => now()->addMonths(2)->setTime(18, 0),
                'registration_opens_at' => now()->subDay(),
                'registration_closes_at' => now()->addMonths(2)->subHour(),
                'location_name_en' => 'Cairo International Convention Center',
                'location_name_ar' => 'مركز القاهرة الدولي للمؤتمرات',
                'capacity' => $capacity,
                'created_by_user_id' => $actor->id,
                'published_by_user_id' => $actor->id,
                'published_at' => now(),
            ],
        );

        $event->branding()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id],
            [
                'brand_reference' => "{$slug}-brand",
                'domain_reference' => 'register.zoon.test',
                'content_en' => ['hero' => $nameEn],
                'content_ar' => ['hero' => $nameAr],
                'sender_name_en' => 'Zonetec Events',
                'sender_name_ar' => 'فعاليات زونتك',
                'status' => 'active',
            ],
        );

        $formIdentity = RegistrationForm::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'name' => 'Public registration'],
            ['status' => 'active', 'created_by_user_id' => $actor->id],
        );

        $fields = [
            ['key' => 'email', 'type' => 'email', 'label_en' => 'Email', 'label_ar' => 'البريد', 'required' => true],
            ['key' => 'company', 'type' => 'text', 'label_en' => 'Company', 'label_ar' => 'الشركة', 'required' => false],
        ];

        $formVersion = RegistrationFormVersion::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'registration_form_id' => $formIdentity->id, 'version' => 1],
            [
                'status' => 'published',
                'fields' => $fields,
                'schema_hash' => hash('sha256', json_encode($fields)),
                'privacy_notice_version' => 'privacy-v1',
                'terms_version' => 'terms-v1',
                'published_by_user_id' => $actor->id,
                'published_at' => now(),
            ],
        );

        $event->forceFill(['active_form_version_id' => $formVersion->id])->save();

        $ticket = $withPaidTickets
            ? $this->ensurePaidTicketCatalog($tenant, $event, $actor, $capacity, $withPriceTiers)
            : app(EnsureDefaultRegistrationSlot::class)->execute($event->fresh(), $actor->id);

        abort_unless($ticket instanceof TicketType, 500, 'Demo event registration ticket could not be provisioned.');

        return [
            'event' => $event->fresh(),
            'form_version_id' => (string) $formVersion->id,
            'ticket_type_id' => (string) $ticket->id,
        ];
    }

    protected function ensureDraftEvent(
        Tenant $tenant,
        User $actor,
        string $slug,
        string $nameEn,
        string $nameAr,
        string $tier = 'corporate',
        string $eventType = 'workshop',
    ): Event {
        return Event::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => $slug],
            [
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'tier' => $tier,
                'event_type' => $eventType,
                'registration_mode' => 'free_registration',
                'status' => 'draft',
                'timezone' => 'Africa/Cairo',
                'start_at' => now()->addMonths(3),
                'end_at' => now()->addMonths(3)->addHours(6),
                'registration_opens_at' => now()->addMonth(),
                'registration_closes_at' => now()->addMonths(3)->subHour(),
                'capacity' => 120,
                'created_by_user_id' => $actor->id,
            ],
        );
    }

    private function resolveRegistrationTicket(Tenant $tenant, Event $event, bool $withPaidTickets): ?TicketType
    {
        if ($withPaidTickets) {
            return TicketType::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->where('code', 'GENERAL')
                ->first();
        }

        return app(EnsureDefaultRegistrationSlot::class)->execute($event, $event->created_by_user_id);
    }

    private function ensurePaidTicketCatalog(
        Tenant $tenant,
        Event $event,
        User $actor,
        int $capacity,
        bool $withPriceTiers,
    ): TicketType {
        $ticket = TicketType::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'code' => 'GENERAL'],
            [
                'name_en' => 'General admission',
                'name_ar' => 'دخول عام',
                'attendee_type' => 'general',
                'base_price_minor' => 15000,
                'currency' => 'EGP',
                'sale_starts_at' => now()->subDay(),
                'sale_ends_at' => $event->registration_closes_at,
                'status' => 'active',
                'created_by_user_id' => $actor->id,
            ],
        );

        TicketInventory::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'ticket_type_id' => $ticket->id],
            ['capacity' => $capacity, 'held_quantity' => 0, 'sold_quantity' => 0],
        );

        if ($withPriceTiers) {
            PriceTier::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'ticket_type_id' => $ticket->id, 'name' => 'Early bird'],
                [
                    'price_minor' => 12000,
                    'currency' => 'EGP',
                    'starts_at' => now()->subDay(),
                    'ends_at' => $event->registration_closes_at,
                    'priority' => 1,
                    'status' => 'active',
                ],
            );
        }

        return $ticket;
    }
}
