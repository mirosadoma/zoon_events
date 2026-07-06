# Feature Specification: Wallet Passes and QR Scanning

**Feature Branch**: `003-wallet-passes-scanning`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "Phase 2 from all_plan.md"

**Product Phase**: Phase 2 Wallet-Passes-And-Scanning

**Deployment Modes**: SaaS and on-premise

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Add Event Credential to Apple or Google Wallet (Priority: P1)

An attendee who has completed registration and holds an active signed
credential can add a wallet pass to Apple Wallet or Google Wallet directly
from their confirmation, containing the same QR credential already issued in
Phase 1, without re-entering any personal data.

**Why this priority**: The wallet pass is the primary convenience upgrade
attendees expect after registration and is the entry point for the rest of
this phase's on-site value; it must work before staff scanning has anything
useful to validate at scale.

**Independent Test**: A completed free or paid registration with an active
credential can generate a valid Apple Wallet pass and a valid Google Wallet
pass, each opening successfully in its respective wallet application and
displaying the correct event, ticket, and QR information.

**Acceptance Scenarios**:

1. **Given** an attendee with an active credential, **When** the attendee opens their confirmation or order status page, **Then** an "Add to Apple Wallet" link and an "Add to Google Wallet" link are both available.
2. **Given** an attendee taps "Add to Apple Wallet" on a supported device, **When** the pass is generated, **Then** the pass contains the event name, date, location, attendee name, ticket type, and the same QR credential issued in Phase 1, with no other personal data.
3. **Given** an attendee taps "Add to Google Wallet", **When** the pass is generated, **Then** the pass contains equivalent event, ticket, and QR information formatted for the Google Wallet object model.
4. **Given** a credential that is not active (revoked, expired, or superseded), **When** wallet pass generation is attempted, **Then** the request is rejected and no pass referencing that credential is created.
5. **Given** wallet pass generation succeeds, **When** the pass is later inspected, **Then** it discloses no national identifier, biometric data, or payment information.

---

### User Story 2 - Validate Entry with a Staff QR Scan (Priority: P1)

Authorized on-site staff scan an attendee's QR code (from a printed ticket,
the confirmation page, or a wallet pass) using a staff device, and the system
authoritatively confirms in real time whether entry is allowed, rejecting
duplicate, revoked, and expired credentials.

**Why this priority**: Reliable entry validation is the operational core of
this phase; wallet passes have no on-site value without a trustworthy scan
result, and every later phase (kiosk, ACS) builds on this validation path.

**Independent Test**: A staff member can scan a valid credential and receive
an "accepted" result exactly once, then scan the same credential again and
receive a "duplicate" rejection, while a revoked or expired credential is
rejected on first scan with a stable, distinguishable reason.

**Acceptance Scenarios**:

1. **Given** an authorized staff member and an active, unscanned credential for the current event, **When** the credential is scanned, **Then** the result is "accepted," the attendee's check-in status updates, and a scan event is recorded.
2. **Given** a credential already accepted once under single-entry rules, **When** it is scanned again, **Then** the result is "duplicate" and entry is rejected unless an authorized staff member performs a permitted, reasoned override.
3. **Given** a revoked credential, **When** it is scanned, **Then** the result is "revoked" and entry is rejected regardless of any wallet pass the attendee may still display.
4. **Given** an expired credential, **When** it is scanned, **Then** the result is "expired" and entry is rejected.
5. **Given** a credential belonging to a different event or tenant than the scanning context, **When** it is scanned, **Then** the result is rejected without revealing which event or tenant the credential belongs to.
6. **Given** a malformed, tampered, or unrecognized QR payload, **When** it is scanned, **Then** the result is rejected with a stable generic reason and no attendee data is disclosed.
7. **Given** an accepted scan, **When** the same credential's underlying record is later revoked or reissued, **Then** any subsequent scan reflects the new authoritative state rather than the earlier accepted result.

---

### User Story 3 - Keep Wallet Passes Synchronized with Event and Credential Changes (Priority: P2)

When an organizer changes material event details (date, time, location, or
branding) or when a credential is revoked or reissued, every wallet pass
referencing that event or credential is updated or invalidated so the
attendee's wallet never shows stale or unusable information.

**Why this priority**: Attendees rely on the pass already saved on their
device; without synchronization a wallet pass can silently become misleading
or unusable, undermining trust in both the pass and the entry process.

**Independent Test**: Changing an event's date/time/location after passes have
been issued triggers an update push to affected wallet passes, and revoking or
reissuing a credential causes its wallet pass to become invalid or be replaced
without manual attendee action.

**Acceptance Scenarios**:

1. **Given** attendees already hold wallet passes for a published event, **When** the organizer changes the event's date, time, or location, **Then** an update is pushed to Apple Wallet and Google Wallet so the pass reflects the new details.
2. **Given** an attendee's credential is revoked, **When** the revocation completes, **Then** the corresponding wallet pass is marked revoked or invalidated so it can no longer present as valid on the device where the wallet platform supports remote invalidation.
3. **Given** an attendee's credential is reissued, **When** the new credential is created, **Then** the prior wallet pass is superseded and, where technically supported, the attendee can add a new pass containing the replacement QR credential.
4. **Given** a wallet push provider is temporarily unreachable, **When** an update or revocation is attempted, **Then** the attempt is retried safely without blocking the underlying event, credential, or registration operation, and the pending update remains visible to authorized staff.

---

### User Story 4 - Monitor Real-Time Check-In Counts (Priority: P2)

An authorized organizer views a live dashboard showing how many attendees
have registered and how many have checked in for their event, so they can
monitor arrival flow during the event.

**Why this priority**: Organizers need visibility into on-site progress, but
this is an operational view built on top of scanning rather than a
prerequisite for entry validation itself.

**Independent Test**: As staff scan attendees into an event, an authorized
organizer viewing the event dashboard sees the check-in count increase without
manually refreshing beyond a short, bounded delay, while registration counts
remain accurate and cross-tenant/event data never appears.

**Acceptance Scenarios**:

1. **Given** an authorized organizer viewing their event's dashboard, **When** staff accept a scan, **Then** the displayed check-in count reflects the accepted scan within a short, bounded delay.
2. **Given** rejected, duplicate, and overridden scans occur, **When** the dashboard is viewed, **Then** these are distinguished from accepted check-ins and do not inflate the check-in count.
3. **Given** an organizer authorized for one event only, **When** the dashboard is viewed, **Then** no other tenant's or event's counts are visible.

---

### User Story 5 - Continue Scanning During a Connectivity Loss (Priority: P3)

Staff using a scanning device that loses connectivity during an event can
continue validating a locally synced allowlist of credentials for that event
and time window, with scans recorded locally and safely reconciled once
connectivity returns.

**Why this priority**: Connectivity loss is a realistic on-site risk, but
online scanning (User Story 2) is the primary path; offline tolerance is a
resilience feature that can be delivered as a documented design with partial
implementation where pilot needs require it.

**Independent Test**: A scanning device pre-synced with an event's active
credential allowlist can accept or reject scans while offline using local
duplicate prevention, then reconcile all locally recorded scans with the
server without creating duplicate accepted entries or losing scan evidence.

**Acceptance Scenarios**:

1. **Given** a scanning device synced with a current allowlist for one event and time window, **When** connectivity is lost, **Then** the device can still accept or reject scans against the locally synced credential set.
2. **Given** two offline scans of the same credential on the same device, **When** the second scan occurs, **Then** it is locally rejected as a duplicate before any server reconciliation.
3. **Given** a device reconnects after offline scanning, **When** synchronization runs, **Then** every locally recorded scan is reconciled with the server exactly once.
4. **Given** two different offline devices independently accept the same credential during a connectivity gap, **When** both reconcile, **Then** the conflict is detected and flagged for staff review rather than silently resolved.

### Edge Cases

- An attendee's device has no compatible wallet application installed or the
  device platform does not support the requested wallet provider.
- A wallet push service (Apple Push Notification service or Google Wallet
  API) is degraded or unreachable when an update or revocation is due.
- An event is cancelled after wallet passes have already been distributed to
  attendees.
- A credential is reissued while a wallet pass update for the prior credential
  is still in flight.
- Two staff members scan the same credential within the same instant at
  different entry points.
- A staff device displays a cached, stale wallet pass state because the
  attendee's device has not yet received a pending revocation or update; the
  scan result MUST always reflect authoritative server state, never the pass
  as displayed on the attendee's device.
- A scan targets a credential for an event that has not yet opened or has
  already ended.
- An authorized staff override is requested without a documented reason.
- Cross-tenant or cross-event credential identifiers are scanned, requested
  for wallet generation, or appear in dashboard queries.
- An actor lacks the specific scan, override, wallet, or dashboard permission
  required for the requested action.
- Background wallet update jobs, scan reconciliation jobs, dashboard caches,
  scan logs, and telemetry attempt to operate without trusted tenant and
  event scope.
- Audit persistence fails during a scan, override, wallet generation, wallet
  update, or wallet revocation.
- The wallet or scanning experience is used in Arabic/RTL and English/LTR
  contexts, including any wallet-provider-imposed localization limits.
- An on-premise deployment loses outbound connectivity to Apple/Google wallet
  services while still needing to perform local scanning.

## Requirements *(mandatory)*

### Functional Requirements

#### Wallet Pass Generation and Lifecycle

- **FR-001**: The system MUST allow an attendee with an active credential to generate an Apple Wallet pass and a Google Wallet pass referencing that credential.
- **FR-002**: A wallet pass MUST contain event name, event date, event location, attendee name, ticket type, the same QR credential already issued for that attendee, and, where configured, an optional zone or tier label.
- **FR-003**: A wallet pass MUST NOT contain national identifiers, biometric data, payment card data, or any personal data beyond what FR-002 specifies.
- **FR-004**: Wallet passes MUST support the lifecycle states created, active, updated, revoked, expired, and failed.
- **FR-005**: Wallet pass generation MUST fail closed for credentials that are not active; no pass may be created referencing a revoked, expired, or superseded credential.
- **FR-006**: Wallet pass generation failure MUST NOT block, delay, or invalidate the attendee's underlying registration, order, or credential; it MUST remain safely retryable.

#### Wallet Pass Synchronization

- **FR-007**: When an event's date, time, location, or branding materially changes after passes have been issued, the system MUST attempt to push an update to every affected wallet pass.
- **FR-008**: When a credential is revoked, the system MUST attempt to invalidate or mark revoked every wallet pass referencing that credential.
- **FR-009**: When a credential is reissued, the system MUST supersede the prior wallet pass and support issuing a replacement pass referencing the new credential.
- **FR-010**: Wallet push failures MUST be retried on a bounded schedule and MUST remain visible to authorized staff without exposing provider secrets or payloads.
- **FR-011**: The authoritative entry decision MUST always be based on live server-side credential and scan state, never solely on the pass content or state displayed on an attendee's device.

#### QR Scanning and Check-In

- **FR-012**: Authorized staff MUST be able to submit a scanned QR payload and receive one of a small set of stable result categories, including at minimum accepted, rejected, duplicate, revoked, expired, and manual override.
- **FR-013**: A scan MUST validate that the credential exists, belongs to the scanning context's event and tenant, is active, is not expired, and is not revoked before returning "accepted."
- **FR-014**: An event or ticket type MAY be configured for single-entry enforcement; when enabled, a second scan of an already-accepted credential MUST be rejected as "duplicate" unless an authorized staff override is applied.
- **FR-015**: A staff override of a duplicate or otherwise rejected scan MUST require an explicit permission and a recorded reason, and MUST be distinguishable from a normal accepted scan in all records and dashboards.
- **FR-016**: Every scan attempt, regardless of outcome, MUST create a scan event record capturing the scanner, result, reason, and timestamp.
- **FR-017**: A scan of a malformed, tampered, or unrecognized QR payload MUST be rejected with a stable generic reason that discloses no attendee, tenant, or event data.
- **FR-018**: A scan of a credential belonging to a different tenant or event than the scanning context MUST be rejected in a way that is indistinguishable from scanning an unknown or nonexistent credential.
- **FR-019**: A successful scan MUST update the attendee's check-in status so subsequent scans and dashboard views reflect current state.

#### Real-Time Dashboard

- **FR-020**: Authorized organizers MUST be able to view, for an event they are authorized to manage, current registration and check-in counts.
- **FR-021**: The dashboard MUST distinguish accepted check-ins from rejected, duplicate, and overridden scan attempts and MUST NOT count non-accepted scans as check-ins.
- **FR-022**: Dashboard data MUST update to reflect new scans within a short, bounded delay without requiring a full page reload.
- **FR-023**: Dashboard access MUST be limited to the tenant and event the organizer is authorized to manage.

#### Offline-Tolerant Scanning

- **FR-024**: The system MUST define a documented design for offline-tolerant scanning using a locally synced allowlist bounded to one event and time window.
- **FR-025**: Where offline scanning is implemented, local duplicate prevention MUST reject a repeated scan of the same credential on the same device before any server reconciliation occurs.
- **FR-026**: Where offline scanning is implemented, every locally recorded scan MUST be synced to the server and reconciled exactly once when connectivity is restored.
- **FR-027**: Where two or more offline devices independently accept the same credential during a connectivity gap, reconciliation MUST detect the conflict and flag it for staff review rather than silently resolving it.

### Constitutional Requirements *(mandatory)*

- **CR-001 Tenant Scope**: Wallet passes, scan events, dashboard counts, offline allowlists, wallet push jobs, scan reconciliation jobs, and related caches, files, logs, and telemetry are tenant-owned and event-scoped. Scan requests MUST resolve tenant and event context authoritatively from the credential itself and the scanning context, never from an unverified client-supplied tenant identifier. Cross-tenant or cross-event access MUST fail closed and return a response indistinguishable from an unknown target.
- **CR-002 RBAC**: This phase adds least-privilege permissions for wallet pass generation, scan submission, scan override, wallet pass administration, and dashboard viewing. On-site staff receive scan and override permissions scoped to their assigned event without gaining organizer financial, attendee-editing, or credential-revocation privileges unless separately granted. Every override requires its own permission and a recorded reason.
- **CR-003 Auditability**: Wallet pass generation, update, revocation, and failure; every scan result including duplicate and override outcomes; offline reconciliation conflicts; and dashboard access to sensitive counts MUST record actor type and identifier, tenant, event, action, target, outcome, reason code where applicable, correlation, channel, and timestamp. Scan and wallet audit evidence MUST commit together with the underlying state change or fail together.
- **CR-004 Credential Security**: Wallet passes carry a reference to an existing Phase 1 signed credential and MUST NOT introduce a separate trust path around it. Scan validation MUST always re-check authoritative credential status (active, expired, revoked, superseded) at scan time rather than trusting a locally cached device pass or a previous scan result. Wallet pass content MUST remain opaque with respect to personal or payment data.
- **CR-005 Data and PDPL**: Wallet passes and scan events contain operational and limited display personal data (attendee name, ticket type, scan timestamps and location context) collected only to support wallet convenience, on-site entry validation, and check-in reporting. This data follows the tenant's approved retention and residency rules established in Phase 1, and deletion or anonymization of attendee data MUST preserve required audit and scan evidence in a form that no longer discloses personal identity where legally required. National identity and biometric data remain out of scope for this phase.
- **CR-006 API and Integrations**: Wallet pass generation, update, and revocation MUST be exposed through a provider-neutral internal wallet contract implemented by an Apple Wallet (PassKit) adapter and a Google Wallet adapter, each with explicit authentication, signing/certificate handling, timeout, retry, idempotency, error mapping, sandbox testing, and production-readiness evidence. Staff scan submission and dashboard queries MUST have documented versioned API contracts. Provider-specific payloads, error codes, and certificates MUST NOT leak into public contracts or client-visible errors.
- **CR-007 White-Label and Localization**: Wallet pass content, staff scan result messaging, and the organizer dashboard MUST support Arabic/RTL and English/LTR presentation and locale-aware dates and times. Where a wallet platform limits localization capability, the limitation MUST be documented and a safe bilingual fallback defined rather than silently defaulting to one language.
- **CR-008 Deployment Parity**: Staff scanning, check-in status updates, scan event recording, and the dashboard MUST remain operable in both SaaS and on-premise deployments, including when outbound connectivity to Apple/Google wallet push services is unavailable; wallet pass generation and updates MUST report an explicit degraded or pending state in that case rather than failing silently or blocking check-in. Offline-tolerant scanning MUST be documented for both deployment modes and MUST NOT depend on continuous cloud connectivity to validate a locally synced allowlist.
- **CR-009 Automated Verification**: Required verification includes wallet pass generation, update, and revocation unit and integration tests for both providers; QR scan validation tests covering accepted, duplicate, revoked, and expired outcomes; staff override permission and audit tests; cross-tenant and cross-event scan and wallet denial tests; dashboard authorization and count-accuracy tests; offline scan and reconciliation tests where implemented; and audit atomicity tests for scan and wallet state changes.
- **CR-010 Phase Alignment**: This phase delivers the second product increment defined by `all_plan.md`: Apple and Google Wallet pass generation/update/revocation, authoritative staff QR scanning with duplicate/revoked/expired rejection, a real-time check-in dashboard, and a documented offline-tolerant scanning design. It depends on the accepted Phase 0 tenant, RBAC, and audit foundation and the accepted Phase 1 credential issuance, revocation, and reissue core. Kiosk check-in and badge printing (Phase 3), zone/lane/ACS access control and anti-passback (Phase 4), identity verification (Phase 5), and venue marketplace (Phase 6) remain later-phase work and are explicitly out of scope here.

### Key Entities

- **Wallet Pass**: A tenant- and event-owned Apple or Google wallet artifact referencing one attendee's credential, tracking its provider, serial identifier, delivery status, and last push time.
- **Scan Event**: An immutable tenant- and event-scoped record of one scan attempt, including the scanning source, the credential referenced, the result category, the reason, and whether it occurred offline before later reconciliation.
- **Credential** *(from Phase 1, referenced not redefined)*: The signed, revocable, reissuable QR identity that both wallet passes and scans authoritatively validate against.
- **Attendee** *(from Phase 1, extended)*: Gains a check-in status reflecting the most recent accepted scan for their event.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: At least 90% of attendees on a supported device can successfully add their event pass to Apple Wallet or Google Wallet on the first attempt.
- **SC-002**: A staff scan returns an accepted or rejected result to the scanning device in under 2 seconds under normal network conditions in 95% of scans.
- **SC-003**: 100% of revoked, expired, and duplicate credential scans are rejected in automated security testing, with zero false accepts.
- **SC-004**: A wallet pass reflects a material event detail change within a bounded, documented time window after the organizer's update, across 100% of tested update scenarios.
- **SC-005**: Revoking or reissuing a credential results in the prior wallet pass becoming unusable for entry (via authoritative scan rejection) in 100% of tested cases, even before any device-side wallet update is received.
- **SC-006**: The organizer dashboard reflects an accepted scan's effect on the check-in count within a short, bounded delay in 100% of tested scenarios, and never displays another tenant's or event's data.
- **SC-007**: Cross-tenant and cross-event wallet generation and scan attempts are denied in 100% of automated tests, with responses indistinguishable from an unknown target.
- **SC-008**: Every wallet pass generation, update, revocation, scan result, and override produces complete sanitized audit evidence, and forced audit failure leaves zero partial state changes.
- **SC-009**: Where offline scanning is implemented, reconciliation after a connectivity gap produces zero duplicate accepted check-ins and correctly flags 100% of tested simultaneous-offline-acceptance conflicts for review.
- **SC-010**: On-site staff report at least 90% satisfaction with scan speed and result clarity during pilot acceptance testing.

## Assumptions

- Phase 0 (tenant isolation, RBAC, audit, adapters, localization) and Phase 1
  (events, ticketing, orders, attendees, and the signed credential lifecycle)
  are complete and remain mandatory dependencies.
- `all_plan.md` is authoritative: kiosk check-in, badge printing, and manual
  desk operations belong to Phase 3; zone/lane mapping, gate authorization,
  and anti-passback belong to Phase 4. This phase implements staff-operated
  scanning (phone or handheld scanner) and the shared scan/check-in and
  wallet foundation those later phases will build on, without implementing
  kiosk- or ACS-specific behavior itself.
- Single-entry (anti-duplicate) enforcement is configurable per event or
  ticket type; multi-zone, multi-lane, and anti-passback entry rules are
  deferred to Phase 4 and are not required for this phase's scan result set.
  "unauthorized_zone" and "anti_passback_rejected" scan outcomes remain
  reserved for that later phase.
  
- Apple Wallet integration uses PassKit web service conventions (signed pass
  bundles and Apple Push Notification service update pings); Google Wallet
  integration uses the Google Wallet API object model. Exact certificate,
  merchant, and issuer account provisioning are planning-phase inputs and
  must satisfy the provider-neutral adapter contract and production-readiness
  evidence before enabling live wallet issuance.
- Offline-tolerant scanning is delivered at minimum as a documented design;
  full local-device implementation may be scoped to pilot need per
  `all_plan.md`, provided the design, local dedupe behavior, and
  reconciliation/conflict-flagging rules are specified and testable.
- The real-time dashboard in this phase is limited to aggregate registration
  and check-in counts for one event; deeper reporting, historical analytics,
  and cross-event reporting are later-phase or out-of-scope work.
- Wallet push services and scanning devices require network connectivity for
  their online paths; on-premise deployments without reachable wallet push
  services operate with wallet updates explicitly degraded while local
  scanning and check-in continue to function.
- This phase excludes kiosks, badge templates/printing, manual desk
  operations, ACS zones/lanes/authorization rules, identity verification,
  venue marketplace, and any production hardware adapter beyond staff
  scanning devices and the wallet provider adapters described above.
