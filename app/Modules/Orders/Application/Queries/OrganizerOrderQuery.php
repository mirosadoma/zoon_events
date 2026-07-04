<?php

namespace App\Modules\Orders\Application\Queries;

use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class OrganizerOrderQuery
{
    /** @return array{items:Collection<int,Order>,next_cursor:?string} */
    public function execute(string $tenantId, string $eventId, ?string $status, ?string $cursor, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $after = $cursor === null ? null : $this->decode($cursor, $tenantId, $eventId, $status);
        $query = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($after, fn ($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->limit($limit + 1);
        $items = $query->get();
        $hasMore = $items->count() > $limit;
        $items = $items->take($limit)->values();

        return [
            'items' => $items,
            'next_cursor' => $hasMore ? $this->encode($tenantId, $eventId, $status, $items->last()->id) : null,
        ];
    }

    private function encode(string $tenantId, string $eventId, ?string $status, string $id): string
    {
        $payload = json_encode(compact('tenantId', 'eventId', 'status', 'id'), JSON_THROW_ON_ERROR);

        return base64_encode($payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key')));
    }

    private function decode(string $cursor, string $tenantId, string $eventId, ?string $status): string
    {
        $decoded = base64_decode($cursor, true);
        if (! is_string($decoded) || ! str_contains($decoded, '.')) {
            throw new InvalidArgumentException('Invalid order cursor.');
        }
        [$payload, $signature] = explode('.', $decoded, 2);
        try {
            $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid order cursor.');
        }
        if (! hash_equals(hash_hmac('sha256', $payload, (string) config('app.key')), $signature)
            || $data['tenantId'] !== $tenantId
            || $data['eventId'] !== $eventId
            || $data['status'] !== $status) {
            throw new InvalidArgumentException('Invalid order cursor.');
        }

        return $data['id'];
    }
}
