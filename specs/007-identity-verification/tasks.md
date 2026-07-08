---
description: "Task list for Phase 5 Identity Verification"
---

# Tasks: Identity Verification

**Input**: Design documents from `/specs/007-identity-verification/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md,
contracts/{openapi.yaml, identity-adapter.md, dashboard-contract.md}, quickstart.md

**Tests**: MANDATORY (Constitution VII). Unit, integration, contract (adapters),
tenant-isolation, RBAC, and sensitive-data audit tasks are included per user story.

**Organization**: Grouped by user story (US1–US5 from spec.md) for independent
implementation and testing.

**Product Phase**: Phase 5 — Identity Verification (builds on accepted Phase 0–4)

## Format: `[ID] [P?] [Story?] [Model] Description with file path`

- **[P]**: Can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: US1–US5 (user-story phases only)
- **[Model]** (per request "make this tasks for cheaper llm model"): recommended
  LLM tier —
  - **[M:H]** = cheaper model (e.g., Haiku): mechanical, well-scoped, pattern-following
    work — migrations from data-model, Eloquent models, mock adapters, presentational
    pages, route wiring, i18n, status badges, and tests written from an explicit spec.
  - **[M:S]** = capable model (e.g., Sonnet): cross-module enforcement wiring, the
    `IdentityGate` trust path, adapter contract design, consent gating, retention job,
    audit atomicity, and isolation/security test design.
- Source of truth per item: data-model.md (entities/fields), contracts/openapi.yaml
  (endpoints), contracts/identity-adapter.md (adapters), contracts/dashboard-contract.md
  (route→permission→states).

## Path Conventions

Laravel modular monolith + Inertia/React. Backend: `app/Modules/IdentityVerification/**`;
cross-module hooks in `app/Modules/{Credentials,AccessControl,Audit}/**`; migrations in
`database/migrations/`; config in `config/`; module API routes in
`app/Modules/IdentityVerification/Routes/api.php` (required from `routes/api.php`);
dashboard Inertia routes in `routes/web.php`. Frontend: `resources/js/**`. Backend
tests: `tests/Feature/IdentityVerification/**`, `tests/Architecture/**`. Frontend
tests: `resources/js/__tests__/**`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Module scaffolding, config, permissions, and boundary allowlisting.

- [X] T001 [M:H] Create `app/Modules/IdentityVerification/` folder tree per plan.md (`Domain/{ValueObjects,Results,Events,Context}`, `Application/{Actions,Queries,Support}`, `Contracts`, `Infrastructure/{Adapters,Persistence/Models}`, `Http/{Controllers,Requests,Resources,Middleware}`, `Providers`, `Routes`, `Testing`)
- [X] T002 [P] [M:H] Add `config/identity-verification.php` with `default_government_adapter='mock'`, `default_face_adapter='mock'`, `residency='on_premise'`, `cross_border_transfer=false`, and default retention windows
- [X] T003 [M:S] Create `app/Modules/IdentityVerification/Providers/IdentityVerificationServiceProvider.php` (adapter bindings via `match(config(...))`, event listener registration) and register it in `app/Providers/ModuleServiceProvider.php`
- [X] T004 [P] [M:H] Require `app/Modules/IdentityVerification/Routes/api.php` from `routes/api.php` inside the versioned `v1` group (mirror how `AccessControl/Routes/api.php` is wired)
- [X] T005 [P] [M:H] Add tenant permission keys `identity.configure`, `identity.review`, `identity.data.view`, `identity.data.manage` to `database/seeders/PermissionSeeder.php` and mirror them into `docs/standards/permission-catalog.md` + new `docs/security/permissions-phase5.md`
- [X] T006 [P] [M:H] Add `app/Modules/IdentityVerification` to the `allowedProductScopePaths` allowlist in `tests/Architecture/ModuleBoundaryTest.php` (the words "identity verification"/"biometric"/"face" are otherwise flagged)

**Checkpoint**: Module skeleton, config, permissions, and boundary allowlist ready.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tables, models, adapters, the `IdentityGate` trust path, audit, and
shared UI primitives every story depends on.

**⚠️ CRITICAL**: No user-story phase may begin until this phase is complete.

- [X] T007 [P] [M:H] Migration `create_identity_verification_requirements_table` (fields + unique `(tenant_id,event_id,ticket_type_id)`) in `database/migrations/`
- [X] T008 [P] [M:H] Migration `create_identity_verifications_table` (fields + unique `(tenant_id,event_id,attendee_id)` + index `(tenant_id,event_id,status)`) in `database/migrations/`
- [X] T009 [P] [M:H] Migration `create_identity_consents_table` in `database/migrations/`
- [X] T010 [P] [M:H] Migration `create_identity_biometric_artifacts_table` (encrypted `storage_reference`, `retention_until`, `purged_at`) in `database/migrations/`
- [X] T011 [P] [M:H] Eloquent models with tenant-scoped casts/relations in `app/Modules/IdentityVerification/Infrastructure/Persistence/Models/{IdentityVerificationRequirement,IdentityVerification,IdentityConsent,IdentityBiometricArtifact}.php`
- [X] T012 [P] [M:H] Status + method + level + reason-code enums/value objects in `app/Modules/IdentityVerification/Domain/ValueObjects/` (from data-model.md state machine and reason-code list)
- [X] T013 [M:S] Define `GovernmentIdentityAdapter` and `FaceCaptureAdapter` interfaces in `app/Modules/IdentityVerification/Contracts/` exactly per contracts/identity-adapter.md (method signatures + provider-neutral result objects in `Domain/Results/`)
- [X] T014 [P] [M:H] Implement `MockGovernmentIdentityAdapter` (success/failure/unsupported paths) in `app/Modules/IdentityVerification/Infrastructure/Adapters/`
- [X] T015 [P] [M:H] Implement `MockFaceCaptureAdapter` (+ liveness passed/failed/unavailable) in `app/Modules/IdentityVerification/Infrastructure/Adapters/`
- [X] T016 [P] [M:H] Implement `FakeGovernmentIdentityAdapter` and `FakeFaceCaptureAdapter` deterministic test doubles in `app/Modules/IdentityVerification/Testing/`
- [X] T017 [M:S] Implement requirement-resolution support (`RequirementResolver`) in `app/Modules/IdentityVerification/Application/Support/` — effective level = tier override else event default else `not_required`
- [X] T018 [M:S] Implement the published `IdentityGate` query in `app/Modules/IdentityVerification/Application/Queries/IdentityGate.php` returning satisfied/blocked + reason for a given boundary (`credential`|`gate`); returns satisfied when `not_required`
- [X] T019 [P] [M:S] Scaffold Phase 5 audit listeners in `app/Modules/Audit/Application/Listeners/Phase5/` and domain events in `app/Modules/IdentityVerification/Domain/Events/`; wire in the service provider (audit writes in the same transaction as the state change)
- [X] T020 [P] [M:H] Add identity status variants (`not_required`/`pending`/`gov_verified`/`face_verified`/`manually_approved`/`rejected`/`expired`) to `resources/js/components/status/StatusBadge.tsx` and a new `resources/js/components/identity/` folder with index barrel
- [X] T021 [P] [M:H] Add shared Phase 5 i18n keys (statuses, consent, review, reasons) to `resources/js/locales/{en,ar}.ts`
- [X] T022 [P] [M:H] [Test] `tests/Architecture/Phase5ModuleBoundaryTest.php` asserting IdentityVerification does not read other modules' persistence (mirror `Phase4ModuleBoundaryTest.php`)
- [X] T023 [P] [M:H] [Test] Adapter contract suites (Mock + Fake) for both adapters in `tests/Feature/IdentityVerification/Adapters/`
- [X] T024 [P] [M:S] [Test] Unit tests for `RequirementResolver` and `IdentityGate` (each level → boundary → satisfied/blocked) in `tests/Feature/IdentityVerification/`

**Checkpoint**: Schema, adapters, trust path, audit, and shared UI ready.

---

## Phase 3: User Story 1 - Configure identity assurance requirements (Priority: P1) 🎯 MVP

**Goal**: Organizer sets per-event and per-tier identity requirements.

**Independent Test**: Set event `required_before_gate` + a VVIP tier
`required_before_credential`; reload and confirm persistence; a user without
`identity.configure` gets 403 and sees no controls.

### Tests for User Story 1 (MANDATORY)

- [X] T025 [P] [US1] [M:H] [Test] Contract/feature test for `GET`/`PUT .../identity/requirements` incl. `dashboard.permission` gating (403 without `identity.configure`) in `tests/Feature/IdentityVerification/RequirementsTest.php`
- [X] T026 [P] [US1] [M:S] [Test] Cross-tenant isolation test: requirement of tenant A never returned/writable for tenant B in `tests/Feature/IdentityVerification/RequirementsIsolationTest.php`
- [X] T027 [P] [US1] [M:H] [Test] Frontend unit test for requirement form render + permission-gated controls in `resources/js/__tests__/identity-requirements.test.tsx`

### Implementation for User Story 1

- [X] T028 [US1] [M:S] `UpsertIdentityRequirementAction` in `app/Modules/IdentityVerification/Application/Actions/` (tenant-scoped upsert, emits configured event → audit)
- [X] T029 [US1] [M:H] `RequirementsController` (`index`, `update`) + FormRequest in `app/Modules/IdentityVerification/Http/{Controllers,Requests}/` and routes in `app/Modules/IdentityVerification/Routes/api.php` behind `permission:identity.configure`
- [X] T030 [P] [US1] [M:H] Requirements page `resources/js/pages/tenant/identity/Requirements.tsx` (event default + tier overrides, `fetch` PUT with `Idempotency-Key`, loading/empty/error/forbidden states) on `DashboardLayout`
- [X] T031 [US1] [M:H] Wire `/tenant/events/{event_id}/identity` Inertia route in `routes/web.php` + nav manifest entry keyed to `identity.configure`
- [X] T032 [US1] [M:H] Add US1 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1 independently demoable (MVP).

---

## Phase 4: User Story 2 - Consent + government identity verification (Priority: P2)

**Goal**: Attendee consents and completes government verification via adapter+mock.

**Independent Test**: On a required event, view consent, decline (status stays
`pending`, nothing stored), then consent + run mock gov check → `gov_verified` with
verified attributes and stored consent.

### Tests for User Story 2 (MANDATORY)

- [X] T033 [P] [US2] [M:S] [Test] Integration test: consent decline stores nothing; consent + mock gov success → `gov_verified` + attribute mapping in `tests/Feature/IdentityVerification/GovVerificationTest.php`
- [X] T034 [P] [US2] [M:S] [Test] Consent-precondition test: verification/face endpoints 409 `identity_consent_missing` without consent in `tests/Feature/IdentityVerification/ConsentGuardTest.php`
- [X] T035 [P] [US2] [M:H] [Test] Signed government callback processed idempotently (no double-apply) in `tests/Feature/IdentityVerification/GovCallbackTest.php`
- [X] T036 [P] [US2] [M:H] [Test] Frontend test for consent notice + start-verification flow in `resources/js/__tests__/identity-verify.test.tsx`

### Implementation for User Story 2

- [X] T037 [US2] [M:S] `CaptureConsentAction` in `app/Modules/IdentityVerification/Application/Actions/` (stores `identity_consents` with version/residency; decline path stores nothing; emits audit)
- [X] T038 [US2] [M:S] `StartGovernmentVerificationAction` + `HandleGovernmentCallbackAction` in `Application/Actions/` (consult consent, call adapter, map attributes, set status/`verified_at`, idempotent callback, audit)
- [X] T039 [US2] [M:H] Attendee verification controller + FormRequests (`consent`, `verification` start, `verification` status GET, government `callback`) in `Http/` and routes in `Routes/api.php` (order-access-token auth for attendee routes; signed callback route)
- [X] T040 [P] [US2] [M:H] Public attendee surface `resources/js/pages/public/identity/Verify.tsx` (consent step → start verification → result; loading/error/`provider-unavailable` states)
- [X] T041 [P] [US2] [M:H] Consent notice component `resources/js/components/identity/ConsentNotice.tsx` (bilingual disclosures: what/why/retention/who/processing-mode/deletion)
- [X] T042 [US2] [M:H] Wire `/identity/{event_slug}/{order_token}` route in `routes/web.php` (public, token-scoped, no workforce RBAC)
- [X] T043 [US2] [M:H] Add US2 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1 + US2 independently functional.

---

## Phase 5: User Story 3 - Enforce requirement at credential issuance and gate entry (Priority: P3)

**Goal**: Block identity-required-but-unverified attendees at issuance and gate.

**Independent Test**: With `required_before_gate`, an unverified attendee is denied
with reason `identity_not_verified`; after verifying, entry is allowed. With
`required_before_credential`, no active credential until verified.

### Tests for User Story 3 (MANDATORY)

- [X] T044 [P] [US3] [M:S] [Test] Credential-issuance enforcement: issuance withheld until verified in `tests/Feature/IdentityVerification/IssuanceEnforcementTest.php`
- [X] T045 [P] [US3] [M:S] [Test] Gate enforcement: unverified → deny `identity_not_verified` on access log; verified → allow in `tests/Feature/IdentityVerification/GateEnforcementTest.php`
- [X] T046 [P] [US3] [M:H] [Test] `rejected` and `expired` treated as not verified at both boundaries in `tests/Feature/IdentityVerification/EnforcementStatusTest.php`

### Implementation for User Story 3

- [X] T047 [US3] [M:S] Integrate `IdentityGate` into the Credentials issuance action in `app/Modules/Credentials/Application/Actions/` (consult published query when boundary=`credential`; withhold active credential; surface pending-identity state) — no persistence read of identity tables
- [X] T048 [US3] [M:S] Integrate `IdentityGate` into the AccessControl gate-authorization decision in `app/Modules/AccessControl/Application/Actions/` (add `identity_not_verified` reason on the existing decision order when boundary=`gate`) — single trust path, no cached identity decision
- [X] T049 [P] [US3] [M:H] Surface identity-pending banner + status badge on attendee/credential detail pages in `resources/js/pages/tenant/**` (reuse StatusBadge from T020)
- [X] T050 [US3] [M:H] Add US3 i18n strings (pending banner, reason labels) to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US3 independently functional.

---

## Phase 6: User Story 4 - Face-capture fallback + manual review (Priority: P4)

**Goal**: Face capture (behind adapter) + reviewer approve/reject with reason.

**Independent Test**: Non-resident submits face capture → `pending` review item
(minimized template stored). Reviewer reject without reason → 422; reject with
reason → `rejected` (stored, audited); approve → `face_verified` (reviewer+time).

### Tests for User Story 4 (MANDATORY)

- [X] T051 [P] [US4] [M:S] [Test] Face-capture submit stores minimized encrypted template + creates review item in `tests/Feature/IdentityVerification/FaceCaptureTest.php`
- [X] T052 [P] [US4] [M:S] [Test] Manual review approve/reject incl. required-reason (422 without) + `identity.review` gating + audit in `tests/Feature/IdentityVerification/ReviewTest.php`
- [X] T053 [P] [US4] [M:H] [Test] Frontend reviewer queue approve/reject (ReasonModal on reject) in `resources/js/__tests__/identity-review.test.tsx`

### Implementation for User Story 4

- [X] T054 [US4] [M:S] `SubmitFaceCaptureAction` in `Application/Actions/` (require consent; call `FaceCaptureAdapter`; store `identity_biometric_artifacts` encrypted with `retention_until`; status `pending`; audit)
- [X] T055 [US4] [M:S] `ReviewVerificationAction` (approve → `face_verified`/`manually_approved` + reviewer/time; reject → `rejected` + required reason; audit) in `Application/Actions/`
- [X] T056 [US4] [M:H] Review queue query + controller (`index`, `review`) + FormRequest in `Application/Queries/` and `Http/`; routes behind `permission:identity.review`; face-capture submit route (order token)
- [X] T057 [P] [US4] [M:H] Reviewer page `resources/js/pages/tenant/identity/ReviewQueue.tsx` (list pending, `ConfirmModal` approve, `ReasonModal` reject) on `DashboardLayout`
- [X] T058 [P] [US4] [M:H] Face-capture panel in `resources/js/pages/public/identity/Verify.tsx` fallback branch + `resources/js/components/identity/FaceCapturePanel.tsx`
- [X] T059 [US4] [M:H] Wire `/tenant/events/{event_id}/identity/review` route in `routes/web.php` + nav entry keyed to `identity.review`
- [X] T060 [US4] [M:H] Add US4 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US4 independently functional.

---

## Phase 7: User Story 5 - Retention, deletion, residency, sensitive-data audit (Priority: P5)

**Goal**: Retention purge job, permitted deletion, audited sensitive access, residency.

**Independent Test**: Short retention window → purge job removes expired artifacts
(`purged_at` set) but keeps status/audit metadata; sensitive-detail view is audited;
deletion removes sensitive data + audits; no cross-border emission.

### Tests for User Story 5 (MANDATORY)

- [X] T061 [P] [US5] [M:S] [Test] Retention purge removes expired sensitive artifacts, preserves metadata in `tests/Feature/IdentityVerification/RetentionPurgeTest.php`
- [X] T062 [P] [US5] [M:S] [Test] Sensitive-data view is audited; `DELETE` gated by `identity.data.manage` + audited in `tests/Feature/IdentityVerification/SensitiveDataTest.php`
- [X] T063 [P] [US5] [M:H] [Test] No raw biometrics/gov payloads in any API resource; residency: cross-border disabled by default in `tests/Feature/IdentityVerification/ResidencyTest.php`

### Implementation for User Story 5

- [X] T064 [US5] [M:S] `PurgeExpiredIdentityArtifacts` job + Laravel schedule registration (delete expired sensitive artifacts/provider payloads, set `purged_at`, emit audit) in `app/Modules/IdentityVerification/Application/Actions/` + `routes/console.php`
- [X] T065 [US5] [M:S] `ViewIdentityDataAction` (audited read; minimized fields only) + `DeleteIdentityDataAction` (permitted deletion + audit) in `Application/Actions/`
- [X] T066 [US5] [M:H] Compliance controller (`show`, `destroy`) + FormRequest in `Http/`; routes behind `permission:identity.data.view` / `identity.data.manage`; resources exclude raw biometric/gov payloads
- [X] T067 [P] [US5] [M:H] Sensitive verification detail page `resources/js/pages/tenant/identity/VerificationDetail.tsx` (audited-on-load; `ReasonModal` delete) on `DashboardLayout`
- [X] T068 [US5] [M:H] Add US5 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: All user stories independently functional.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Docs, compliance sweep, parity, accessibility, and final validation.

- [X] T069 [P] [M:H] Update `docs/standards/audit-event-catalog.md` with the Phase 5 identity events and `docs/standards/data-classification.md` with identity/biometric classification, retention, and residency
- [X] T070 [P] [M:H] Sync OpenAPI: merge `specs/007-identity-verification/contracts/openapi.yaml` operations into the authoritative contract and run `npm run openapi:sync` / lint
- [X] T071 [P] [M:S] [Test] Accessibility + Arabic/RTL sweep (axe) across identity pages in `resources/js/__tests__/phase5-accessibility.test.tsx`
- [X] T072 [M:S] Verify SaaS/on-premise parity: adapters via config, local sensitive processing, unavailable-adapter degrades to fallback/retry (never fabricated success)
- [X] T073 [P] [M:S] [Test] Backend feature test: zero cross-tenant props/records across all IdentityVerification controllers in `tests/Feature/IdentityVerification/CrossTenantTest.php`
- [X] T074 [M:H] Run quality gates: `composer test`, `composer quality` (OpenAPI sync/lint, docs check, phase-boundary check), `npm run lint`, `npm run typecheck`, `npm run test`, `vite build` — fix failures
- [X] T075 [M:H] Run `quickstart.md` five scenarios end-to-end and record results

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies.
- **Foundational (Phase 2)**: depends on Setup; BLOCKS all user stories.
- **User Stories (Phases 3–7)**: depend on Foundational; then largely independent.
  US3 enforcement depends on the `IdentityGate` (T018) and reads statuses produced
  by US2/US4 but is independently testable by seeding a status. Priority order
  P1→P5; parallelizable across models after Phase 2.
- **Polish (Phase 8)**: depends on all targeted user stories.

### Cross-module tasks (handle carefully, [M:S])

- T047 (Credentials) and T048 (AccessControl) integrate the published `IdentityGate`
  query only — never a direct read of identity persistence, never a second credential
  trust path.

### Within Each User Story

- Tests first (fail) → actions/queries → controllers/routes → pages → i18n.

### Parallel Opportunities

- All `[P]` Setup/Foundational tasks (migrations T007–T010, models T011, mocks
  T014–T016, UI primitives T020–T021, tests T022–T024) run in parallel.
- After Phase 2, US1–US5 can proceed in parallel; within a story, `[P]` pages/tests
  run in parallel.
- Cheaper-model batch: dispatch all `[M:H]` migration/model/mock/page/i18n/test tasks
  to a cheaper model where `[P]`; reserve `[M:S]` for enforcement, adapters, gating,
  retention, and isolation-test design.

---

## Parallel Example: User Story 2

```text
# Actions/adapters wiring (capable model):
Task T037 [M:S] CaptureConsentAction
Task T038 [M:S] Start + HandleGovernmentCallback actions
# Presentational + tests (cheaper model, in parallel):
Task T040 [M:H] public Verify.tsx surface
Task T041 [M:H] ConsentNotice component
Task T036 [M:H] frontend verify test
```

---

## Model-Tier Summary (per "make this tasks for cheaper llm model")

- **[M:H] cheaper-model-eligible** (migrations/models/mocks/pages/routes/i18n/tests
  from explicit specs): T001–T002, T004–T012, T014–T016, T020–T023, T025, T027,
  T029–T032, T035–T036, T039–T043, T046, T049–T050, T053, T056–T060, T063, T066–T070,
  T074–T075.
- **[M:S] capable-model-recommended** (trust path/enforcement/adapters/consent/
  retention/audit/isolation-tests): T003, T013, T017–T019, T024, T026, T028, T033–T034,
  T037–T038, T044–T045, T047–T048, T051–T052, T054–T055, T061–T062, T064–T065, T071–T073.
- Every task, regardless of tier, must pass the same lint/type/test/quality gates.

---

## Implementation Strategy

### MVP First (User Story 1)

Phase 1 Setup → Phase 2 Foundational → Phase 3 US1 → validate → demo. US1 proves
per-event/tier requirement configuration with RBAC and tenant scope — the smallest
useful increment.

### Incremental Delivery

Add US2…US5 one at a time; each is independently testable. Enforcement (US3) lands
once US2 can produce a verified status; face fallback (US4) and compliance (US5)
round out coverage.

## Notes

- [P] = different files, no incomplete-task dependency.
- Tests are mandatory and must fail before implementation (Constitution VII).
- Presentation and cross-module hooks never read another module's persistence; data
  flows via the published `IdentityGate` query and module contracts (Constitution VI).
- No credential secret/key is ever signed, stored, or exposed; identity status only
  gates issuance/entry.
- Every sensitive surface stays Arabic/English + RTL and axe-clean; every sensitive
  action is audited in the same transaction as its state change.
- Production government-provider integration stays a tracked blocking assumption
  (research.md open questions); ship against mocks.
