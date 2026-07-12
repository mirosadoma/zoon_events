<?php

namespace App\Modules\Orders\Application\Support;

use App\Modules\Credentials\Application\Presentation\CredentialPresentationToken;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Orders\Domain\CompletedRegistration;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class CompletedRegistrationResolver
{
    public function __construct(private CredentialPresentationToken $presentationTokens) {}

    public function fromExistingOrder(Order $order): CompletedRegistration
    {
        $credential = $this->resolveActiveCredential($order);

        return new CompletedRegistration(
            (string) $order->id,
            (string) $order->public_reference,
            null,
            $credential['id'] ?? null,
            $credential['token'] ?? null,
            $credential['expires_at'] ?? null,
            true,
        );
    }

    /**
     * @return array{id:string,token:string,expires_at:CarbonImmutable}|null
     */
    private function resolveActiveCredential(Order $order): ?array
    {
        $attendeeId = OrderItem::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->value('attendee_id');

        if ($attendeeId === null) {
            return null;
        }

        $credential = Credential::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('attendee_id', $attendeeId)
            ->where('status', 'active')
            ->first();

        if ($credential === null) {
            return null;
        }

        try {
            return [
                'id' => (string) $credential->id,
                'token' => $this->presentationTokens->resolve($credential),
                'expires_at' => CarbonImmutable::parse($credential->expires_at),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
