@# Feature Specification: ACS and Access Control

**Feature Branch**: `005-acs-access-control`

**Created**: 2026-07-07

**Status**: Draft

**Input**: User description: "Phase 4"

**Product Phase**: Phase 4 ACS-Access-Control

**Deployment Modes**: SaaS and on-premise

## User Scenarios & Testing _(mandatory)_

### User Story 1 - Gate Releases Only When the Credential Is Authorized (Priority: P1)

At a physical lane, an attendee presents their credential (QR, NFC, RFID, or
a matched identity reference). The access control system (ACS) asks Zonetec
whether the gate should release; Zonetec validates the credential and the
event's access rules and returns an allow or deny decision with a reason, and
the gate releases only on allow.

**Why this priority**: The authorization decision is the core promise of this
phase and the smallest slice that delivers value: without it, physical gates
cannot make trustworthy release decisions from Zonetec credentials. Every
other story in this phase builds on this request/decision exchange.

**Independent Test**: A simulated ACS lane sends an authorization request for a
valid, in-scope credential and receives an allow decision with a reason; it
sends requests for invalid, expired, revoked, and out-of-scope credentials and
receives deny decisions, each with a distinct machine-readable reason, without
any zone/lane mapping, anti-passback, or dashboard features present.

**Acceptance Scenarios**:

1. **Given** a valid, active credential presented at a lane, **When** the ACS
   requests authorization, **Then** Zonetec returns an allow decision with a
   reason and the interaction is recorded as an access event.
2. **Given** an expired, revoked, or unknown credential, **When** the ACS
   requests authorization, **Then** Zonetec returns a deny decision with a
   distinct reason and no gate release is authorized.
3. **Given** a credential belonging to a different event or tenant than the
   lane, **When** the ACS requests authorization, **Then** Zonetec denies it in
   a way indistinguishable from an unknown credential.
4. **Given** an authorization request, **When** Zonetec returns its decision,
   **Then** the response includes a stable reason code suitable for logging by
   the ACS and by Zonetec.

---

### User Story 2 - Operator Maps Ticket Types to Zones and Lanes (Priority: P1)

An authorized ACS operator defines the venue's zones and lanes, links them to
the external ACS's zone and lane identifiers, and configures which ticket
types and attendee types are allowed in which zones and lanes, and during
which time windows.

**Why this priority**: Authorization decisions are only meaningful once access
rules exist; zone/lane mapping and ticket-type rules are what turn "valid
credential" into "allowed here, now." This is required for User Story 1 to
produce differentiated allow/deny outcomes beyond basic credential validity.

**Independent Test**: An operator creates a zone and a lane, maps them to
external ACS identifiers, and adds a rule allowing a specific ticket type into
that zone during a time window; a credential of that ticket type is then
authorized for that lane, while a credential of a non-permitted ticket type is
denied with a zone/lane reason.

**Acceptance Scenarios**:

1. **Given** an authorized operator, **When** they create a zone and lane and
   set the external ACS zone/lane identifiers, **Then** the mapping is saved
   and scoped to their event and tenant only.
2. **Given** a zone/lane with a ticket-type authorization rule, **When** an
   attendee of a permitted ticket type presents at that lane, **Then** the
   authorization decision reflects the rule and allows entry.
3. **Given** a ticket type with no rule permitting a zone, **When** an attendee
   of that type presents at a lane in that zone, **Then** the decision denies
   entry with a zone/lane-not-permitted reason.
4. **Given** a rule with a valid-from/valid-until window, **When** a credential
   is presented outside that window, **Then** the decision denies entry with a
   time-window reason.
5. **Given** an operator authorized for one event only, **When** they view
   zones, lanes, or rules, **Then** no other tenant's or event's mappings are
   visible or editable.

---

### User Story 3 - Anti-Passback Rejects Duplicate Re-Entry (Priority: P2)

To prevent credential sharing and pass-back, the system rejects a re-entry
through a controlled zone unless a corresponding exit has been recorded, per
the anti-passback rules configured for the event, zone, and ticket type.

**Why this priority**: Anti-passback is a key access-integrity control for
paid and restricted events, but it depends on the authorization decision (User
Story 1) and directional zone/lane configuration (User Story 2) already
existing.

**Independent Test**: With anti-passback enabled for a zone, a credential is
authorized for entry, then presented again for entry without an intervening
exit and is denied with an anti-passback reason; after an exit is recorded,
the same credential is authorized for entry again.

**Acceptance Scenarios**:

1. **Given** anti-passback enabled for a zone, **When** a credential that is
   already inside (entered, no exit recorded) requests entry again, **Then**
   the decision denies entry with an anti-passback reason.
2. **Given** a credential that entered and then exited, **When** it requests
   entry again, **Then** the decision allows entry (subject to other rules).
3. **Given** anti-passback disabled for a zone or ticket type, **When** a
   credential re-enters, **Then** anti-passback does not cause a denial.
4. **Given** anti-passback rules, **When** they are configured, **Then** they
   are settable per event, per zone, and per ticket type independently.

---

### User Story 4 - Entry and Exit Events Are Logged and Reconciled (Priority: P2)

After the ACS acts on a decision (releases or holds a gate) and the attendee
passes through, the ACS sends entry and exit event callbacks to Zonetec, which
records them as access events, reconciles them with its decision, and updates
occupancy and reporting.

**Why this priority**: Reliable entry/exit logging is what makes access data
trustworthy for reporting, occupancy, and anti-passback state, but it layers on
top of the decision exchange rather than being required for a single gate to
open.

**Independent Test**: A simulated ACS sends entry and exit event callbacks for
a prior authorization; Zonetec records matching access events attributed to the
correct lane, zone, credential, and direction, and the entry/exit counts and
occupancy update accordingly.

**Acceptance Scenarios**:

1. **Given** an allowed authorization, **When** the ACS sends an entry event
   callback, **Then** Zonetec records an access event with direction, lane,
   zone, credential, and timestamp.
2. **Given** a recorded entry, **When** the ACS sends a matching exit event,
   **Then** Zonetec records the exit and updates anti-passback state and
   occupancy.
3. **Given** a duplicate or replayed event callback, **When** it is received,
   **Then** Zonetec processes it idempotently without double-counting.
4. **Given** an event callback referencing a different tenant's or event's
   lane, **When** it is received, **Then** it is rejected and not recorded.

---

### User Story 5 - Emergency Egress Fails Open and Is Recorded (Priority: P2)

When a fire alarm or emergency signal is raised, the system supports
emergency-egress behavior: gates fail open where configured, the emergency
event is recorded, and the dashboard is notified so operators can respond.

**Why this priority**: Life-safety egress is a hard requirement for physical
venues and must be explicitly designed and testable, but it is a distinct
override path layered on the normal decision and event flow.

**Independent Test**: An emergency-egress signal is raised for a zone
configured to fail open; the system records an emergency event, surfaces it on
the dashboard, and subsequent presentations at affected lanes reflect the
fail-open state, all verifiable without a live gate.

**Acceptance Scenarios**:

1. **Given** a zone configured to fail open on emergency, **When** an emergency
   egress signal is received, **Then** the system records an emergency event
   and reflects fail-open behavior for that zone.
2. **Given** an emergency event, **When** it is recorded, **Then** it is
   visible on the gate/access dashboard to authorized viewers.
3. **Given** an emergency egress signal, **When** it is processed, **Then** the
   event is auditable and can be replayed in tests.

---

### User Story 6 - Operator Monitors Gate Events and Health (Priority: P3)

An authorized ACS operator or organizer views live gate events (allowed,
denied, entry, exit, emergency) and the health/connectivity status of lanes and
the ACS integration, so they can respond to access problems during the event.

**Why this priority**: Operational visibility improves on-site response but is a
monitoring layer over already-functioning authorization and event flows; it
delivers no standalone access value on its own.

**Independent Test**: As authorization decisions and events flow and as an ACS
lane's connectivity changes, an authorized viewer sees gate events and lane/ACS
health update within a short, bounded delay, scoped only to their authorized
event.

**Acceptance Scenarios**:

1. **Given** authorization decisions and access events occurring, **When** an
   authorized operator views the dashboard, **Then** allowed, denied, entry,
   exit, and emergency events are visible with their reasons.
2. **Given** a lane or the ACS integration loses connectivity, **When** the
   configured threshold elapses, **Then** its health status is shown as
   degraded or offline to authorized viewers.
3. **Given** an operator authorized for one event only, **When** they view gate
   events or health, **Then** no other tenant's or event's data is visible.

### Edge Cases

- An authorization request arrives for a credential that is valid but has no
  matching zone/lane rule for the presented lane.
- The ACS integration is unreachable, slow, or times out during an
  authorization request (latency budget exceeded).
- An entry event callback is received with no corresponding prior authorization
  decision, or arrives out of order relative to its exit.
- A credential is presented at two lanes in different zones within the same
  instant.
- Anti-passback is enabled but no exit was ever recorded because the attendee
  left through an uncontrolled or emergency-egress path.
- An emergency egress signal is raised for a zone that is configured to fail
  closed, or the signal itself cannot be verified as authentic.
- The external ACS reports a zone/lane identifier that is not mapped in
  Zonetec, or a mapping is deleted while lanes are live.
- A signed credential is expired, revoked, replayed, or signed by an
  unknown/rotated key at the moment of gate authorization.
- An actor lacks the specific ACS-configuration, decision-view, or
  emergency-management permission required for the requested action.
- Cross-tenant or cross-event zone, lane, rule, credential, or event-callback
  identifiers are referenced in a request or callback.
- Audit persistence fails during an authorization decision, event callback, or
  emergency-egress recording.
- Dashboards and operator configuration are used in Arabic/RTL and English/LTR
  contexts, including reason messages.
- The ACS adapter is unreachable in an on-premise deployment with no outbound
  cloud connectivity, requiring documented offline/degraded behavior.

## Requirements _(mandatory)_

### Functional Requirements

#### Authorization Contract and Decisions

- **FR-001**: The system MUST expose a documented authorization request that
  accepts a credential or identity reference plus lane context and returns an
  allow or deny decision.
- **FR-002**: Every authorization decision MUST include a stable, machine
  -readable reason code (e.g., allowed, expired, revoked, unknown-credential,
  zone-not-permitted, lane-not-permitted, outside-time-window,
  anti-passback-violation).
- **FR-003**: An authorization decision MUST validate the credential using the
  existing Phase 1 signed-credential validation and Phase 2 scan-validation
  paths, without introducing a separate credential trust path.
- **FR-004**: An authorization request naming a lane, zone, event, or tenant
  that does not match the credential's scope MUST be denied in a manner
  indistinguishable from an unknown credential.
- **FR-005**: The system MUST record every authorization decision (allow and
  deny) as an access event attributed to the lane, zone, credential, direction,
  reason, and timestamp.

#### Zone, Lane, and Rule Configuration

- **FR-006**: Authorized operators MUST be able to create, edit, and deactivate
  zones and lanes for their event and link them to the external ACS's zone and
  lane identifiers.
- **FR-007**: Authorized operators MUST be able to define authorization rules
  mapping ticket types and attendee types to permitted zones, lanes, access
  direction, and valid-from/valid-until time windows.
- **FR-008**: Authorization decisions MUST enforce configured zone rules, lane
  rules, access direction, and time windows.
- **FR-009**: A credential with no rule permitting the presented lane/zone MUST
  be denied with a zone/lane-not-permitted reason rather than allowed by
  default.

#### Anti-Passback

- **FR-010**: The system MUST support anti-passback configurable independently
  per event, per zone, and per ticket type.
- **FR-011**: When anti-passback is enabled, a re-entry request for a credential
  currently recorded as inside a zone (entered without a recorded exit) MUST be
  denied with an anti-passback reason.
- **FR-012**: When a recorded exit exists for a credential, a subsequent entry
  request MUST NOT be denied for anti-passback reasons.

#### Entry/Exit Event Callbacks

- **FR-013**: The system MUST accept entry and exit event callbacks from the
  ACS and record them as access events with direction, lane, zone, credential,
  and timestamp.
- **FR-014**: Event callback processing MUST be idempotent so duplicate or
  replayed callbacks do not double-count entries, exits, or occupancy.
- **FR-015**: An event callback referencing a lane/zone/event/tenant not owned
  by the caller's resolved context MUST be rejected and not recorded.

#### Emergency Egress

- **FR-016**: The system MUST support emergency-egress behavior that fails open
  for zones configured to do so and records an emergency event.
- **FR-017**: Emergency events MUST be surfaced to authorized viewers on the
  gate/access dashboard and MUST be auditable and replayable in tests.

#### Gate Events and Health

- **FR-018**: The system MUST provide authorized operators a view of gate events
  (allowed, denied, entry, exit, emergency) with reasons, scoped to their
  authorized event and tenant.
- **FR-019**: The system MUST track and display the health/connectivity status
  (online, degraded, offline) of lanes and the ACS integration.

#### Failure and Offline Behavior

- **FR-020**: When the ACS integration is unreachable or exceeds its latency
  budget, the system MUST apply and record a documented, configurable
  fail-open/fail-closed behavior per zone rather than silently dropping the
  request.

### Constitutional Requirements _(mandatory)_

- **CR-001 Tenant Scope**: Zones, lanes, authorization rules, access events,
  anti-passback state, emergency events, and lane/ACS health records are
  tenant-owned and event-scoped. Authorization requests and event callbacks
  MUST resolve tenant and event context authoritatively from the credential and
  the lane's registration/mapping, never from an unverified client-supplied
  identifier. Cross-tenant or cross-event access MUST fail closed and return a
  response indistinguishable from an unknown target.
- **CR-002 RBAC**: This phase adds least-privilege permissions for ACS
  configuration (zones, lanes, rules, anti-passback), decision/event viewing,
  gate/ACS health viewing, and emergency-egress configuration. The ACS
  integration itself authenticates as a machine-to-machine actor limited to
  requesting authorization and posting event callbacks for its mapped
  lanes/event, without gaining organizer, financial, or credential-lifecycle
  privileges.
- **CR-003 Auditability**: Every authorization decision (allow and deny), zone
  /lane/rule creation and change, anti-passback configuration change, entry/exit
  event ingestion, and emergency-egress event MUST record actor type and
  identifier, tenant, event, action, target, outcome, reason code, correlation,
  channel, and timestamp. This audit evidence MUST commit together with the
  underlying state change or fail together.
- **CR-004 Credential Security**: Gate authorization MUST reuse the Phase 1
  signed credential and Phase 2 scan validation (signature, expiry, revocation,
  replay, key identification/rotation) without a separate trust path. The ACS
  receives only an allow/deny decision and reason, never signing keys, raw
  secrets, or a re-scoped credential. Replayed authorization requests and event
  callbacks MUST be detectable and handled idempotently.
- **CR-005 Data and PDPL**: Access events and decisions capture the minimum
  needed for access control and audit (credential reference, zone/lane,
  direction, reason, timestamp) and MUST NOT store national identifiers,
  biometric templates, or payment data. Any identity-reference data used at a
  face/biometric lane is validated upstream (Phase 5) and only referenced here;
  access-event data follows the tenant's approved retention, residency, and
  deletion rules from Phase 1, preserving audit evidence in a
  non-identity-disclosing form where legally required.
- **CR-006 API and Integrations**: Authorization requests, event callbacks,
  zone/lane/rule configuration, emergency egress, and health MUST have
  documented versioned API contracts. The external ACS (Runa) MUST be accessed
  only through an ACS adapter interface with explicit authentication, timeout,
  latency budget, retry, idempotency, error mapping, offline/degraded behavior,
  and production-readiness evidence; a mock/fake ACS MUST exist behind that
  interface for development and contract tests, and adapter-specific payloads or
  errors MUST NOT leak into public contracts or client-visible errors.
- **CR-007 White-Label and Localization**: Operator-facing configuration and
  dashboard content, and any human-readable decision/reason messages, MUST
  support Arabic/RTL and English/LTR presentation, tenant branding, and
  locale-aware dates/times. Machine-readable reason codes remain
  language-neutral, with localized display resolved for display only.
- **CR-008 Deployment Parity**: Authorization decisions, zone/lane rules,
  anti-passback, event ingestion, and emergency egress MUST behave identically
  in SaaS and on-premise deployments. On-premise operation MUST support a local
  ACS integration with no outbound cloud dependency, and the configured
  fail-open/fail-closed and degraded behavior on ACS unavailability MUST be
  documented and tested for both modes; any intentional difference MUST be
  approved in the plan's Constitution Check.
- **CR-009 Automated Verification**: Required verification includes ACS adapter
  contract tests against the mock ACS; authorization decision tests for allow,
  expired, revoked, unknown, zone-not-permitted, lane-not-permitted, and
  time-window outcomes; zone and lane authorization tests; anti-passback tests
  (entry, blocked re-entry, exit-then-entry, disabled); entry/exit event
  callback tests including idempotency and out-of-order handling; emergency
  egress tests; ACS unreachable/latency-exceeded fail-open/fail-closed tests;
  cross-tenant and cross-event denial tests for every ACS endpoint and callback;
  ACS/lane health tests; and audit-atomicity tests for decisions, callbacks,
  and emergency events.
- **CR-010 Phase Alignment**: This phase delivers the fourth product increment
  defined by `all_plan.md`: the credential-to-ACS authorization contract, ACS
  adapter, zone/lane mapping, ticket-type-to-zone/lane rules, entry/exit
  logging, anti-passback, gate event callbacks, emergency egress, and a gate
  health dashboard. It depends on the accepted Phase 0 tenant/RBAC/audit/adapter
  foundation, the accepted Phase 1 signed-credential lifecycle, and the accepted
  Phase 2 scan/check-in validation core, and it reuses Phase 3 on-site concepts
  where relevant. Identity verification (Phase 5), venue marketplace (Phase 6),
  and enterprise/on-premise hardening (Phase 7) remain later-phase work and are
  out of scope here.

### Key Entities

- **ACS Zone**: A tenant- and event-owned controlled area, linked to an external
  ACS zone identifier, with a name and active/inactive status.
- **ACS Lane**: A tenant- and event-owned gate/lane within a zone, linked to an
  external ACS lane identifier, with a gate type, access direction support, and
  online/degraded/offline status.
- **ACS Authorization Rule**: A tenant- and event-owned rule mapping a ticket
  type and attendee type to a permitted zone, lane, access direction, and
  valid-from/valid-until window.
- **Access Event**: A tenant- and event-scoped record of an authorization
  decision or an entry/exit/emergency event, referencing the credential, zone,
  lane, direction, reason code, source, and timestamp.
- **Anti-Passback State**: The per-credential, per-zone inside/outside state
  derived from recorded entry and exit events, used to evaluate re-entry.
- **Emergency Event**: A tenant- and event-scoped record of an emergency-egress
  signal and the fail-open/fail-closed behavior applied.
- **Credential** _(from Phase 1, referenced not redefined)_: The signed,
  revocable, reissuable identity that gate authorization validates against.
- **Scan Event** _(from Phase 2, referenced)_: The existing check-in/scan record
  whose validation logic the authorization decision reuses.

## Success Criteria _(mandatory)_

### Measurable Outcomes

- **SC-001**: 100% of authorization requests for expired, revoked, unknown, and
  out-of-scope credentials are denied in automated security testing, with zero
  false accepts.
- **SC-002**: 100% of authorization decisions return a stable reason code, and
  every decision produces a matching access-event record in automated testing.
- **SC-003**: Zone, lane, direction, and time-window rules are enforced
  correctly in 100% of automated rule-authorization tests.
- **SC-004**: Anti-passback blocks re-entry without a recorded exit in 100% of
  tested cases when enabled, and never blocks re-entry when disabled.
- **SC-005**: Entry/exit event callbacks are processed idempotently, with zero
  double-counted entries, exits, or occupancy in duplicate/replay tests.
- **SC-006**: Emergency-egress fail-open behavior is applied and recorded in
  100% of tested emergency scenarios for zones configured to fail open, with the
  event visible on the dashboard.
- **SC-007**: Cross-tenant and cross-event ACS configuration, authorization, and
  event-callback requests are denied in 100% of automated tests, with responses
  indistinguishable from an unknown target.
- **SC-008**: When the ACS is unreachable or exceeds its latency budget, the
  configured fail-open/fail-closed behavior is applied and recorded in 100% of
  tested failure scenarios, with none silently dropped.
- **SC-009**: A gate/lane or ACS-integration status change (online, degraded,
  offline) is visible to authorized viewers within a short, bounded delay in
  100% of tested scenarios, and never displays another tenant's or event's data.
- **SC-010**: Every authorization decision, event callback, and emergency event
  produces complete sanitized audit evidence, and forced audit failure leaves
  zero partial state changes.
- **SC-011**: A median gate authorization decision from Zonetec is returned
  within a documented latency budget suitable for turnstile operation (target:
  under 500 ms at the application boundary) in load testing.

## Assumptions

- Phase 0 (tenant isolation, RBAC, audit, adapter interfaces, localization),
  Phase 1 (events, ticketing, orders, attendees, signed credential lifecycle),
  Phase 2 (wallet passes, scanning, check-in), and Phase 3 (kiosk, badge
  printing, manual desk) are complete and remain mandatory dependencies.
- `all_plan.md` is authoritative for scope: this phase covers ACS authorization,
  zone/lane mapping, rules, anti-passback, entry/exit logging, emergency egress,
  and gate health; identity verification (Phase 5) is out of scope and is only
  referenced where a lane uses an already-verified identity reference.
- The exact Runa ACS protocol (REST, WebSocket, TCP, MQTT, or other), its
  latency budget, its zone/lane representation, how emergency-egress signals
  arrive, and how anti-passback is currently implemented in the ACS are open
  integration questions in `all_plan.md`; they are treated as adapter-boundary
  and planning-phase decisions. This spec defines behavior in a protocol
  -agnostic way, and a mock/fake ACS behind the adapter interface is assumed for
  development and contract testing until the real contract is documented.
- Gate hardware release is performed by the external ACS; Zonetec's
  responsibility is the allow/deny decision, the rules, the event/state
  records, and the dashboards — not directly driving physical gate hardware.
- Anti-passback state is derived from Zonetec-recorded entry/exit events;
  reconciliation behavior when the ACS also maintains its own anti-passback is a
  planning-phase decision within the adapter contract.
- Fail-open vs fail-closed on ACS unavailability is an organizer/operator
  configuration per zone, with a documented safe default; life-safety
  emergency-egress fail-open is configurable per zone independently of the
  general unavailability behavior.
- Occupancy/reporting surfaced here reuses the existing Event/Reporting modules;
  deep analytics and marketplace-level reporting are later-phase work.
- This phase excludes identity verification methods and providers, venue
  marketplace, and any ACS vendor beyond the adapter-mediated Runa integration
  described above.
