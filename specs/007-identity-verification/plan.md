# Implementation Plan: Identity Verification

**Branch**: `007-identity-verification` | **Date**: 2026-07-08 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/007-identity-verification/spec.md`

**Product Phase**: Phase 5 — Identity Verification

**Deployment Modes**: SaaS and on-premise

## Summary

Add the fifth product increment: a provider-neutral **identity assurance** layer
that lets an organizer require a chosen level of identity verification per event
and per ticket/attendee tier, lets an attendee satisfy it under informed consent
through a government-identity check or a face-capture/manual-review fallback, and
blocks an identity-required attendee from receiving a credential or entering a
gate until verified. All sensitive identity/biometric data is minimized,
encrypted, retention-bound, residency-aware, and access-audited.

The design adds one new owned module — **`IdentityVerification`** (distinct from
the existing Foundation `Identity` auth module) — that owns verification
requirements, per-attendee verification records, consent records, and biometric
artifacts, plus two provider-neutral adapter contracts (`GovernmentIdentityAdapter`
and `FaceCaptureAdapter`) each backed by a mock. Because production KSA government
identity access (Nafath/Absher/Yaqeen) is an open legal/commercial question
(`all_plan.md` §20.3, §39.1) it stays a **blocking assumption**: the phase ships
against mock providers behind the adapters and adds real transports later without
changing domain logic, following the Phase 1/2/4 payment/wallet/ACS adapter
pattern.

Enforcement layers on top of the **existing** boundaries without a second trust
path: the Phase 1 credential-issuance action and the Phase 4 gate-authorization
decision consult a single published `IdentityGate` query to add an
`identity_not_verified` outcome when the event/tier requires it. No new credential
signing path, no cached entry decision, and no direct cross-module persistence
read are introduced. Venue marketplace (Phase 6) and enterprise/on-premise
hardening (Phase 7) remain out of scope.

## Technical Context

**Language/Version**: PHP 8.3 (backend domain) and TypeScript 5.9 (React 19
organizer/attendee/reviewer surfaces)

**Primary Dependencies**: Laravel 13, Sanctum, Fortify, Inertia 3, React 19,
Tailwind CSS 4, the Laravel HTTP client/queue/event/scheduler facilities reused
for the government + face adapters, consent capture, review queue, and the
retention-purge job; `react-i18next` for Arabic/English. No new vendor SDK enters
domain code — the government and face/liveness transports depend on providers
confirmed at integration time and are isolated behind adapter interfaces.

**Storage**: MySQL 8.4 shared schema with tenant-first composite constraints,
consistent with Phase 0–4; sensitive biometric artifacts stored encrypted with a
`retention_until` boundary and preferably as templates over raw images. No new
storage technology.

**Testing**: PHPUnit/Laravel with MySQL integration, queue/event/HTTP fakes, mock
government and face adapters, adapter contract suites, retention-job tests,
cross-tenant isolation and RBAC tests, sensitive-data audit tests, OpenAPI
lint/sync, Vitest/React Testing Library/axe for the UI surfaces, Pint, ESLint,
`tsc --noEmit`, and Vite build.

**Target Platform**: Native Windows or Linux web/worker/scheduler processes for
multi-tenant SaaS and supported on-premise deployments; on-premise MUST process
sensitive identity/biometric data locally with no outbound cloud dependency. No
Docker.

**Project Type**: API-first modular web application extending the existing
same-origin dashboard with organizer configuration and reviewer surfaces, an
attendee-facing consent/verification surface reached through the public
registration/order journey, and adapter-backed provider callbacks.

**Performance Goals**: An organizer configures a requirement in under 2 minutes
(SC-001); an enforcement decision (credential issuance / gate entry) adds only a
bounded identity-status lookup to the existing decision path; the retention-purge
job removes every expired sensitive artifact within one scheduled run after
`retention_until` (SC-006).

**Constraints**: Fail-closed tenant/event scope resolved server-side from the
attendee/credential and event mapping, never a client-supplied identifier; no
identity capture without a stored consent record; identity status gates issuance/
entry but never signs or exposes credential secrets; sensitive artifacts are
minimized (templates preferred), encrypted, retention-bound, residency-restricted
(on-premise processing supported, cross-border transfer configurable/blocked by
default), and access-audited; raw biometric data and raw government payloads are
never returned through any API; verification decisions and their audit evidence
commit or fail together; Arabic/English + RTL/LTR parity for every user-visible
surface; production government provider integration is a documented blocking
assumption, never faked as production-ready.

**Scale/Scope**: Existing Phase 0–4 target of 1,000 tenants and up to 100,000
attendees per event; one new domain module, roughly four owned tables
(`identity_verification_requirements`, `identity_verifications`,
`identity_consents`, `identity_biometric_artifacts`), two adapter contracts with
mocks, about a dozen review API operations (requirement config, consent capture,
verification start/callback/result, face-capture submit, review approve/reject,
sensitive-data view/delete, retention purge), organizer configuration + reviewer
dashboard surfaces, and an attendee consent/verification surface.

## Constitution Check

*GATE: PASS before research; PASS after design.*

| Gate | Design evidence | Status |
|---|---|---|
| API-first | `contracts/openapi.yaml` defines requirement config, consent capture, verification start/callback/result, face-capture submit, manual review approve/reject, sensitive-data view/delete, and retention operations with auth/context, validation, idempotency, envelopes, errors, and compatibility notes for merge into the authoritative contract. | PASS |
| Tenant isolation | Every new table carries `tenant_id` (+ `event_id`/`attendee_id` where owned); requirements, verifications, consents, artifacts, review queues, and adapter calls resolve tenant/event server-side, never from a client header. Composite constraints + negative isolation tests required (`data-model.md` invariant 1). | PASS |
| RBAC and auditability | New human permissions: `identity.configure`, `identity.review`, `identity.data.view`, `identity.data.manage`. Attendee self-service verification uses the public order/registration access token, not workforce RBAC. Every requirement change, consent capture/withdrawal, verification start/result, review decision (with reason), sensitive-data access, deletion, and retention purge is append-only audited in the same transaction as the state change. | PASS |
| Credential security | Identity status gates issuance/entry through the single published `IdentityGate` query; the feature never signs, mints, stores, or exposes credential secrets/keys. Enforcement reuses the unmodified Phase 1 credential validation and Phase 4 gate decision order and only adds an `identity_not_verified` reason. No second credential trust path. | PASS |
| Deployment parity | Same-origin surfaces + adapters run identically in SaaS and on-premise via configuration; on-premise processes sensitive identity/biometric data locally with no cloud-only dependency; unavailable adapters degrade to fallback or a clear error, never a fabricated verified result. No Docker. | PASS |
| GCC/KSA and PDPL | Identity/biometric are the most sensitive classes: lawful basis = stored consent, data minimized (templates over raw images), encrypted, retention-bound + deletable, residency-restricted (cross-border off by default), and access restricted to audited permitted actors. Government access remains an explicit blocking assumption behind adapters, never a disguised production integration. (`data-classification.md` updated.) | PASS |
| White-label and localization | Consent notices, statuses, rejection reasons, prompts, and reviewer surfaces honor tenant branding and are Arabic/English with RTL/LTR and locale-aware dates; consent notice text is tenant/event configuration, not code-forked. | PASS |
| Modularity and adapters | Only the new `IdentityVerification` module and `resources/js` change plus narrow, published-query integration points in `Credentials` and `AccessControl`. Government and face/liveness capabilities are reached only through adapter interfaces with mocks for tests and validated adapters for production. No module reads another module's persistence internals. | PASS |
| Automated tests | Unit (requirement resolution, status transitions, retention math), integration (config, consent, mock gov success/failure/attribute mapping, face fallback, review approve/reject, issuance + gate enforcement, retention purge, deletion), contract (both adapters), isolation/RBAC, sensitive-data audit, and E2E for the primary journeys per CR-009/`test-plan`. | PASS |
| Phased delivery | Phase 5 builds on the accepted Phase 0–4 core (tenant/RBAC/audit/adapter foundation, attendees/credentials/events/tiers, ACS gate) and MUST NOT begin until Phase 4's Definition of Done passes; it precedes Phase 6 and weakens no existing contract. | PASS |

No constitution exception is required. The only genuinely unresolved external
dependency — production KSA government identity access — is recorded as a blocking
assumption behind the `GovernmentIdentityAdapter`, consistent with Constitution IV.

## Architecture and Module Ownership

### IdentityVerification (new, owned)

Owns: verification requirement policy (per event/tier), per-attendee verification
records and their status machine, consent records, biometric artifacts (encrypted,
retention-bound), the two adapter contracts + mocks, the manual review queue
query, the retention-purge scheduled job, and the published `IdentityGate` query.
Layout mirrors existing modules: `Domain/`, `Application/{Actions,Queries,Support}`,
`Contracts/`, `Infrastructure/{Adapters,Persistence}`, `Http/{Controllers,Requests,
Resources,Middleware}`, `Providers/`, `Routes/`, `Testing/`.

### Existing modules (consumed / narrowly integrated)

- **Credentials** (Phase 1): its issuance action consults `IdentityGate` when the
  event/tier requires verification "before credential issuance" — via the published
  query, never a persistence read.
- **AccessControl** (Phase 4): its gate authorization decision consults `IdentityGate`
  when the event/tier requires "before gate entry", adding the `identity_not_verified`
  reason on the existing decision path.
- **Attendees / Events / Ticketing**: supply attendee, event, and tier context;
  verified status is surfaced on the attendee.
- **Audit**: new Phase 5 listeners record identity requirement, consent,
  verification, review, sensitive-access, deletion, and retention events.
- **Authorization**: new permission keys seeded in `PermissionSeeder` and mirrored
  in `docs/standards/permission-catalog.md` (+ `docs/security/`).
- **Tenancy / Shared**: tenant context, idempotency, error envelopes, config.

### Frontend (`resources/js`, extended, on the Phase 6 dashboard shell)

Organizer requirement-configuration surface (event settings), reviewer queue +
approve/reject with reason, attendee consent + verification surface (public
journey), and identity-status badges on attendee/credential detail — all on the
existing `DashboardLayout`, shared components, and `locales/{en,ar}.ts`.

## Enforcement and Adapter Boundaries

- **Single trust path**: `IdentityGate::evaluate(tenant, event, attendee, boundary)`
  returns satisfied/blocked + reason. Credential issuance and gate authorization
  call it; neither re-implements identity logic. Absent a requirement it returns
  satisfied (`not_required`).
- **GovernmentIdentityAdapter**: `startVerification`, `handleCallback`,
  `fetchResult`, `mapAttributes` → provider-neutral result; `MockGovernmentIdentityAdapter`
  exercises success/failure/unsupported. Selected via `config('identity-verification.default_government_adapter','mock')`.
- **FaceCaptureAdapter**: `submitCapture` (+ optional `liveness`) → capture
  reference/template; `MockFaceCaptureAdapter` for tests. Selected via
  `config('identity-verification.default_face_adapter','mock')`.
- Adapters define timeout, retry, idempotency, error mapping, and observability per
  `docs/standards/adapter-authoring.md`; production readiness is evidence-gated.

## Data, Retention, and Residency

- Owned tables carry `tenant_id` (+ `event_id`/`attendee_id`). Sensitive artifacts
  live in `identity_biometric_artifacts` with `retention_until`, encrypted at rest,
  template preferred over raw image, and access logged.
- A scheduled retention-purge job deletes expired sensitive artifacts and provider
  payloads while preserving non-sensitive status/audit metadata per policy.
- Residency: cross-border transfer is off by default and configurable; on-premise
  processes locally. Decisions recorded in `docs/standards/data-classification.md`.

## Cross-Cutting UX Strategy

- **States**: consent, verification, and review surfaces have loading, empty,
  error, and forbidden states; submit controls disable while in flight.
- **Consent**: bilingual, configuration-driven notice shown before any capture;
  stored with version + timestamp; decline captures nothing.
- **Status**: one identity `StatusBadge` variant set (`not_required`/`pending`/
  `gov_verified`/`face_verified`/`manually_approved`/`rejected`/`expired`).
- **Localization/RTL**: `react-i18next` strings in `locales/{en,ar}.ts`, logical
  Tailwind properties, locale-aware formatting.

## Project Structure

### Documentation (this feature)

```text
specs/007-identity-verification/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── openapi.yaml            # review API operations for this phase
│   ├── identity-adapter.md     # GovernmentIdentityAdapter + FaceCaptureAdapter contracts
│   └── dashboard-contract.md   # route → permission → states for the UI surfaces
├── checklists/
│   └── requirements.md
└── tasks.md                    # created later by /speckit.tasks
```

### Source Code (repository root)

```text
app/
├── Modules/
│   └── IdentityVerification/
│       ├── Domain/{ValueObjects, Results, Events, Context}
│       ├── Application/{Actions, Queries, Support}   # IdentityGate query lives here
│       ├── Contracts/{GovernmentIdentityAdapter.php, FaceCaptureAdapter.php}
│       ├── Infrastructure/
│       │   ├── Adapters/{MockGovernmentIdentityAdapter.php, MockFaceCaptureAdapter.php}
│       │   └── Persistence/{Models, Migrations}
│       ├── Http/{Controllers, Requests, Resources, Middleware}
│       ├── Providers/IdentityVerificationServiceProvider.php
│       ├── Routes/api.php
│       └── Testing/{FakeGovernmentIdentityAdapter.php, FakeFaceCaptureAdapter.php}
├── Modules/Credentials/**        # issuance consults IdentityGate (published query)
├── Modules/AccessControl/**      # gate decision consults IdentityGate (published query)
├── Modules/Audit/Application/Listeners/Phase5/**
└── Modules/Authorization/**      # new permission keys (seeder)
config/identity-verification.php   # adapter selection, retention windows, residency mode
database/migrations/**             # four new owned tables
database/seeders/PermissionSeeder.php  # identity.* keys
resources/js/
├── pages/tenant/identity/**       # requirement config, reviewer queue
├── pages/public/identity/**       # attendee consent + verification surface
├── components/identity/**         # consent notice, status badge, review panel
└── locales/{en,ar}.ts
routes/web.php                     # dashboard route wiring behind auth + permission
tests/
├── Feature/IdentityVerification/**
├── Architecture/Phase5ModuleBoundaryTest.php
└── (frontend) resources/js/__tests__/**
docs/
├── standards/permission-catalog.md   # + identity.* keys
├── standards/data-classification.md   # identity/biometric classification + retention/residency
└── security/permissions-phase5.md
```

**Structure Decision**: Keep the single Laravel + Inertia/React deployment. New
behavior lands in one owned `IdentityVerification` module plus narrow published-query
integration points in `Credentials` and `AccessControl`, the shared React surfaces,
and one new config file. No separate service, no new credential path, no direct
cross-module persistence reads.

## Testing and Documentation Gates

- **Unit**: requirement resolution (event vs tier), status transitions, retention
  math, consent gating.
- **Integration**: config; consent capture/decline; mock government
  success/failure/unsupported + attribute mapping; face-capture fallback; review
  approve/reject with reason; enforcement at credential issuance and gate entry;
  retention purge; deletion; cross-tenant isolation; RBAC gating; sensitive-data
  audit.
- **Contract**: `GovernmentIdentityAdapter` and `FaceCaptureAdapter` suites (mock +
  fake).
- **E2E (Vitest/RTL + axe)**: organizer configures requirement; attendee consents +
  verifies; reviewer approves/rejects; blocked-then-allowed at the gate; Arabic/RTL.
- **Quality gates**: `composer quality` (OpenAPI sync/lint, docs check,
  phase-boundary check), Pint, ESLint `--max-warnings=0`, `tsc --noEmit`, Vite build.

Documentation deliverables: the three contracts docs, updates to
`permission-catalog.md`, `data-classification.md`, `audit-event-catalog.md` (Phase 5
events), and a new `docs/security/permissions-phase5.md`.

## Post-Design Constitution Re-check

The design preserves every pre-design PASS: one new owned module with published-query
integration (no persistence reads across modules); tenant scope resolved server-side;
RBAC + append-only audit on every sensitive action; no credential secret/key exposure
and no second trust path; Arabic/English + RTL parity; SaaS/on-premise parity with
local sensitive-data processing; and Phase 6+ surfaces kept absent by the
phase-boundary check. Government provider access remains an explicit blocking
assumption behind an adapter.

**Result**: PASS. No complexity exception or governance waiver is required.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
