# Feature Specification: Venue Marketplace

**Feature Branch**: `009-venue-marketplace`

**Created**: 2026-07-14

**Status**: Draft

**Input**: User description: "Phase 6 — Venue Marketplace"

**Product Phase**: Phase 6 — Venue Marketplace (depends on Foundation and the
accepted event, venue-infrastructure, access-control, and identity phases)

**Deployment Modes**: both (SaaS and on-premise)

## User Scenarios & Testing *(mandatory)*

Venue Marketplace lets a venue-owner organization publish selected fixed
infrastructure for business rental, lets an event organizer request available
assets for a specific event and operating window, and lets the venue owner
approve the request before narrowly scoped control becomes active. The control
ends automatically, the commercial outcome is summarized in a settlement
statement, and every marketplace or delegated-control decision is auditable.

Only intentionally published catalog information crosses organization
boundaries. Private venue data, unpublished assets, other organizer requests,
operational credentials, and tenant administration remain isolated.

Each story below is an independently testable product slice. Together they form
the complete Phase 6 workflow.

### User Story 1 - Publish a venue and rentable assets (Priority: P1)

A venue owner creates a venue profile, records its fixed infrastructure and
capabilities, defines availability and fixed pricing, and publishes only the
assets that organizers may discover.

**Why this priority**: A trustworthy, owner-controlled catalog is the minimum
viable marketplace and the prerequisite for every rental workflow.

**Independent Test**: As a Venue Owner Admin, create a draft venue, add one of
each supported asset type, define capabilities, availability, price and
currency, publish selected assets, and confirm an organizer can see only the
published business-facing fields while drafts and owner-only data remain
hidden.

**Acceptance Scenarios**:

1. **Given** a venue-owner organization and an authorized owner, **When** the owner creates a venue with a name, location, timezone and bilingual description, **Then** the venue is saved as a private draft owned by that organization.
2. **Given** a draft venue, **When** the owner adds a turnstile, security gate, camera, kiosk, printer, scanner, access lane or access zone, **Then** the owner can record its location, capabilities, throughput where applicable, status, availability and fixed price.
3. **Given** a complete active asset, **When** the owner publishes it, **Then** organizers can discover only its approved catalog fields and bookable windows.
4. **Given** an asset with incomplete pricing, invalid availability or an inactive status, **When** the owner attempts to publish it, **Then** publication is rejected with field-specific guidance.
5. **Given** an asset used by a future approved rental, **When** the owner attempts to unpublish or make it unavailable for that reserved window, **Then** the change is blocked until the rental is cancelled or otherwise resolved.

---

### User Story 2 - Discover assets and request them for an event (Priority: P2)

An event organizer searches published venue infrastructure, checks availability
for an event window, selects suitable assets, reviews an itemized price, and
submits one rental request to the venue owner.

**Why this priority**: Discovery and request submission produce the marketplace
demand that venue owners can act on while preserving owner approval.

**Independent Test**: As an organizer with an eligible event, search by city,
venue, asset type, capability, capacity and date; choose several assets from one
venue; submit the request; and confirm the request contains an immutable event,
window and price snapshot visible to both parties.

**Acceptance Scenarios**:

1. **Given** published assets, **When** an organizer filters by location, asset type, capability, capacity and required window, **Then** only assets published for organizer discovery and available for the complete requested window are returned.
2. **Given** an event owned by the organizer, **When** the organizer selects assets from one venue and a window covering event operations, **Then** the platform shows an itemized price, currency and total before submission.
3. **Given** a valid selection, **When** the organizer submits the request, **Then** one requested rental is created with the event, venue, assets, requested window and price snapshot, and the venue owner is notified.
4. **Given** an event outside the organizer's organization, an unpublished asset, mixed venues, mixed currencies or an unavailable window, **When** submission is attempted, **Then** no rental is created and the reason is explained without revealing private data.
5. **Given** the same submission is repeated, **When** it carries the same request identity, **Then** it returns the original rental rather than creating a duplicate.

---

### User Story 3 - Approve or reject a conflict-safe rental (Priority: P3)

The venue owner reviews the event, requested assets, operating window and price
snapshot, then approves or rejects the entire request with a recorded decision.
Approval reserves every requested asset atomically so overlapping rentals cannot
both be approved.

**Why this priority**: Owner approval protects venue operations and turns a
request into a reliable reservation without accidental double-booking.

**Independent Test**: Submit two overlapping requests for the same asset;
approve the first; confirm the second cannot be approved; reject the second
with a reason; and verify both parties see the correct state and audit trail.

**Acceptance Scenarios**:

1. **Given** a requested rental and an owner with approval authority, **When** the owner reviews it, **Then** the owner sees the event summary, assets, requested window, itemized price, total and any current availability conflicts.
2. **Given** every asset remains available, **When** the owner approves, **Then** all requested assets are reserved together and the rental becomes approved; partial or silent approval is not allowed.
3. **Given** any requested asset overlaps an approved or active rental, **When** approval is attempted, **Then** the entire approval is rejected as a conflict and neither request nor asset is partially changed.
4. **Given** a requested rental, **When** the owner rejects it, **Then** a reason is required, no asset is reserved, and the organizer is notified.
5. **Given** an approved rental whose control window has not begun, **When** the organizer cancels it or the owner revokes it with a reason, **Then** its reservations are released, its history is retained, and both parties are notified.

---

### User Story 4 - Use and revoke time-boxed delegated control (Priority: P4)

During the approved window, authorized members of the organizer organization
can configure only the rented assets for the linked event. The delegation never
replaces normal role permissions, can be revoked by the venue owner, and expires
without manual intervention.

**Why this priority**: Time-boxed control delivers the operational value of the
rental while keeping the venue owner in control and preserving the existing
credential and access-control trust paths.

**Independent Test**: Approve a rental with a short window; confirm the
organizer cannot configure the asset before it starts, can configure only that
asset for the linked event while active, loses access immediately after owner
revocation, and remains denied after the scheduled end.

**Acceptance Scenarios**:

1. **Given** an approved rental before its start time, **When** an organizer attempts asset control, **Then** access is denied as not yet active.
2. **Given** the rental start time has arrived and all preconditions remain valid, **When** an organizer member with the matching operational permission configures a rented asset for the linked event, **Then** the action is allowed and audited.
3. **Given** an active rental, **When** the organizer targets another event, an unrented asset, an operation outside the asset capability, or an action beyond the approved window, **Then** access is denied without changing venue state.
4. **Given** an active rental, **When** the venue owner revokes delegated control with a reason, **Then** new control actions are denied immediately and both parties see the revoked state.
5. **Given** the approved end time passes, **When** no person intervenes, **Then** delegated control expires automatically and cannot be restored without a new approved rental.
6. **Given** delegated control is unavailable because a venue adapter cannot be reached, **When** either party views the rental, **Then** the degraded state is explicit, retries cannot extend the window, and no successful control outcome is fabricated.

---

### User Story 5 - Review settlement and marketplace activity (Priority: P5)

After a rental completes, the venue owner and authorized finance users receive
an itemized settlement statement. Platform administrators can review
marketplace activity, statements and reported disputes without gaining ordinary
control of venue assets.

**Why this priority**: Statements and oversight close the commercial record and
support reconciliation, accountability and exception handling; automated money
movement is intentionally outside this phase.

**Independent Test**: Complete a rental; confirm one statement is generated
from the approved price snapshot; let each party view the same totals; report a
dispute; and verify a platform administrator can record an outcome without
altering the original statement or moving funds.

**Acceptance Scenarios**:

1. **Given** a rental reaches completed, cancelled or revoked status, **When** its commercial outcome is finalized, **Then** one itemized statement records the parties, event, venue, assets, agreed window, price snapshot, currency, total and outcome.
2. **Given** a statement, **When** an authorized venue finance user, organizer finance user or scoped platform administrator views it, **Then** each sees the same immutable commercial facts in their permitted scope.
3. **Given** a party disputes a statement, **When** it supplies a reason, **Then** the statement is marked disputed, the original remains unchanged, and the platform administrator can record notes and an outcome in the audit history.
4. **Given** a duplicate completion or recovery event, **When** statement generation runs again, **Then** no duplicate statement is created.
5. **Given** a statement exists, **When** it is viewed or exported, **Then** access is permission-checked and audited; the statement does not claim that payment or payout occurred unless a separately accepted payment capability has supplied that fact.

### Edge Cases

- **Overlapping requests**: Multiple pending requests may overlap, but approval of one atomically reserves every selected asset and prevents approval of any conflicting request.
- **Availability or price changes after submission**: A submitted request retains its availability and price snapshot. The owner cannot silently edit it; changed terms require rejection/cancellation and a new request.
- **Timezone and daylight changes**: Availability, event windows, delegation start/end and statements use the venue timezone for display and unambiguous instants for comparison, including daylight-saving boundaries where relevant.
- **Asset status changes**: An asset made offline or under maintenance blocks new requests. If already reserved or active, both parties receive a visible operational warning and the owner must revoke or resolve it explicitly.
- **Partial approval or partial activation failure**: A multi-asset request is approved as one unit. If one asset cannot activate, the rental shows a degraded state and never represents the whole set as operational.
- **Cross-tenant isolation**: Only explicitly published catalog fields and the counterpart's shared rental/statement view cross organization boundaries. Drafts, contacts not selected for sharing, unrelated rentals, internal notes, credentials, files, jobs, caches and operational events remain inaccessible.
- **Missing permission**: Controls are hidden for convenience but every read and action is independently denied by the trusted authorization boundary when the actor lacks the required role permission or active delegation.
- **Owner revocation during active use**: New control actions stop immediately; prior audit and operational records remain. Revocation never disables emergency egress or weakens existing safety behavior.
- **Audit failure**: A publication, rental decision, delegation change, control action, statement change or dispute outcome is not reported complete when its required audit record cannot be persisted.
- **Duplicate or delayed work**: Repeated submissions, approvals, activation, expiry and statement-generation attempts have one durable outcome and cannot extend access or duplicate financial records.
- **Credential security**: Delegated control does not mint, reveal or bypass attendee credentials, scanner secrets, device pairing secrets or access-control authorization rules.
- **Camera assets**: Cameras may be listed by capability and availability, but Phase 6 grants no live feed, recording access or biometric-processing rights.
- **Disconnected on-premise operation**: Already-approved local delegations continue only within their stored window and auto-expire locally. New remote discovery or cross-deployment requests show an unavailable state until connectivity returns.
- **Localization and accessibility**: Catalog, pricing, request decisions, delegated-control status, statements and disputes are complete in Arabic/RTL and English/LTR, with locale-aware dates, numbers and currencies and equivalent keyboard/screen-reader behavior.

## Requirements *(mandatory)*

### Functional Requirements

**Venue-owner accounts and profiles**

- **FR-001**: The system MUST support a distinct venue-owner organization/account type using the existing membership and role model, without granting venue privileges to organizer accounts by default.
- **FR-002**: An authorized venue owner MUST be able to create, update, archive and view venue profiles containing business name, bilingual description, address, city, country, timezone, status and approved marketplace contact details.
- **FR-003**: Venue profiles and assets MUST remain private drafts until explicitly published; archived, suspended or incomplete records MUST NOT appear in organizer discovery.

**Asset inventory, availability and pricing**

- **FR-004**: An authorized venue owner MUST be able to add, update, archive and view fixed assets of type turnstile, security gate, camera, kiosk, printer, scanner, access lane or access zone.
- **FR-005**: Each asset MUST record its venue, name, bilingual description, physical location, supported capabilities, capacity or throughput where applicable, operational status and marketplace publication status.
- **FR-006**: The owner MUST be able to define one or more availability windows per asset, and the system MUST reject invalid or internally overlapping windows.
- **FR-007**: The owner MUST be able to define a fixed per-hour, per-day or per-rental price and currency for an asset; a published asset MUST have complete, valid pricing.
- **FR-008**: The system MUST prevent changes to publication, availability or lifecycle state that would invalidate an approved or active rental unless that rental is first cancelled, revoked or otherwise resolved through an audited transition.

**Organizer discovery and requests**

- **FR-009**: An authorized organizer MUST be able to search and filter published assets by venue, city, asset type, capability, capacity, price/currency and availability for a requested window.
- **FR-010**: Discovery MUST expose only owner-approved catalog fields and MUST NOT expose draft assets, internal owner data, other organizers' requests or operational secrets.
- **FR-011**: A rental request MUST reference one organizer-owned event, one venue, at least one published asset, one requested start/end window and one currency; all selected assets MUST cover the complete window.
- **FR-012**: Before submission, the system MUST show an itemized quote and total calculated from the currently published prices, and submission MUST preserve an immutable snapshot of the selected assets, prices, currency and requested window.
- **FR-013**: Rental submission MUST revalidate event ownership, asset publication, availability, venue/currency consistency and actor permission; invalid requests MUST create no partial rental or reservation.
- **FR-014**: Repeating the same rental submission MUST return the original outcome and MUST NOT create a duplicate request.

**Owner decision and reservation**

- **FR-015**: The venue owner MUST be able to review submitted requests for owned venues with the event summary, requested assets, window, itemized price, total and any current conflicts.
- **FR-016**: An owner with rental-approval permission MUST be able to approve the complete request or reject it with a required reason; partial approval is outside this phase.
- **FR-017**: Approval MUST atomically confirm that every selected asset is still published, operational and available, then reserve them all; if any check fails, no selected asset is reserved and the request remains unapproved.
- **FR-018**: The system MUST prevent overlapping approved or active rentals for the same asset and window, including concurrent approval attempts.
- **FR-019**: The system MUST notify the counterpart when a request is submitted, approved, rejected, cancelled, revoked, activated, completed or disputed, without including secrets or unnecessary private data.
- **FR-020**: Before activation, an authorized organizer MAY cancel an approved rental and the venue owner MAY revoke it with a required reason; either action MUST release future reservations and retain history. Fees, refunds and penalties are not calculated in this phase.

**Delegated control**

- **FR-021**: An approved rental MUST create a delegation scoped simultaneously to the organizer organization, linked event, rented assets, permitted asset capabilities and approved start/end window.
- **FR-022**: Delegation MUST become usable no earlier than the approved start, MUST expire no later than the approved end, and MUST NOT be extended by delayed, repeated or failed background work.
- **FR-023**: Delegation MUST supplement rather than replace RBAC: an organizer member MUST have both an active rental delegation and the existing permission for the requested asset operation.
- **FR-024**: The owner MUST be able to revoke an active delegation immediately with a reason; every later control attempt MUST be denied unless a new rental is approved.
- **FR-025**: Delegated control MUST use the existing venue-device, kiosk, scanner and access-control application boundaries and MUST NOT bypass credential validation, emergency egress, anti-passback, device authentication or safety rules.
- **FR-026**: Every delegated control decision MUST verify the current event, asset, capability, time window, rental state and actor permission; denial MUST make no configuration change and MUST return a non-sensitive reason.
- **FR-027**: Adapter or connectivity failure MUST be shown as degraded or unavailable, MUST NOT be reported as successful control, and MUST NOT change the delegation window.

**Settlement, dispute and oversight**

- **FR-028**: The system MUST generate at most one settlement statement for a finalized rental, containing the owner and organizer organizations, event, venue, rented assets, agreed window, price snapshot, currency, total, rental outcome and creation time.
- **FR-029**: Settlement statements MUST be immutable commercial records; corrections MUST be represented as linked revisions or dispute outcomes rather than silent edits.
- **FR-030**: Authorized venue and organizer finance users MUST be able to view and export statements in their rental scope, while a platform administrator MAY use an explicit audited privileged path for marketplace oversight.
- **FR-031**: Either rental party MUST be able to report a statement dispute with a reason, and an authorized platform administrator MUST be able to record review notes and an outcome without changing the original commercial facts or initiating money movement.
- **FR-032**: Statements MUST distinguish quoted/agreed amounts from externally confirmed payments or payouts and MUST NOT imply that funds moved when no accepted payment capability provided that result.
- **FR-033**: Authorized platform administrators MUST be able to filter marketplace activity by organization, venue, event, rental status, date and dispute status without receiving ordinary venue-asset control.

**Cross-cutting experience**

- **FR-034**: Venue, catalog, rental, delegation, statement and dispute surfaces MUST provide loading, empty, validation, conflict, degraded, forbidden and retry states; submit controls MUST prevent accidental duplicate actions.
- **FR-035**: User-visible marketplace content and notifications MUST be available in Arabic and English, support RTL/LTR and tenant branding, and display venue-local dates plus locale-aware numbers and currencies.
- **FR-036**: Catalog and operational surfaces MUST meet equivalent keyboard, focus, screen-reader and responsive-layout behavior in Arabic and English.

### Constitutional Requirements *(mandatory)*

- **CR-001 Tenant Scope**: Venue profiles, assets, availability, pricing, reservations, rentals, delegations, statements, disputes, files, jobs, caches, events and audit records MUST retain trusted owner/participant organization scope. Cross-organization access is permitted only for explicitly published catalog fields or the named counterpart's shared rental/statement view. No client-supplied tenant identifier establishes trust; unrelated tenant access is denied and tested across synchronous requests, background work, caches, exports and adapter calls.
- **CR-002 RBAC**: Actors are Venue Owner Admin and Venue Asset Manager (venue.manage), an owner approver (rentals.approve), Organizer Admin/Event Manager (marketplace.manage for discovery and requests), authorized operational users (their existing kiosk/scanner/ACS permission plus active delegation), Finance Manager (reports.view), Auditor (audit.view), and Platform Admin through explicit marketplace oversight/dispute permissions. Least privilege applies, UI visibility is not authorization, platform oversight grants no ordinary asset control, and privileged access is audited.
- **CR-003 Auditability**: Account classification, venue/asset publication, availability and price changes, rental submission/approval/rejection/cancellation, reservation conflict, delegation activation/denial/revocation/expiry, delegated control, statement generation/view/export/revision, and dispute actions MUST record actor, owner/participant scope, action, target, event/rental correlation, timestamp and outcome. A required audited state change is not reported complete if audit persistence fails.
- **CR-004 Credential Security**: This phase neither issues nor validates attendee credentials. Delegated control MUST NOT reveal signing material, QR payloads, device secrets or paired-session credentials, and MUST NOT introduce a second credential trust path. Existing signature, expiry, revocation, replay, key-rotation, anti-passback and emergency rules remain authoritative.
- **CR-005 Data and PDPL**: Marketplace records primarily contain business, location, contact, operational and commercial data; named contacts may be personal data. Collection MUST be limited to catalog, contracting, operations, reconciliation and support purposes under the applicable contractual or approved business basis. Only approved business contact fields may be published. Retention, deletion, export and residency follow tenant policy plus applicable contractual/accounting holds; immutable audit evidence and legally retained statements are minimized rather than silently erased. Camera listing grants no feed, recording, biometric or attendee-data access.
- **CR-006 API and Integrations**: Venue, asset, availability, discovery, rental, delegation, statement and dispute capabilities MUST use documented, versioned application contracts with validation, authorization, deterministic errors and safe retry behavior. Fixed infrastructure remains behind the accepted ACS, kiosk, scanner and device adapter boundaries, each with timeout, retry, idempotency, observability and test doubles. No payment/payout adapter or remote camera-feed integration is required by this phase.
- **CR-007 White-Label and Localization**: Owner and organizer surfaces, notifications, exported statements and status/reason labels MUST honor the viewing tenant's validated branding and support Arabic/English, RTL/LTR, locale-aware venue dates, numbers and currencies, and equivalent accessibility without tenant-specific code forks. Shared commercial facts remain identical across localized views.
- **CR-008 Deployment Parity**: The same catalog, rental, delegation, statement, isolation, RBAC and audit rules MUST work in SaaS and supported on-premise deployments. An isolated on-premise deployment may operate a marketplace among organizations on that deployment. Cross-deployment federation is outside Phase 6. When remote connectivity is unavailable, new remote discovery/request actions show an unavailable state, while locally stored approved delegations remain bounded and auto-expire locally; no cloud dependency may silently extend control.
- **CR-009 Automated Verification**: Required verification includes venue/profile lifecycle; every asset type; capability, availability and fixed-pricing rules; catalog publication/privacy; search/filter behavior; quote snapshots; request validation and duplicate submission; concurrent conflict-safe approval; rejection/cancellation/revocation; delegation start/end, RBAC composition and scope denial; adapter degradation; statement idempotency and disputes; Arabic/English, RTL/LTR, accessibility and branding; cross-tenant catalog/shared/private matrices; audit failure atomicity; API contract compatibility; and SaaS/on-premise parity.
- **CR-010 Phase Alignment**: This is Phase 6 from all_plan.md. It depends on accepted Foundation tenant context, membership/RBAC, audit, idempotency, notifications, localization, adapter and deployment boundaries; Phase 1 events; Phase 3 fixed kiosk/printer/scanner capabilities; Phase 4 ACS assets and control; and Phase 5 completion. It MUST NOT begin until Phase 5 passes its Definition of Done or weaken earlier contracts. Phase 7 on-premise enterprise hardening and Phase 8 launch/scale remain later work.

### Key Entities *(include if feature involves data)*

- **Venue-owner Organization/Account**: A distinct organization classification using existing users, memberships and roles; owns venue profiles, assets and rental decisions.
- **Venue**: Owner-scoped physical venue with name, bilingual description, address, city, country, timezone, status, approved marketplace contact and publication state.
- **Venue Asset**: Fixed rentable infrastructure owned by one venue; type, name, description, location, capabilities, capacity/throughput, operational status, publication status, pricing and currency.
- **Asset Availability**: A venue-local bookable interval for one asset, with status and any resulting reservation relationship.
- **Asset Price**: The active fixed pricing model and amount for an asset; supported models are per-hour, per-day and per-rental, with one currency per rental.
- **Rental Request**: The organizer's event-bound request to one venue, with requested window, status, selected assets, immutable price snapshot, total, owner decision/reason and lifecycle timestamps.
- **Rental Asset**: One asset and its agreed price within a rental; links the commercial selection to its reservation and delegation state.
- **Asset Reservation**: The conflict-control record that prevents overlapping approved/active use of an asset for the same window.
- **Control Delegation**: The time-boxed authorization relationship among owner organization, organizer organization, event, rental assets, allowed capabilities and start/end times; status includes pending, active, revoked, expired and completed.
- **Settlement Statement**: Immutable, itemized commercial summary of one finalized rental, its parties, agreed prices, currency, total and outcome; it records but does not itself move money.
- **Marketplace Dispute**: A reasoned challenge to a rental or statement with reporter, review notes, status, outcome and audit history; it does not rewrite the original statement.
- **Marketplace Audit Entry**: Tamper-evident record of actor, participating scopes, action, target, correlation, timestamp and outcome for marketplace and delegated-control events.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A trained venue owner can create and publish a complete venue with five rentable assets, availability and pricing in under 15 minutes, with no private draft field exposed to organizers.
- **SC-002**: At least 90% of organizers can find suitable assets and submit a valid event-bound request on their first attempt in under 5 minutes.
- **SC-003**: At least 95% of catalog searches covering up to 10,000 published assets show the first useful results within 2 seconds under the agreed operating load.
- **SC-004**: 100% of approved rentals have complete asset, event, party, price and window snapshots, and 0 overlapping approved or active rentals exist for the same asset.
- **SC-005**: 100% of organizer control attempts before start, after expiry/revocation, for another event, for an unrented asset or without the matching permission are denied without changing venue configuration.
- **SC-006**: Authorized organizer control becomes available within 60 seconds of the approved start and becomes unavailable within 60 seconds of expiry, or immediately on owner revocation, without manual cleanup.
- **SC-007**: 100% of finalized rentals produce no more than one itemized settlement statement within 5 minutes of finalization, and every viewer sees identical commercial facts in the selected locale.
- **SC-008**: 100% of defined marketplace state changes and privileged reads produce a complete audit record, and none is reported successful when the required audit write fails.
- **SC-009**: Cross-tenant security tests expose 0 unpublished assets, unrelated rentals, private contacts, statements, operational credentials or adapter secrets while preserving the explicitly published catalog and authorized counterpart views.
- **SC-010**: All critical owner and organizer journeys can be completed in Arabic/RTL and English/LTR with no high-severity accessibility defect and no tenant-specific presentation fork.
- **SC-011**: At least 90% of pilot venue owners and organizers rate the listing, request, approval and control-status workflow as clear or very clear.

## Assumptions

- **Prior phases are accepted**: Phase 6 starts only after Phase 5 meets its Definition of Done. It reuses existing tenant context, accounts, roles, events, notifications, audit, idempotency, adapters, kiosk/scanner/printer assets and ACS control boundaries.
- **Business-to-business scope**: This is a private infrastructure marketplace between venue-owner and organizer organizations. Public attendee event discovery, sponsor/reseller marketplaces and consumer ticket listings remain out of scope.
- **Controlled onboarding**: Existing platform/tenant onboarding creates or classifies venue-owner organizations and assigns Venue Owner Admin/Asset Manager roles. Public self-service venue signup, owner identity/KYB verification and commercial contract execution are outside this phase.
- **Fixed pricing only**: Per-hour, per-day and per-rental pricing are supported. Dynamic demand pricing, negotiation/counter-offers, coupons, bundled cross-venue requests and partial approvals are later capabilities.
- **Statements, not funds movement**: The statement records agreed commercial amounts and lifecycle outcomes. Marketplace payment collection, escrow, automated payouts, refunds, penalties, commission calculation, VAT/tax invoicing and bank reconciliation are outside Phase 6 unless separately specified and accepted.
- **One venue and currency per request**: Organizers create separate requests for separate venues or currencies. A request may contain multiple assets from its single venue.
- **Event operating window**: A rental is bound to an existing organizer-owned event and may include reasonable setup/teardown time around the public event schedule when the venue owner approves it.
- **Published projection**: Cross-organization discovery is an explicit, minimal publication of owner-approved venue/asset fields; it is not unrestricted tenant data access.
- **No hardware logistics or camera feeds**: Zonetec lists and delegates control of already installed fixed infrastructure. Manufacturing, shipping, installation, maintenance dispatch, live CCTV feeds, recordings and biometric processing are outside this phase.
- **Retention and residency are policy-driven**: Business contacts, requests and disputes follow tenant retention/deletion policy; settlement and audit evidence follows approved contractual, accounting and legal holds. Exact statutory durations are governance configuration, not hard-coded behavior.
- **On-premise marketplace boundary**: Full cross-deployment federation and enterprise offline synchronization belong to Phase 7. Phase 6 supports complete local behavior within one deployment and safe degradation when a remote catalog is unreachable.
