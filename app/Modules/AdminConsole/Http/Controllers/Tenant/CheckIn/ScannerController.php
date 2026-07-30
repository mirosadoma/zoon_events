<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ScannerController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
    ) {}

    public function show(Request $request, string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'checkin.scan.submit',
        );

        $zones = EventZone::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('zone_name_en')
            ->get(['id', 'zone_name_en', 'zone_name_ar', 'scanner_code', 'capacity']);

        $code = trim((string) $request->query('code', ''));
        $zoneId = trim((string) $request->query('zone_id', ''));

        $selected = null;
        if ($code !== '' && preg_match('/^\d{8}$/', $code) === 1) {
            $selected = $zones->first(fn (EventZone $zone): bool => (string) $zone->scanner_code === $code);
        } elseif ($zoneId !== '') {
            $selected = $zones->first(fn (EventZone $zone): bool => (string) $zone->id === $zoneId);
        }

        return Inertia::render('tenant/checkin/Scanner', [
            'event' => [
                'id' => $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'tenantId' => $context->tenant->id,
            'zones' => $zones->map(fn (EventZone $zone): array => [
                'id' => (string) $zone->id,
                'name' => ['en' => (string) $zone->zone_name_en, 'ar' => (string) $zone->zone_name_ar],
                'scanner_code' => $zone->scanner_code !== null ? (string) $zone->scanner_code : null,
                'capacity' => $zone->capacity !== null ? (int) $zone->capacity : null,
            ])->values()->all(),
            'selectedZone' => $selected instanceof EventZone ? [
                'id' => (string) $selected->id,
                'name' => ['en' => (string) $selected->zone_name_en, 'ar' => (string) $selected->zone_name_ar],
                'scanner_code' => $selected->scanner_code !== null ? (string) $selected->scanner_code : null,
                'capacity' => $selected->capacity !== null ? (int) $selected->capacity : null,
            ] : null,
            'codeError' => ($code !== '' || $zoneId !== '') && $selected === null
                ? 'invalid_scanner_code'
                : null,
        ]);
    }
}
