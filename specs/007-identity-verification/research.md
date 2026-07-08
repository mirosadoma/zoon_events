# Research: Identity Verification (Phase 5)

All Technical Context unknowns are resolved below. Government-provider access is
the only genuinely open external dependency; it is deliberately deferred behind an
adapter as a blocking assumption, consistent with Constitution IV.

## Decision 1 — New `IdentityVerification` module (not the existing `Identity` module)

- **Decision**: Create a new owned module `app/Modules/IdentityVerification`. Do
  **not** extend the existing `app/Modules/Identity` module.
- **Rationale**: `Identity` is Foundation **authentication** (user login, MFA, API
  keys, service tokens — see `Application/AuthenticateUser.php`,
  `Contracts/MfaAuthenticator.php`). Phase 5 is a distinct **attendee identity
  assurance** domain with its own tables, adapters, and lifecycle. Mixing them would
  blur ownership and violate Constitution VI boundaries.
- **Alternatives considered**: (a) extend `Identity` — rejected (conflated domains);
  (b) fold into `Attendees` — rejected (attendee is the subject, not the owner of
  verification policy, consent, biometric artifacts, or adapters).

## Decision 2 — Provider-neutral adapters with mocks; production KSA access deferred

- **Decision**: Define `GovernmentIdentityAdapter` and `FaceCaptureAdapter`
  interfaces in `Contracts/`, ship `MockGovernmentIdentityAdapter` /
  `MockFaceCaptureAdapter` in `Infrastructure/Adapters/`, and select the active
  implementation via `config('identity-verification.default_government_adapter')`
  and `default_face_adapter`. Bind in `IdentityVerificationServiceProvider` using
  the same `match(config(...))` pattern as `AccessControlServiceProvider`.
- **Rationale**: `all_plan.md` §20.3 mandates "Do not hardcode Nafath, Absher, or
  Yaqeen"; §39.1 lists production access, commercial terms, verifiable attributes,
  and non-resident coverage as open questions. Constitution IV requires unknown
  government API access to remain an explicit blocking assumption, never a disguised
  production integration. The Phase 1/2/4 payment/wallet/ACS adapters set the
  precedent.
- **Adapter surface** (government): `startVerification`, `handleCallback`,
  `fetchResult`, `mapAttributes`. (face): `submitCapture`, optional `liveness`.
  Each documents timeout, retry, idempotency, error mapping, observability per
  `docs/standards/adapter-authoring.md`.
- **Alternatives considered**: direct SDK integration now — rejected (no confirmed
  access, PDPL/legal unresolved, would fork domain logic).

## Decision 3 — Single enforcement trust path via a published `IdentityGate` query

- **Decision**: Expose `IdentityGate` as an Application Query on
  `IdentityVerification`. The Phase 1 credential-issuance action calls it for the
  "before credential issuance" boundary; the Phase 4 gate-authorization decision
  calls it for the "before gate entry" boundary, adding a language-neutral
  `identity_not_verified` reason. No identity logic is duplicated in either module,
  and no identity persistence is read outside `IdentityVerification`.
- **Rationale**: Mirrors the Phase 4 rule that gate authorization reuses the single
  credential validation/scan decision order rather than a second trust path
  (005 plan Summary). Keeps enforcement consistent and boundary-clean (Constitution VI).
- **Alternatives considered**: (a) each module re-checks status by reading identity
  tables — rejected (persistence coupling); (b) event-driven eventual enforcement —
  rejected (enforcement must be synchronous and fail-closed at the boundary).

## Decision 4 — Requirement resolution: tier overrides event default

- **Decision**: A requirement is stored at event level and optionally per ticket
  type / attendee tier. An attendee's effective requirement = the most specific
  matching rule (tier rule if present, else event default, else `not_required`).
  Levels: `not_required`, `optional`, `required_before_credential`,
  `required_before_gate`, `required_vip`, `required_vvip` (the VIP/VVIP levels are
  shorthand that resolve to a boundary for the matching tier).
- **Rationale**: `all_plan.md` §20.1 enumerates exactly these options; per-tier
  strictness (VIP/VVIP) is called out in §33.4.
- **Alternatives considered**: event-only configuration — rejected (spec FR-002
  requires per-tier).

## Decision 5 — Consent is a hard precondition, stored and versioned

- **Decision**: Persist consent in `identity_consents` (notice version, timestamp,
  disclosures, residency mode) before any capture. No consent ⇒ no capture; decline
  leaves status `pending` and stores nothing sensitive. Withdrawal (where permitted)
  triggers deletion of associated sensitive artifacts and reverts to unverified.
- **Rationale**: `all_plan.md` §20.2 lists required disclosures (what/why/how long/
  who/on-prem vs SaaS/deletion) and "Consent must be stored"; Constitution IV
  requires a lawful basis before build.
- **Alternatives considered**: implicit consent via ToS — rejected (biometric data
  needs explicit, purpose-specific consent).

## Decision 6 — Status machine and method set

- **Decision**: Statuses `not_required → pending → {gov_verified | face_verified |
  manually_approved | rejected} ; any verified/pending → expired`. Methods:
  `gov_identity`, `face_capture`, `manual_review` are the assurance methods added
  here; `email_otp`/`phone_otp` (listed in `all_plan.md` §12.20) are treated as
  existing Registration contact verification, not re-implemented.
- **Rationale**: Matches `all_plan.md` §12.20 statuses/methods; avoids duplicating
  Registration OTP.
- **Alternatives considered**: collapsing verified states into one — rejected (audit
  and reporting need to distinguish gov vs face vs manual assurance).

## Decision 7 — Biometric minimization, encryption, retention, residency

- **Decision**: Store templates over raw images where feasible in
  `identity_biometric_artifacts`, encrypted at rest, with `retention_until`. A
  scheduled `PurgeExpiredIdentityArtifacts` job (Laravel scheduler) deletes expired
  sensitive artifacts + provider payloads while keeping non-sensitive status/audit
  metadata. Cross-border transfer is configurable and off by default; on-premise
  processes locally.
- **Rationale**: `all_plan.md` §20.5 and §38.7 biometric compliance; Constitution IV
  minimization and residency rules; parity with existing scheduled jobs
  (`RefreshCheckInSummary`).
- **Alternatives considered**: indefinite retention with manual cleanup — rejected
  (PDPL retention window is mandatory).

## Decision 8 — Actors, permissions, and attendee self-service

- **Decision**: New tenant permission keys `identity.configure` (organizer),
  `identity.review` (reviewer), `identity.data.view` (sensitive read),
  `identity.data.manage` (deletion / retention override), seeded in
  `PermissionSeeder::definitions()` and mirrored into
  `docs/standards/permission-catalog.md` + new `docs/security/permissions-phase5.md`.
  Attendee self-service verification is reached through the **public order/
  registration access token**, not workforce RBAC — the same pattern as
  `wallet.pass.generate` on the attendee journey.
- **Rationale**: permission-catalog.md shows CI compares the seeder to the doc, and
  public attendee flows deliberately sit outside organizer RBAC (host resolution +
  opaque tokens + idempotency).
- **Alternatives considered**: reuse `attendee.manage` for review — rejected (review
  is a distinct sensitive-data duty needing its own least-privilege key).

## Decision 9 — Auditability

- **Decision**: Add Phase 5 audit listeners
  (`app/Modules/Audit/Application/Listeners/Phase5/**`) for: requirement configured,
  consent captured/withdrawn, verification started, government result, face
  submitted, review approved, review rejected (with reason), sensitive-data
  accessed, data deleted, retention purged. Each writes in the same transaction as
  the state change; a failed audit fails the action.
- **Rationale**: Constitution II/III require append-only audit for identity events;
  `audit-event-catalog.md` is updated with the Phase 5 catalog.

## Decision 10 — Deployment parity and degraded behavior

- **Decision**: Adapters and processing target configuration, not deployment mode.
  If the government adapter is unavailable, the UI offers the face fallback where
  enabled or shows a clear retry — never a fabricated verified result. On-premise
  runs the same code with local processing and cross-border transfer disabled.
- **Rationale**: Constitution III parity; spec CR-008.

## Open questions carried forward (blocking assumptions, not code)

Tracked from `all_plan.md` §39.1 — to be resolved before any production provider
integration, and explicitly out of scope for this phase:

1. Production access to Nafath / Absher / Yaqeen and commercial/legal terms.
2. Exact attendee attributes each provider can verify.
3. Whether non-residents can be government-verified (drives face-fallback reliance).
4. Final bilingual consent notice wording (configuration-driven placeholder ships).
