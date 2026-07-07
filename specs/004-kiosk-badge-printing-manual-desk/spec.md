# Feature Specification: Kiosk Check-In, Badge Printing, and Manual Desk

**Feature Branch**: `004-kiosk-badge-printing-manual-desk`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "Phase 3"

**Product Phase**: Phase 3 Kiosk-Badge-Printing-Manual-Desk

**Deployment Modes**: SaaS and on-premise

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Staff Operate the Manual Desk for Check-In and Badge Printing (Priority: P1)

Authorized staff at a manual desk search for a registered attendee by name,
email, or phone; view their credential and check-in status; check them in;
and print their badge, without requiring a kiosk device.

**Why this priority**: The manual desk is the fallback and staff-operated
path that must exist before any self-service kiosk can be trusted on-site;
it reuses Phase 2's scan/check-in core and delivers standalone value even if
no kiosk is deployed for an event.

**Independent Test**: A staff member with desk permissions can search for an
attendee by partial name, email, or phone; see their current credential and
check-in state; check them in; and produce a printed badge containing the
attendee's configured badge fields, all without a kiosk device present.

**Acceptance Scenarios**:

1. **Given** an authorized staff member at the manual desk, **When** they
   search by name, email, or phone, **Then** matching attendees for the
   current event and tenant are returned with their credential and check-in
   status, and no other event's or tenant's attendees appear.
2. **Given** a matched attendee with an active credential, **When** staff
   select "check in," **Then** the same scan/check-in rules from Phase 2
   apply (duplicate, revoked, and expired credentials are rejected) and a
   scan event is recorded attributing the action to the manual desk.
3. **Given** a checked-in attendee, **When** staff request a badge print,
   **Then** a badge print job is created containing the attendee's name,
   ticket type, and QR, rendered from the event's active badge template.
4. **Given** a badge template is not yet configured for the event, **When**
   staff attempt to print, **Then** the system rejects the print with a clear
   reason rather than producing a badge with missing or default content.

---

### User Story 2 - Attendee Self-Service Check-In and Badge Print at a Kiosk (Priority: P1)

An attendee approaches a registered, healthy kiosk, presents their QR
credential (from a printed ticket, confirmation page, or wallet pass) or
looks themselves up by name/email/phone, and receives a printed badge after
a successful check-in, without staff assistance.

**Why this priority**: Self-service kiosk check-in is the primary scope
objective of this phase and the main way attendee volume is handled on-site
without proportionally scaling staff; it depends on the same check-in and
badge-printing foundation as User Story 1 but adds the unattended device and
lookup-without-staff path.

**Independent Test**: A kiosk registered to an event can scan a valid QR
credential, or accept a name/email/phone lookup when no QR is available,
check the attendee in under the same validation rules as staff scanning, and
produce a printed badge, while rejecting duplicate, revoked, or expired
credentials with a clear on-screen message and no staff intervention.

**Acceptance Scenarios**:

1. **Given** a kiosk registered and healthy for the current event, **When**
   an attendee presents a valid, unscanned QR credential, **Then** the kiosk
   displays a success message, checks the attendee in, and prints their
   badge.
2. **Given** an attendee without a scannable QR, **When** they search by
   name, email, or phone at the kiosk, **Then** the kiosk returns only
   matches for its registered event and, optionally, requires the attendee
   to confirm a one-time code before proceeding, to reduce misidentification.
3. **Given** a credential already checked in under single-entry rules,
   **When** it is presented again at a kiosk, **Then** the kiosk shows a
   clear "already checked in" message and does not print a duplicate badge
   without staff override.
4. **Given** a revoked or expired credential, **When** it is presented at a
   kiosk, **Then** the kiosk shows a clear rejection message consistent with
   Phase 2's scan rejection reasons and does not print a badge.
5. **Given** a kiosk that is registered to one event, **When** a credential
   for a different event or tenant is presented, **Then** the kiosk rejects
   it in a way indistinguishable from an unknown credential.

---

### User Story 3 - Reprint a Badge with Permission and Reason (Priority: P2)

Authorized staff reprint a lost, damaged, or incorrect badge for an
already-checked-in attendee, after providing a required reason, from either
the manual desk or a kiosk in staff-assisted mode.

**Why this priority**: Badges get lost, damaged, or misprinted during real
events; reprinting is an operational necessity but depends on User Stories 1
and 2 already producing an initial badge and check-in record.

**Independent Test**: A staff member with reprint permission can select a
previously badged attendee, provide a required reason, and receive a newly
printed badge; a staff member without reprint permission is blocked, and
every reprint attempt (successful or blocked) is recorded.

**Acceptance Scenarios**:

1. **Given** an attendee who already has a printed badge, **When** an
   authorized staff member requests a reprint and provides a reason,
   **Then** a new badge print job is created, linked to the original, and an
   audit record captures the actor, attendee, and reason.
2. **Given** a staff member without reprint permission, **When** they attempt
   a reprint, **Then** the request is rejected and no badge print job is
   created.
3. **Given** a reprint request without a provided reason, **When** it is
   submitted, **Then** the system rejects the request until a reason is
   supplied.
4. **Given** an organizer has configured old-badge QR revocation on reprint,
   **When** a reprint completes, **Then** the prior badge's QR is invalidated
   for entry purposes consistent with Phase 2 credential/scan rules.

---

### User Story 4 - Register a Walk-Up Attendee at the Manual Desk (Priority: P2)

Authorized staff register an attendee who arrives without a prior
registration record — a walk-up — directly at the manual desk, when the
organizer has enabled walk-up registration for the event, and immediately
check them in and print their badge.

**Why this priority**: Walk-ups are common at in-person events and require
staff-operated registration, but this capability builds on the already
-working check-in and badge printing paths from User Stories 1-2 rather than
being a prerequisite for them.

**Independent Test**: With walk-up registration enabled for an event, staff
can create a new attendee record with the minimum required fields, generate
a credential for them consistent with Phase 1 issuance rules, check them in,
and print their badge, all in one desk session; when walk-up registration is
disabled for an event, the option is unavailable.

**Acceptance Scenarios**:

1. **Given** an event with walk-up registration enabled, **When** staff
   create a walk-up attendee with the required minimum fields, **Then** an
   attendee record and a signed credential are created consistent with
   Phase 1 issuance rules.
2. **Given** a newly created walk-up attendee, **When** staff proceed to
   check-in, **Then** the same check-in and badge-printing behavior from
   User Story 1 applies.
3. **Given** an event with walk-up registration disabled, **When** staff open
   the manual desk, **Then** no walk-up registration option is offered.
4. **Given** a walk-up registration requiring payment collection, **When** an
   organizer has not enabled an on-site payment method, **Then** the walk-up
   flow clearly indicates payment cannot be collected on-site rather than
   silently registering an unpaid attendee as paid.

---

### User Story 5 - Organizer Designs a Badge Template Without Code (Priority: P2)

An authorized organizer creates and edits a badge template for their event —
choosing which fields appear (name, company, job title, QR, ticket type,
tier, zone, sponsor/organizer logos, color coding) and the paper/printer
target — without engineering involvement, and activates it for use by kiosks
and the manual desk.

**Why this priority**: Kiosks and the manual desk cannot print anything
meaningful without a configured template; this is required before User
Stories 1-2 can produce real badges for a given event, but is organizer setup
work rather than the on-site attendee/staff interaction being tested.

**Independent Test**: An organizer can create a badge template, assign
supported fields and a paper/printer target, save it as a draft, activate
it, and see it immediately used by subsequent badge print jobs for that
event, without any code change or engineering ticket.

**Acceptance Scenarios**:

1. **Given** an authorized organizer, **When** they create a badge template
   with a name, selected fields, and a paper/printer target, **Then** the
   template is saved and available for later activation.
2. **Given** a draft badge template, **When** the organizer activates it,
   **Then** subsequent badge print jobs for that event use the active
   template's field layout.
3. **Given** an event with no active badge template, **When** staff or a
   kiosk attempt to print, **Then** the print request is rejected with a
   clear configuration reason rather than falling back to an undocumented
   default.
4. **Given** an organizer authorized for one event only, **When** they view
   badge templates, **Then** no other tenant's or event's templates are
   visible or editable.

---

### User Story 6 - Operations Monitor Kiosk and Printer Health (Priority: P3)

Authorized operations or organizer staff view the live status (online,
offline, degraded) of every kiosk and its connected printer for an event, so
they can respond to hardware or connectivity problems during the event.

**Why this priority**: Health visibility is important for smooth on-site
operations but is a monitoring capability layered on top of already
-functioning kiosks and printers from User Story 2; it delivers no standalone
attendee- or staff-facing value on its own.

**Independent Test**: As a kiosk's connectivity or printer status changes, an
authorized viewer sees the kiosk's status update (online, offline, printer
error) within a short, bounded delay, scoped only to their authorized event.

**Acceptance Scenarios**:

1. **Given** a kiosk that stops sending heartbeats, **When** the configured
   offline threshold elapses, **Then** its status changes to offline and is
   visible to authorized viewers.
2. **Given** a kiosk's printer reports an error (e.g., out of paper, jammed),
   **When** the kiosk relays that status, **Then** the printer error is
   visible alongside the kiosk's own status.
3. **Given** an operations viewer authorized for one event only, **When**
   they view kiosk health, **Then** no other tenant's or event's kiosk
   status is visible.

### Edge Cases

- A kiosk loses network connectivity mid-session after a QR scan but before
  the check-in and badge print complete.
- A printer runs out of paper, jams, or disconnects mid-print-job.
- Two kiosks, or a kiosk and the manual desk, attempt to check in the same
  credential within the same instant.
- A kiosk's registered event ends or is cancelled while the kiosk remains
  powered on and reachable.
- An attendee's name/email/phone lookup at a kiosk returns multiple
  plausible matches.
- A walk-up registration is attempted for an event that has reached its
  capacity limit.
- A badge template is edited or deactivated while print jobs referencing it
  are still queued or in flight.
- A reprint is requested for an attendee who was never issued an initial
  badge.
- An actor lacks the specific desk, kiosk-management, badge-print, or
  badge-reprint permission required for the requested action.
- Cross-tenant or cross-event attendee, credential, kiosk, or badge template
  identifiers are looked up, scanned, or referenced in a print job.
- Audit persistence fails during a check-in, badge print, reprint, walk-up
  registration, or kiosk health transition.
- The kiosk and manual desk experience is used in Arabic/RTL and
  English/LTR contexts, including badge layouts and printed content.
- A kiosk or printer adapter is unreachable in an on-premise deployment with
  no outbound cloud connectivity.

## Requirements *(mandatory)*

### Functional Requirements

#### Kiosk Registration, Sessions, and Health

- **FR-001**: The system MUST allow an authorized organizer or operations
  actor to register a kiosk device to a specific event and tenant.
- **FR-002**: A kiosk MUST authenticate its session (including a
  configurable kiosk-level confirmation step) before it can submit scans,
  lookups, or print requests.
- **FR-003**: The system MUST track kiosk status (online, offline, degraded)
  based on periodic heartbeats and MUST track the connected printer's
  reported status where the printer adapter supports it.
- **FR-004**: A kiosk MUST be usable only for the event and tenant it is
  registered to; any scan, lookup, or print request naming a different
  event or tenant MUST be rejected.

#### Attendee Lookup and Check-In

- **FR-005**: Staff at the manual desk and attendees at a kiosk MUST be able
  to look up an attendee by QR credential, name, email, or phone, scoped to
  the current event and tenant only.
- **FR-006**: A name/email/phone lookup MAY require an additional
  confirmation step (such as a one-time code) before check-in when
  configured, to reduce misidentification risk.
- **FR-007**: Check-in performed at a kiosk or the manual desk MUST apply
  the same credential validation and single-entry/duplicate rules already
  defined for staff QR scanning, including override permission and reason
  requirements for authorized exceptions.
- **FR-008**: Every check-in attempt at a kiosk or the manual desk,
  regardless of outcome, MUST create a record attributing the scanning
  source (kiosk identifier or manual desk) consistent with existing scan
  event tracking.

#### Badge Templates and Printing

- **FR-009**: Authorized organizers MUST be able to create, edit, activate,
  and deactivate badge templates for their event without requiring a code
  change, specifying which supported fields appear and the target
  paper/printer type.
- **FR-010**: A badge print job MUST only be created when the target event
  has an active badge template; printing without an active template MUST be
  rejected with a clear, actionable reason.
- **FR-011**: A badge print job MUST render only the fields configured in
  the active template at the time the job is created, and MUST NOT include
  personal data beyond what the template explicitly configures.
- **FR-012**: The system MUST track each badge print job's status (queued,
  printed, failed) and associate it with the attendee, credential, and
  originating kiosk or desk user.

#### Reprints

- **FR-013**: A badge reprint MUST require an explicit reprint permission
  distinct from the initial print permission.
- **FR-014**: A badge reprint MUST require a recorded reason before the
  reprint job is created.
- **FR-015**: When an organizer has enabled old-badge QR revocation on
  reprint, completing a reprint MUST invalidate the prior badge's QR for
  entry purposes.

#### Walk-Up Registration

- **FR-016**: The system MUST support an organizer-configurable toggle for
  whether walk-up registration is allowed for an event.
- **FR-017**: When walk-up registration is enabled, authorized manual desk
  staff MUST be able to create a new attendee record and an associated
  signed credential using the same issuance rules defined for standard
  registration.
- **FR-018**: When walk-up registration requires payment and no on-site
  payment method is enabled for the event, the system MUST clearly indicate
  that payment cannot be collected rather than silently marking the
  attendee as paid.
- **FR-019**: When walk-up registration is disabled for an event, the manual
  desk MUST NOT offer the option.

### Constitutional Requirements *(mandatory)*

- **CR-001 Tenant Scope**: Kiosks, kiosk sessions, badge templates, badge
  print jobs, walk-up registrations, and kiosk/printer health records are
  tenant-owned and event-scoped. Kiosk and desk requests MUST resolve
  tenant and event context authoritatively from the kiosk's registration or
  the authenticated staff session, never from an unverified client-supplied
  identifier. Cross-tenant or cross-event access MUST fail closed and
  return a response indistinguishable from an unknown target.
- **CR-002 RBAC**: This phase adds least-privilege permissions for kiosk
  management/registration, manual desk operation, badge print, badge
  reprint, walk-up registration, and kiosk/printer health viewing. On-site
  staff and kiosks receive only the permissions required for their assigned
  event without gaining organizer financial, attendee-editing, or
  credential-revocation privileges unless separately granted. Every
  override and reprint requires its own permission and a recorded reason.
- **CR-003 Auditability**: Kiosk registration and status changes; every
  check-in performed at a kiosk or manual desk; every badge print and
  reprint (including blocked attempts); walk-up registration; and badge
  template creation, activation, and deactivation MUST record actor type
  and identifier, tenant, event, action, target, outcome, reason code where
  applicable, correlation, channel, and timestamp. This audit evidence MUST
  commit together with the underlying state change or fail together.
- **CR-004 Credential Security**: Kiosk and manual desk check-in MUST reuse
  the Phase 1 signed credential and Phase 2 scan validation paths without
  introducing a separate trust path; badge printing exposes only the same
  QR credential already issued to the attendee, never a newly minted or
  differently scoped identifier. Reprints that revoke a prior badge's QR
  MUST use the existing credential revocation/reissue mechanism rather than
  a badge-specific shortcut.
- **CR-005 Data and PDPL**: Badge templates and printed badges display only
  organizer-configured, purpose-limited attendee fields (name, company, job
  title, ticket type, tier, zone, QR) collected for on-site identification
  and entry, and MUST NOT display national identifiers, biometric data, or
  payment information. Walk-up registration collects only the minimum
  fields required for credential issuance and check-in. This data follows
  the tenant's approved retention, residency, and deletion rules
  established in Phase 1, and anonymization of attendee data MUST preserve
  required audit and print-job evidence in a form that no longer discloses
  personal identity where legally required.
- **CR-006 API and Integrations**: Kiosk registration, session
  authentication, lookup, check-in, badge print/reprint, walk-up
  registration, and kiosk/printer health MUST have documented versioned
  API contracts. Printer output MUST be exposed through a provider-neutral
  printer adapter interface with explicit authentication, timeout, retry,
  idempotency, error mapping, and production-readiness evidence; adapter
  specific payloads and errors MUST NOT leak into public contracts or
  client-visible errors.
- **CR-007 White-Label and Localization**: Kiosk on-screen messages, manual
  desk staff UI, and printed badge content MUST support Arabic/RTL and
  English/LTR presentation, tenant branding (organizer/sponsor logos, color
  coding), and locale-aware text. Where a printer or paper format limits
  bilingual layout, the limitation MUST be documented and a safe fallback
  defined rather than silently defaulting to one language.
- **CR-008 Deployment Parity**: Kiosk registration, manual desk check-in,
  and badge printing MUST remain operable in both SaaS and on-premise
  deployments, including when a kiosk or printer adapter is temporarily
  unreachable; the system MUST report an explicit degraded or failed state
  in that case rather than silently dropping the check-in or print request.
  Kiosk/printer health monitoring MUST be documented for both deployment
  modes and MUST NOT depend on continuous cloud connectivity for local
  check-in to continue functioning.
- **CR-009 Automated Verification**: Required verification includes kiosk
  registration and session authentication tests; kiosk and manual desk
  check-in tests covering accepted, duplicate, revoked, and expired
  outcomes consistent with Phase 2; badge template CRUD and activation
  tests; badge print and reprint permission, reason, and audit tests;
  walk-up registration tests including the disabled-toggle and
  payment-not-collected cases; cross-tenant and cross-event denial tests for
  every kiosk/desk/badge endpoint; kiosk/printer health status tests; and
  audit atomicity tests for check-in, print, reprint, and registration state
  changes.
- **CR-010 Phase Alignment**: This phase delivers the third product
  increment defined by `all_plan.md`: kiosk device registration and
  sessions, kiosk and manual desk check-in reusing the Phase 2 scan core,
  a no-code badge template designer, badge print/reprint with permission and
  reason enforcement, walk-up registration, and kiosk/printer health
  monitoring. It depends on the accepted Phase 0 tenant/RBAC/audit
  foundation, the accepted Phase 1 credential issuance/revocation/reissue
  core, and the accepted Phase 2 scan/check-in validation core. Zone/lane
  mapping, gate authorization, and anti-passback (Phase 4), identity
  verification (Phase 5), and venue marketplace (Phase 6) remain later-phase
  work and are explicitly out of scope here.

### Key Entities

- **Kiosk**: A tenant- and event-owned physical or virtual check-in device
  with a device name/code, location, online/offline/degraded status, last
  seen time, and its connected printer's reported status.
- **Badge Template**: A tenant- and event-owned, organizer-configured
  layout defining which attendee fields appear on a printed badge, the
  target paper size and printer type, and its draft/active/inactive status.
- **Badge Print Job**: A tenant- and event-scoped record of one print
  attempt, referencing the attendee, credential, badge template, originating
  kiosk or desk user, status (queued, printed, failed), and, for reprints,
  the reprint reason and link to the original print job.
- **Scan Event** *(from Phase 2, extended)*: Gains kiosk and manual desk as
  additional scanning sources alongside staff phone and handheld scanner.
- **Attendee** *(from Phase 1, extended)*: Gains a walk-up origination flag
  for records created directly at the manual desk rather than through
  standard registration.
- **Credential** *(from Phase 1, referenced not redefined)*: The signed,
  revocable, reissuable QR identity that kiosk and manual desk check-in
  validate against, and that a reprint may revoke and replace when old-badge
  QR revocation is enabled.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An attendee with a valid QR credential completes self-service
  kiosk check-in and receives a printed badge in under 30 seconds in 95% of
  tested kiosk interactions.
- **SC-002**: A manual desk staff member can locate an attendee by name,
  email, or phone and complete check-in and badge printing in under 60
  seconds in 90% of tested desk interactions.
- **SC-003**: 100% of duplicate, revoked, and expired credential check-ins
  at a kiosk or manual desk are rejected in automated security testing, with
  zero false accepts.
- **SC-004**: 100% of badge print attempts against an event with no active
  badge template are rejected with a clear configuration reason, with zero
  badges printed with missing or default content.
- **SC-005**: 100% of badge reprints in automated testing require both a
  distinct permission and a recorded reason, with zero reprints completing
  without either.
- **SC-006**: Cross-tenant and cross-event kiosk, lookup, check-in, badge,
  and walk-up requests are denied in 100% of automated tests, with responses
  indistinguishable from an unknown target.
- **SC-007**: A kiosk or printer status change (online, offline, degraded,
  printer error) is visible to authorized viewers within a short, bounded
  delay in 100% of tested scenarios, and never displays another tenant's or
  event's kiosk data.
- **SC-008**: Every kiosk registration, check-in, badge print, reprint, and
  walk-up registration produces complete sanitized audit evidence, and
  forced audit failure leaves zero partial state changes.
- **SC-009**: Organizers can create and activate a usable badge template
  without engineering involvement in 100% of tested configuration
  scenarios.
- **SC-010**: On-site staff and pilot attendees report at least 90%
  satisfaction with kiosk and manual desk check-in speed and clarity during
  pilot acceptance testing.

## Assumptions

- Phase 0 (tenant isolation, RBAC, audit, adapters, localization), Phase 1
  (events, ticketing, orders, attendees, and the signed credential
  lifecycle), and Phase 2 (wallet passes, staff QR scanning, check-in
  summaries) are complete and remain mandatory dependencies.
- `all_plan.md` is authoritative: zone/lane mapping, gate authorization, and
  anti-passback enforcement belong to Phase 4 and are out of scope here;
  this phase's kiosk and manual desk check-in reuse Phase 2's single-entry
  and override rules without introducing zone- or lane-aware access logic.
- Kiosk hardware is treated as a managed, registered device operating
  through a provider-neutral printer adapter; exact printer make/model
  integration details are planning-phase inputs and must satisfy the
  adapter contract and production-readiness evidence before enabling live
  printing.
- Kiosk name/email/phone lookup with optional one-time-code confirmation is
  a configurable anti-misidentification control; the exact confirmation
  channel (SMS, email, on-screen code) is a planning-phase decision within
  the existing notification adapter boundary.
- Badge templates support a defined, bounded set of fields (name, company,
  job title, QR, ticket type, tier, zone, sponsor logo, organizer logo,
  color coding) rather than fully arbitrary custom layouts in this phase.
- Walk-up registration reuses Phase 1's registration and credential issuance
  rules; it does not introduce a separate identity or payment model, and
  on-site payment collection depends on an organizer-enabled payment method.
- Kiosk/printer health monitoring in this phase is limited to status
  visibility (online/offline/degraded, printer error) for one event at a
  time; deeper fleet management, remote configuration push, and predictive
  maintenance are later-phase or out-of-scope work.
- This phase excludes ACS zones/lanes/authorization rules and anti-passback,
  identity verification, venue marketplace, and any hardware adapter beyond
  kiosk scanning/lookup input and printer output described above.
