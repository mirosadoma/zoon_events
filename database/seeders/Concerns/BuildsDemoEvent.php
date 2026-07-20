<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use App\Modules\Events\Domain\EventCodeGenerator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

/**
 * Legacy helper retained for older demo seed flows / classmap compatibility.
 */
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
        $existing = Event::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->first();

        if ($existing instanceof Event && $existing->active_form_version_id !== null) {
            $ticket = TicketType::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $existing->id)
                ->where('code', 'GENERAL')
                ->first();

            if ($ticket instanceof TicketType) {
                return [
                    'event' => $existing,
                    'form_version_id' => (string) $existing->active_form_version_id,
                    'ticket_type_id' => (string) $ticket->id,
                ];
            }
        }

        $eventType = $options['event_type'] ?? 'conference';
        $registrationMode = $options['registration_mode'] ?? 'free_registration';
        $tier = $options['tier'] ?? 'public';
        $withPaidTickets = (bool) ($options['with_paid_tickets'] ?? false);
        $withPriceTiers = (bool) ($options['with_price_tiers'] ?? false);
        $code = ($existing instanceof Event && is_string($existing->code) && $existing->code !== '')
            ? $existing->code
            : app(EventCodeGenerator::class)->generate();

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
                'code' => $code,
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

        $ticket = TicketType::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'code' => 'GENERAL'],
            [
                'name_en' => 'General admission',
                'name_ar' => 'دخول عام',
                'attendee_type' => 'general',
                'base_price_minor' => $withPaidTickets ? 15000 : 0,
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

        return [
            'event' => $event->fresh() ?? $event,
            'form_version_id' => (string) $formVersion->id,
            'ticket_type_id' => (string) $ticket->id,
        ];
    }
}
