<?php

namespace Database\Factories;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventCheckInSummary> */
final class EventCheckInSummaryFactory extends Factory
{
    protected $model = EventCheckInSummary::class;

    public function definition(): array
    {
        return [
            'registered_count' => 0,
            'checked_in_count' => 0,
            'rejected_count' => 0,
            'duplicate_count' => 0,
            'last_scan_at' => null,
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
