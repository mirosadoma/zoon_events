<?php

namespace App\Modules\AdminConsole\ViewModels\Events;

use Illuminate\Support\Number;

final class OrganizerOperationsViewModel
{
    /** @param iterable<object> $records @return list<array<string,mixed>> */
    public function orders(iterable $records, string $locale = 'en'): array
    {
        return collect($records)->map(fn ($order): array => [
            'id' => $order->id,
            'reference' => $order->public_reference,
            'status' => $order->status,
            'total' => Number::currency(
                $order->total_minor / 100,
                in: $order->currency,
                locale: $locale === 'ar' ? 'ar_SA' : 'en_SA',
            ),
        ])->values()->all();
    }

    /** @param iterable<object> $records @return list<array<string,mixed>> */
    public function attendees(iterable $records): array
    {
        return collect($records)->map(fn ($attendee): array => [
            'id' => $attendee->id,
            'status' => $attendee->registration_status,
            'locale' => $attendee->preferred_locale,
        ])->values()->all();
    }
}
