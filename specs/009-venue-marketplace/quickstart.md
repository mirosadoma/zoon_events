# Quickstart: Venue Marketplace Validation

This guide validates the completed Phase 6 implementation. It is a runbook for
acceptance and regression checks, not implementation instructions. Detailed
schema/state rules are in data-model.md; API operations are in
contracts/openapi.yaml; module and UI authorization rules are in the other
contracts.

## Prerequisites

- Accepted Phase 0–5 implementation and migrations.
- PHP 8.3+, Composer dependencies, Node 20+, npm dependencies and MySQL. Do not
  use SQLite for reservation concurrency/constraint validation.
- A worker and scheduler available for activation, expiry, notification and
  statement checks.
- Seeded actors:
  - platform administrator with platform marketplace permissions
  - venue-owner tenant with Venue Owner Admin and Venue Asset Manager
  - organizer tenant with Event Manager and Finance/report permissions
  - unrelated third tenant for negative isolation checks
- One organizer-owned eligible event spanning the planned rental window.
- Fake/mock operational provisioners configured for ACS, kiosk, printer and
  scanner. Camera remains catalog-only.

## Install and Reset

From repository root:

~~~text
composer install
npm install
php artisan migrate
php artisan db:seed
~~~

Use an isolated validation database. Phase 6 seed data must be deterministic and
must never contain production contacts, bindings, credentials or provider IDs.

Run the app and queue using the existing development scripts:

~~~text
composer dev
composer dev:queue
php artisan schedule:work
~~~

If schedule:work is not used, invoke the Phase 6 activation/expiry/statement
commands directly at the indicated validation points.

## Contract and Static Gates

~~~text
npx redocly lint specs/009-venue-marketplace/contracts/openapi.yaml
npm run openapi:sync
composer quality
npm run lint
npm run typecheck
npm test
npm run build
~~~

Expected:

- review OpenAPI is valid and its accepted operations are synchronized into the
  authoritative contract
- permission/audit/data-classification documentation matches executable sources
- phase/module boundary checks find no cross-module infrastructure access
- PHP, frontend, type and build gates pass with no warnings/errors

## Scenario 1 — Venue Owner Publishes Fixed Infrastructure

1. Sign in to the venue-owner tenant and open /tenant/venues.
2. Create a bilingual venue with KSA country/city, IANA timezone and a private
   business contact; leave publish-contact off.
3. Add these active assets: turnstile, security gate, camera, kiosk, printer,
   scanner, access lane and access zone.
4. For controlled assets use the configured fake binding; confirm the binding
   value becomes masked after save. Camera must accept catalog-only only.
5. Set type-valid capabilities, capacity where applicable, one currency and
   per-hour/per-day/per-rental prices.
6. Add non-overlapping availability covering the organizer event plus setup and
   teardown.
7. Attempt to publish one incomplete/offline asset; expect field-specific denial.
8. Publish complete assets.

Expected:

- drafts are owner-private; publication returns new opaque IDs/versions
- organizer catalog contains only published bilingual/location/capability/
  capacity/price fields and optional explicitly public contact
- private contact, binding, adapter key, owner-local IDs and secrets are absent
  from page props, API response, logs and audit metadata
- camera has no control/feed operation
- every owner mutation has owner-tenant audit evidence

## Scenario 2 — Organizer Searches, Quotes and Requests

1. Sign in to the organizer tenant and open /tenant/marketplace.
2. Filter by city, asset type, capability, minimum capacity, event window and
   currency. Confirm only complete-window availability is returned.
3. Select multiple assets from the one venue and the organizer-owned event.
4. Review quote math:
   - a partial hour bills one started hour
   - each venue-local calendar day touched bills one day
   - per-rental bills one unit
5. Change one published source price as the owner, then submit the old quote.
   Expect 409 marketplace_quote_changed and a new reviewable quote; no request.
6. Submit the new quote twice with the same Idempotency-Key.

Expected:

- mixed venue/currency and another tenant's event are rejected without partial
  writes or information leakage
- both submissions return one rental public ID and one immutable line/total
  snapshot
- owner receives a notification and participant audit correlation exists in
  both tenant audit views

## Scenario 3 — Conflict-Safe Approval

1. From a second organizer actor/fixture, submit an overlapping request for at
   least one of the same assets. Pending overlap is allowed.
2. As the owner, open both requests and approve the first.
3. Attempt to approve the second. Then reject it with a reason.
4. Run the dedicated concurrent approval integration test with two database
   connections:

~~~text
php artisan test --filter=ConcurrentRentalApprovalTest
~~~

Expected:

- first approval reserves every selected asset atomically
- second returns 409 marketplace_reservation_conflict, creates no partial
  reservation and does not identify the competing organizer
- concurrent test proves exactly one approved rental for the overlapping asset
- rejection requires a reason, releases no nonexistent reservation and notifies
  the organizer
- forced audit failure rolls back approval and all reservations

## Scenario 4 — Time-Bounded Delegated Control

1. Approve a short rental whose start is a few minutes ahead.
2. Before start, try a provisioned ACS/kiosk/printer/scanner management action as
   an organizer user who has the normal operational permission. Expect
   marketplace_delegation_not_started.
3. At start, run the activation command/scheduler. Confirm resources provision
   only through their owning module and the rental is active or explicitly
   degraded.
4. During the window:
   - allowed user + matching event/asset/capability succeeds
   - user without the normal operational permission is denied
   - another event, unrented asset or ungranted capability is denied
5. Stop workers and move the application clock past the end; attempt the action
   before running expiry. Expect marketplace_delegation_expired.
6. In a separate rental, have the owner revoke during active use with a reason;
   retry immediately before cleanup. Expect marketplace_delegation_revoked.
7. Restore workers and run recovery twice.

Expected:

- time/revocation denial is synchronous and cannot be extended by stale UI/cache
  or a stopped scheduler
- recovery releases each module resource once and does not create a new grant
- credential validation, device sessions, anti-passback and emergency behavior
  remain on their existing paths
- camera exposes no operational action
- allowed and denied control decisions are sanitized and audited

## Scenario 5 — Statement, Export and Dispute

1. Finalize one completed rental and one cancelled/revoked rental.
2. Run statement finalization twice.
3. As owner finance and organizer finance, open the same statement and compare
   revision, line items, agreed total, currency, window and outcome.
4. Export CSV from both participant scopes.
5. Open one dispute with a bounded reason.
6. As platform dispute manager, start review, add one participant-visible note
   and one platform-only note, then resolve with a stable code/summary.

Expected:

- one revision-1 statement per rental appears within five minutes; retry creates
  no duplicate
- both parties see identical facts localized for their view; cancelled/revoked
  outcome does not invent refund, penalty, payable, payment or payout
- CSV is streamed to the authorized requester, audited and not stored in a shared
  path
- organizer/owner do not see platform-only note; unrelated tenant gets not found
- resolution changes dispute state only; original statement and rental control
  are unchanged

## Scenario 6 — Cross-Tenant, RBAC and Secret Sweep

Run the Phase 6 security suites:

~~~text
php artisan test --testsuite=Integration --filter=Marketplace
php artisan test --filter=Phase6IsolationSweepTest
php artisan test --filter=Phase6PermissionMatrixTest
php artisan test --filter=MarketplaceAuditAtomicityTest
php artisan test --filter=MarketplaceSecretRedactionTest
~~~

Verify owner, organizer, unrelated tenant and platform actors across every venue,
catalog, rental, delegation, statement, export and dispute operation.

Expected:

- unrelated tenant receives not found for participant public IDs
- catalog contains only active publication fields
- owner/organizer projections differ only by documented action/private fields
- platform routes fail in tenant context and tenant routes fail in platform-only
  context
- missing permission fails server-side even when calling the API directly
- jobs/events/cache keys/notifications retain correct participant scope
- no binding, credential, secret, raw provider ID or private note appears in
  response, logs, metrics, notifications or audit

## Scenario 7 — Arabic, RTL, Accessibility and Branding

Run browser/component suites and manually inspect the five core journeys:

~~~text
npm run test:browser
npm test -- resources/js/__tests__/marketplace-owner.test.tsx
npm test -- resources/js/__tests__/marketplace-organizer.test.tsx
npm test -- resources/js/__tests__/marketplace-settlement.test.tsx
~~~

Expected:

- venue/catalog/rental/delegation/statement/dispute content and validation are
  complete in Arabic and English
- RTL/LTR has no horizontal page overflow, reversed semantics or inaccessible
  action order
- dates show venue timezone; numbers/currency use viewer locale without changing
  stored facts
- loading, empty, filtered-empty, stale/conflict, forbidden, degraded and error
  states have keyboard focus and screen-reader announcements
- each viewing tenant's branding renders without changing counterpart facts

## Scenario 8 — Performance and On-Premise Recovery

1. Load the performance fixture with 10,000 active publications and representative
   capability/availability/reservation density.
2. Run:

~~~text
php artisan test --filter=Phase6MarketplacePerformanceTest
~~~

3. Repeat the deployment-parity suite with:

~~~text
ZONETEC_DEPLOYMENT_MODE=on_premise php artisan test --filter=Phase6MarketplaceDeploymentParityTest
~~~

4. Simulate unavailable remote catalog/provisioner and stopped queue, then restore.

Expected:

- 95th-percentile catalog search meets 2 seconds under agreed test load, uses
  bounded cursors and has no N+1 query growth
- on-premise local venue/organizer tenants complete the same workflow
- remote/fake dependency failure is explicit and never falls back to private data
  or fabricated control success
- local stored grants expire/revoke while disconnected; recovery is idempotent

## Final Definition of Done

Phase 6 is ready only when:

- all scenarios and automated gates above pass
- authoritative OpenAPI, permission, audit, data classification and operations
  documents are updated
- migrations and rollback/recovery behavior are verified on MySQL
- observability shows catalog, approval conflict, provisioning, delegation,
  statement and dispute health without sensitive values
- no open constitution exception, clarification or undocumented capability remains

## Validation Evidence

### 2026-07-14 — Phase 2 foundational prerequisites (MySQL accepted)

- Non-database suite: 20 tests / 367 assertions (ports, boundaries, Phase6Problem, VOs, fakes).
- MySQL suite with `phpunit.xml` forced Laragon `root` / empty password against `zonetec_testing`:
  `php vendor/bin/phpunit tests/Feature/Tenancy/OrganizationEligibilityTest.php tests/Feature/VenueMarketplace/MarketplacePermissionMatrixTest.php tests/Feature/Events/DatabaseMarketplaceEventReaderTest.php tests/Feature/VenueMarketplace/Phase6FoundationSmokeTest.php`
  → **OK (10 tests, 68 assertions)**.
- Earlier blocker: `.env` testing credentials used a non-empty root password / broken `zonetec_testing` user plugin; resolved via `phpunit.xml` `force="true"` empty root password. A stuck `idempotency_records` CHECK ALTER had also locked migrate; DB was recreated and migrations completed including `2026_07_14_000001_add_organization_type_to_tenants_table`.
- T011–T017, T022, and T035 accepted and marked complete.

### 2026-07-14 — Vitest Inertia leak fixed

- Eager `import.meta.glob('./pages/**/*.tsx')` was loading `__tests__` under pages into the browser, causing “Vitest failed to access its internal state”.
- Fixed in `resources/js/app.tsx` and `resources/js/ssr.tsx` by excluding `__tests__` and `*.test.tsx`.
- `npm run test:phase6` → 22 tests passed.

### 2026-07-14 — Phase 5 architecture gate updated for Phase 6

- `Phase5ModuleBoundaryTest` no longer forbids Marketplace/Rental vocabulary (Phase 6 is in active implementation); forbids Phase 7/8 names only.

### 2026-07-14 — Phase 3 / User Story 1 schema and domain slice

- Applied migrations `2026_07_14_000002` through `000005` to the already-migrated
  `zonetec_testing` MySQL database. This added tenant-scoped venue, asset,
  opaque/encrypted binding, availability, immutable catalog publication, and
  normalized publication-capability storage with reversible foreign keys,
  lifecycle checks, and one-active-publication enforcement.
- TDD validation used:
  `php vendor/bin/phpunit tests/Feature/VenueMarketplace/VenueCatalogSchemaTest.php tests/Unit/VenueMarketplace/VenueLifecycleTest.php tests/Unit/VenueMarketplace/VenueAssetCapabilityTest.php tests/Feature/VenueMarketplace/AssetAvailabilityWindowTest.php tests/Feature/VenueMarketplace/MarketplacePublicationProjectionTest.php tests/Feature/VenueMarketplace/OwnerVenueAuditTest.php tests/Feature/VenueMarketplace/Phase6FoundationSmokeTest.php`
  → **OK (18 tests, 231 assertions)**.
- The suite covers lifecycle fail-closed reason codes, all eight asset types,
  local-to-UTC availability normalization and atomic overlap/version rejection,
  immutable allowlisted publication/withdrawal, private/binding redaction from
  serialized output and query bindings, and rollback when synchronous audit
  writing fails.
- Pint passed for the changed marketplace module, tests, and migrations; IDE
  diagnostics reported no errors.
- Accepted and marked complete: T036–T040, T043, T046–T049, T053–T055, T059,
  and T064–T066. T041–T042 remain pending because the owner API query,
  request/resource, controller, and protected route layer (T067–T071) is not yet
  implemented.

### 2026-07-14 — User Story 1 owner backend completed

- Added tenant-scoped owner venue/asset queries, explicit allowlist resources,
  OpenAPI-aligned requests, thin controllers, 13 named authenticated
  `venue.manage` routes, stable Phase6Problem rendering, and synchronous
  secret-free marketplace audit dispatch.
- Added owner API contract and authorization coverage for authentication,
  idempotency, validation, response shapes, pagination, venue-owner/hybrid
  eligibility, organizer/permission denial, platform non-escalation, and
  foreign opaque IDs returning tenant-scoped 404 responses.
- Added the aggregate marketplace fixture factory for a private venue, all eight
  asset types, bindings, availability, and published projections through
  application actions.
- Complete backend suite:
  `php vendor/bin/phpunit tests/Unit/VenueMarketplace tests/Feature/VenueMarketplace`
  → **OK (38 tests, 625 assertions)** in 16:08.972 against MySQL.
- Focused owner API rerun:
  `php vendor/bin/phpunit tests/Feature/VenueMarketplace/OwnerVenueApiContractTest.php tests/Feature/VenueMarketplace/OwnerVenueAuthorizationTest.php`
  → **OK (6 tests, 55 assertions)**.
- Accepted and marked complete: T041–T042, T050–T052, T056–T058, T060–T063,
  T067–T072, and T078.

### 2026-07-14 — User Story 3 dependency-safe domain slice

- Confirmed US2 persistence is not yet available: neither `RentalRequest` nor
  `SubmitRentalRequestAction` exists. No US2-owned file or rental-dependent US3
  migration/action was created.
- Added T113 schema contracts for reservations, control delegations, delegated
  resources, composite/opaque integrity, conflict-scan indexes, and reversible
  migrations. Validation command:
  `php vendor/bin/phpunit tests/Feature/VenueMarketplace/VenueCatalogSchemaTest.php`
  → expected red state with **one failure only**:
  `Missing asset_reservations table` (4 tests, 114 assertions, 1 skipped).
- Added database-free rental lifecycle and reservation conflict tests plus their
  domain services. Validation command:
  `php vendor/bin/phpunit tests/Unit/VenueMarketplace/RentalStateMachineTest.php tests/Unit/VenueMarketplace/ReservationConflictDetectorTest.php`
  → **OK (32 tests, 41 assertions)**.
- Coverage includes authoritative owner/organizer/system actors, required
  rejection/revocation reasons, optimistic versions, terminal states, half-open
  interval boundaries, all overlap directions, released/completed exclusions,
  deterministic sorted asset lock order, UTC normalization, and opaque-only
  deterministic conflict metadata.
- Accepted and marked complete: T113–T115, T128–T129. T116–T127 and T130–T145
  remain pending until US2 supplies the canonical rental tables/models/actions.

### 2026-07-15 — User Story 3 persistence dependency follow-up

- During the same implementation session, US2 supplied `RentalRequest`,
  `RentalAsset`, migrations `000006`/`000007`, and `SubmitRentalRequestAction`.
  Work then resumed without editing those US2-owned files.
- Added migrations `000008`/`000009` for tenant/participant-scoped reservations,
  pending control delegations, and delegated resource placeholders. The schema
  uses half-open UTC windows, deterministic conflict-scan indexes, composite
  rental integrity, lifecycle checks, opaque references, and hashed idempotency.
- Schema validation:
  `php vendor/bin/phpunit tests/Feature/VenueMarketplace/VenueCatalogSchemaTest.php`
  → **OK (4 tests, 184 assertions)**. Accepted and marked complete: T123–T124.
- Added reservation/delegation/resource models, owner-scoped release service,
  direct correlated database audit writer, and conflict-safe approve plus
  reject/cancel/revoke actions. These remain unchecked in `tasks.md` until all
  named authorization, concurrency, audit, API, and lifecycle acceptance suites
  pass.
- Focused approval persistence validation:
  `php vendor/bin/phpunit tests/Feature/VenueMarketplace/RentalDecisionLifecycleTest.php --filter=approve_is_atomic`
  → **OK (1 test, 7 assertions)**. The complete lifecycle file could not produce
  reliable evidence because another concurrent `RefreshDatabase` process wiped
  the shared `zonetec_testing` schema; its errors were missing/already-existing
  framework tables, not a US3 assertion failure.
- Final database-free regression:
  `php vendor/bin/phpunit tests/Unit/VenueMarketplace/RentalStateMachineTest.php tests/Unit/VenueMarketplace/ReservationConflictDetectorTest.php`
  → **OK (32 tests, 41 assertions)**. Pint passed for all US3 PHP files and IDE
  diagnostics reported no errors.

