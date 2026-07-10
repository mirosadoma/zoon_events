# Phase 1 Validation Quickstart

This guide validates the implemented registration, ticketing, orders, payments,
attendees, notifications, and signed credentials. It uses synthetic data and
native services only.

## Prerequisites

- Completed and passing Phase 0 foundation
- PHP 8.3, Composer 2, Node.js 20+, and npm
- MySQL 8.4 test database
- Sodium extension enabled
- Local queue worker and private storage
- Fake payment, email, and SMS adapters for deterministic validation
- No production credentials in `.env`, source, fixtures, or command output

## 1. Install and Validate Configuration

```powershell
composer install
npm install
php artisan zonetec:config:validate --env=testing
php artisan config:cache --env=testing
php artisan zonetec:config:validate --env=testing
php artisan config:clear
```

Expected: configuration declares credential key references, personal-data key
references, payment and notification adapter selection, public host policy,
inventory hold duration, and retention policy without printing secret values.

## 2. Rebuild the Synthetic Test Database

```powershell
php artisan migrate:fresh --seed --env=testing
php artisan migrate:status --env=testing
```

Expected:

- All Phase 0 and Phase 1 tables exist with tenant/event foreign keys.
- Seeders create synthetic tenant, event, form, ticket, price, user, and adapter
  configuration only in test/development.
- Repeated seeding is idempotent.
- No wallet, scan, check-in, kiosk, badge, ACS, identity, marketplace, or
  hardware table exists.

## 3. Validate Contracts

```powershell
npx redocly lint specs/002-registration-ticketing-credentials/contracts/openapi.yaml
php scripts/sync-openapi.php --check
php artisan test --testsuite=Contract --group=phase-1
```

Expected: the review contract is valid, its implemented operations are present
in the authoritative API contract, every route has its success and principal
failure cases, and public routes do not trust `X-Tenant-ID`.

## 4. Validate Event Setup and Publication

```powershell
php artisan test --group=events
php artisan test --group=registration-forms
php artisan test --group=white-label
```

Verify:

1. An authorized organizer creates a draft event.
2. Corporate/public/VIP/VVIP defaults are deterministic.
3. Invalid schedule, missing published form, missing active ticket, or invalid
   domain/brand reference blocks publication.
4. The published form appears in English/LTR and Arabic/RTL on the approved
   host.
5. Cross-tenant and unauthorized event IDs disclose no data.

## 5. Validate Free Registration

```powershell
php artisan test --group=free-registration
```

Expected:

- Invalid form input commits nothing.
- A successful free submission creates one submission, paid zero-value order,
  sold inventory unit, attendee, active signed credential, notification intent,
  and required audit evidence.
- Repeating the same intent returns the original outcome without duplicate
  effects.
- The QR payload contains no personal or payment data.

## 6. Validate Inventory and Pricing

```powershell
php artisan test --group=ticket-inventory
php artisan test --group=price-tiers
php artisan test --group=phase-1-performance
```

Expected:

- Concurrent requests for the final unit result in exactly one sale.
- Held plus sold never exceeds capacity.
- Expired/failed holds are released once.
- Boundary-time, timezone, capacity-threshold, and priority cases select one
  price.
- Ambiguous active tiers are rejected.
- The 10,000-attempt capacity fixture produces no oversell.

## 7. Validate Paid Registration and Reconciliation

```powershell
php artisan test --group=payments
php artisan test --group=paid-registration
php artisan test --group=payment-reconciliation
```

Run the fake adapter through:

- action required then captured;
- explicit failure/cancellation;
- duplicate callback;
- callback before browser return;
- browser return before callback;
- timeout before send;
- unknown outcome followed by authoritative fetch;
- amount, currency, account, order-reference, and live-mode mismatch;
- full and partial refund;
- refund duplication and unknown result.

Expected: only authoritative matching capture completes an order and issues one
credential; unknown outcomes remain recoverable; cumulative refunds never
exceed captured amount.

## 8. Validate Credential Lifecycle

```powershell
php artisan test --group=credentials
php artisan zonetec:credentials:keys-check --env=testing
```

Expected:

- Ed25519 token issue and verification pass for active keys.
- Tamper, malformed token, unknown/retired/compromised key, expiry, wrong
  tenant/event, revocation, and supersession fail safely.
- Reissue invalidates the old QR and creates one replacement.
- Revocation/reissue completes with audit evidence or rolls back completely.
- No scan, entry, anti-passback, or check-in state is created.

## 9. Validate Notifications

```powershell
php artisan test --group=notifications
```

Expected:

- One English or Arabic email intent is created for every completed order.
- SMS is created only when enabled and a valid phone exists.
- Temporary failure retries with bounds; permanent failure is visible.
- Notification outage does not invalidate the order or credential.
- Duplicate jobs never create duplicate notification intents.

## 10. Prove Tenant, Event, RBAC, Audit, and Privacy Controls

```powershell
php artisan test --group=phase-1-isolation
php artisan test --group=phase-1-rbac
php artisan test --group=phase-1-audit
php artisan test --group=phase-1-privacy
```

Expected:

- 100% of cross-tenant and cross-event attempts are denied across HTTP, models,
  jobs, events, cache, files, exports, logs, telemetry, and adapters.
- Every Phase 1 permission has an allow and deny case with immediate revocation.
- Forced audit failure leaves no partial required business state.
- Raw form answers, contact values, QR tokens, card data, provider secrets, and
  provider payloads are absent from audit, logs, metrics, errors, and fixtures.
- Encrypted fields and blind indexes do not reveal original values.

## 11. Validate Organizer and Public Experiences

```powershell
npm run lint
npm run typecheck
npm run test
npm run build
php artisan test --group=phase-1-dashboard
```

Expected: event, form, ticket, order, attendee, and credential views enforce the
same application contracts as the API; Arabic/English, RTL/LTR, keyboard,
responsive, loading, empty, error, forbidden, conflict, dependency, and pending
reconciliation states pass; cross-tenant data appears in zero page props.

## 12. Validate SaaS and On-Premise Parity

```powershell
$env:ZONETEC_DEPLOYMENT_MODE = 'saas'
php artisan test --group=phase-1-deployment-parity

$env:ZONETEC_DEPLOYMENT_MODE = 'on_premise'
php artisan test --group=phase-1-deployment-parity
```

Expected: core event, free registration, ticketing, attendee, credential, RBAC,
and audit behavior is identical. With outbound network blocked, paid checkout
and notifications report explicit degraded/pending states and recover without
duplicate effects after connectivity returns.

## 13. Run the Complete Release Gate

```powershell
composer validate --strict
vendor/bin/pint --test
composer audit
npm audit --audit-level=high
npm run lint
npm run typecheck
npm run test
npm run build
php artisan migrate:fresh --seed --env=testing
php artisan test
npx redocly lint specs/002-registration-ticketing-credentials/contracts/openapi.yaml
php scripts/sync-openapi.php --check
php artisan zonetec:docs:check
php artisan zonetec:phase-boundary:check
```

Phase 1 is ready only when every command passes, provider production-readiness
evidence and approved secret references exist for enabled live channels,
retention/residency policy is approved, no expired governance exception exists,
and no Phase 2+ feature has entered source, schema, routes, or navigation.
