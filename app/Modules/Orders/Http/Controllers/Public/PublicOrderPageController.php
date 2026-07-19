<?php

namespace App\Modules\Orders\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class PublicOrderPageController extends Controller
{
    public function __construct(
        private readonly PersonalDataCipher $cipher,
    ) {}

    public function show(Request $request): Response
    {
        if (! $request->hasValidSignature() && ! app()->isLocal()) {
            abort(404);
        }

        $publicReference = (string) $request->route('public_reference');
        abort_if($publicReference === '', 404);

        $order = Order::query()
            ->where('public_reference', $publicReference)
            ->firstOrFail();

        $event = Event::query()
            ->where('tenant_id', $order->tenant_id)
            ->whereKey($order->event_id)
            ->firstOrFail();

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $attendee = Attendee::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('order_id', $order->id)
            ->first();

        $attendeeName = $this->resolveAttendeeName($attendee);
        $credential = $this->resolveActiveCredential($order, $attendee);
        $qrPayload = $credential !== null ? $order->public_reference : null;

        $walletExpiresAt = now()->addDays(90);
        $applePassUrl = null;
        $googleSaveUrl = null;

        if ($credential !== null) {
            $applePassUrl = URL::temporarySignedRoute(
                'public.order.wallet.apple',
                $walletExpiresAt,
                ['locale' => $locale, 'public_reference' => $order->public_reference],
            );
            $googleSaveUrl = URL::temporarySignedRoute(
                'public.order.wallet.google',
                $walletExpiresAt,
                ['locale' => $locale, 'public_reference' => $order->public_reference],
            );
        }

        return Inertia::render('public/registration/Confirmation', [
            'locale' => $locale,
            'reference' => $order->public_reference,
            'eventName' => $locale === 'ar' ? $event->name_ar : $event->name_en,
            'attendeeName' => $attendeeName,
            'qrPayload' => $qrPayload,
            'applePassUrl' => $applePassUrl,
            'googleSaveUrl' => $googleSaveUrl,
            'credentialStatus' => $credential !== null ? (string) $credential->status : 'inactive',
        ]);
    }

    private function resolveAttendeeName(?Attendee $attendee): string
    {
        if ($attendee === null) {
            return 'Participant';
        }

        try {
            $scope = "{$attendee->tenant_id}:{$attendee->event_id}:attendee";
            $firstName = $this->cipher->decrypt([
                'key_id' => $attendee->encryption_key_id,
                'ciphertext' => $attendee->first_name_ciphertext,
            ], $scope);

            return trim($firstName) !== '' ? trim($firstName) : 'Participant';
        } catch (Throwable) {
            return 'Participant';
        }
    }

    private function resolveActiveCredential(Order $order, ?Attendee $attendee): ?Credential
    {
        if ($attendee === null) {
            return null;
        }

        return Credential::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('attendee_id', $attendee->id)
            ->where('status', 'active')
            ->latest('issued_at')
            ->first();
    }
}
