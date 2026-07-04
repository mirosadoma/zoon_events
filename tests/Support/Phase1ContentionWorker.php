<?php

declare(strict_types=1);

use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentWebhookReceipt;
use App\Modules\Ticketing\Application\Inventory\InventoryService;
use App\Modules\Ticketing\Domain\ValueObjects\Money;
use App\Modules\Ticketing\Domain\ValueObjects\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? '';
$attempts = max(1, (int) ($argv[2] ?? 1));
$successes = 0;

if ($mode === 'inventory') {
    [$tenantId, $eventId, $ticketTypeId] = array_slice($argv, 3, 3);
    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        try {
            $app->make(InventoryService::class)->reserve(
                $tenantId,
                $eventId,
                $ticketTypeId,
                1,
                new PriceQuote(new Money(0, 'SAR'), null, CarbonImmutable::now()),
                CarbonImmutable::now()->addMinutes(15),
            );
            $successes++;
        } catch (Throwable) {
            // Expected after the final unit is reserved.
        }
    }
} elseif ($mode === 'callback') {
    $accountId = $argv[3] ?? '';
    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        PaymentWebhookReceipt::query()->firstOrCreate(
            ['payment_account_id' => $accountId, 'provider_event_id' => 'burst-event'],
            ['payload_digest' => hash('sha256', 'synthetic'), 'status' => 'received', 'received_at' => now()],
        );
        $successes++;
    }
}

echo (string) $successes;
