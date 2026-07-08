<?php

namespace App\Modules\AdminConsole\ViewModels\Orders;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use Illuminate\Support\Collection;

final readonly class OrderDetailViewModel
{
    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<string, string>  $notificationStatuses
     * @return array{event: array<string, mixed>, orders: list<array<string, mixed>>}
     */
    public function index(Event $event, Collection $orders, array $notificationStatuses = []): array
    {
        return [
            'event' => $this->eventRow($event),
            'orders' => $orders->map(fn (Order $order): array => $this->orderRow($order, $notificationStatuses[$order->id] ?? null))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @param  list<array<string, mixed>>  $attendees
     * @return array{event: array<string, mixed>, order: array<string, mixed>}
     */
    public function detail(Event $event, Order $order, Collection $items, array $attendees, ?string $notificationStatus = null): array
    {
        return [
            'event' => $this->eventRow($event),
            'order' => [
                ...$this->orderRow($order, $notificationStatus),
                'items' => $items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'quantity' => $item->quantity,
                    'total_minor' => $item->total_minor,
                    'currency' => $item->currency,
                    'ticket_name' => $item->ticket_name_snapshot,
                    'attendee_id' => $item->attendee_id,
                ])->values()->all(),
                'attendees' => $attendees,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'created_at' => $order->created_at?->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'status' => $event->status,
        ];
    }

    /** @return array<string, mixed> */
    private function orderRow(Order $order, ?string $notificationStatus): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->public_reference,
            'status' => $order->status,
            'total' => $this->formatMoney($order->total_minor, $order->currency),
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'notification_status' => $notificationStatus,
            'locale' => $order->locale,
        ];
    }

    private function formatMoney(int $minor, string $currency): string
    {
        $major = number_format($minor / 100, 2, '.', ',');

        return "{$major} {$currency}";
    }
}
