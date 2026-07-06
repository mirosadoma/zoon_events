<?php

namespace Database\Factories;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventCheckInSetting> */
final class EventCheckInSettingFactory extends Factory
{
    protected $model = EventCheckInSetting::class;

    public function definition(): array
    {
        return [
            'single_entry_enabled' => true,
            'single_entry_scope' => 'event',
        ];
    }

    public function forEvent(Tenant $tenant, Event $event): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
        ]);
    }
}
