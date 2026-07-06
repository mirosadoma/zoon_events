<?php

namespace Database\Factories;

use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ScanEvent> */
final class ScanEventFactory extends Factory
{
    protected $model = ScanEvent::class;

    public function definition(): array
    {
        return [
            'scanner_type' => 'staff_phone',
            'scanner_id' => (string) Str::ulid(),
            'direction' => 'in',
            'result' => 'accepted',
            'reason' => 'entry_granted',
            'offline_mode' => false,
            'scanned_at' => now(),
        ];
    }

    public function forCredential(Tenant $tenant, Event $event, Credential $credential): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'attendee_id' => $credential->attendee_id,
            'credential_id' => $credential->id,
        ]);
    }
}
