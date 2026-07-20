<?php

namespace App\Modules\VenueMarketplace\Application\Services;

use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetReservation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use DateTimeInterface;

final class ReservationReleaseService
{
    public function release(
        RentalRequest $rental,
        string $reasonCode,
        DateTimeInterface $releasedAt,
    ): int {
        $reservations = AssetReservation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $rental->tenant_id)
            ->where('organizer_tenant_id', $rental->organizer_tenant_id)
            ->where('rental_request_id', $rental->id)
            ->whereIn('status', ['reserved', 'active'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->forceFill([
                'status' => 'released',
                'release_reason_code' => $reasonCode,
                'released_at' => $releasedAt,
            ])->save();
        }

        return $reservations->count();
    }
}
