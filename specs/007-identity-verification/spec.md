# Feature Specification: Identity Verification

**Feature Branch**: `007-identity-verification`

**Created**: 2026-07-08

**Status**: Draft

**Input**: User description: "Phase 5 — Identity Verification"

**Product Phase**: Phase 5 — Identity Verification (depends on Foundation +
Phase 1 Registration-Ticketing-Credentials + Phase 4 ACS Access Control)

**Deployment Modes**: both (SaaS and on-premise)

## User Scenarios & Testing *(mandatory)*

Identity Verification lets an organizer require a chosen level of identity
assurance for an event or a specific ticket/attendee tier, lets an attendee
satisfy that requirement (with informed consent) through a government-identity
check or a face-capture/manual-review fallback, and prevents an
identity-required attendee from receiving a credential or entering a gate until
they are verified. All sensitive identity/biometric data is minimized,
retention-bound, residency-aware, and access-audited.

Each user story below is an independently shippable slice: implementing only one
still yields a usable increment.

### User Story 1 - Configure identity assurance requirements per event and tier (Priority: P1)

An organizer opens an event's settings and sets the identity-verification
requirement level for the event and, optionally, per ticket type / attendee tier
(e.g., stricter for VIP/VVIP). The requirement determines whether and when an
attendee must be verified.

**Why this priority**: Nothing else is meaningful without a requirement to
satisfy. This slice is the MVP: it establishes the policy that every downstream
verification and enforcement step reads.

**Independent Test**: As an organizer, set an event to "required before gate
entry" and set a VVIP tier to "required before credential issuance"; reload and
confirm each requirement persists and is shown correctly; confirm an event left
as "not required" tracks every attendee as `not_required`.

**Acceptance Scenarios**:

1. **Given** an event and the `identity.configure` permission, **When** the organizer selects a requirement level (not required / optional / required before credential issuance / required before gate entry / required only for VIP / required only for VVIP), **Then** the choice is saved and applied to matching attendees.
2. **Given** a tier-specific requirement, **When** an attendee of that tier registers, **Then** their identity requirement reflects the tier rule rather than the event default.
3. **Given** a user lacking `identity.configure`, **When** they view event settings, **Then** the identity-requirement controls are not shown.
4. **Given** an event set to "not required", **When** attendees register, **Then** their identity status is `not_required` and no verification is prompted.

---

### User Story 2 - Attendee consents and completes government identity verification (Priority: P2)

An attendee who is required (or opts) to verify sees a clear consent notice,
gives consent, and completes a government-identity check through a provider
adapter. On success their verified attributes and status are recorded.

**Why this priority**: This is the primary happy-path assurance method and the
first path that produces a verified attendee, proving the consent + adapter +
status pipeline end-to-end.

**Independent Test**: As an attendee on an event requiring verification, view the
consent notice, consent, run the (mock) government check, and confirm the
attendee's status becomes `gov_verified` with verified name/nationality recorded
and consent stored; confirm declining consent leaves status `pending` and no
verification data is captured.

**Acceptance Scenarios**:

1. **Given** a verification is required, **When** the attendee begins, **Then** a consent notice stating what data is collected, why, how long it is kept, who can access it, whether it is processed on-premise or SaaS, and how to request deletion is shown before any capture.
2. **Given** the consent notice, **When** the attendee consents, **Then** the consent record is stored with timestamp and version, and verification may proceed; **When** they decline, **Then** no identity data is captured and status stays `pending`.
3. **Given** consent is stored, **When** the government identity check succeeds, **Then** status becomes `gov_verified`, verified name/nationality and provider reference are recorded, and `verified_at` is set.
4. **Given** the government check fails or is unsupported for the attendee, **Then** status remains `pending` and the attendee is offered the face-capture fallback (US4) where enabled.
5. **Given** any government interaction, **When** it runs, **Then** it goes through the provider adapter interface (start / callback / fetch result / map attributes / store status) and no specific provider is hardcoded into business logic.

---

### User Story 3 - Enforce identity requirement before credential issuance and gate entry (Priority: P3)

The platform blocks an identity-required attendee from receiving a credential
and/or from passing a gate until they are verified, according to the configured
requirement level.

**Why this priority**: Enforcement is the reason the feature exists — it turns a
recorded status into an operational control at the credential and access-control
boundaries.

**Independent Test**: With an event set to "required before gate entry", attempt
a gate scan for an unverified attendee and confirm rejection with an
identity-not-verified reason; verify the same attendee; retry and confirm entry
is allowed. With "required before credential issuance", confirm no active
credential is issued until the attendee is verified.

**Acceptance Scenarios**:

1. **Given** "required before credential issuance", **When** an unverified attendee would be issued a credential, **Then** issuance is withheld until identity status is verified, and the attendee/credential shows the pending-identity state.
2. **Given** "required before gate entry", **When** an unverified attendee is scanned at a gate/lane, **Then** entry is rejected with a clear identity-not-verified reason recorded on the access log.
3. **Given** a verified attendee, **When** their identity status is `gov_verified` / `face_verified` / `manually_approved`, **Then** verified status attaches to the attendee and their credential and no longer blocks issuance/entry.
4. **Given** a `rejected` or `expired` identity status, **When** enforcement runs, **Then** the attendee is treated as not verified and blocked at the configured boundary.

---

### User Story 4 - Face-capture fallback with manual reviewer approval (Priority: P4)

When government verification is unavailable or fails (non-residents, guests,
unsupported cases, or face-enabled events), the attendee completes a face-capture
fallback, and a reviewer approves or rejects it with a reason.

**Why this priority**: The fallback extends coverage to attendees who cannot use
government verification, but it depends on the consent and status machinery from
US2 and the enforcement of US3.

**Independent Test**: As a non-resident attendee, complete face capture; as a
reviewer, open the review queue, approve one submission and reject another with a
reason; confirm approved becomes `face_verified` / `manually_approved` and
rejected becomes `rejected` with the reason stored and both outcomes audited.

**Acceptance Scenarios**:

1. **Given** government verification is unavailable/failed or the event enables face verification, **When** the attendee proceeds, **Then** the face-capture fallback is offered behind its adapter interface, with liveness applied where available.
2. **Given** a face-capture submission, **When** a reviewer with `identity.review` opens the queue, **Then** they can approve or reject it.
3. **Given** a rejection, **When** the reviewer confirms, **Then** a rejection reason is required and stored, and the attendee status becomes `rejected`.
4. **Given** an approval, **When** the reviewer confirms, **Then** status becomes `face_verified` (or `manually_approved` for pure manual review) with reviewer identity and timestamp recorded.
5. **Given** any captured biometric data, **When** it is stored, **Then** collection is minimized (templates preferred over raw images), it is encrypted, and access is logged.

---

### User Story 5 - Consent, retention, deletion, and sensitive-data audit (Priority: P5)

The platform enforces the retention window on identity/biometric data, supports
deletion requests where applicable, and audits every access to sensitive
identity data, honoring residency (on-premise/cross-border) constraints.

**Why this priority**: Compliance rounds out the feature and is required for
PDPL/biometric safety, but it operates on data produced by the earlier slices.

**Independent Test**: Set a short retention window, run the retention cleanup job,
and confirm expired verification artifacts are removed while status/audit
metadata is preserved as configured; open a sensitive record and confirm the
access is audited; confirm data does not leave the configured residency
boundary.

**Acceptance Scenarios**:

1. **Given** a `retention_until` on a verification record, **When** the retention cleanup job runs after that time, **Then** the sensitive artifacts (images/templates/provider payloads) are deleted while non-sensitive status/audit metadata is retained per policy.
2. **Given** a reviewer or admin views sensitive identity data, **When** the record is accessed, **Then** an audit event records actor, tenant, target, timestamp, and outcome.
3. **Given** a deletion request permitted by policy, **When** it is processed, **Then** the associated sensitive identity data is removed and the action is audited.
4. **Given** on-premise deployment or a residency constraint, **When** sensitive identity/biometric data is processed, **Then** it is processed locally and not transferred across approved boundaries.

---

### Edge Cases

- **Requirement change after registration**: If an organizer raises the requirement after attendees have registered, previously unverified attendees become subject to the new requirement at the next enforcement point; already-verified attendees remain verified until expiry.
- **Consent declined or withdrawn**: With no consent, no identity data is captured or retained; a withdrawn consent (where permitted) triggers deletion of the associated sensitive data and reverts status to unverified.
- **Adapter/provider unavailable**: If the government or face adapter is unavailable, the page shows a clear error/retry and offers the fallback where configured; the feature never fabricates a verified result.
- **Cross-tenant isolation**: Verification records, consent, review queues, and audit entries are visible only within the signed-in user's tenant; a tenant-mismatched record is treated as an error, never displayed.
- **Missing permission**: `identity.configure` / `identity.review` / sensitive-data view controls are hidden without the permission; a forbidden response shows a clear permission message, not partial data.
- **Audit failure**: If an audit record for a sensitive access or a verification decision cannot be written, the action is not reported as completed.
- **Expired verification**: An `expired` status is treated as not verified at enforcement; re-verification is required.
- **Duplicate submissions**: Verification and review submit controls disable while a request is in flight to prevent duplicate writes.
- **Localization/RTL**: Consent notices, statuses, rejection reasons, and prompts render correctly in Arabic (RTL) and English (LTR) with locale-aware dates.

## Requirements *(mandatory)*

### Functional Requirements

**Configuration**

- **FR-001**: The system MUST let an organizer with `identity.configure` set an event's identity-verification requirement to one of: not required, optional, required before credential issuance, required before gate entry, required only for VIP, required only for VVIP.
- **FR-002**: The system MUST allow the requirement to be set per ticket type / attendee tier, overriding the event default for matching attendees.
- **FR-003**: The system MUST derive and track each attendee's applicable identity requirement and initial status (`not_required` when the event/tier does not require verification).

**Consent**

- **FR-004**: Before any identity or biometric capture, the system MUST present a consent notice stating what data is collected, why, how long it is retained, who can access it, whether processing is on-premise or SaaS, and how to request deletion where applicable.
- **FR-005**: The system MUST store consent (with timestamp and notice version) and MUST NOT capture identity/biometric data without stored consent; declining consent leaves the attendee unverified and captures no identity data.

**Government identity verification**

- **FR-006**: The system MUST perform government identity verification through an adapter interface supporting start verification, verify callback, fetch verification result, map verified attributes, and store verification status — with no specific provider (e.g., Nafath/Absher/Yaqeen) hardcoded into business logic.
- **FR-007**: The system MUST provide a mock government provider that exercises the full adapter flow for testing and for deployments without a production provider.
- **FR-008**: On successful government verification the system MUST record status `gov_verified`, verified name and nationality, provider and provider reference, and `verified_at`.

**Face-capture fallback and manual review**

- **FR-009**: The system MUST offer a face-capture fallback (behind an adapter interface, with liveness where available) for non-residents, guests, attendees without supported government verification, failed government verification, and events where face verification is enabled.
- **FR-010**: The system MUST provide a manual review queue where a reviewer with `identity.review` can approve or reject a face-capture or manual submission.
- **FR-011**: The system MUST require and store a rejection reason on rejection (status `rejected`) and MUST record reviewer identity and timestamp on approval (status `face_verified` or `manually_approved`).

**Status and enforcement**

- **FR-012**: The system MUST track identity status across the lifecycle: `not_required`, `pending`, `gov_verified`, `face_verified`, `manually_approved`, `rejected`, `expired`.
- **FR-013**: The system MUST attach verified identity status to the attendee and their credential when verification succeeds.
- **FR-014**: With "required before credential issuance", the system MUST withhold an active credential from an unverified attendee until they are verified.
- **FR-015**: With "required before gate entry", the system MUST reject an unverified attendee at the gate/lane with a clear identity-not-verified reason recorded on the access log, and MUST allow entry once verified.
- **FR-016**: The system MUST treat `rejected` and `expired` statuses as not verified at every enforcement point.

**Retention, deletion, and residency**

- **FR-017**: The system MUST set a retention window (`retention_until`) on identity/biometric artifacts and MUST run a cleanup job that deletes expired sensitive artifacts while preserving non-sensitive status/audit metadata per policy.
- **FR-018**: The system MUST minimize biometric collection, prefer templates over raw images, encrypt sensitive data at rest and in transit, and support processing sensitive identity/biometric data on-premise, restricting cross-border transfer per residency configuration.
- **FR-019**: The system MUST support permitted deletion requests for sensitive identity data and audit each deletion.

**Auditability and access**

- **FR-020**: The system MUST audit every access to sensitive identity data and every verification decision (start, consent, verification result, approval, rejection, deletion) with actor, tenant, target, timestamp, and outcome.
- **FR-021**: The system MUST NOT expose raw biometric data or raw government payloads through any API or client surface.

**Cross-cutting UX**

- **FR-022**: Every consent, verification, and review surface MUST show loading, empty, error, and forbidden states, disable submit controls while a request is in flight, and render correctly in Arabic/RTL and English/LTR with locale-aware formatting.

### Constitutional Requirements *(mandatory)*

- **CR-001 Tenant Scope**: Verification records, consent records, review queues, audit entries, cached results, and adapter calls MUST be scoped to the signed-in user's tenant via trusted server-side context. No screen or job accepts a client-supplied tenant id; a tenant-mismatched record is treated as an error. Automated tests attempt cross-tenant access and prove denial.
- **CR-002 RBAC**: Actors are Organizer/Admin (`identity.configure`), Identity Reviewer (`identity.review`), Attendee (self-service verification), and platform admins for compliance operations. Least privilege applies; without a permission the corresponding control is hidden. UI checks are UX-only; the server remains the authorization boundary.
- **CR-003 Auditability**: Identity requirement changes, consent capture/withdrawal, verification start, government/face results, manual approvals/rejections (with reason), sensitive-data access, retention deletions, and deletion requests MUST create tamper-evident audit records; an action is not reported complete if its audit write fails.
- **CR-004 Credential Security**: Identity status gates credential issuance and gate entry but the feature never signs, mints, or exposes credential secrets. Verified status flows to the credential lifecycle; expired/rejected identity states block issuance/entry exactly as revoked/expired credentials do.
- **CR-005 Data and PDPL**: Identity and biometric data are the most sensitive data classes. Processing MUST have a lawful basis (consent), be minimized (templates over raw images), be retention-bound and deletable, honor residency (on-premise/cross-border restrictions), and restrict sensitive access to audited, permitted actors. No shadow copies of sensitive data are created.
- **CR-006 API and Integrations**: Government identity and face/liveness capabilities MUST be reached only through versioned adapter contracts (start/callback/fetch/map/store) with defined failure modes and a mock provider for tests; missing production providers degrade to the fallback or a clear error, never a fabricated result.
- **CR-007 White-Label and Localization**: Consent notices, statuses, prompts, and reviewer surfaces MUST honor tenant branding and be available in Arabic and English with correct RTL/LTR and locale-aware dates/numbers. Consent notice text is configuration-driven, not code-forked per tenant.
- **CR-008 Deployment Parity**: The feature MUST behave equivalently in SaaS and on-premise, targeting adapters via configuration, with sensitive identity/biometric processing supported locally on-premise. Unavailable adapters degrade to clear error/fallback states and remain navigable; no cloud-only dependency is required.
- **CR-009 Automated Verification**: Required tests — identity configuration, consent capture/decline, mock government verification (success/failure/attribute mapping), face-capture fallback, manual review approve/reject with reason, enforcement at credential issuance and gate entry, retention cleanup, deletion, cross-tenant isolation, RBAC gating, and sensitive-data audit.
- **CR-010 Phase Alignment**: This is Phase 5; it depends on Foundation (auth/RBAC/tenant/audit/compliance), Phase 1 (attendees, credentials, events, tiers), and Phase 4 (ACS gate enforcement). It MUST NOT begin until Phase 4 has passed its Definition of Done, MUST NOT weaken existing contracts, and precedes Phase 6 (Venue Marketplace).

### Key Entities *(include if feature involves data)*

- **Identity Verification Requirement**: Per-event and per-tier policy selecting one of the requirement levels; determines whether/when an attendee must verify.
- **Identity Verification**: The per-attendee verification record — tenant, event, attendee, method (`email_otp`, `phone_otp`, `gov_identity`, `face_capture`, `manual_review`), status (`not_required`/`pending`/`gov_verified`/`face_verified`/`manually_approved`/`rejected`/`expired`), consent reference, provider and provider reference, verified name/nationality, `verified_at`, manual reviewer + review time, rejection reason, and `retention_until`.
- **Consent Record**: Stored attendee consent with notice version, timestamp, purpose/retention/access disclosures, and residency mode (on-premise/SaaS).
- **Government Verification Adapter (interface)**: The provider-agnostic boundary (start / callback / fetch result / map attributes / store status) with a mock implementation.
- **Face Capture / Biometric Artifact**: Minimized, encrypted, retention-bound capture (template preferred over raw image) subject to access logging and deletion.
- **Manual Review Item**: A queued submission a reviewer approves or rejects with reason.
- **Audit Log entry**: Read-only actor/tenant/action/target/timestamp/outcome for every sensitive access and verification decision.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An organizer can configure an event/tier identity requirement in under 2 minutes, and the requirement is applied to matching attendees with zero cross-tier leakage.
- **SC-002**: 100% of identity captures are preceded by a stored consent record; no sensitive identity/biometric data exists without a corresponding consent.
- **SC-003**: At least 90% of VVIP attendees on a verification-required event reach a verified status before the event start.
- **SC-004**: 100% of identity-required-but-unverified attendees are blocked at the configured boundary (credential issuance or gate entry) with a clear reason, and 0% of verified attendees are wrongly blocked.
- **SC-005**: 100% of sensitive identity-data accesses and verification decisions produce an audit record; none succeed silently.
- **SC-006**: 100% of expired-retention sensitive artifacts are removed by the cleanup job within one scheduled run after `retention_until`.
- **SC-007**: 0% of sensitive identity/biometric data crosses a configured residency boundary; on-premise deployments process it locally.
- **SC-008**: 100% of user-visible identity content (consent, statuses, reasons, prompts) is available in Arabic and English with correct RTL/LTR rendering.

## Assumptions

- **Adapter-first, mock-backed**: This phase delivers the government-identity and face/liveness capabilities behind adapter interfaces plus a working mock government provider. Production integrations (e.g., Nafath, Absher, Yaqeen) and their commercial/legal terms, exact verifiable attributes, and non-resident coverage are open questions and are out of scope for this phase; they are added later behind the same adapters.
- **OTP methods reuse existing verification**: `email_otp` / `phone_otp` methods represent existing contact verification from Registration; the new identity-assurance methods added here are `gov_identity`, `face_capture`, and `manual_review`.
- **Enforcement points already exist**: Credential issuance (Phase 1) and gate/lane entry (Phase 4) are the enforcement boundaries this phase hooks into; no new access-control mechanism is introduced.
- **Retention window is configurable**: A conservative default retention window applies per tenant/event and is configurable; sensitive artifacts are deleted after it while non-sensitive status/audit metadata is preserved per policy.
- **Liveness is optional**: Liveness is applied where the face adapter supports it; its absence does not block the fallback but is recorded.
- **Consent language is configurable**: The exact consent notice wording is tenant/event configuration (bilingual) rather than hardcoded, pending final legal text.
- **Backend is source of truth**: Authorization, audit writing, credential lifecycle, retention/deletion, and residency enforcement remain backend responsibilities; UI surfaces status and never fabricates verified results.
