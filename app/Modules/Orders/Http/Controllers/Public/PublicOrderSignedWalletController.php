<?php

namespace App\Modules\Orders\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use App\Modules\WalletPasses\Application\Actions\GenerateWalletPassAction;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class PublicOrderSignedWalletController extends Controller
{
    public function apple(
        string $locale,
        string $public_reference,
        GenerateWalletPassAction $action,
    ): Response {
        $resolved = $this->resolve($public_reference);
        $pass = $action->execute(
            $resolved['order']->tenant_id,
            $resolved['order']->event_id,
            $resolved['attendee']->id,
            $resolved['credential']->id,
            'apple',
            $locale === 'ar' ? 'ar' : 'en',
        );

        if ($pass->pass_url === null || ! is_file($pass->pass_url)) {
            return response('', Response::HTTP_OK, [
                'Content-Type' => 'application/vnd.apple.pkpass',
            ]);
        }

        return response()->file($pass->pass_url, [
            'Content-Type' => 'application/vnd.apple.pkpass',
        ]);
    }

    public function google(
        string $locale,
        string $public_reference,
        GenerateWalletPassAction $action,
    ): RedirectResponse|Response {
        $resolved = $this->resolve($public_reference);
        $pass = $action->execute(
            $resolved['order']->tenant_id,
            $resolved['order']->event_id,
            $resolved['attendee']->id,
            $resolved['credential']->id,
            'google',
            $locale === 'ar' ? 'ar' : 'en',
        );

        if ($pass->pass_url === null || $pass->pass_url === '') {
            abort(404);
        }

        return redirect()->away($pass->pass_url);
    }

    /** @return array{order:Order,attendee:Attendee,credential:Credential} */
    private function resolve(string $publicReference): array
    {
        $order = Order::query()
            ->where('public_reference', $publicReference)
            ->firstOrFail();

        $attendee = Attendee::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('order_id', $order->id)
            ->first();

        if ($attendee === null) {
            $item = OrderItem::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('event_id', $order->event_id)
                ->where('order_id', $order->id)
                ->firstOrFail();

            $attendee = Attendee::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('event_id', $order->event_id)
                ->findOrFail($item->attendee_id);
        }

        $credential = Credential::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('attendee_id', $attendee->id)
            ->where('status', 'active')
            ->latest('issued_at')
            ->firstOrFail();

        return compact('order', 'attendee', 'credential');
    }
}
