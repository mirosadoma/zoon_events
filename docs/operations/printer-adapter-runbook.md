# Printer Adapter Runbook

The `PrinterAdapter` contract (`app/Modules/BadgePrinting/Contracts/PrinterAdapter.php`)
decouples badge printing from any specific physical printer vendor. This runbook covers
adapter selection, onboarding a new adapter, secret rotation, and outage handling.

## Configuration

```
PRINTER_ADAPTER=fake
```

Set in `.env` / `.env.testing`. Supported values:

| Value  | Class                   | Purpose                     |
|--------|-------------------------|-----------------------------|
| `fake` | `FakePrinterAdapter`    | CI / local development      |

Additional adapters are registered in `BadgePrintingServiceProvider::register()`:

```php
return match (config('printing.default_printer_adapter', 'fake')) {
    default => $app->make(FakePrinterAdapter::class),
};
```

Add a new `case` for each real adapter you implement.

## Onboarding a New Printer Adapter

1. Create a class implementing `PrinterAdapter` in
   `app/Modules/BadgePrinting/Infrastructure/Adapters/`.
2. Implement all three methods:
   - `print(PrintPayload $payload): PrintResult`
   - `checkHealth(): PrinterHealthResult`
   - Ensure `PrintResult::success` is `false` on non-fatal errors so that check-in
     is never blocked by a print failure.
3. Add a `case` in `BadgePrintingServiceProvider` and a corresponding `PRINTER_ADAPTER`
   enum value.
4. Run `php artisan test --group=badge-printing` to confirm the fake adapter tests still
   pass, then add adapter-specific integration tests.

## Secret Rotation

Store any API keys or hardware tokens the adapter needs in environment variables or a
secrets manager. Never commit secrets to the repository. Reference them from `config/printing.php`.

## Health Monitoring

The `badge_printer` health check calls `BadgePrinterHealthCheck::run()` which verifies
`PRINTER_ADAPTER` is set. The `kiosk_fleet` health check aggregates live kiosk statuses;
a kiosk with `printer_status = 'error'` in its heartbeat contributes to the `degraded`
count.

## Outage Handling

1. **Printer unreachable**: `print()` returns `PrintResult` with `success = false`.
   The `BadgePrintJob` is saved with `status = 'failed'`. Check-in still proceeds.
2. **Reattempt**: Issue a reprint via the desk UI or API. The new job goes through
   the adapter again.
3. **Adapter swap**: Change `PRINTER_ADAPTER` and restart the application. In-flight
   print jobs already recorded in `badge_print_jobs` are unaffected.
4. **Forced test failure**: Use `FakePrinterAdapter::forcePrintFailure(true)` in feature
   tests to assert the check-in completes and the job lands in `failed` status.
