# Phase 3 Validation Quickstart

This guide validates the implemented kiosk registration/session/health,
badge template designer, badge print/reprint, manual desk lookup/check-in,
and walk-up registration. It uses synthetic data, a fake printer adapter,
and native services only.

## Prerequisites

- Completed and passing Phase 0 foundation, Phase 1 registration/
  ticketing/credentials core, and Phase 2 wallet passes/scanning core
- PHP 8.3, Composer 2, Node.js 20+, and npm
- MySQL 8.4 test database
- A fake printer adapter for deterministic validation (no real printer
  hardware/driver required for automated tests)
- No production printer connection secrets, kiosk device-session secrets,
  or attendee lookup confirmation codes in `.env`, source, fixtures, or
  command output

## 1. Install and Validate Configuration

```powershell
composer install
npm install
php artisan zonetec:config:validate --env=testing
php artisan config:cache --env=testing
php artisan zonetec:config:validate --env=testing
php artisan config:clear
```

Expected: configuration declares the printer adapter selection (fake/real),
kiosk offline-threshold default, and lookup-confirmation notification
channel, without printing any connection secret or device-session material.

## 2. Rebuild the Synthetic Test Database

```powershell
php artisan migrate:fresh --seed --env=testing
php artisan migrate:status --env=testing
```

Expected:

- All Phase 0, Phase 1, Phase 2, and Phase 3 tables exist, including
  `kiosks`, `kiosk_sessions`, `badge_templates`, `badge_print_jobs`, plus
  the `walk_up_registration_enabled`/`walk_up_payment_method_enabled`/
  `reprint_revokes_old_qr`/`lookup_confirmation_required`/
  `kiosk_offline_threshold_seconds` columns on `event_check_in_settings`
  and the `origin` column on `attendees`.
- Seeders create a synthetic tenant, published event, ticket, and at least
  one completed attendee/credential (reusing the Phase 1/2 isolation
  seeder) so kiosk, desk, and badge flows have a valid target.
- No ACS zone/lane, identity verification, or venue marketplace table
  exists.

## 3. Validate Contracts

```powershell
npx redocly lint specs/004-kiosk-badge-printing-manual-desk/contracts/openapi.yaml
php scripts/sync-openapi.php --check
php artisan test --testsuite=Contract --group=phase-3
```

Expected: the review contract is valid, its implemented operations are
present in the authoritative API contract, the Phase 2 `submitScan`
operation's `scanner_type` enum now includes `manual_desk`, and every route
has its success and principal failure cases.

## 4. Validate Kiosk Registration, Pairing, and Session Auth

```powershell
php artisan test --group=kiosk
```

Expected:

- An authorized actor can register a kiosk and pair it, receiving a raw
  device-session secret exactly once.
- Re-pairing a kiosk immediately revokes its previous session.
- Confirmation-required sessions reject scans/lookups/prints until
  confirmed with the correct PIN/one-time code.
- A retired kiosk's session is rejected for every operation.
- Heartbeats update kiosk/printer status and drive online/offline
  derivation against the configured threshold.

## 5. Validate Attendee Lookup and Kiosk/Desk Check-In

```powershell
php artisan test --group=check-in-onsite
```

Expected:

- QR and name/email/phone lookups both resolve to the same scan decision
  order and stable result set from Phase 2 (`accepted`, `manual_override`,
  `duplicate`, `revoked`, `expired`, `rejected`).
- An overly broad name/email/phone fragment returns a bounded
  "refine your search" response, never an unbounded list.
- When lookup confirmation is enabled, check-in is blocked until a valid
  one-time code is confirmed.
- `ScanEvent` rows from kiosk and manual desk check-ins carry
  `scanner_type` of `kiosk`/`manual_desk` respectively.

## 6. Validate Badge Templates and Printing

```powershell
php artisan test --group=badge-printing
```

Expected:

- A template referencing a field outside the fixed allowlist is rejected
  at save time.
- Activating a template deactivates any previously active template for
  the same event.
- Printing against an event with no active template is rejected without
  creating a print job.
- A successful print renders only the active template's configured fields.

## 7. Validate Reprints

```powershell
php artisan test --group=badge-reprint
```

Expected:

- A reprint without the distinct `badge.reprint` permission is rejected.
- A reprint without a reason is rejected before any job is created.
- A reprint for an attendee with no prior print job is rejected.
- With `reprint_revokes_old_qr` enabled, the prior credential/QR is
  rejected by a subsequent scan after the reprint completes.
- Every reprint attempt (successful or blocked) produces exactly one audit
  record.

## 8. Validate Walk-Up Registration

```powershell
php artisan test --group=walk-up-registration
```

Expected:

- With the event toggle disabled, the walk-up registration endpoint is
  unavailable.
- With the toggle enabled, a walk-up attendee is created using the exact
  Phase 1 registration and credential-issuance action, tagged
  `origin = walk_up`.
- Without an enabled on-site payment method, the response clearly
  indicates payment cannot be collected rather than marking the attendee
  paid.

## 9. Validate Kiosk and Printer Health Monitoring

```powershell
php artisan test --filter=KioskHealthTest
npm run test -- kiosk-health
```

Expected:

- A kiosk that stops sending heartbeats transitions to `offline` after the
  configured threshold.
- A relayed printer error is visible alongside the kiosk's own status.
- An operations viewer authorized for one event never sees another
  tenant's or event's kiosk health.

## 10. Validate Cross-Tenant and Cross-Event Isolation

```powershell
php artisan test --group=phase-3-isolation
```

Expected: kiosk registration/pairing/session, lookup, check-in, badge
template/print/reprint, and walk-up registration all deny cross-tenant and
cross-event targets indistinguishably from an unknown target, across HTTP,
jobs, events, caches, files, and logs.

## 11. Validate Documentation and Phase Boundary

```powershell
php artisan test --testsuite=Architecture --group=phase-3
php artisan zonetec:docs:check
```

Expected: no ACS zone/lane/anti-passback, identity verification, venue
marketplace, or production hardware adapter code exists beyond the printer
adapter contract; permission and audit catalogs match the implemented
kiosk, badge, desk, and walk-up actions exactly.

## 12. Run the Full Phase 3 Gate

```powershell
composer run quality
npm run lint
npm run typecheck
npm run test
npm run build
```

Expected: zero failures, zero lint warnings, a clean production build, and
every Phase 0/1/2 gate remains green alongside the new Phase 3 suites.
