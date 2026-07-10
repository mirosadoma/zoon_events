# Phase 1 Research: Registration, Ticketing, Orders, and Credentials

**Date**: 2026-07-03

This research resolves the technical choices needed by the Phase 1 plan. The
accepted Phase 0 architecture remains authoritative unless a decision below
explicitly extends it.

## Decision 1: Extend the modular monolith

**Decision**: Add Events, Registration, Ticketing, Orders, Payments, Attendees,
Credentials, and Notifications as owned modules in the existing application.
Tenant workforce users are organizers; Phase 1 does not create a separate
login or tenant model for an "organizer."

**Rationale**: This follows the constitution and reuses trusted tenant context,
RBAC, audit, idempotency, queues, telemetry, and adapter invocation. Each module
owns its persistence and exposes application contracts or immutable events.

**Alternatives considered**:

- Separate ticketing and payment services: rejected because Phase 1 has no
  measured scale or ownership boundary that justifies distributed transactions.
- One large Events module: rejected because inventory, money, credentials, and
  notifications have distinct invariants and future consumers.

## Decision 2: Resolve public tenant context from host and event slug

**Decision**: Public registration routes derive tenant and event scope from an
approved host mapping plus an event slug. A platform fallback host may use a
tenant slug and event slug. Public requests never accept `X-Tenant-ID` as
authority.

**Rationale**: Attendees are unauthenticated, so a client-supplied tenant header
cannot be trusted. Host mappings already belong to governed tenant
configuration. The resolved event must be published, the tenant active, and
registration open before any form or inventory data is exposed.

**Alternatives considered**:

- Tenant header on public routes: rejected as forgeable.
- Globally unique event slug: rejected because it leaks namespace across tenants
  and weakens white-label ownership.

## Decision 3: Use immutable form versions and schema-bound submissions

**Decision**: Store a registration form identity separately from immutable
published versions. Each version contains bounded field definitions and
validation rules. Every submission records the exact version and stores
encrypted answers plus normalized consent evidence.

**Rationale**: Historical submissions must remain interpretable after an
organizer edits a form. Versioning supports deterministic validation and audit
without copying mutable form state into application code.

**Alternatives considered**:

- Mutable form JSON: rejected because old submissions would change meaning.
- One relational table per custom field: rejected because it creates unbounded
  schema churn and complex conditional-field joins.

## Decision 4: Serialize inventory changes with row locks

**Decision**: Maintain one inventory record per ticket type and reserve units
inside a short transaction using an InnoDB locking read. Create a 15-minute
hold, update counters, and create the order atomically. Expiry and reconciliation
jobs release holds idempotently. Do not use `SKIP LOCKED` for sales.

**Rationale**: MySQL locking reads hold examined rows until transaction end;
this gives a portable oversell guard without Redis or a distributed lock.
MySQL warns that `SKIP LOCKED` returns an inconsistent view, so it is suitable
for queue-like work, not authoritative inventory sales. See the
[MySQL 8.4 locking-read documentation](https://dev.mysql.com/doc/refman/8.4/en/select.html).

**Alternatives considered**:

- Cache/distributed locks: rejected because they add a second authority and
  weaken on-premise parity.
- Optimistic retries only: rejected because high contention on the last units
  creates avoidable failures and more complex reconciliation.

## Decision 5: Store all monetary values in minor units

**Decision**: Store subtotal, tax, fees, discounts, totals, captures, and refunds
as signed 64-bit integer minor units with an ISO 4217 currency. Snapshot every
order-item amount and price-tier identity. One order has one currency.

**Rationale**: Integer arithmetic avoids rounding drift and gives deterministic
amount matching across callbacks and refunds. SAR is mandatory; additional
currencies require explicit configuration and tests.

**Alternatives considered**:

- Floating point: rejected for financial calculations.
- Provider-formatted decimal strings as authority: rejected because formatting
  and scale differ by provider and locale.

## Decision 6: Moyasar is the first KSA payment adapter

**Decision**: Define a provider-neutral `PaymentGateway` contract and implement
fake and Moyasar adapters. Moyasar is the initial production candidate, subject
to merchant onboarding and finance/legal approval before release. The adapter
supports create/fetch/capture/void/refund and maps provider states to stable
domain outcomes.

**Rationale**: Moyasar's current official API documents creation, authoritative
fetch, capture, void, full/partial refund, statuses, and idempotency. Its
webhooks cover paid, failed, refunded, voided, authorized, captured, and
verified events. See [Payments API](https://docs.moyasar.com/category/payments-api)
and [webhook reference](https://docs.moyasar.com/api/other/webhooks/webhook-reference).

**Security and reliability rules**:

- Each tenant payment account stores only a secret reference and non-secret
  account metadata.
- Incoming webhook event IDs are deduplicated.
- The configured webhook secret is checked, but no financial state changes
  solely from the callback body; the adapter fetches the authoritative payment
  and matches tenant account, order reference, amount, currency, and live mode.
- Callback acknowledgement is quick; durable processing is idempotent and
  reconciliation repairs missed callbacks.

**Alternatives considered**:

- HyperPay, Amazon Payment Services, and Tap: viable future adapters, but not
  selected for the first contract implementation.
- Provider SDK in order logic: rejected because it couples core states and error
  semantics to one vendor.

## Decision 7: SMTP email and Unifonic SMS behind channel contracts

**Decision**: Use the existing mail transport boundary for email and add a
provider-neutral SMS sender with fake and Unifonic adapters. Notifications are
outbox records queued after the registration transaction commits.

**Rationale**: SMTP keeps email portable in SaaS and on-premise deployments.
Unifonic provides GCC-oriented programmable SMS, supports Unicode content,
international Saudi phone format, correlation IDs, asynchronous sending, and
delivery callbacks. See the [Unifonic SMS send contract](https://unifonic.readme.io/reference/sendmessage).

**Alternatives considered**:

- Send synchronously in checkout: rejected because provider latency or outage
  must not undo a valid order and credential.
- One combined omnichannel provider contract: rejected because email and SMS
  have different delivery, sender, payload, and fallback semantics.

## Decision 8: Sign compact opaque QR credentials with Ed25519

**Decision**: Use a compact versioned token:
`zt1.<key-id>.<base64url-canonical-payload>.<base64url-signature>`. The payload
contains only credential, event, ticket-type, issued-at, expiry, and nonce
identifiers. Sign and verify with Ed25519 using the PHP Sodium extension.

**Rationale**: Ed25519 supports detached public-key signatures, letting later
scanner and on-premise clients verify integrity without receiving a signing
secret. PHP exposes detached signing and verification directly; see the
[Sodium signing documentation](https://www.php.net/manual/en/function.sodium-crypto-sign-detached.php).
An explicit format version and key ID support evolution and rotation.

**Alternatives considered**:

- HMAC credentials: rejected because every offline verifier would need the
  signing secret.
- Encrypt personal data into QR: rejected because the QR does not need personal
  data and minimization is safer than encryption of unnecessary content.
- General-purpose JWT claims: rejected for Phase 1 because the required compact
  credential contract is smaller and avoids ambiguous optional claims.

## Decision 9: Authoritative status defeats replay and stale QR use

**Decision**: Signature verification proves integrity only. Online validation
must also load the tenant/event-scoped credential and check active status,
expiry, key state, supersession, and a random nonce. Revocation or reissue
updates authoritative state transactionally. Phase 1 does not record entry or
anti-passback scans.

**Rationale**: A correctly signed QR can still be revoked or superseded. This
contract gives Phase 2 wallet/scanner clients stable validation semantics
without prematurely implementing check-in.

**Alternatives considered**:

- Signature-only acceptance: rejected because revocation would not work.
- One-time consumption: rejected because entry/check-in rules belong to Phase 2.

## Decision 10: Encrypt attendee data and use blind indexes

**Decision**: Encrypt attendee contact fields, custom answers, buyer contact
fields, and notification destinations with a versioned application key ring.
Store HMAC blind indexes of normalized email and phone only where exact search
or duplicate detection is required. Never store card data, payment secrets, or
provider payloads wholesale.

**Rationale**: This satisfies at-rest protection and data minimization while
retaining bounded organizer lookup. Key IDs permit rotation. Audit and telemetry
store identifiers and redacted change markers rather than personal values.

**Alternatives considered**:

- Plaintext searchable fields: rejected for confidential attendee data.
- General encrypted full-text search: rejected as unnecessary Phase 1
  complexity; organizer search is exact/prefix-limited through safe indexes and
  non-sensitive status filters.

## Decision 11: Use an outbox for external effects and after-commit events

**Decision**: Payment reconciliation and notification delivery use durable,
tenant-scoped intent/outbox records. Required state and audit evidence commit
together; provider calls occur outside long database transactions. Immutable
events dispatch after commit, and workers restore tenant/correlation context.

**Rationale**: External calls cannot participate in the database transaction.
Durable intents make retries and unknown outcomes explicit while preserving the
Phase 0 transaction and audit guarantees.

**Alternatives considered**:

- Provider calls inside transactions: rejected because network timeouts would
  hold inventory locks and still could not guarantee atomicity.
- Fire-and-forget events without durable state: rejected because work could be
  lost between commit and delivery.

## Decision 12: Retention is policy-driven, not hard-coded

**Decision**: Classify registration answers and attendee contact data as
confidential personal data; order/payment references as confidential financial
records; credentials as confidential security records; consent and audit as
compliance evidence. Tenant-approved residency and retention policies determine
anonymization, deletion, and legal hold. Defaults cannot be enabled in
production until compliance owners approve them.

**Rationale**: The project does not yet have approved legal durations. Encoding
an invented period would violate the constitution. Referential tombstones keep
financial and audit integrity after eligible personal data is anonymized.

**Alternatives considered**:

- One global fixed retention period: rejected because event purpose, contract,
  deployment, and legal basis vary.
- Delete complete orders with attendees: rejected because it can destroy
  required financial and audit evidence.

## Resolved Unknowns

No unresolved clarification markers remain. Merchant onboarding, production keys,
sender approval, and final retention durations are release-readiness inputs,
not unresolved design choices; fake adapters and synthetic data cover local and
automated validation.
