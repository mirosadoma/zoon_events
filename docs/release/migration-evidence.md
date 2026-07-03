# Phase 0 Migration Evidence

Verified on 2026-07-03 against the configured native MySQL test database.

- `php artisan migrate:fresh --seed --env=testing --force`: passed.
- All migrations `000000` through `000006` ran successfully.
- `FoundationIsolationSeeder` ran twice without duplicates.
- `SystemRoleSeeder` ran twice without duplicates.
- `php artisan migrate:status --env=testing`: every migration reported `Ran`.

The synthetic isolation seeder refuses production. Normal seeding creates only the permission catalog; administrator bootstrap requires the explicit command.
