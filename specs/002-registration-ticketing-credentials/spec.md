# Feature Specification: Registration, Ticketing, Orders, and Credentials

**Feature Branch**: `002-registration-ticketing-credentials`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: "Phase 1 from all_plan.md"

**Product Phase**: Phase 1 Registration-Ticketing-Credentials

**Deployment Modes**: SaaS and on-premise

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Launch a Branded Event (Priority: P1)

An authorized organizer creates an event, selects its corporate, public, VIP,
or VVIP tier, configures dates, capacity, location, branding, registration
fields, ticket types, and scheduled price tiers, and publishes a bilingual
registration experience on the organizer's approved domain.

**Why this priority**: Every registration, order, attendee, and credential
belongs to a published event. This is the smallest organizer-owned setup that
unlocks the rest of Phase 1.

**Independent Test**: An organizer can configure and publish one free event,
open its public registration page, and verify that the correct tenant branding,
language, dates, fields, and ticket availability are shown.

**Acceptance Scenarios**:

1. **Given** an authorized organizer in an active tenant, **When** the organizer creates a valid draft event, **Then** the event is owned by that tenant and begins in draft status.
2. **Given** a draft event, **When** the organizer selects an event tier, **Then** the tier's documented defaults are applied without preventing authorized customization.
3. **Given** an event missing a required date, active registration form, or ticket type, **When** publication is requested, **Then** publication is rejected with a clear list of missing requirements.
4. **Given** a fully configured event, **When** the organizer publishes it, **Then** its branded Arabic and English registration pages become available only through approved domains.
5. **Given** an event owned by another tenant, **When** an organizer attempts to view or change it, **Then** the request is denied without revealing whether the event exists.

---

### User Story 2 - Complete Free Self-Registration (Priority: P1)

An attendee opens a branded registration link, chooses an available free ticket
type, completes the configured form and required consent, and receives a unique
signed QR credential and confirmation without staff assistance.

**Why this priority**: Free self-registration proves the core attendee journey
without depending on an external payment service and is the first independently
releasable product slice.

**Independent Test**: A new attendee can submit a valid free registration,
after which one attendee, one completed order, and one active credential exist
and a confirmation is queued exactly once.

**Acceptance Scenarios**:

1. **Given** a published event with open registration and available free inventory, **When** an attendee submits valid required fields and consent, **Then** one attendee and one completed zero-value order are created and inventory decreases once.
2. **Given** an invalid, incomplete, or conditionally inconsistent form, **When** it is submitted, **Then** no attendee, order, inventory change, or credential is committed.
3. **Given** a successful free registration, **When** processing completes, **Then** one active signed credential is issued and the attendee receives a localized confirmation.
4. **Given** the same submission is safely retried, **When** the retry is recognized as the same intent, **Then** the original result is returned without duplicate attendees, orders, inventory changes, credentials, or notifications.

---

### User Story 3 - Purchase a Paid Ticket (Priority: P1)

An attendee selects an available paid ticket, reviews the final amount in the
event currency, pays through the configured regional payment service, and
receives a credential only after payment is authoritatively confirmed.

**Why this priority**: Paid ticketing is part of the v1 anchor and must preserve
inventory and financial correctness across retries, delays, and uncertain
payment outcomes.

**Independent Test**: A paid registration can be driven through successful,
failed, delayed, duplicated, and unknown payment outcomes while proving that
money, order state, inventory, attendee state, and credential issuance remain
consistent.

**Acceptance Scenarios**:

1. **Given** an available paid ticket, **When** checkout begins, **Then** the attendee sees an immutable price summary containing subtotal, taxes, fees, total, and currency before payment authorization.
2. **Given** a confirmed successful payment, **When** the confirmation is processed, **Then** the order becomes paid and exactly one attendee and active credential are created.
3. **Given** a failed or cancelled payment, **When** the outcome is processed, **Then** no active credential is issued and reserved inventory is released according to the documented hold policy.
4. **Given** a duplicated payment confirmation, **When** it is processed repeatedly, **Then** the order is charged and completed only once.
5. **Given** an unknown or delayed payment outcome, **When** the status cannot be proven, **Then** the order remains pending, no credential is issued, and reconciliation can safely resume later.

---

### User Story 4 - Control Ticket Inventory and Scheduled Pricing (Priority: P1)

An organizer defines ticket types, allocations, sale windows, and scheduled or
capacity-threshold price tiers while the system prevents overselling and always
applies one deterministic price.

**Why this priority**: Inventory and pricing correctness protect revenue,
attendee trust, and the viability of concurrent public sales.

**Independent Test**: Concurrent buyers compete for the final available units,
with no overselling, and boundary-time and boundary-capacity scenarios always
select the same documented price tier.

**Acceptance Scenarios**:

1. **Given** limited ticket inventory, **When** more attendees attempt to reserve the final units than remain, **Then** successful reservations never exceed the configured allocation.
2. **Given** a ticket sale outside its sale window, **When** registration is attempted, **Then** the ticket cannot be reserved or purchased.
3. **Given** overlapping or ambiguous price tiers, **When** the organizer attempts to activate them, **Then** activation is rejected with an actionable conflict.
4. **Given** a scheduled tier boundary, **When** pricing is evaluated, **Then** one deterministic tier and price are selected using the event's configured timezone.
5. **Given** exhausted inventory, **When** the registration page is viewed or submitted, **Then** the ticket is shown as sold out and cannot be oversold.

---

### User Story 5 - Manage Attendees and Orders (Priority: P2)

An authorized organizer searches and reviews the event's attendees and orders,
understands payment and registration status, corrects permitted attendee
details, cancels eligible orders, and initiates supported refunds.

**Why this priority**: Organizers need operational control after registrations
start, but this journey builds on the P1 purchase and registration flows.

**Independent Test**: An authorized event manager can find a synthetic
attendee, inspect the related order and credential, perform each allowed
change, and see complete audit evidence; an unauthorized or cross-tenant actor
sees no data.

**Acceptance Scenarios**:

1. **Given** registrations across two tenants, **When** an organizer searches attendees or orders, **Then** only records from the trusted tenant and authorized event are returned.
2. **Given** an attendee correction that does not alter financial or credential identity, **When** an authorized organizer submits it with a reason, **Then** the change is applied and audited without exposing historical sensitive values.
3. **Given** a paid order eligible for refund, **When** an authorized organizer requests a supported full or partial refund, **Then** the order reflects the confirmed outcome and repeated requests cannot refund more than the captured amount.
4. **Given** a refund service outage or unknown result, **When** a refund is attempted, **Then** the financial state is not guessed and the operation is left safely reconcilable.

---

### User Story 6 - Revoke and Reissue Credentials (Priority: P2)

An authorized organizer revokes a compromised or invalid credential or reissues
it to the same attendee, ensuring the old QR immediately stops validating and
the replacement is unique and traceable.

**Why this priority**: Credential lifecycle control is required before later
wallet and scanning phases can safely consume credentials.

**Independent Test**: Validate an active credential, revoke it, prove it is
rejected, reissue it, and prove only the new credential validates while both
actions retain complete audit evidence.

**Acceptance Scenarios**:

1. **Given** an active credential, **When** an authorized organizer revokes it with a reason, **Then** all future validations reject it as revoked.
2. **Given** an active or revoked credential, **When** it is reissued, **Then** the old credential is revoked and a new uniquely signed credential is issued in one indivisible business operation.
3. **Given** an expired, revoked, malformed, tampered, replayed, or unknown-key credential, **When** validation is requested, **Then** it is rejected with a stable reason that exposes no attendee personal data.
4. **Given** another tenant's credential identifier, **When** an organizer attempts revocation or reissue, **Then** the response is indistinguishable from a random unknown identifier.

---

### User Story 7 - Receive Reliable Localized Confirmations (Priority: P3)

An attendee receives an Arabic or English email and, when enabled, SMS
confirmation containing event details and a safe route to the issued QR
credential, while delivery failures remain visible and retryable to authorized
staff.

**Why this priority**: Confirmation completes the attendee experience but
registration and credential correctness must not depend on one messaging
provider being available.

**Independent Test**: Successful registration queues one localized confirmation
through configured channels, safely retries temporary failures, and never
duplicates the order or credential.

**Acceptance Scenarios**:

1. **Given** an attendee's supported locale and configured event sender identity, **When** registration completes, **Then** the confirmation uses the correct language, direction, branding, dates, times, and currency.
2. **Given** a temporary notification outage, **When** delivery fails, **Then** the completed registration remains valid and delivery retries are bounded and observable.
3. **Given** a permanent delivery failure, **When** retry limits are reached, **Then** authorized staff see a safe failure category without message secrets or provider payloads.

### Edge Cases

- Registration closes, the event is cancelled, or the tenant is suspended while
  an attendee has an active inventory hold or pending payment.
- Two attendees attempt to purchase the final ticket at the same instant.
- The event timezone changes after scheduled price tiers or confirmations exist.
- A form version changes while an attendee is completing an older version.
- An attendee refreshes, double-clicks, or repeats a callback after a successful
  registration or payment.
- A payment succeeds externally after the local order was marked timed out or
  cancelled; reconciliation must prevent both lost payment and duplicate issue.
- A refund is repeated, partially succeeds, or returns an unknown result.
- An attendee email or phone already exists for the same event under a different
  ticket or incomplete order.
- A tenant custom domain, brand asset, translated label, or sender identity is
  invalid, unavailable, or no longer approved.
- A credential expires exactly at the validation boundary, is signed by a
  retired key, or is reissued concurrently by two authorized actors.
- Audit persistence fails during an event, inventory, order, payment, attendee,
  refund, revocation, or reissue change.
- A payment or notification service is unavailable in SaaS or on-premise mode.
- An actor lacks the exact event, ticketing, order, payment-refund, attendee, or
  credential permission required for the requested action.
- Background work, cached values, exports, logs, metrics, files, and integration
  calls attempt to operate without trusted tenant and event scope.

## Requirements *(mandatory)*

### Functional Requirements

#### Event and Publication

- **FR-001**: Authorized organizers MUST be able to create tenant-owned events with a unique tenant-scoped slug, name, description, tier, capacity, location, timezone, start and end times, and registration window.
- **FR-002**: Events MUST support draft, configured, published, registration-open, registration-closed, live, completed, cancelled, and archived lifecycle states with explicit allowed transitions.
- **FR-003**: Event tiers MUST include corporate, public, VIP, and VVIP and MUST apply documented defaults without silently enabling later-phase identity, wallet, scanning, kiosk, or access-control capabilities.
- **FR-004**: Organizers MUST be able to configure event-specific branding and localized public content using only tenant-approved domains and brand references.
- **FR-005**: Publication MUST require a valid event schedule, active registration form version, at least one active ticket type, valid branding, and all mandatory policy notices.
- **FR-006**: Publication, cancellation, reopening, and archival MUST require explicit permissions and a recorded reason where the change affects attendees.

#### Registration Forms

- **FR-007**: Organizers MUST be able to define versioned registration forms with text, email, phone, number, date, dropdown, multi-select, checkbox, hidden, and consent fields.
- **FR-008**: Form fields MUST support localized labels and help text, required or optional state, display order, bounded validation rules, conditional visibility, and public or internal-only visibility.
- **FR-009**: A published form version MUST remain immutable for historical submissions; changes MUST create a new version.
- **FR-010**: Registration submissions MUST be validated against the exact form version presented to the attendee.
- **FR-011**: File uploads and arbitrary executable form logic MUST NOT be available in Phase 1.
- **FR-012**: Required privacy, terms, and marketing consent choices MUST be explicit, separately recorded, and never inferred from form submission.

#### Ticket Types, Inventory, and Pricing

- **FR-013**: Organizers MUST be able to define tenant- and event-owned ticket types with name, localized description, attendee type, capacity, price, currency, sale window, and lifecycle status.
- **FR-014**: Ticket inventory MUST distinguish total allocation, active holds, completed sales, released units, and remaining availability.
- **FR-015**: Inventory reservation and sale MUST be atomic and MUST prevent overselling under concurrent demand.
- **FR-016**: Inventory holds MUST have a bounded expiry and MUST be released after payment failure, cancellation, or timeout unless an authoritative successful payment requires reconciliation.
- **FR-017**: Organizers MUST be able to configure scheduled and capacity-threshold price tiers with a deterministic priority.
- **FR-018**: Active price tiers MUST be non-ambiguous; conflicting windows, thresholds, currencies, or priorities MUST be rejected before sales open.
- **FR-019**: The price quoted at checkout MUST remain immutable for the lifetime of its valid inventory hold.
- **FR-020**: The attendee MUST see sold-out, not-yet-on-sale, sale-ended, and unavailable states without being able to bypass them.
- **FR-021**: Waitlist enrollment, demand-based dynamic pricing, invite codes, host allocations, approvals, and group registration MUST NOT be delivered in Phase 1.

#### Registration, Orders, and Payments

- **FR-022**: A registration MUST create a tenant- and event-owned attendee linked to the selected ticket type and the exact submitted form version.
- **FR-023**: Every registration MUST produce an order, including free registrations, with immutable line-item price, tax, fee, total, and currency snapshots.
- **FR-024**: Orders MUST support draft, pending-payment, paid, failed, cancelled, refunded, and partially-refunded states with explicit transition rules.
- **FR-025**: Payment outcomes MUST support pending, authorized, captured, failed, cancelled, refunded, partially-refunded, and unknown states.
- **FR-026**: Paid orders MUST use a provider-neutral payment boundary that supports creating payment intent, confirming authoritative outcomes, checking status, and requesting refunds.
- **FR-027**: An active credential MUST NOT be issued for a paid order until captured payment is authoritatively confirmed.
- **FR-028**: Payment callbacks and reconciliation MUST be authenticated, tenant-mapped, idempotent, order-bound, amount-bound, and safe under duplicate or out-of-order delivery.
- **FR-029**: Unknown payment and refund outcomes MUST remain pending reconciliation; the system MUST NOT infer success or failure.
- **FR-030**: Refunds MUST require explicit permission and reason, MUST NOT exceed captured funds, and MUST preserve the original financial record.
- **FR-031**: Repeated registration, checkout, payment confirmation, refund, and reconciliation intents MUST not create duplicate financial or credential effects.
- **FR-032**: Buyer and attendee views MUST display localized totals and statuses without exposing payment credentials or provider secrets.

#### Attendees and Credentials

- **FR-033**: Authorized organizers MUST be able to search and view attendees and orders using bounded, tenant- and event-scoped filters.
- **FR-034**: Authorized corrections to attendee data MUST preserve submission history, require a reason for sensitive changes, and never rewrite financial history.
- **FR-035**: Each successfully completed attendee registration MUST receive exactly one active credential unless a later authorized reissue supersedes it.
- **FR-036**: A credential MUST contain a unique credential identifier, event identifier, attendee reference, ticket type reference, issue time, expiry time, key identifier, and signature.
- **FR-037**: The QR payload MUST contain no attendee name, email, phone, national identifier, payment data, or other direct personal data.
- **FR-038**: Credential validation MUST check signature integrity, key status, event ownership, lifecycle status, expiry, revocation, supersession, and replay-sensitive validation context.
- **FR-039**: Credential validation MUST return stable accepted or rejected categories suitable for later wallet and scanning consumers without returning attendee personal data.
- **FR-040**: Credential revocation MUST require permission and reason and MUST take effect immediately for subsequent validation.
- **FR-041**: Credential reissue MUST revoke or supersede the old credential and issue a new unique credential as one indivisible business operation.
- **FR-042**: Credential signing keys MUST support identification, activation, rotation, retirement, and verification of credentials issued under still-trusted historical keys.

#### Notifications and Operations

- **FR-043**: Successful free or paid registration MUST queue one localized confirmation containing safe event, order, and credential-access information.
- **FR-044**: Email and SMS delivery MUST use provider-neutral boundaries with explicit tenant sender identity, timeout, retry, idempotency, failure classification, and delivery status.
- **FR-045**: Notification failure MUST NOT roll back a valid paid order or issued credential; it MUST remain safely retryable and visible to authorized staff.
- **FR-046**: Organizers MUST be able to inspect aggregate registration, inventory, order, payment, credential, and notification states for events they are authorized to manage.
- **FR-047**: All public and organizer-facing collection results MUST be bounded and deterministic.
- **FR-048**: All mutating operations MUST define retry-safe intent and return consistent conflict outcomes for duplicate, stale, or concurrent changes.

### Constitutional Requirements *(mandatory)*

- **CR-001 Tenant Scope**: Events, form versions, submissions, ticket types, price tiers, inventory, holds, orders, line items, payment records, attendees, credentials, and notifications are tenant-owned and event-scoped. Every query, uniqueness rule, cache key, file, job, event, export, log, metric, and adapter call MUST carry trusted tenant scope. Event scope MUST be validated within that tenant. Missing scope fails closed and never implies platform access.
- **CR-002 RBAC**: Phase 1 MUST add least-privilege permissions for event create/update/publish/cancel, registration-form management, ticketing management, order viewing/management, refund initiation, attendee viewing/management, and credential issue/revoke/reissue. Tenant Administrator receives governed system-role grants; custom roles receive none automatically. Staff and attendee-facing flows MUST not gain organizer privileges, and privileged overrides require separate permission, reason, and audit evidence.
- **CR-003 Auditability**: Event lifecycle changes, form publication, ticket and price changes, inventory overrides, registration outcomes, order and payment transitions, callback denial, reconciliation, refunds, attendee-sensitive corrections, credential issue/revoke/reissue/validation denial, and notification terminal failure MUST record actor type and identifier, tenant, event, action, target, outcome, reason code, correlation, channel, timestamp, and sanitized change summary. Required business changes and their audit evidence MUST commit or fail together.
- **CR-004 Credential Security**: Credentials MUST be uniquely identifiable, signed with approved versioned keys, expiry-aware, immediately revocable, supersession-aware, and resistant to tampering and replay. Verification MUST use the credential key identifier, accept only trusted key states, avoid timing-sensitive comparison leaks, and consult authoritative status. QR payloads contain opaque references, never personal or payment data.
- **CR-005 Data and PDPL**: Attendee identity/contact data, registration answers, consent evidence, order details, and payment references are confidential personal or financial-adjacent data collected only for event registration, fulfillment, support, compliance, and fraud prevention. Forms MUST minimize collection by tier and purpose. Tenant-configured retention and residency rules apply; deletion or anonymization MUST preserve legally required financial and audit evidence. Marketing consent is optional and separate. National identity, biometric, health, and raw payment-card data are outside Phase 1.
- **CR-006 API and Integrations**: Event, registration, ticketing, order, attendee, credential, and organizer operations MUST have documented versioned contracts before implementation. Public payment and notification dependencies MUST use provider-neutral contracts with authentication, tenant mapping, idempotency, timeout, retry, reconciliation, failure mapping, observability, sandbox testing, and production-readiness evidence. Provider payloads and codes MUST not escape as public contracts.
- **CR-007 White-Label and Localization**: Public registration, checkout, confirmation, validation, and organizer management experiences MUST support Arabic/RTL and English/LTR with equivalent validation and accessibility. Tenant-approved domains, brand assets, sender identity, localized content, event timezone, phone formats, dates, numbers, and currencies MUST render correctly without tenant-specific code forks.
- **CR-008 Deployment Parity**: Core event, registration, ticketing, order, attendee, credential, authorization, and audit behavior MUST be equivalent in SaaS and supported on-premise deployments. Free registration and credential lifecycle MUST remain locally operable. Paid checkout and outbound notifications MUST report explicit degraded or unavailable states when their configured services are unreachable, preserve pending work, and recover without duplicate effects. No silent fallback to an unapproved provider is allowed.
- **CR-009 Automated Verification**: Required verification includes event lifecycle and publication tests; form version and validation tests; inventory and price-tier unit tests; concurrent final-ticket tests; free and paid end-to-end registration; payment success, failure, duplicate, delayed, unknown, and reconciliation tests; refund bounds; attendee privacy; credential signing, rotation, expiry, tamper, revoke, reissue, and replay tests; notification contract tests; Arabic/English and white-label tests; cross-tenant and cross-event denial; complete RBAC matrices; audit atomicity/privacy; contract compatibility; and SaaS/on-premise parity.
- **CR-010 Phase Alignment**: This phase delivers the first product core defined by `all_plan.md`: organizer event creation, event tiers and branding, configurable self-registration, ticket types, scheduled pricing, orders, payment boundary, attendees, signed credential issue/revoke/reissue, confirmations, audit, isolation, and RBAC. It depends on the accepted Phase 0 tenant context, authorization, audit, idempotency, queue, telemetry, adapter, configuration, localization, and contract foundations. Wallet passes, QR scanning/check-in, kiosks/badges, ACS, identity verification, marketplace, and production hardware adapters remain later-phase work.

### Key Entities

- **Event**: A tenant-owned organizer event with tier, lifecycle, schedule, timezone, location, capacity, branding, registration window, and publication state.
- **Registration Form**: The organizer-defined form for one event, with immutable published versions.
- **Form Field**: A localized field definition with type, validation, visibility, ordering, and consent semantics.
- **Registration Submission**: The immutable attendee answers and consent evidence captured against one form version.
- **Ticket Type**: An event-specific attendance product with allocation, price, currency, sale window, attendee type, and status.
- **Price Tier**: A scheduled or capacity-threshold price rule for one ticket type.
- **Inventory Hold**: A time-bounded reservation of ticket quantity for one checkout intent.
- **Order**: The buyer's financial and fulfillment transaction for one event.
- **Order Item**: An immutable ticket, quantity, attendee, and price snapshot within an order.
- **Payment Attempt**: A provider-neutral record of payment intent, authoritative outcomes, reconciliation, and refund totals without raw payment credentials.
- **Attendee**: The person registered for an event, linked to a ticket type, order, submission, consent, and credential lifecycle.
- **Credential**: A unique signed event credential with expiry, status, key identifier, revocation, and supersession relationships.
- **Notification**: A localized confirmation delivery intent and its safe channel/status history.
- **Event Branding**: Event-specific public presentation derived from tenant-approved brand and domain configuration.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A trained organizer can create, configure, and publish a valid free event in under 30 minutes without engineering assistance.
- **SC-002**: At least 90% of test participants complete a valid free self-registration on their first attempt in under 3 minutes.
- **SC-003**: At least 90% of test participants complete a valid paid registration on their first attempt in under 5 minutes, excluding time spent in an external payment challenge.
- **SC-004**: Across 10,000 concurrent attempts for limited inventory, completed sales never exceed the configured allocation and no paid attendee is left without exactly one active credential.
- **SC-005**: Scheduled and capacity-based price selection is correct in 100% of tested timezone, boundary-time, and boundary-capacity cases.
- **SC-006**: Duplicate submissions, callbacks, refunds, and retries produce zero duplicate orders, captures, inventory decrements, attendees, active credentials, or confirmation intents.
- **SC-007**: Credential revocation and reissue affect validation within 5 seconds, and the superseded QR is rejected in 100% of subsequent validation attempts.
- **SC-008**: Tampered, expired, revoked, superseded, replayed, and unknown-key credentials are rejected in 100% of security test cases without personal-data disclosure.
- **SC-009**: Cross-tenant and cross-event access attempts are denied in 100% of automated request, background-work, cache, file, export, log, and integration tests.
- **SC-010**: Every published Phase 1 permission has at least one allowed and one denied automated scenario, with immediate revocation verified.
- **SC-011**: Every required event, financial, attendee, credential, and notification action produces complete sanitized audit evidence, and forced audit failure leaves zero partial required state changes.
- **SC-012**: Arabic and English registration, checkout, confirmation, validation, and organizer journeys pass equivalent functional, accessibility, and visual-direction checks.
- **SC-013**: Temporary payment or notification outages cause zero lost confirmed payments and zero duplicate credentials; pending work is recoverable after service restoration.
- **SC-014**: Organizer acceptance testing reports at least 90% satisfaction for event setup, attendee search, order status clarity, and credential lifecycle management.

## Assumptions

- Phase 0 is complete and its tenant isolation, RBAC, audit, idempotency,
  localization, health, telemetry, queue, configuration, and adapter contracts
  are available and remain mandatory.
- `all_plan.md` is authoritative where it differs from the broader PRD:
  Apple/Google Wallet passes and QR scanning/check-in begin in Phase 2.
- The organizer is merchant of record for paid ticketing. Settlement,
  chargebacks, tax invoicing, and marketplace payouts beyond recording the
  confirmed ticket payment/refund outcome are outside this phase.
- The specific KSA payment and messaging providers are selected during planning
  and must satisfy the provider-neutral contracts and production-readiness
  evidence before paid or outbound messaging capabilities are released.
- SAR is required; additional currencies may be enabled only when their payment
  and refund behavior is validated. One order uses one currency.
- Phase 1 supports one attendee per order item. Group/bulk registration,
  invite-only allocations, host approvals, and waitlists are later work.
- Checkout requires online connectivity. On-premise deployments may configure
  approved reachable payment and notification services; when unavailable,
  free registration and credential management continue while affected paid or
  notification work remains explicitly degraded and recoverable.
- Event-specific retention uses the tenant's approved retention and residency
  policy. Final legal retention durations are configuration and governance
  inputs, not hard-coded product behavior.
- Phase 1 excludes file-upload form fields, national identity and biometric
  collection, wallet pass generation, scanning/check-in, anti-passback,
  kiosks, badges, ACS, venue marketplace, dynamic demand pricing, public event
  discovery, and production hardware adapters.
