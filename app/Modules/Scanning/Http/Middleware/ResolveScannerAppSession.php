<?php

namespace App\Modules\Scanning\Http\Middleware;

use App\Modules\Scanning\Domain\Context\ScannerAppSessionContextStore;
use App\Modules\Scanning\Domain\ValueObjects\ScannerAppSessionContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScannerAppSession;
use App\Modules\Shared\Http\Problems\Phase2Problem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveScannerAppSession
{
    public function __construct(
        private readonly ScannerAppSessionContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'ScannerApp ')) {
            throw Phase2Problem::make('scanner_app_session_invalid');
        }

        $rawToken = substr($header, strlen('ScannerApp '));
        if ($rawToken === '' || $rawToken === false) {
            throw Phase2Problem::make('scanner_app_session_invalid');
        }

        $session = ScannerAppSession::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($session === null) {
            throw Phase2Problem::make('scanner_app_session_invalid');
        }

        $session->forceFill(['last_seen_at' => now()])->save();

        $this->store->bind(new ScannerAppSessionContext(
            tenantId: (string) $session->tenant_id,
            eventId: (string) $session->event_id,
            eventZoneId: (string) $session->event_zone_id,
            sessionId: (string) $session->id,
        ));

        return $next($request);
    }
}
