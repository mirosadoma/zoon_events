<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use DateTimeZone;
use Throwable;

final class VenueLifecyclePolicy
{
    private const TRANSITIONS = [
        'draft' => ['active', 'archived'],
        'active' => ['suspended', 'archived'],
        'suspended' => ['active', 'archived'],
        'archived' => [],
    ];

    public function transition(string $current, string $target, array $venue): string
    {
        if (! in_array($target, self::TRANSITIONS[$current] ?? [], true)) {
            $this->deny();
        }

        if ($target === 'active') {
            $this->assertActivationReady($venue);
        }

        return $target;
    }

    public function assertActivationReady(array $venue): void
    {
        foreach (['name_en', 'name_ar', 'address_en', 'address_ar', 'country_code', 'city_code', 'timezone'] as $field) {
            if (! is_string($venue[$field] ?? null) || trim($venue[$field]) === '') {
                $this->deny();
            }
        }

        if (! preg_match('/^[A-Z]{2}$/', $venue['country_code'])) {
            $this->deny();
        }

        try {
            new DateTimeZone($venue['timezone']);
        } catch (Throwable) {
            $this->deny();
        }

        $email = $venue['business_contact_email'] ?? null;
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->deny();
        }
    }

    private function deny(): never
    {
        throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_PUBLISHABLE);
    }
}
