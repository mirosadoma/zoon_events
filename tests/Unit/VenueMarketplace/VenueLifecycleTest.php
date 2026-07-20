<?php

namespace Tests\Unit\VenueMarketplace;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\VenueLifecyclePolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use PHPUnit\Framework\TestCase;

class VenueLifecycleTest extends TestCase
{
    private VenueLifecyclePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new VenueLifecyclePolicy;
    }

    public function test_supported_transitions_follow_the_venue_state_machine(): void
    {
        self::assertSame('active', $this->policy->transition('draft', 'active', $this->completeVenue()));
        self::assertSame('suspended', $this->policy->transition('active', 'suspended', $this->completeVenue()));
        self::assertSame('active', $this->policy->transition('suspended', 'active', $this->completeVenue()));

        foreach (['draft', 'active', 'suspended'] as $status) {
            self::assertSame('archived', $this->policy->transition($status, 'archived', $this->completeVenue()));
        }
    }

    public function test_activation_requires_complete_bilingual_location_and_valid_timezone(): void
    {
        foreach (['name_en', 'name_ar', 'address_en', 'address_ar', 'country_code', 'city_code'] as $field) {
            $venue = $this->completeVenue();
            $venue[$field] = '';
            $this->assertDenied(fn () => $this->policy->transition('draft', 'active', $venue));
        }

        $venue = $this->completeVenue();
        $venue['timezone'] = 'Not/A-Timezone';
        $this->assertDenied(fn () => $this->policy->transition('draft', 'active', $venue));
    }

    public function test_contact_fields_are_validated_without_requiring_private_contact_publication(): void
    {
        $venue = $this->completeVenue();
        $venue['business_contact_email'] = 'not-an-email';
        $this->assertDenied(fn () => $this->policy->transition('draft', 'active', $venue));

        $venue = $this->completeVenue();
        $venue['business_contact_email'] = null;
        $venue['publish_contact'] = false;
        self::assertSame('active', $this->policy->transition('draft', 'active', $venue));
    }

    public function test_archive_is_terminal_and_other_illegal_transitions_fail_closed(): void
    {
        foreach ([['archived', 'active'], ['archived', 'suspended'], ['draft', 'suspended'], ['active', 'draft']] as [$from, $to]) {
            $this->assertDenied(fn () => $this->policy->transition($from, $to, $this->completeVenue()));
        }
    }

    private function assertDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected lifecycle transition to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_VENUE_NOT_PUBLISHABLE, $exception->reasonCode);
        }
    }

    private function completeVenue(): array
    {
        return [
            'name_en' => 'Riyadh Expo',
            'name_ar' => 'معرض الرياض',
            'address_en' => 'King Fahd Road',
            'address_ar' => 'طريق الملك فهد',
            'country_code' => 'SA',
            'city_code' => 'riyadh',
            'timezone' => 'Asia/Riyadh',
            'business_contact_email' => 'owner@example.test',
            'publish_contact' => false,
        ];
    }
}
