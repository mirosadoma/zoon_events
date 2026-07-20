# Venue Marketplace operations

Owner: Platform Operations  
Last reviewed: 2026-07-15

---

## 1. Migration order (Phase 6)

Run `php artisan migrate --pretend` first. Back up the database before applying. Migrations must
run in sequence — each depends on prior tables or columns.

| # | Migration | Effect |
|---|-----------|--------|
| 1 | `2026_07_14_000001_add_organization_type_to_tenants_table` | Adds `organization_type` column (`organizer`, `venue_owner`, `hybrid`) to `tenants` |
| 2 | `2026_07_14_000002_create_venues_table` | `venues` — venue profiles owned by venue-owner tenants |
| 3 | `2026_07_14_000003_create_venue_assets_and_bindings_tables` | `venue_assets`, `venue_asset_bindings` — hardware/resource inventory |
| 4 | `2026_07_14_000004_create_asset_availability_windows_table` | `asset_availability_windows` — time-slot availability for assets |
| 5 | `2026_07_14_000005_create_marketplace_catalog_publications_tables` | `marketplace_catalog_publications` — published catalog entries |
| 6 | `2026_07_14_000006_create_rental_requests_table` | `rental_requests` — organizer-to-owner rental lifecycle |
| 7 | `2026_07_14_000007_create_rental_assets_table` | `rental_assets` — assets attached to a rental |
| 8 | `2026_07_14_000008_create_asset_reservations_table` | `asset_reservations` — calendar-level reservation locks |
| 9 | `2026_07_14_000009_create_control_delegations_and_resources_tables` | `control_delegations`, `delegated_resources` — cross-tenant delegation |
| 10 | `2026_07_14_000010_add_marketplace_delegation_refs_to_acs_resources` | Adds delegation FK columns to ACS zone/gate tables |
| 11 | `2026_07_14_000011_add_marketplace_delegation_refs_to_kiosks` | Adds delegation FK columns to kiosk tables |
| 12 | `2026_07_14_000012_create_delegated_printer_allocations_table` | `delegated_printer_allocations` — printer delegation tracking |
| 13 | `2026_07_14_000013_create_delegated_scanner_allocations_table` | `delegated_scanner_allocations` — scanner delegation tracking |
| 14 | `2026_07_14_000014_create_settlement_statements_tables` | `settlement_statements`, `settlement_statement_lines` — financial settlement |
| 15 | `2026_07_14_000015_create_marketplace_disputes_tables` | `marketplace_disputes`, `marketplace_dispute_notes` — dispute lifecycle |

```bash
php artisan migrate --pretend
php artisan migrate
```

## 2. Seed commands

Marketplace permissions must be seeded before creating marketplace roles or assigning demo users.

```bash
php artisan db:seed --class=PermissionSeeder
```

Marketplace permissions registered by the seeder:

| Key | Scope | Description |
|-----|-------|-------------|
| `venue.manage` | tenant | Manage venue profiles, assets, availability, pricing, publication |
| `marketplace.manage` | tenant | Browse catalog, request quotes, submit rental requests |
| `rentals.approve` | tenant | Approve, reject, revoke rental requests |
| `reports.view` | tenant | View and export marketplace statements |
| `platform.marketplace.view` | platform | Cross-participant marketplace oversight |
| `platform.marketplace.disputes.manage` | platform | Review and resolve statement disputes |

## 3. Scheduler and queue commands

### 3.1 Scheduled commands

Registered in `routes/console.php`:

| Command | Schedule | Purpose |
|---------|----------|---------|
| `marketplace:activate-rentals` | Every minute | Activates approved rentals whose `requested_start_at` has arrived |
| `marketplace:expire-rentals` | Every minute | Expires active/approved rentals whose `requested_end_at` has passed |
| `marketplace:finalize-statements` | Every 5 minutes | Dispatches settlement statement generation for terminal rentals without a statement |

All three use `->withoutOverlapping()` to prevent concurrent execution.

### 3.2 Manual invocation

```bash
# Activate approved rentals whose start time has arrived
php artisan marketplace:activate-rentals --chunk=100

# Expire active/approved rentals whose end time has passed
php artisan marketplace:expire-rentals --chunk=100

# Generate settlement statements for completed/cancelled/revoked rentals
php artisan marketplace:finalize-statements --chunk=100
```

The `--chunk` option controls batch size (default 100, max 500 for finalize-statements).

### 3.3 Queue jobs

| Job | Queue | Dispatched by |
|-----|-------|---------------|
| `GenerateRentalSettlementStatement` | default | `marketplace:finalize-statements` command |
| `ProvisionMarketplaceDelegation` | default | Rental activation flow |
| `ReleaseMarketplaceDelegation` | default | Rental expiration/revocation flow |

Ensure the queue worker is running:

```bash
php artisan queue:work --queue=default --tries=3
```

## 4. Recovery procedures

### 4.1 Activation recovery

If approved rentals were not activated (scheduler was down), run manually:

```bash
php artisan marketplace:activate-rentals
```

The command selects all `approved` rentals with `requested_start_at <= now()` and activates them
idempotently. Each rental is processed independently — failures are reported and logged but do not
halt the batch.

### 4.2 Expiration recovery

If active rentals were not expired on time:

```bash
php artisan marketplace:expire-rentals
```

Selects `active` and `approved` rentals with `requested_end_at <= now()`. Safe to re-run.

### 4.3 Statement recovery

If settlement statements were not generated for terminal rentals:

```bash
php artisan marketplace:finalize-statements
```

Finds rentals in `completed`, `cancelled`, or `revoked` status that lack a revision-1 statement.
Dispatches `GenerateRentalSettlementStatement` jobs. Safe to re-run — existing statements are
excluded from the query.

### 4.4 Failed job recovery

```bash
php artisan queue:failed
php artisan queue:retry <job-id>
```

Retry only after identifying and correcting the root cause.

## 5. Degraded delegation handling

Delegation provisions hardware resources (ACS zones/gates, kiosks, badge printers, scanners) from
the venue-owner tenant to the organizer tenant for the rental period. The provisioner registry
(`DelegatedAssetProvisionerRegistry`) dispatches to module-specific adapters:

| Module | Adapter |
|--------|---------|
| `access_control` | `DelegatedAcsAssetPort` |
| `kiosk` | `DelegatedKioskAssetPort` |
| `badge_printing` | `DelegatedPrinterAssetPort` |
| `scanning` | `DelegatedScannerAssetPort` |
| `catalog_only` | `CatalogOnlyCameraProvisioner` (no hardware provisioning) |

If a delegation adapter fails, the provisioning job reports the error and the delegation is marked
as degraded. The rental itself remains active — delegation failures are non-blocking. Operators
can inspect the `control_delegations` and `delegated_resources` tables to identify failed
provisions and re-trigger:

```bash
php artisan queue:retry <failed-provision-job-id>
```

## 6. Cache invalidation

The marketplace catalog uses a generation-based cache (`MarketplaceCatalogCache`). Cache is
disabled by default (`MARKETPLACE_CATALOG_CACHE_ENABLED=false`).

When enabled, cached entries are keyed by a SHA-256 digest of the generation counter, actor tenant,
locale, filters, and cursor. TTL defaults to 300 seconds (`MARKETPLACE_CATALOG_CACHE_TTL_SECONDS`).

### Manual invalidation

Cache invalidation increments the generation counter, effectively expiring all cached entries:

```bash
php artisan tinker --execute="app(\App\Modules\VenueMarketplace\Application\Services\MarketplaceCatalogCache::class)->invalidate();"
```

Catalog mutations (venue publish, withdraw, asset update) already call `invalidate()` in their
action flows. Manual invalidation is only needed if data was corrected directly in the database.

### Configuration

| Env variable | Default | Purpose |
|-------------|---------|---------|
| `MARKETPLACE_CATALOG_CACHE_ENABLED` | `false` | Enable/disable catalog caching |
| `MARKETPLACE_CATALOG_CACHE_TTL_SECONDS` | `300` | Cache entry TTL in seconds |

## 7. Export behavior

Settlement statement CSV export uses `StreamedResponse` (`StreamSettlementStatementCsv`). The
response streams directly to the HTTP client — no temporary files are written to disk and no
shared temp directories are used. This is safe for multi-process and containerized deployments.

Features:
- UTF-8 BOM prefix for Excel compatibility
- CSV formula injection protection (prefixes `=`, `+`, `-`, `@`, tab, CR with `'`)
- Bilingual headers (English default, Arabic when `locale=ar`)
- Audit trail entry written before streaming begins

## 8. Health checks

Marketplace operations are covered by the existing health infrastructure:

- `/api/v1/health/live` — process liveness (includes marketplace routes)
- `/health/ready` — database, queue, and storage readiness
- Platform health (`operations.health.view`) reports category-level status

No separate marketplace health endpoint is required. Monitor scheduler execution and queue failure
rates through standard queue observability.

### Observability toggles

| Env variable | Default | Purpose |
|-------------|---------|---------|
| `MARKETPLACE_OBSERVABILITY_CATALOG_QUERIES_ENABLED` | `false` | Log catalog query telemetry |
| `MARKETPLACE_OBSERVABILITY_PROVISIONING_ENABLED` | `false` | Log delegation provisioning telemetry |
| `MARKETPLACE_OBSERVABILITY_LIFECYCLE_COMMANDS_ENABLED` | `false` | Log scheduler command telemetry |

## 9. Retention policy

`MarketplaceRetentionPolicy` enforces configurable retention periods. Active/issued records are
never pruned regardless of age.

| Record type | Env variable | Default |
|-------------|-------------|---------|
| Settlement statements | `MARKETPLACE_RETENTION_STATEMENT_DAYS` | 2555 (~7 years) |
| Disputes | `MARKETPLACE_RETENTION_DISPUTE_DAYS` | 2555 (~7 years) |
| Audit entries | `MARKETPLACE_RETENTION_AUDIT_DAYS` | 2555 (~7 years) |

Dispute evidence minimization is only permitted after the dispute is resolved and the retention
period has elapsed.

## 10. Rollback limits

Phase 6 migrations add 13 new tables and alter 3 existing tables. Rollback is structurally
supported (`down()` methods exist) but is not the recommended recovery path because:

- Rental requests, settlements, and dispute records constitute retained audit evidence
- Delegation references in ACS and kiosk tables may have been used by active operations
- `organization_type` on `tenants` is referenced by application logic across modules

**Prefer forward repair over rollback.** If rollback is absolutely necessary, take a full database
backup first, ensure no active rentals exist, and roll back in reverse order:

```bash
php artisan migrate:rollback --step=15
```

## 11. SaaS / on-premise parity

The Venue Marketplace operates identically in SaaS and on-premise deployments:

- All data stays in the local database — no external API calls or third-party service dependencies
- Delegation provisioning resolves hardware through local module adapters only
- Catalog cache uses the configured Laravel cache driver (database, file, or Redis)
- CSV export streams directly — no cloud storage dependency
- Scheduler and queue commands use database-backed queues

**No federation.** The marketplace does not federate data across instances. All venue owners,
organizers, and rental transactions exist within the same application instance. Cross-instance
marketplace discovery is not supported and is not planned.

## 12. Configuration reference

All marketplace configuration is in `config/marketplace.php`, overridden by environment variables:

| Key | Env variable | Default | Purpose |
|-----|-------------|---------|---------|
| `catalog.cache_enabled` | `MARKETPLACE_CATALOG_CACHE_ENABLED` | `false` | Enable catalog cache |
| `catalog.cache_ttl_seconds` | `MARKETPLACE_CATALOG_CACHE_TTL_SECONDS` | `300` | Cache TTL |
| `activation.batch_size` | `MARKETPLACE_ACTIVATION_BATCH_SIZE` | `100` | Activation chunk size |
| `statement.batch_size` | `MARKETPLACE_STATEMENT_BATCH_SIZE` | `100` | Statement chunk size |
| `export.chunk_size` | `MARKETPLACE_EXPORT_CHUNK_SIZE` | `500` | CSV export chunk size |
| `retention.statement_days` | `MARKETPLACE_RETENTION_STATEMENT_DAYS` | `2555` | Statement retention |
| `retention.dispute_days` | `MARKETPLACE_RETENTION_DISPUTE_DAYS` | `2555` | Dispute retention |
| `retention.audit_days` | `MARKETPLACE_RETENTION_AUDIT_DAYS` | `2555` | Audit retention |
