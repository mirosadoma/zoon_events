# Phase 2 Validation Quickstart

This guide validates the implemented wallet pass issuance/sync, staff QR
scanning, check-in dashboard, and offline-tolerant scanning design. It uses
synthetic data, fake wallet/push adapters, and native services only.

## Prerequisites

- Completed and passing Phase 0 foundation and Phase 1 registration/
  ticketing/credentials core
- PHP 8.3, Composer 2, Node.js 20+, and npm
- MySQL 8.4 test database
- Fake Apple and Google wallet adapters for deterministic validation (no
  real Pass Type ID certificate or Google service-account key required for
  automated tests)
- Local queue worker for wallet push/update jobs and offline-reconciliation
  jobs
- No production wallet certificates, service-account keys, or device push
  tokens in `.env`, source, fixtures, or command output

## 1. Install and Validate Configuration

```powershell
composer install
npm install
php artisan zonetec:config:validate --env=testing
php artisan config:cache --env=testing
php artisan zonetec:config:validate --env=testing
php artisan config:clear
```

Expected: configuration declares wallet adapter selection (fake/apple/google)
and their secret references, single-entry default policy, and offline
allowlist window bounds, without printing certificate or key material.

## 2. Rebuild the Synthetic Test Database

```powershell
php artisan migrate:fresh --seed --env=testing
php artisan migrate:status --env=testing
```

Expected:

- All Phase 0, Phase 1, and Phase 2 tables exist, including `wallet_passes`,
  `wallet_pass_apple_device_registrations`, `scan_events`,
  `event_check_in_settings`, `event_check_in_summaries`, and
  `offline_scan_reconciliation_batches`, all with tenant/event foreign keys.
- Seeders create a synthetic tenant, published event, ticket, and at least
  one completed attendee/credential (reusing the Phase 1 isolation seeder)
  so wallet and scan flows have a valid target.
- No kiosk, badge, ACS zone/lane, identity verification, or marketplace
  table exists.

## 3. Validate Contracts

```powershell
npx redocly lint specs/003-wallet-passes-scanning/contracts/openapi.yaml
php scripts/sync-openapi.php --check
php artisan test --testsuite=Contract --group=phase-2
```

Expected: the review contract is valid, its implemented operations are
present in the authoritative API contract, and every route has its success
and principal failure cases.

## 4. Validate Wallet Pass Generation and Sync

```powershell
php artisan test --group=wallet-passes
```

Expected:

- A pass can be generated for an active credential for both `apple` and
  `google` fake adapters and fails closed for a revoked/expired credential.
- An event date/time/location change triggers an update push/patch on
  existing passes.
- A credential revocation marks the wallet pass `revoked` and a reissue
  supersedes the prior pass.
- Apple device registration, unregistration, updated-serials, and
  updated-pass endpoints enforce the per-pass authentication token.

## 5. Validate Staff QR Scanning

```powershell
php artisan test --group=check-in
```

Expected:

- A valid credential's first scan returns `accepted` and updates the
  attendee's check-in status.
- A second scan of the same credential returns `duplicate` unless an
  authorized override with a reason is supplied, which returns
  `manual_override`.
- Revoked and expired credentials are rejected with their specific stable
  result; malformed and cross-tenant/cross-event credentials are rejected
  indistinguishably from an unknown credential.
- Scan result, check-in state update, and audit evidence commit or fail
  together under a forced audit failure fixture.

## 6. Validate the Check-In Dashboard

```powershell
php artisan test --filter=CheckInSummaryTest
npm run test -- checkin-dashboard
```

Expected:

- `checked_in_count` increases only for `accepted`/`manual_override` scans.
- An organizer authorized for one event never sees another tenant's or
  event's counts.
- The dashboard UI reflects updated counts within the documented short
  polling interval, in both Arabic/RTL and English/LTR.

## 7. Validate Offline-Tolerant Scanning

```powershell
php artisan test --group=offline-scanning
```

Expected:

- A device can fetch a signed, time-windowed allowlist and use it to accept/
  reject scans without connectivity.
- A repeated offline scan of the same credential on one device is locally
  rejected as a duplicate before reconciliation.
- Reconciling two devices that each offline-accepted the same credential
  during the same gap produces exactly one `accepted` and flags the
  conflict.

## 8. Validate Cross-Tenant and Cross-Event Isolation

```powershell
php artisan test --group=phase-2-isolation
```

Expected: wallet generation, wallet update/revocation, scan submission,
dashboard queries, and offline allowlist/reconciliation all deny cross-tenant
and cross-event targets indistinguishably from an unknown target, across
HTTP, jobs, events, caches, files, and logs.

## 9. Validate Documentation and Phase Boundary

```powershell
php artisan test --testsuite=Architecture --group=phase-2
php artisan zonetec:docs:check
```

Expected: no kiosk, badge printing, manual desk, ACS zone/lane/anti-passback,
identity verification, venue marketplace, or production hardware adapter
code exists; permission and audit catalogs match the implemented wallet and
scan actions exactly.

## 10. Run the Full Phase 2 Gate

```powershell
composer run quality
npm run lint
npm run typecheck
npm run test
npm run build
```

Expected: zero failures, zero lint warnings, a clean production build, and
every Phase 0/Phase 1 gate remains green alongside the new Phase 2 suites.
