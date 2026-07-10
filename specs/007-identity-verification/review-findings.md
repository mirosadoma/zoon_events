# Phase 5 — Identity Verification: Deep Review Findings

**Reviewed**: 2026-07-08
**Reviewer**: senior model (deep review of the cheaper-model implementation)
**Commit under review**: `83092c5 Implement Phase 5 Identity Verification Features`
**Audience**: cheaper LLM model — this file lists the remaining work. Each item is
scoped, points at exact files, and states acceptance criteria. Do the **[HIGH]**
and **[MEDIUM]** items; **[LOW]** items are optional/needs-a-decision.

---

## TL;DR — overall verdict

The implementation is **high quality and ~95% complete**. All 75 tasks in `tasks.md`
are genuinely implemented (not stubs), and the following gates PASS locally:

- Backend: `php artisan test tests/Feature/IdentityVerification tests/Architecture/Phase5ModuleBoundaryTest.php` → **30 passed (354 assertions)**
- Frontend: `identity-requirements`, `identity-verify`, `identity-review`, `phase5-accessibility` tests → **9 passed**
- `npm run typecheck` → PASS
- `npm run lint` (eslint, 0 warnings) → PASS
- `vendor/bin/pint --test` (identity + credentials + gate) → PASS
- `php scripts/sync-openapi.php --check`, `zonetec:docs:check`, `zonetec:phase-boundary:check` → PASS
- i18n: identity keys are complete and identical in `en.ts` and `ar.ts` (70 keys each)

Enforcement is correctly wired through the single `IdentityGate` trust path:
`IssueCredential` (credential boundary) and `AuthorizeGateAction` (gate boundary).
Audit events fire inside the same DB transaction as each state change. Attendee
self-service routes are properly authenticated by the order access token
(`X-Order-Access-Token`, verified with `hash_equals` in `PublicOrderIdentityContext`).

The items below are genuine gaps found during review that the tests do **not** catch
(because tests seed states directly rather than exercising the missing mechanism).

---

## [HIGH] 1. No mechanism ever sets `expired` status

**Problem**: `IdentityVerificationStatus::EXPIRED` is defined, read by `IdentityGate`
(mapped to reason `identity_expired`), and covered by tests that **seed** the status
directly (`EnforcementStatusTest`). But **nothing in production code ever writes
`expired`**. There is no scheduled job and no validity-window logic that transitions
`gov_verified` / `face_verified` / `manually_approved` → `expired` when a verification
lapses.

This contradicts:
- `data-model.md` state machine: `{gov_verified|face_verified|manually_approved|pending} → expired (validity lapses)`
- `spec.md` FR-012 (lifecycle includes `expired`) and Edge Case "Expired verification"
- Success criterion SC-004 (verified-but-expired must be blocked at the boundary)

The retention purge job (`PurgeExpiredIdentityArtifacts`) only deletes biometric
artifacts and sets `purged_at`; it does **not** expire the verification status.

**Fix**:
1. Add a config validity window, e.g. `verification_validity_days` in
   `config/identity-verification.php` (default e.g. 365).
2. Create `app/Modules/IdentityVerification/Application/Actions/ExpireStaleVerifications.php`
   that finds verifications with status in
   `{gov_verified, face_verified, manually_approved}` whose `verified_at` is older
   than the validity window (tenant-scoped, chunked) and sets status `expired`
   inside a DB transaction, emitting an audit event
   (add `IdentityVerificationExpired` domain event + listener method, or reuse
   `IdentityVerificationResultRecorded` with `status=expired`).
3. Register an Artisan command `zonetec:identity:expire-stale` and schedule it
   `->daily()` in `routes/console.php` (mirror the existing purge command wiring).

**Acceptance**: a verification whose `verified_at` is beyond the validity window is
transitioned to `expired` by the scheduled command, an audit row is written, and
`IdentityGate` then blocks it at both boundaries. Add a feature test
`tests/Feature/IdentityVerification/ExpiryTest.php`.

---

## [MEDIUM] 2. `required_vip` / `required_vvip` are not really tier-aware

**Problem**: In
`app/Modules/IdentityVerification/Application/Queries/IdentityGate.php`,
`boundaryRequiresVerification()` treats `required_vip` and `required_vvip` as
"required at the **credential** boundary" for **any** attendee whose resolved level
is vip/vvip. Two issues:

1. **No tier check**: the gate never compares the attendee's actual
   tier / `attendee_type` against VIP/VVIP. If an organizer sets the **event-level**
   default to `required_vip` (ticket_type_id = null), `RequirementResolver` returns
   `required_vip` for **every** attendee, so verification is effectively required for
   everyone — not "only for VIP". The distinction between `required_vip` /
   `required_vvip` and `required_before_credential` currently collapses to the same
   behavior.
2. **Never enforced at the gate**: at the `gate` boundary only `required_before_gate`
   triggers, so `required_vip` / `required_vvip` never block gate entry.

Spec references: FR-001 lists "required only for VIP / VVIP" as distinct levels;
US1 Acceptance Scenario 1 implies these are meaningful, tier-scoped choices.

**Fix (needs a small design decision — pick the interpretation and implement)**:
- Preferred: make `required_vip` / `required_vvip` apply only when the attendee's
  `attendee_type` (from `TicketType.attendee_type`, already read in
  `AuthorizeGateAction`) is `vip` / `vvip`, at **both** boundaries. In `IdentityGate`,
  read the attendee's `attendee_type` and only require verification when it matches
  the level; otherwise return satisfied.
- Update `IdentityGate::boundaryRequiresVerification()` (or add a tier check in
  `evaluate()`), and add unit cases to `IdentityGateTest.php`:
  vip-level + non-vip attendee → satisfied; vip-level + vip attendee unverified →
  blocked at both credential and gate.

**Acceptance**: an event set to `required_vip` blocks unverified VIP attendees at the
configured boundary(ies) but does **not** block non-VIP attendees; documented and
unit-tested.

---

## [MEDIUM] 3. Consent withdrawal is not implemented

**Problem**: The `identity_consents.withdrawn_at` column exists and is honored by
`PublicOrderIdentityContext::activeConsent()` (it filters `whereNull('withdrawn_at')`),
but **there is no action or route to actually withdraw consent**. Nothing ever sets
`withdrawn_at`.

Spec Edge Case "Consent declined or withdrawn": *"a withdrawn consent (where permitted)
triggers deletion of the associated sensitive data and reverts status to unverified."*

**Fix** (scope it as "where permitted", so guard behind existing auth):
1. Add `WithdrawConsentAction` in
   `app/Modules/IdentityVerification/Application/Actions/` that: sets `withdrawn_at`,
   deletes/blanks associated sensitive data (reuse `DeleteIdentityDataAction` logic for
   artifacts + verified_* fields + provider payloads), reverts the verification status
   to `pending` (or `not_required` if the requirement no longer applies), all inside a
   transaction, and emits an audit event.
2. Expose it: either an attendee route (order-token auth, in
   `Routes/api.php` attendee group) or a compliance route. Prefer the attendee
   self-service path since consent belongs to the attendee.
3. Add `tests/Feature/IdentityVerification/ConsentWithdrawalTest.php`: withdraw →
   sensitive data gone, status reverted, audit written, subsequent capture blocked
   until re-consent.

**Acceptance**: withdrawing consent deletes associated sensitive data, reverts status,
and is audited.

---

## [LOW] 4. No automatic (re)issuance of credential after late verification

**Problem**: With `required_before_credential`, `IssueCredential::execute()` correctly
returns `null` (withheld) and `CompleteFreeRegistration` / `CompletePaidRegistration`
handle the null gracefully (skip notification, return null credential). But when the
attendee later becomes verified, **nothing automatically issues the credential** — it
requires a manual reissue (`ReissueCredential`).

This is acceptable per the "backend is source of truth / manual reissue exists"
assumptions, but it is not wired as an automatic flow. **Decision needed**: is an
automatic post-verification issuance expected? If yes, add a listener on
`IdentityVerificationResultRecorded` / `IdentityReviewApproved` that triggers issuance
when the attendee has no active credential and the requirement is
`required_before_credential`. If no, just document it in `quickstart.md`.

---

## [LOW] 5. `showVerification` tenant-session path is not permission-gated

**Problem**: In `AttendeeIdentityController::authorizeVerificationRead()`, when there is
no `X-Order-Access-Token`, it falls back to the tenant session and only checks that the
event belongs to the tenant — it does **not** require any `identity.*` permission. So
any authenticated tenant user can read any attendee's (non-sensitive) verification
status via `GET .../identity/verification`.

Only a non-sensitive status projection is returned (no raw biometrics / gov payloads),
so risk is low, but it is inconsistent with CR-002 least-privilege. Consider requiring
`identity.review` or `identity.data.view` on the session fallback path, and add a test.

---

## Environmental note (NOT a code defect) — re-validate the full `composer test`

Running the Phase-4 test `tests/Feature/AccessControl/GateAuthorizationApiTest.php`
**in isolation** fails with `Base table or view not found: 'event_branding'`. This is
**not** a Phase 5 regression: that test relies on a pre-migrated DB, whereas the
identity tests use `RefreshDatabase` (migrate fresh per run). In a full `composer test`
run the first `RefreshDatabase` test migrates the whole schema, so the table exists by
the time this test runs. The local `zonetec_testing` MySQL DB was left in a partial
state during review (many `2026_07_03_*` migrations showed `Pending`).

**Action for the implementer**: before claiming T074 green, run the **full** suite
once on a clean DB:

```
php artisan migrate:fresh --env=testing --force
composer test
```

The `AdminConsole` / `CrossTenantPropsTest` suite ran very slowly during review and was
not completed end-to-end here; confirm it passes in the full run.

---

## What was verified as correct (no action needed)

- Module tree, service provider bindings (`match(config(...))` for gov/face adapters),
  `ModuleServiceProvider` registration, API route wiring, web/Inertia routes, console
  schedule for the purge job.
- Migrations match `data-model.md` (unique keys, indexes, encrypted `storage_reference`,
  `retention_until`, `purged_at`).
- Adapters (Mock + Fake) implement the contracts; contract test suites pass.
- Consent precondition enforced before any capture (`409 identity_consent_missing`).
- Government callback is signed (HMAC) and idempotent (`lockForUpdate` + gov_verified
  short-circuit).
- Face capture stores an encrypted, minimized template with `retention_until`.
- Manual review requires a reason on reject, records reviewer + time, audits both paths.
- Retention purge removes expired artifacts, sets `purged_at`, preserves metadata.
- Sensitive-data view is audited; delete is gated by `identity.data.manage` and audited.
- API resources never return raw biometrics or raw government payloads (`ResidencyTest`).
- Cross-tenant isolation enforced (`CrossTenantTest`, `RequirementsIsolationTest`).
- Frontend pages are complete (not stubs) with loading/empty/error/forbidden states,
  RTL/LTR, and axe-clean; i18n complete in both locales.
