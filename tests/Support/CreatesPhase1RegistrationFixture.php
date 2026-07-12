<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Domain\Fields\RegistrationSystemFields;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Str;

trait CreatesPhase1RegistrationFixture
{
    /** @return array{actor:User,tenant:Tenant,event:Event,form:RegistrationFormVersion,ticket:TicketType} */
    protected function createRegistrationFixture(int $priceMinor = 0, int $capacity = 10, string $domainReference = 'register.example.test'): array
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->create(['created_by_user_id' => $actor->id]);
        $event = Event::query()->create([
            'tenant_id' => $tenant->id, 'slug' => 'registration-'.Str::lower((string) Str::ulid()), 'name_en' => 'Free Event', 'name_ar' => 'فعالية مجانية',
            'tier' => 'public', 'status' => 'published', 'timezone' => 'Africa/Cairo',
            'start_at' => '2027-01-10 12:00:00', 'end_at' => '2027-01-10 18:00:00',
            'registration_opens_at' => '2026-01-01 00:00:00', 'registration_closes_at' => '2027-01-10 11:00:00',
            'capacity' => $capacity, 'created_by_user_id' => $actor->id, 'published_by_user_id' => $actor->id, 'published_at' => now(),
        ]);
        $event->branding()->create([
            'tenant_id' => $tenant->id, 'brand_reference' => 'synthetic-brand', 'domain_reference' => $domainReference,
            'content_en' => [], 'content_ar' => [], 'sender_name_en' => 'Synthetic', 'sender_name_ar' => 'تجريبي', 'status' => 'active',
        ]);
        $formIdentity = RegistrationForm::query()->create([
            'tenant_id' => $tenant->id, 'event_id' => $event->id, 'name' => 'Public form', 'status' => 'active', 'created_by_user_id' => $actor->id,
        ]);
        $fields = RegistrationSystemFields::definitions();
        $form = RegistrationFormVersion::query()->create([
            'tenant_id' => $tenant->id, 'event_id' => $event->id, 'registration_form_id' => $formIdentity->id,
            'version' => 1, 'status' => 'published', 'fields' => $fields, 'schema_hash' => hash('sha256', json_encode($fields)),
            'privacy_notice_version' => 'privacy-v1', 'terms_version' => 'terms-v1',
            'published_by_user_id' => $actor->id, 'published_at' => now(),
        ]);
        $event->forceFill(['active_form_version_id' => $form->id])->save();
        $ticket = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'event_id' => $event->id, 'code' => 'FREE',
            'name_en' => 'General', 'name_ar' => 'عام', 'attendee_type' => 'general',
            'base_price_minor' => $priceMinor, 'currency' => 'SAR', 'sale_starts_at' => '2026-01-01 00:00:00',
            'sale_ends_at' => '2027-01-10 11:00:00', 'status' => 'active', 'created_by_user_id' => $actor->id,
        ]);
        TicketInventory::query()->create([
            'tenant_id' => $tenant->id, 'event_id' => $event->id, 'ticket_type_id' => $ticket->id, 'capacity' => $capacity,
        ]);

        return compact('actor', 'tenant', 'event', 'form', 'ticket');
    }

    /** @param array{actor:User,tenant:Tenant,event:Event,form:RegistrationFormVersion,ticket:TicketType} $fixture */
    protected function registrationPayload(array $fixture): array
    {
        return [
            'form_version_id' => $fixture['form']->id,
            'ticket_type_id' => $fixture['ticket']->id,
            'buyer' => ['first_name' => 'Synthetic', 'last_name' => 'Buyer', 'email' => 'buyer@example.test'],
            'attendee' => ['first_name' => 'Synthetic', 'last_name' => 'Attendee', 'email' => 'attendee@example.test'],
            'answers' => [
                'full_name' => 'Synthetic Attendee',
                'email' => 'attendee@example.test',
                'phone' => '+966501234567',
            ],
            'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
        ];
    }
}
