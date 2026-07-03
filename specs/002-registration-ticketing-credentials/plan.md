# Implementation Plan: Registration, Ticketing, Orders, and Credentials

**Branch**: `002-registration-ticketing-credentials` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from
`/specs/002-registration-ticketing-credentials/spec.md`

**Product Phase**: Phase 1 Registration-Ticketing-Credentials

**Deployment Modes**: SaaS and on-premise

## Summary

Extend the accepted Phase 0 modular monolith with the first product core:
tenant-owned events, immutable registration-form versions, atomic ticket
inventory, scheduled pricing, free and paid orders, attendees, Ed25519-signed
QR credentials, revocation/reissue, and localized email/SMS confirmations.

The design keeps MySQL as the authority for inventory and business state,
serializes sales through short row-locking transactions, stores money in minor
units, encrypts attendee/contact/form data with blind indexes for exact lookup,
and routes external effects through durable tenant-scoped intents. A
provider-neutral payment contract has fake and Moyasar implementations; email
uses configured SMTP and SMS has fake and Unifonic implementations. Provider
outages and unknown outcomes remain explicit and reconcilable. Wallet passes,
scanning/check-in, kiosk/badge, ACS, identity, and marketplace work remain out
of scope.

## Technical Context

**Language/Version**: PHP 8.3 and TypeScript 5.9

**Primary Dependencies**: Laravel 13, Sanctum, Fortify, Inertia 3, React 19,
Tailwind CSS 4, shadcn/ui prerequisites, PHP Sodium extension, Laravel HTTP/mail
and database queue facilities; no provider SDK is required in domain code

**Storage**: MySQL 8.4 shared schema with tenant-first composite constraints;
private tenant storage only for approved exports/evidence, not QR secrets or
provider payload archives

**Testing**: PHPUnit/Laravel test runner with MySQL integration, queue/event and
HTTP fakes, adapter contract suites, OpenAPI lint/conformance, Vitest/React
Testing Library/axe, browser/system tests, Pint, ESLint, TypeScript, and Vite
build

**Target Platform**: Native Windows or Linux web/worker/scheduler processes for
multi-tenant SaaS and supported on-premise deployments; no Docker or Sail

**Project Type**: API-first modular web application with same-origin organizer
dashboard and public attendee registration experience

**Performance Goals**:

- p95 published event/form/availability read under 500 ms at planned load;
- p95 local free-registration commit under 1 second, excluding notification;
- p95 paid-checkout local processing under 1 second, excluding payment-provider
  response/challenge;
- revocation/reissue reflected by online validation within 5 seconds;
- 10,000 concurrent controlled attempts against limited inventory with zero
  oversell;
- bounded organizer collection queries at representative 100,000-attendee
  events.

**Constraints**: Fail-closed tenant and event scope; no raw personal or payment
secrets in logs/audit/errors; no card-data storage; required state and audit
evidence are atomic; provider calls occur outside database locks; unknown
financial outcomes reconcile before retry; credentials contain no PII; core
free registration and credential lifecycle operate without internet;
Arabic/English and RTL/LTR parity; no Phase 2+ feature or placeholder

**Scale/Scope**: Existing Phase 0 target of 1,000 tenants and 100,000 workforce
users; up to 100,000 attendees and 20 ticket/price configurations per event;
millions of historical orders/attendees across tenants; eight new domain
modules, about 20 owned tables, 24 review API operations, public registration
pages, and organizer dashboard sections

## Constitution Check

*GATE: PASS before research; PASS after design.*

| Gate | Design evidence | Status |
|---|---|---|
| API-first | `contracts/openapi.yaml` defines public and tenant operations, validation, auth/context, idempotency, envelopes, errors, and compatibility. Dashboard actions use the same application actions. | PASS |
| Tenant isolation | Every product table has `tenant_id`; event-owned tables also have `event_id`; composite foreign keys, host-derived public scope, tenant-aware jobs/events/adapters, tenant-first cache/file/log keys, and negative matrices are required. | PASS |
| RBAC and auditability | Explicit permissions cover event, form, ticket, order, refund, attendee, credential, and validation work. Required lifecycle, inventory override, financial, attendee-sensitive, credential, callback, and terminal notification events use append-only audit evidence. | PASS |
| Credential security | `contracts/credential-contract.md` fixes Ed25519 signing, version/key ID, canonical payload, expiry, authoritative status, revocation, supersession, rotation, replay-sensitive nonce, and PII-free validation. | PASS |
| Deployment parity | MySQL, database queue, local keys, SMTP, and adapter configuration work in both modes. Network-dependent payment/SMS states degrade explicitly and reconcile without behavior forks. | PASS |
| GCC/KSA and PDPL | SAR and Saudi phone formats are covered; Moyasar and Unifonic are adapter choices. Personal/form/contact data is minimized, encrypted, blind-indexed only where needed, policy-retained, residency-bound, and consent-versioned. | PASS |
| White-label/localization | Trusted host/domain and branding references drive public pages; Arabic/English content, RTL/LTR, timezone, phone, number, and currency behavior is required for web and notifications. | PASS |
| Modularity/adapters | Events, Registration, Ticketing, Orders, Payments, Attendees, Credentials, and Notifications own data/contracts. Payment and notification providers implement stable adapter contracts. | PASS |
| Automated tests | Unit, MySQL concurrency, contract, adapter, E2E, isolation, RBAC, audit atomicity/privacy, credential security, localization/accessibility, performance, and deployment parity suites are release gates. | PASS |
| Phased delivery | The design implements only the `all_plan.md` Phase 1 core. Wallet/scanning/check-in, kiosk/badge, ACS, identity, marketplace, and hardware integrations are forbidden by architecture tests. | PASS |

No constitution exception is required. Live paid/SMS release remains gated on
merchant/sender onboarding, approved secret references, and contract-test
evidence; disabled live adapters do not misrepresent readiness.

## Research Decisions

Detailed decisions and alternatives are in [research.md](research.md):

1. Extend the modular monolith with eight owned Phase 1 modules.
2. Resolve public tenant/event scope from approved host plus event slug.
3. Use immutable form versions and version-bound encrypted submissions.
4. Serialize inventory with short MySQL row-locking transactions and 15-minute
   holds.
5. Store all money in integer minor units.
6. Use a provider-neutral payment contract with fake and Moyasar adapters.
7. Use configured SMTP email and fake/Unifonic SMS adapters.
8. Sign compact PII-free credentials with Ed25519 via Sodium.
9. Require authoritative credential status in addition to signature validity.
10. Encrypt personal data and use HMAC blind indexes for bounded exact lookup.
11. Use durable outbox/intents and after-commit events for external effects.
12. Keep retention/residency policy-driven and compliance-approved.

## Architecture and Module Ownership

### Events

Owns events, event branding references, tiers, schedules, lifecycle, publication
readiness, public host/event resolution, and event summary queries. It consumes
tenant configuration through a Tenancy contract and publishes immutable event
lifecycle facts.

### Registration

Owns form identities, immutable form versions, field/schema validation,
conditional rules, submissions, and consent evidence. It exposes the published
form contract and a submission validator; it never creates orders or attendees
directly.

### Ticketing

Owns ticket types, price tiers, inventory counters, inventory holds, quote
selection, reserve/convert/release actions, and sold-out availability. It
exposes explicit inventory and quote contracts to Orders.

### Orders

Owns orders, order items, buyer snapshots, checkout orchestration, public order
access, cancellation rules, and order queries. It coordinates Registration,
Ticketing, Payments, Attendees, Credentials, and Notifications through their
application contracts, not persistence imports.

### Payments

Owns payment accounts, attempts, webhook receipts, refunds, state mapping,
payment/refund reconciliation, and the `PaymentGateway` adapter contract.
Provider adapters live under Payments infrastructure and use the Phase 0
adapter invocation/telemetry conventions.

### Attendees

Owns attendee records, encrypted contact values, blind indexes, safe organizer
queries, correction history, cancellation, and anonymization. It consumes a
validated submission snapshot but does not query Registration persistence.

### Credentials

Owns signing-key metadata, token format, issuance, validation, revocation,
reissue/supersession, and key health. It exposes a stable validation contract
for later wallet/scanner modules.

### Notifications

Owns immutable notification intents, templates/version, encrypted destinations,
 email/SMS channel contracts, delivery jobs/callbacks, bounded retry, and safe
delivery status. It consumes identifiers and approved view data from events,
orders, attendees, and credentials.

### Existing modules

- Tenancy provides trusted context, active tenant/membership, domain/branding,
  residency, and retention configuration.
- Authorization owns Phase 1 permission catalog, evaluator, policies, and route
  guards.
- Audit provides synchronous audited transactions and listeners.
- Shared provides errors, envelopes, signed cursor, idempotency, encryption
  primitives, clock, correlation, redaction, and public rate-limit helpers.
- Operations provides health/readiness/telemetry for key rings, payment,
  notification, queue, and hold/reconciliation backlog.
- AdminConsole owns organizer/public presentation and explicit view models only.

## Core Execution Flows

### Event publication

```text
authorized organizer
  -> validate event transition and localized schedule
  -> verify trusted brand/domain reference
  -> verify published form and active ticket/inventory
  -> audited transaction: publish event
  -> after-commit EventPublished
  -> public host+slug resolver may expose event
```

### Free registration

```text
trusted public host + event slug + idempotency key
  -> resolve active tenant/event and published form
  -> validate exact form version and consent
  -> lock ticket inventory and select deterministic quote
  -> audited transaction:
       submission + hold + zero-value paid order/item
       convert hold to sold
       attendee + signed credential + notification intent
  -> after-commit delivery job
  -> return one-time order access token and QR credential
```

The token is returned only to the authorized public journey and not stored
verbatim. A failed required write or audit insert rolls the transaction back.

### Paid registration

```text
public registration
  -> audited local transaction:
       submission + inventory hold + pending order/item
  -> create durable payment attempt
  -> invoke payment adapter outside inventory transaction
  -> return provider action

verified callback / browser reconciliation / scheduled reconciliation
  -> fetch authoritative provider payment
  -> match account + order + amount + currency + live mode
  -> lock order, attempt, hold, inventory
  -> audited transaction:
       capture state + paid order + convert hold
       attendee + credential + notification intent
  -> after-commit delivery
```

Failure releases an eligible hold once. Unknown outcome marks reconciliation
and retains the hold according to bounded policy; it never guesses. A confirmed
late capture is reconciled even if the browser abandoned checkout.

### Refund

```text
authorized actor + reason + idempotency
  -> lock order/refund totals and validate refundable balance
  -> persist pending refund intent + audit
  -> call adapter outside transaction
  -> reconcile authoritative result
  -> audited transaction updates refund and order aggregate
```

Phase 1 refund does not automatically reactivate inventory or revoke a
credential; the explicit business policy/action determines credential and
attendance effects and records them separately.

### Credential reissue

```text
authorized actor + reason
  -> lock attendee credential set
  -> validate tenant/event and current status
  -> sign replacement with active key
  -> audited transaction:
       supersede/revoke old + insert new credential
  -> return new QR once
```

## Public Context and Access

- Public endpoints live under `/api/v1/public` and do not use authenticated
  tenant middleware.
- `ResolvePublicEventContext` accepts only the normalized request host and event
  slug, resolves an approved tenant domain or platform fallback mapping, checks
  active tenant and published/open event, binds an immutable limited context,
  and clears it after every request.
- Public order status requires both opaque `public_reference` and a high-entropy
  access token. Only a token hash is stored. Tokens are bound to order/event,
  expire, rotate after suspected compromise, and never authorize organizer
  operations.
- Public responses expose only active form fields, localized public event data,
  coarse ticket availability, immutable price quote, safe order/payment status,
  and the initial credential result.
- Enumeration, form submission, and checkout have host/event/source-aware rate
  limits and uniform not-found behavior.

## Data Protection and Key Management

- `PersonalDataCipher` uses a versioned key ring supplied by secret references.
  Ciphertext records carry key ID and authenticated encryption metadata.
- `BlindIndex` HMACs normalized email/phone with a separate versioned key; key
  rotation supports dual-read/reindex windows without logging source values.
- Credential signing uses a separate Ed25519 key ring. Private keys are never
  stored in MySQL, caches, jobs, audit, logs, or application responses.
- Payment provider keys, webhook secrets, SMTP credentials, and SMS credentials
  are secret references. Per-tenant account records contain mapping metadata
  only.
- QR token digest and nonce digest are stored, not the raw token. Raw QR is
  returned only at initial issue/reissue and rendered only to the authorized
  attendee journey.
- Retention/anonymization jobs run in tenant context, honor legal hold, preserve
  financial/audit referential tombstones, and produce audit summaries without
  personal values.

## API and Contract Strategy

- [contracts/openapi.yaml](contracts/openapi.yaml) is the Phase 1 review
  contract. Implementation merges it into the authoritative
  `specs/001-project-foundation/contracts/openapi.yaml` and generated docs
  without weakening Phase 0 standards.
- Tenant organizer routes require bearer/session authentication,
  `X-Tenant-ID`, active membership, exact permission, idempotency for writes,
  correlation, locale, and bounded rate limits.
- Public routes derive tenant/event context from host and slug, require
  idempotency for writes, and use order access tokens where state is private.
- Unknown fields are rejected on all security, financial, form, attendee, and
  credential writes.
- Collections use signed cursors bound to tenant, event, filters, and search
  blind-index version.
- Stable errors extend the Phase 0 catalog with:
  `event_not_publishable`, `registration_closed`, `ticket_unavailable`,
  `inventory_conflict`, `price_changed`, `payment_action_required`,
  `payment_pending`, `payment_mismatch`, `refund_not_allowed`,
  `credential_invalid`, `credential_expired`, `credential_revoked`,
  `credential_superseded`, and `notification_unavailable`.
- Provider-specific codes/payloads never become API error codes.

## RBAC and Audit Catalog

Permissions:

```text
event.view
event.manage
event.publish
event.cancel
registration.manage
ticketing.manage
order.view
order.manage
payment.refund
attendee.view
attendee.manage
credential.view
credential.validate
credential.revoke
credential.reissue
```

System Tenant Administrator receives these through an idempotent role update.
New Event Manager and Ticketing Manager system-role templates may be seeded
only with documented least-privilege subsets. Custom roles remain empty.

Required audit action families:

- `event.*`: created, updated, publish denied/succeeded, cancelled, archived;
- `registration_form.*`: version created, publish denied/succeeded, retired;
- `ticket.*` and `price_tier.*`: created, changed, retired, conflict;
- `inventory.*`: held, converted, released, expired, override denied/succeeded;
- `registration.*`: accepted, denied, duplicate replay;
- `order.*`: created, paid, failed, cancelled, refund transition;
- `payment.*`: intent, callback verified/denied, reconciled, mismatch, refund;
- `attendee.*`: created, corrected, cancelled, anonymized;
- `credential.*`: issued, validation denied, revoked, reissued, key failure;
- `notification.*`: queued and terminal failure;
- `public_context.*`: suspicious/denied host or event resolution.

High-volume successful availability reads and valid credential checks use
telemetry rather than one audit row each; denied credential validation and
all lifecycle changes remain audited. This distinction is documented in the
audit catalog before implementation.

## Queues, Scheduling, and Recovery

- `DeliverNotificationJob` restores tenant/correlation context and processes one
  notification intent idempotently.
- `ReconcilePaymentAttemptJob` fetches one due pending/unknown payment before
  any retry.
- `ExpireInventoryHoldsJob` processes bounded locked batches, using
  `SKIP LOCKED` only for queue-like job selection, then locks authoritative
  inventory during each release.
- `ReconcileRefundJob`, notification callback processing, key-health checks,
  personal-data re-encryption, and retention/anonymization are bounded and
  observable.
- Security-critical audit remains synchronous; queue failure cannot remove or
  rewrite required evidence.
- Readiness reports safe categories for credential key, personal-data key,
  payment adapter, notification adapter, stale holds, and reconciliation
  backlog. Public readiness exposes aggregate state only.

## Project Structure

### Documentation (this feature)

```text
specs/002-registration-ticketing-credentials/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── openapi.yaml
│   ├── payment-adapter.md
│   ├── notification-adapter.md
│   ├── credential-contract.md
│   └── dashboard-contract.md
└── tasks.md                 # Created later by speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Modules/
│   ├── Events/
│   │   ├── Application/{Actions,Queries}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,ValueObjects}
│   │   ├── Http/{Controllers,Middleware,Requests,Resources}
│   │   ├── Infrastructure/Persistence/Models/
│   │   └── Providers/
│   ├── Registration/
│   │   ├── Application/{Actions,Validation}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Fields}
│   │   ├── Http/{Controllers,Requests,Resources}
│   │   ├── Infrastructure/Persistence/Models/
│   │   └── Providers/
│   ├── Ticketing/
│   │   ├── Application/{Actions,Pricing,Inventory}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,ValueObjects}
│   │   ├── Http/{Controllers,Requests,Resources}
│   │   ├── Infrastructure/Persistence/Models/
│   │   └── Providers/
│   ├── Orders/
│   │   ├── Application/{Actions,Queries}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,ValueObjects}
│   │   ├── Http/{Controllers,Requests,Resources}
│   │   ├── Infrastructure/Persistence/Models/
│   │   └── Providers/
│   ├── Payments/
│   │   ├── Application/{Actions,Jobs,Reconciliation}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Results,ValueObjects}
│   │   ├── Http/Controllers/Webhooks/
│   │   ├── Infrastructure/{Adapters,Persistence}
│   │   ├── Providers/
│   │   └── Testing/
│   ├── Attendees/
│   │   ├── Application/{Actions,Queries}
│   │   ├── Contracts/
│   │   ├── Domain/Events/
│   │   ├── Http/{Controllers,Requests,Resources}
│   │   ├── Infrastructure/Persistence/Models/
│   │   └── Providers/
│   ├── Credentials/
│   │   ├── Application/{Actions,Signing,Validation}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Results,ValueObjects}
│   │   ├── Http/{Controllers,Requests,Resources}
│   │   ├── Infrastructure/Persistence/Models/
│   │   └── Providers/
│   ├── Notifications/
│   │   ├── Application/{Jobs,Rendering}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Results}
│   │   ├── Infrastructure/{Adapters,Persistence}
│   │   ├── Providers/
│   │   └── Testing/
│   └── AdminConsole/
│       ├── Http/Controllers/Tenant/Events/
│       └── ViewModels/Events/
├── Console/Commands/
└── Providers/ModuleServiceProvider.php
config/
├── credentials.php
├── payments.php
├── notifications.php
└── registration.php
database/
├── factories/
├── migrations/
└── seeders/
resources/js/
├── components/{events,registration,ticketing,orders,attendees,credentials}/
├── pages/public/registration/
└── pages/tenant/events/
routes/
├── api.php
└── web.php
tests/
├── Architecture/
├── Contract/{Phase1,Payments,Notifications}/
├── Feature/{Events,Registration,Ticketing,Orders,Attendees,Credentials}/
├── Integration/{MySql,Payments,Queue,Security}/
├── Performance/
├── Unit/{Events,Registration,Ticketing,Orders,Credentials}/
└── Browser/Phase1/
```

**Structure Decision**: Keep one Laravel deployment and React/Inertia frontend,
adding domain-owned modules beside the Phase 0 modules. Controllers remain
thin; application actions coordinate public module contracts; domain objects
own transitions and calculations; infrastructure owns persistence, encryption,
provider HTTP, queues, and mail. No generic repository, microservice, separate
frontend deployment, or provider SDK leaks across module boundaries.

## Migration and Rollback Strategy

Migration order:

1. events and event branding;
2. registration forms and immutable versions;
3. ticket types, inventory, and price tiers;
4. submissions, inventory holds, orders, and order items;
5. payment accounts, attempts, webhook receipts, and refunds;
6. attendees;
7. credential key metadata and credentials;
8. notifications;
9. permission/system-role catalog updates and indexes.

Rules:

- Add tenant/event composite unique keys before dependent composite foreign
  keys.
- Add lifecycle checks, money/counter checks, one-active-credential constraint
  strategy, and tenant-first indexes in the creation migration.
- Production rollout is expand-first: deploy schema/config/readiness, then
  disabled modules and fake/test adapters, validate, enable free registration,
  and enable each live payment/SMS tenant only after onboarding evidence.
- Rollback disables publication/checkout first, drains/reconciles pending
  payments/refunds/notifications, preserves all financial/audit/credential
  evidence, then rolls application behavior back. Production never drops
  populated Phase 1 tables automatically.
- Upgrade tests start from the accepted Phase 0 schema and verify fresh install,
  upgrade, repeatable seeders, key rotation, adapter disabled/enabled profiles,
  backup/restore, and on-premise blocked-network behavior.

## Testing and Documentation Gates

Required tests:

- Unit: event transitions/readiness, form schema/cycles, price selection, money,
  inventory counters, order/payment/refund transitions, canonical credential
  payload/sign/verify, encryption/blind indexes, and error mapping.
- MySQL integration: all composite tenant/event constraints, immutable records,
  final-unit concurrency, hold expiry/late capture, refund bounds, one active
  credential, revocation/reissue concurrency, and audited rollback.
- Feature/API: every OpenAPI success and principal 401/403/404/409/422/429/503
  path, public host resolution, order token, cursor, rate limit, locale, and
  idempotency.
- Adapter contract: fake, Moyasar, SMTP, and Unifonic matrices including
  duplicate, timeout-before-send, unknown outcome, reconciliation, malformed
  response, provider redaction, and test/live separation.
- System: publish event, free registration, paid capture, payment failure,
  callback/browser race, refund, attendee correction, revoke/reissue,
  confirmation failure/recovery, and cancellation with pending payment.
- Security: cross-tenant/event/host/resource matrices across all channels,
  permission allow/deny and immediate revocation, forged callback, amount and
  currency mismatch, token tampering/key states/replay, secret/card/PII leak,
  XSS/custom-form abuse, mass assignment, and public enumeration.
- Frontend/browser: Arabic/English, RTL/LTR, branding, conditional fields,
  checkout summaries, accessibility, keyboard, responsive layout, all required
  states, tenant switching, and zero unauthorized props.
- Performance: 10,000 concurrent inventory attempts without oversell, bounded
  100,000-attendee/order query plans, callback burst deduplication, and backlog
  recovery.
- Deployment parity: native SaaS/on-premise profiles, local free flow, blocked
  outbound network, recovery, key/secret readiness, queue, scheduler, and no
  runtime CDN.

Documentation deliverables:

- Phase 1 API and public-context standards;
- event lifecycle and publication readiness;
- form field/schema authoring and privacy/consent rules;
- ticket inventory/hold/price and financial state runbooks;
- payment onboarding, callback, reconciliation, refund, and outage guide;
- credential format, key ceremony/rotation/compromise/reissue guide;
- notification sender/template/delivery troubleshooting;
- attendee data inventory, encryption, blind-index, retention, anonymization,
  residency, access, and breach-handling guidance;
- organizer/public dashboard design and localization/accessibility rules;
- permission and audit catalog additions;
- migrations, rollback, backup/restore, telemetry/alerts, support, and Phase 1
  readiness evidence.

All existing Composer/npm, backend/frontend, OpenAPI sync/lint/compatibility,
documentation, phase-boundary, dependency audit, migration, and security gates
remain mandatory.

## Post-Design Constitution Re-check

The completed data model and contracts preserve every pre-design PASS:

- composite tenant/event ownership and public host resolution close isolation;
- the API and dashboard contracts expose no persistence bypass;
- financial unknown outcomes and external failures are explicit;
- Ed25519 plus authoritative state provides signing, rotation, revocation, and
  later verifier compatibility;
- encrypted personal data and policy-driven retention satisfy PDPL design
  inputs without inventing legal periods;
- deployment behavior remains one portable core with configured adapters;
- all Phase 2+ capabilities are expressly absent.

**Result**: PASS. No complexity exception or governance waiver is required.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
