<?php

namespace App\Modules\Kiosk\Http\Middleware;

use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Domain\ValueObjects\KioskSessionContext;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\KioskSession;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveKioskSession
{
    /** @var list<string> */
    private const UNCONFIRMED_ALLOWED_ROUTES = [
        'api.v1.kiosk.session.confirm',
        'api.v1.kiosk.heartbeat',
    ];

    public function __construct(
        private readonly KioskSessionContextStore $store,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'KioskSession ')) {
            throw Phase3Problem::make('kiosk_session_invalid');
        }

        $rawSecret = substr($header, strlen('KioskSession '));

        if ($rawSecret === '' || $rawSecret === false) {
            throw Phase3Problem::make('kiosk_session_invalid');
        }

        $secretHash = hash('sha256', $rawSecret);

        /** @var KioskSession|null $session */
        $session = KioskSession::query()
            ->where('secret_hash', $secretHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($session === null) {
            throw Phase3Problem::make('kiosk_session_invalid');
        }

        /** @var Kiosk|null $kiosk */
        $kiosk = Kiosk::query()
            ->where('tenant_id', $session->tenant_id)
            ->find($session->kiosk_id);

        if ($kiosk === null) {
            throw Phase3Problem::make('kiosk_session_invalid');
        }

        if ($kiosk->status === 'retired') {
            throw Phase3Problem::make('kiosk_retired');
        }

        $allowsUnconfirmed = $request->routeIs(...self::UNCONFIRMED_ALLOWED_ROUTES);

        if ($kiosk->confirmation_required && $session->confirmed_at === null && ! $allowsUnconfirmed) {
            throw Phase3Problem::make('kiosk_session_unconfirmed');
        }

        $this->store->bind(new KioskSessionContext(
            tenantId: $kiosk->tenant_id,
            eventId: $kiosk->event_id,
            kioskId: $kiosk->id,
            confirmed: $session->confirmed_at !== null,
        ));

        return $next($request);
    }
}
