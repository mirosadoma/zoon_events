# Phase 4 Validation Quickstart

This guide validates the implemented ACS zone/lane/rule configuration, gate
authorization decisions, anti-passback, entry/exit event ingestion, emergency
egress, and ACS/lane health. It uses synthetic data, a mock ACS adapter, and
native services only.

## Prerequisites

- Completed and passing Phase 0 foundation, Phase 1 registration/ticketing/
  credentials core, Phase 2 wallet passes/scanning core, and Phase 3 kiosk/
  badge/manual-desk increment
- PHP 8.3, Composer 2, Node.js 20+, and npm
- MySQL 8.4 test database
- A mock ACS adapter for deterministic validation (no real Runa ACS
  transport/hardware required for automated tests)
- No production ACS integration secrets or transport tokens in `.env`,
  source, fixtures, or command output

## 1. Install and Validate Configuration

```powershell
composer install
npm install
php artisan zonetec:config:validate --env=testing
php artisan config:cache --env=testing
php artisan zonetec:config:validate --env=testing
php artisan config:clear
```

Expected: configuration declares the ACS adapter selection (mock/real), the
authorization latency budget, and the default zone unavailability/emergency
modes, without printing any ACS integration secret or transport material.

## 2. Rebuild the Synthetic Test Database

```powershell
php artisan migrate:fresh --seed --env=testing
php artisan migrate:status --env=testing
```

Expected:

- All Phase 0-3 tables exist plus `acs_zones`, `acs_lanes`,
  `acs_authorization_rules`, `access_events`, `anti_passback_states`, and
  `emergency_events`, plus the ACS M2M integration credential store.
- `ScanEvent.scanner_type` accepts `acs_gate` (already reserved by Phase 2,
  or added by the single Phase 4 check-constraint extension migration).
- Seeders create a synthetic tenant, published event, ticket type, and at
  least one completed attendee/credential (reusing the Phase 1/2 isolation
  seeder) so authorization flows have a valid target.
- No identity verification or venue marketplace table exists.

## 3. Validate Contracts

```powershell
npx redocly lint specs/005-acs-access-control/contracts/openapi.yaml
php scripts/sync-openapi.php --check
php artisan test --testsuite=Contract --group=phase-4
```

Expected: the review contract is valid, its implemented operations are
present in the authoritative API contract, and every route has its success
and principal failure cases.

## 4. Validate Zone, Lane, and Rule Configuration

```powershell
php artisan test --group=acs-config
```

Expected:

- An authorized operator can create zones and lanes with external ACS
  identifiers and authorization rules scoped to their event/tenant only.
- Duplicate `external_acs_zone_id`/`external_acs_lane_id` per event is
  rejected.
- Cross-tenant/cross-event zone/lane/rule access is denied indistinguishably
  from an unknown target.

## 5. Validate Gate Authorization Decisions

```powershell
php artisan test --group=acs-authorization
```

Expected:

- A valid credential with a permitting rule and in-window entry returns
  `allow`/`allowed` and appends one `decision` `AccessEvent`.
- Expired, revoked, and unknown credentials return
  `credential_expired`/`credential_revoked`/`credential_unknown`.
- No permitting zone rule returns `zone_not_permitted`; wrong lane returns
  `lane_not_permitted`; outside the window returns `outside_time_window`.
- An allowed entry at an `is_admission_lane` lane also records a Phase 2
  `ScanEvent` (`scanner_type = 'acs_gate'`) linked via
  `AccessEvent.scan_event_id`; a non-admission lane does not.

## 6. Validate Anti-Passback

```powershell
php artisan test --group=acs-anti-passback
```

Expected:

- With anti-passback enabled, re-entry while inside returns
  `anti_passback_violation`; after a recorded exit the same credential is
  allowed again.
- An `anti_passback_exempt` rule never triggers the violation.
- Anti-passback is settable per event, per zone, and per ticket type
  independently.

## 7. Validate Entry/Exit Event Ingestion

```powershell
php artisan test --group=acs-events
```

Expected:

- An entry/exit callback records an `AccessEvent` and updates
  `AntiPassbackState`.
- A duplicate `external_event_id` is an idempotent no-op (no duplicate event,
  no double state transition).
- An out-of-order entry/exit reconciles to the correct final state by
  `occurred_at`.
- A callback referencing a lane/zone outside the integration actor's mapped
  event is rejected with `acs_event_out_of_scope` and not recorded.

## 8. Validate ACS Unavailability (Fail-Open / Fail-Closed)

```powershell
php artisan test --group=acs-unavailable
```

Expected:

- With the mock ACS made unreachable or latency-exceeded, a `fail_open` zone
  returns `allow`/`acs_unavailable_fail_open` and a `fail_closed` zone
  returns `deny`/`acs_unavailable_fail_closed`.
- Every such decision is recorded on an `AccessEvent`; no request is silently
  dropped.

## 9. Validate Emergency Egress

```powershell
php artisan test --group=acs-emergency
```

Expected:

- Raising an emergency for a `fail_open` zone causes subsequent entry
  decisions at affected lanes to return `allow`/`emergency_fail_open` and
  records an `EmergencyEvent` plus an `AccessEvent`.
- The active emergency is visible on the gate-events/health surface.
- Clearing the emergency restores normal decisioning.

## 10. Validate Gate Events and ACS/Lane Health

```powershell
php artisan test --filter=AcsHealthTest
npm run test -- acs-health
```

Expected:

- The gate-events feed shows allowed/denied/entry/exit/emergency rows with
  reason codes, scoped to one authorized event.
- A lane with no ACS contact within its threshold shows `offline`; the
  overall ACS integration status reflects mock adapter reachability.
- A viewer authorized for one event never sees another tenant's or event's
  ACS data.

## 11. Validate Cross-Tenant and Cross-Event Isolation

```powershell
php artisan test --group=phase-4-isolation
```

Expected: zone/lane/rule configuration, authorization requests, event
callbacks, emergency signals, and gate-event/health views all deny
cross-tenant and cross-event targets indistinguishably from an unknown
target, across HTTP, jobs, events, caches, files, and logs; the M2M
integration actor can never act outside its mapped event/lanes.

## 12. Validate Documentation and Phase Boundary

```powershell
php artisan test --testsuite=Architecture --group=phase-4
php artisan zonetec:docs:check
```

Expected: no identity verification or venue marketplace code exists beyond
the ACS adapter contract; the ACS is reached only through the `AcsAdapter`
interface (no transport type in domain code); permission and audit catalogs
match the implemented ACS configuration, authorization, event, and emergency
actions exactly.

## 13. Run the Full Phase 4 Gate

```powershell
composer run quality
npm run lint
npm run typecheck
npm run test
npm run build
```

Expected: zero failures, zero lint warnings, a clean production build, and
every Phase 0/1/2/3 gate remains green alongside the new Phase 4 suites.
