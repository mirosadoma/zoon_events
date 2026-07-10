<?php

namespace Tests\Integration\Ticketing;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Ticketing\Application\Inventory\InventoryService;
use App\Modules\Ticketing\Application\Jobs\ExpireInventoryHoldsJob;
use App\Modules\Ticketing\Domain\ValueObjects\Money;
use App\Modules\Ticketing\Domain\ValueObjects\PriceQuote;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('ticket-inventory')]
final class InventoryConcurrencyTest extends Phase1MySqlTestCase
{
    use DatabaseTransactions;

    public function test_only_one_hold_can_reserve_the_final_inventory_unit(): void
    {
        [$tenant, $event, $ticket] = $this->fixture(1);
        $inventory = app(InventoryService::class);
        $quote = new PriceQuote(new Money(0, 'SAR'), null, CarbonImmutable::now());

        $inventory->reserve($tenant->id, $event->id, $ticket->id, 1, $quote, CarbonImmutable::now()->addMinutes(15));

        try {
            $inventory->reserve($tenant->id, $event->id, $ticket->id, 1, $quote, CarbonImmutable::now()->addMinutes(15));
            self::fail('A second final-unit hold should be rejected.');
        } catch (FoundationException $exception) {
            self::assertSame('ticket_unavailable', $exception->problemCode);
        }

        $row = TicketInventory::query()->where('tenant_id', $tenant->id)->where('ticket_type_id', $ticket->id)->firstOrFail();
        self::assertSame(1, $row->held_quantity);
        self::assertSame(0, $row->sold_quantity);
    }

    public function test_conversion_and_release_are_terminal_and_idempotent(): void
    {
        [$tenant, $event, $ticket] = $this->fixture(2);
        $inventory = app(InventoryService::class);
        $quote = new PriceQuote(new Money(500, 'SAR'), null, CarbonImmutable::now());

        $converted = $inventory->reserve($tenant->id, $event->id, $ticket->id, 1, $quote, CarbonImmutable::now()->addMinutes(15));
        $inventory->convert($tenant->id, $converted->id);
        $inventory->convert($tenant->id, $converted->id);

        $released = $inventory->reserve($tenant->id, $event->id, $ticket->id, 1, $quote, CarbonImmutable::now()->addMinutes(15));
        $inventory->release($tenant->id, $released->id, 'checkout_abandoned');
        $inventory->release($tenant->id, $released->id, 'duplicate_release');

        $row = TicketInventory::query()->where('tenant_id', $tenant->id)->where('ticket_type_id', $ticket->id)->firstOrFail();
        self::assertSame(0, $row->held_quantity);
        self::assertSame(1, $row->sold_quantity);
    }

    public function test_reservation_updates_only_its_composite_inventory_row(): void
    {
        [$firstTenant, $firstEvent, $firstTicket] = $this->fixture(2);
        [$secondTenant, $secondEvent, $secondTicket] = $this->fixture(3);

        app(InventoryService::class)->reserve(
            $firstTenant->id,
            $firstEvent->id,
            $firstTicket->id,
            1,
            new PriceQuote(new Money(0, 'SAR'), null, CarbonImmutable::now()),
            CarbonImmutable::now()->addMinutes(15),
        );

        self::assertSame(1, TicketInventory::query()
            ->where('tenant_id', $firstTenant->id)
            ->where('event_id', $firstEvent->id)
            ->where('ticket_type_id', $firstTicket->id)
            ->firstOrFail()->held_quantity);
        self::assertSame(0, TicketInventory::query()
            ->where('tenant_id', $secondTenant->id)
            ->where('event_id', $secondEvent->id)
            ->where('ticket_type_id', $secondTicket->id)
            ->firstOrFail()->held_quantity);
    }

    public function test_duplicate_expiry_workers_release_each_hold_once(): void
    {
        [$tenant, $event, $ticket] = $this->fixture(1);
        $inventory = app(InventoryService::class);
        $hold = $inventory->reserve(
            $tenant->id,
            $event->id,
            $ticket->id,
            1,
            new PriceQuote(new Money(0, 'SAR'), null, CarbonImmutable::now()),
            CarbonImmutable::now()->subSecond(),
        );

        $job = new ExpireInventoryHoldsJob;
        $job->handle($inventory);
        $job->handle($inventory);

        self::assertSame('expired', $hold->refresh()->status);
        self::assertSame(0, TicketInventory::query()->where('tenant_id', $tenant->id)->where('ticket_type_id', $ticket->id)->firstOrFail()->held_quantity);
    }

    /** @return array{Tenant,Event,TicketType} */
    private function fixture(int $capacity): array
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->create(['created_by_user_id' => $actor->id]);
        $event = Event::query()->create([
            'tenant_id' => $tenant->id, 'slug' => 'inventory-test', 'name_en' => 'Inventory', 'name_ar' => 'المخزون',
            'tier' => 'public', 'status' => 'configured', 'timezone' => 'Africa/Cairo',
            'start_at' => '2027-01-10 12:00:00', 'end_at' => '2027-01-10 18:00:00',
            'registration_opens_at' => '2027-01-01 00:00:00', 'registration_closes_at' => '2027-01-10 11:00:00',
            'capacity' => $capacity, 'created_by_user_id' => $actor->id,
        ]);
        $ticket = TicketType::query()->create([
            'tenant_id' => $tenant->id, 'event_id' => $event->id, 'code' => 'GENERAL',
            'name_en' => 'General', 'name_ar' => 'عام', 'attendee_type' => 'general',
            'base_price_minor' => 0, 'currency' => 'SAR', 'sale_starts_at' => '2027-01-01 00:00:00',
            'sale_ends_at' => '2027-01-10 11:00:00', 'status' => 'active', 'created_by_user_id' => $actor->id,
        ]);
        TicketInventory::query()->create([
            'tenant_id' => $tenant->id, 'event_id' => $event->id, 'ticket_type_id' => $ticket->id, 'capacity' => $capacity,
        ]);

        return [$tenant, $event, $ticket];
    }
}
