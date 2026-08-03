<?php

namespace App\Modules\Scanning\Http\Controllers\ScannerApp;

use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Scanning\Domain\Context\ScannerAppSessionContextStore;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScannerAppSession;
use App\Modules\Shared\Http\Problems\Phase2Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ScannerAppAuthController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly ScannerAppSessionContextStore $sessions,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scanner_code' => ['required', 'string', 'regex:/^\d{8}$/'],
        ]);

        $zone = EventZone::query()
            ->where('scanner_code', $validated['scanner_code'])
            ->first();

        if ($zone === null) {
            throw Phase2Problem::make('scanner_app_zone_not_found');
        }

        $event = Event::query()
            ->where('tenant_id', $zone->tenant_id)
            ->whereKey($zone->event_id)
            ->first();

        if ($event === null) {
            throw Phase2Problem::make('scanner_app_zone_not_found');
        }

        $rawToken = Str::random(48);
        $ttlHours = (int) config('scanning.scanner_app.session_ttl_hours', 24);
        $expiresAt = now()->addHours($ttlHours);

        ScannerAppSession::query()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'event_zone_id' => $zone->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => $expiresAt,
            'last_seen_at' => now(),
        ]);

        return $this->success([
            'token' => $rawToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'authorization' => 'ScannerApp '.$rawToken,
            'event' => [
                'id' => (string) $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'zone' => [
                'id' => (string) $zone->id,
                'name' => ['en' => $zone->zone_name_en, 'ar' => $zone->zone_name_ar],
                'scanner_code' => (string) $zone->scanner_code,
                'capacity' => $zone->capacity !== null ? (int) $zone->capacity : null,
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $context = $this->sessions->current();
        $zone = EventZone::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->whereKey($context->eventZoneId)
            ->firstOrFail();

        $event = Event::query()
            ->where('tenant_id', $context->tenantId)
            ->whereKey($context->eventId)
            ->firstOrFail();

        return $this->success([
            'event' => [
                'id' => (string) $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'zone' => [
                'id' => (string) $zone->id,
                'name' => ['en' => $zone->zone_name_en, 'ar' => $zone->zone_name_ar],
                'scanner_code' => (string) $zone->scanner_code,
                'capacity' => $zone->capacity !== null ? (int) $zone->capacity : null,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        $context = $this->sessions->current();

        ScannerAppSession::query()
            ->whereKey($context->sessionId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return $this->success(['revoked' => true]);
    }
}
