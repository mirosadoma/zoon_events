<?php

namespace App\Modules\VenueMarketplace\Domain\Exceptions;

use App\Exceptions\FoundationException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;

final class MarketplaceDomainException extends FoundationException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message = 'The marketplace operation could not be completed.',
        ?int $status = null,
    ) {
        $status ??= Phase6Problem::statusFor($reasonCode);
        parent::__construct(
            $reasonCode,
            $status,
            match ($status) {
                403 => 'Forbidden',
                404 => 'Resource not found',
                409 => 'Conflict',
                422 => 'Validation failed',
                default => 'Service unavailable',
            },
            $message,
        );
    }
}
