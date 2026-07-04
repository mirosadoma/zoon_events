<?php

namespace App\Modules\AdminConsole\ViewModels\Events;

use App\Modules\Events\Application\Publication\PublicationReadiness;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Facades\DB;

final readonly class EventSetupViewModel
{
    public function __construct(private PublicationReadiness $readiness) {}

    /** @return array{event:array<string,mixed>,can:array{manage:bool,publish:bool}} */
    public function make(Event $event, bool $canManage, bool $canPublish): array
    {
        return [
            'event' => [
                'id' => $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'status' => $event->status,
                'tier' => $event->tier,
                'readiness' => $this->readiness->missing([
                    ...$event->only(['name_en', 'name_ar', 'timezone', 'start_at', 'end_at', 'registration_opens_at', 'registration_closes_at', 'active_form_version_id']),
                    'active_ticket_types' => DB::table('ticket_types')
                        ->where('tenant_id', $event->tenant_id)
                        ->where('event_id', $event->id)
                        ->where('status', 'active')
                        ->count(),
                    'branding_active' => $event->branding()->where('status', 'active')->exists(),
                ]),
            ],
            'can' => ['manage' => $canManage, 'publish' => $canPublish],
        ];
    }
}
