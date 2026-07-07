# Implementation Plan: Kiosk Check-In, Badge Printing, and Manual Desk

**Branch**: `004-kiosk-badge-printing-manual-desk` | **Date**: 2026-07-06 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from
`/specs/004-kiosk-badge-printing-manual-desk/spec.md`

**Product Phase**: Phase 3 Kiosk-Badge-Printing-Manual-Desk

**Deployment Modes**: SaaS and on-premise

## Summary

Extend the accepted Phase 0 tenant/RBAC/audit foundation, Phase 1
registration/ticketing/credential core, and Phase 2 wallet-pass/scanning
core with the third product increment: self-service kiosk check-in with
paired device-session authentication and health monitoring, a staff-
operated manual desk that reuses the same check-in core for attendee
lookup/check-in/print without a kiosk device, a no-code organizer badge
template designer, permission-and-reason-gated badge print/reprint through
a provider-neutral printer adapter, and toggleable walk-up registration
that reuses Phase 1's exact registration/credential-issuance action.

The design adds two new owned modules — `Kiosk` and `BadgePrinting` — and
extends `Scanning` (attendee lookup, `kiosk`/`manual_desk` scanner
sources — both already reserved by Phase 2's schema) and `Attendees`
(walk-up origin flag and registration action). Neither new module
introduces a second credential trust path, a cached entry decision, or a
bypass of Phase 2's scan validation order. Kiosk devices authenticate with
a paired device-session secret, never a human RBAC identity. Badge
templates are structured, allowlisted JSON, never free-form markup.
Printer output is isolated behind one new adapter contract, matching the
Apple/Google wallet adapter pattern from Phase 2. ACS zone/lane/anti-
passback (Phase 4), identity verification (Phase 5), and venue marketplace
(Phase 6) remain explicitly out of scope.

## Technical Context

**Language/Version**: PHP 8.3 and TypeScript 5.9

**Primary Dependencies**: Laravel 13, Sanctum, Fortify, Inertia 3, React 19,
Tailwind CSS 4, shadcn/ui prerequisites, Laravel HTTP client, queue, and
notification facilities reused for the printer adapter and lookup-
confirmation codes; no new vendor SDK is required in domain code (the
printer adapter's real implementation depends on the specific hardware
chosen at rollout time and is isolated behind the adapter interface)

**Storage**: MySQL 8.4 shared schema with tenant-first composite
constraints, consistent with Phase 0/1/2; no new storage technology
introduced

**Testing**: PHPUnit/Laravel test runner with MySQL integration, queue/
event and HTTP fakes, a fake printer adapter, adapter contract suites,
OpenAPI lint/conformance, Vitest/React Testing Library/axe, browser/system
tests, Pint, ESLint, TypeScript, and Vite build

**Target Platform**: Native Windows or Linux web/worker/scheduler processes
for multi-tenant SaaS and supported on-premise deployments; no Docker or
Sail

**Project Type**: API-first modular web application extending the existing
same-origin organizer dashboard and staff check-in surface with kiosk
device endpoints, a manual desk UI, and a badge template designer

**Performance Goals**:

- p95 self-service kiosk check-in-to-printed-badge completes under 30
  seconds in at least 95% of tested kiosk interactions (SC-001);
- manual desk lookup-to-printed-badge completes under 60 seconds in at
  least 90% of tested desk interactions (SC-002);
- a kiosk/printer status change is visible to authorized viewers within a
  short, bounded polling delay (SC-007), consistent with Phase 2's
  dashboard bound;
- zero false accepts across duplicate/revoked/expired kiosk/desk check-ins
  in automated security testing (SC-003);
- zero badges printed with missing/default content when no active
  template exists (SC-004).

**Constraints**: Fail-closed tenant/event scope resolved from the kiosk's
paired session or the authenticated staff session, never a client-supplied
identifier; no kiosk-specific or badge-specific second credential trust
path around the Phase 1 credential and Phase 2 scan decision order; no raw
kiosk device-session secret, printer connection credential, or lookup
confirmation code in logs/audit/errors/responses; check-in, print/reprint,
walk-up registration, and their audit evidence commit or fail together;
printer/kiosk unavailability degrades explicitly and remains retryable
without silently dropping a check-in or print request; badge templates are
restricted to a fixed field allowlist, never arbitrary markup; Arabic/
English and RTL/LTR parity for kiosk screens, the desk UI, and printed
badge layouts; no Phase 4+ feature (ACS zones/lanes/anti-passback, identity
verification, venue marketplace) or placeholder

**Scale/Scope**: Existing Phase 0/1/2 target of 1,000 tenants and up to
100,000 attendees per event; two new domain modules, about four new owned
tables (`kiosks`, `kiosk_sessions`, `badge_templates`, `badge_print_jobs`)
plus companion columns on `event_check_in_settings` and `attendees`,
roughly a dozen new review API operations (kiosk registration/pairing/
retirement/health, kiosk-session heartbeat/lookup/scan/print, manual desk
lookup/walk-up, badge template CRUD/activate/deactivate, badge print/
reprint), an organizer badge template designer page, a kiosk health page,
and a manual desk UI surface

## Constitution Check

*GATE: PASS before research; PASS after design.*

| Gate | Design evidence | Status |
|---|---|---|
| API-first | `contracts/openapi.yaml` defines kiosk management, kiosk device-session, manual desk, badge template, and badge print/reprint operations with auth/context, validation, idempotency, envelopes, errors, and compatibility notes for merge into the authoritative contract, including the Phase 2 `submitScan` `scanner_type` enum extension. | PASS |
| Tenant isolation | Every new table carries `tenant_id`; event-owned tables also carry `event_id`; kiosk requests resolve tenant/event from the paired session record, desk requests from the authenticated staff session, never a client header. Composite foreign keys and negative isolation tests are required (`data-model.md` invariant 1). | PASS |
| RBAC and auditability | New permissions cover kiosk management/health, manual desk operation, badge print/reprint, badge template management, and walk-up registration. Kiosk registration/pairing/status change, every kiosk/desk check-in, every print/reprint (including blocked attempts), and walk-up registration are append-only audited in the same transaction as the state change. | PASS |
| Credential security | `contracts/kiosk-contract.md` and `contracts/badge-contract.md` fix the rule that kiosk and desk check-in reuse the unmodified Phase 2 scan decision order, and that reprint's old-QR revocation reuses the unmodified Phase 1 credential revoke/reissue action; no second signing key or entry-decision path is introduced. | PASS |
| Deployment parity | Kiosk check-in, manual desk operation, and badge printing remain fully operable in both SaaS and on-premise; a kiosk or printer adapter that is unreachable reports an explicit degraded/failed state rather than silently dropping the request (`printer-adapter.md`). | PASS |
| GCC/KSA and PDPL | Badge templates and printed badges store only the allowlisted fields listed in `data-model.md`; no national ID or biometric data. Walk-up registration collects only the Phase 1 minimum fields. Retention/residency/anonymization reuse Phase 1's approved tenant policy and preserve non-identifying print-job/audit evidence after anonymization. | PASS |
| White-label/localization | Kiosk on-screen messages, the manual desk UI, and printed badge content support Arabic/English and RTL/LTR (`dashboard-contract.md`); documented fallback where a printer/paper format limits bilingual layout. | PASS |
| Modularity/adapters | `Kiosk` and `BadgePrinting` are new owned modules; the printer adapter is a stable interface (`printer-adapter.md`) with a fake implementation for tests; core domain code contains no printer driver/SDK types. | PASS |
| Automated tests | Unit, MySQL concurrency (kiosk pairing/session, simultaneous kiosk+desk check-in), contract (printer adapter, kiosk session), isolation, RBAC (kiosk/desk/print/reprint/walk-up), audit atomicity, and health-monitoring suites are release gates per CR-009. | PASS |
| Phased delivery | The design implements only the `all_plan.md` Phase 3 scope. ACS zone/lane/anti-passback, identity verification, and venue marketplace remain forbidden by architecture tests, matching Phase 1/2's enforcement pattern. | PASS |

No constitution exception is required. Live printer hardware integration
remains gated on adapter contract-test evidence and production-readiness
review, matching the Phase 1/2 pattern for live payment/wallet adapters;
the disabled/fake printer adapter does not misrepresent readiness.

## Research Decisions

Detailed decisions and alternatives are in [research.md](research.md):

1. Add `Kiosk` and `BadgePrinting` as the only two new owned modules;
   "manual desk" is a surface reusing `Scanning`/`Attendees`, not a third
   module.
2. Kiosk devices authenticate with a paired device-session token, never a
   human RBAC identity.
3. Attendee lookup by name/email/phone is added to `Scanning`, reused by
   both `Kiosk` and the manual desk.
4. Optional lookup confirmation reuses the existing notification adapter,
   not a new channel.
5. Badge templates are validated structured JSON over a fixed field
   allowlist, rendered server-side into a provider-neutral print payload.
6. Printer output is a new `PrinterAdapter` contract following the Phase 0
   adapter-contract pattern.
7. Reprint creates a new linked `BadgePrintJob`; old-badge QR revocation
   reuses the existing credential revoke/reissue action verbatim.
8. Kiosk and printer health reuse Phase 2's bounded-polling dashboard
   pattern; no new persistent-connection infrastructure.
9. Walk-up registration reuses the Phase 1 registration/credential-
   issuance action with an `origin` flag, not a second identity or
   payment model.
10. Kiosk, badge template/print, and the walk-up toggle are tenant/event-
    scoped exactly like Phase 1/2 configuration.

## Architecture and Module Ownership

### Kiosk

Owns kiosk device registration, paired device-session records, heartbeat-
derived health, and the kiosk-session-authenticated HTTP surface (lookup,
scan, print trigger). It consumes `Scanning`'s lookup query and
`SubmitScanAction` contract, and `BadgePrinting`'s print contract; it never
persists or re-derives credential validity, check-in state, or badge
template content itself.

### Badge Printing

Owns badge templates, badge print jobs, the provider-neutral printer
adapter contract, and its fake/hardware implementations. It consumes
`Attendees`/`Credentials` read data to render a payload and, for reprints
with old-QR revocation enabled, calls the Phase 1 `Credentials`
revoke-and-reissue action; it never persists or re-derives credential
validity itself.

### Existing modules (extended)

- Scanning (Phase 2) gains a bounded `LookupAttendeesQuery` and begins
  producing `kiosk`/`manual_desk` `ScanEvent.scanner_type` values already
  reserved by its Phase 2 migration; no new `ScanEvent.result` value is
  introduced, and no schema migration is required for scanner type
  activation.
- Attendees (Phase 1) gains an `origin` column and a
  `RegisterWalkUpAttendeeAction` that calls the exact Phase 1 registration
  and credential-issuance code path with an `origin = walk_up` flag.
- Events (Phase 1)/EventCheckInSetting (Phase 2) gains the walk-up,
  reprint-QR-revocation, lookup-confirmation, and kiosk-offline-threshold
  configuration fields, owned and written only by the module that already
  owns the setting row (`Scanning`).
- Credentials (Phase 1) is extended with no new fields or behavior; Phase
  3 only consumes its existing validation and revoke/reissue contract.
- Tenancy, Authorization, Audit, Shared, Operations, and AdminConsole
  provide the same trusted context, RBAC, audited transactions, errors/
  envelopes/idempotency, telemetry, and presentation conventions
  established in Phase 0/1/2.

## Core Execution Flows

### Kiosk registration, pairing, and confirmation

```text
authorized organizer/ops actor (kiosk.manage)
  -> register Kiosk row scoped to tenant/event
  -> pair: issue device-session secret, store only its hash,
     show raw secret exactly once, revoke any prior session
  -> (if confirmation_required) kiosk operator confirms PIN/one-time code
     before the session may submit scans/lookups/prints
```

### Kiosk or manual desk check-in and badge print

```text
kiosk (KioskSessionAuth) or staff (TenantSession, checkin.desk.perform)
  -> Lookup: qr_payload -> Credentials.validate() (Phase 1 order, unchanged)
     OR name/email/phone -> Scanning.LookupAttendeesQuery (bounded, scoped)
     OR (if lookup_confirmation_required) one-time code confirmed first
  -> SubmitScanAction (Phase 2, unchanged) with scanner_type =
     'kiosk' | 'manual_desk', scanner_id = kiosk id | staff user id
  -> on accepted/manual_override: BadgePrinting.createPrintJob
       -> resolve event's active BadgeTemplate (reject if none)
       -> render allowlisted fields into PrintPayload
       -> PrinterAdapter.print() (fake in tests; hardware-backed live)
       -> BadgePrintJob status: queued -> printed | failed
  -> after commit: audit evidence for check-in and print
```

### Badge reprint

```text
authorized staff (badge.reprint) + non-empty reason
  -> resolve immediately preceding BadgePrintJob for the attendee
     (reject if none exists)
  -> (if reprint_revokes_old_qr) Credentials revoke-and-reissue action
     (Phase 1, unchanged) for the attendee's credential
  -> render new PrintPayload from the (possibly reissued) credential
  -> create new BadgePrintJob: is_reprint = true,
     original_print_job_id = prior job, reprint_reason recorded
  -> PrinterAdapter.print()
  -> audit record for every attempt, including blocked ones
```

### Walk-up registration

```text
authorized manual desk staff (attendee.walkup.register)
  -> verify EventCheckInSetting.walk_up_registration_enabled (reject if false)
  -> Attendees: exact Phase 1 registration + Credentials issuance action,
     origin = 'walk_up'
  -> if payment required and walk_up_payment_method_enabled = false:
       mark payment_status = payment_pending, surface explicitly
     else: proceed through the existing Phase 1 payment path
  -> immediately eligible for the kiosk/desk check-in and print flow above
```

### Kiosk and printer health

```text
kiosk (KioskSessionAuth) periodic heartbeat
  -> report printer_status (from PrinterAdapter.health())
  -> server updates Kiosk.last_heartbeat_at / printer_status
  -> Kiosk.status derived: online / offline (threshold) / degraded (printer fault)
  -> authorized viewer polls bounded, tenant/event-scoped kiosk list
     (contracts/dashboard-contract.md, same short-interval pattern as
     Phase 2's check-in dashboard)
```

## Kiosk, Desk, and Access Context

- Kiosk registration/pairing/retirement and kiosk health viewing require
  authenticated tenant session/bearer auth, `X-Tenant-ID`, active
  membership, and an exact permission (`kiosk.manage`,
  `kiosk.health.view`); none of these routes trust a client-supplied
  event or tenant identifier beyond what the authenticated context
  resolves.
- Kiosk device-session routes (`/kiosk/v1/*`) are unauthenticated by tenant
  session (the caller is unattended hardware, not a logged-in user) but
  authenticate every call via the paired session secret, resolving tenant/
  event/kiosk scope from the matched session record, never from the
  request path or body.
- Manual desk, badge template, badge print/reprint, and walk-up
  registration routes require authenticated tenant session/bearer auth,
  `X-Tenant-ID`, active membership, and an exact permission
  (`checkin.desk.perform`, `badge.template.manage`, `badge.print`,
  `badge.reprint`, `attendee.walkup.register`).
- A kiosk or desk request targeting a credential, attendee, template, or
  print job outside the authenticated context's tenant/event returns the
  same rejected/not-found response as an unknown target; the specific
  mismatch is never disclosed to the caller.

## Data Protection and Key Management

- Kiosk device-session secrets are hashed at rest, shown in raw form
  exactly once at pairing time, and never logged, audited, queued, or
  returned by any other endpoint.
- Printer connection credentials (network address, driver token, pairing
  key) are `secret_reference` values resolved only inside adapter
  infrastructure, following the Phase 1 payment/notification pattern.
- Badge template content and rendered print payloads are limited to the
  fixed field allowlist in `data-model.md` §Badge Template and never
  include attendee contact details, national identifiers, biometric data,
  or payment data.
- Lookup one-time confirmation codes are short-lived, single-use, and
  excluded from logs and audit metadata beyond a "confirmation succeeded/
  failed" outcome.
- Retention, residency, and anonymization reuse Phase 1's tenant-approved
  policy; anonymizing an attendee preserves `BadgePrintJob` and audit rows
  with identity fields redacted rather than deleting required evidence,
  matching Phase 2's wallet/credential anonymization cascade pattern.

## API and Contract Strategy

- [contracts/openapi.yaml](contracts/openapi.yaml) is the Phase 3 review
  contract. Implementation merges it into the authoritative
  `specs/001-project-foundation/contracts/openapi.yaml` and generated docs,
  and extends the existing Phase 2 `submitScan` operation's `scanner_type`
  enum with `manual_desk` (kiosk check-in uses the separate kiosk-session-
  authenticated `/kiosk/v1/scans` operation).
- [contracts/printer-adapter.md](contracts/printer-adapter.md) governs the
  printer adapter's operations, error taxonomy, and contract test matrix,
  extending `specs/001-project-foundation/contracts/adapter-contract.md`.
- [contracts/kiosk-contract.md](contracts/kiosk-contract.md) governs kiosk
  pairing/session authentication, lookup, and check-in, extending
  `specs/003-wallet-passes-scanning/contracts/scan-contract.md` exactly at
  its documented Phase 2/Phase 3 boundary.
- [contracts/badge-contract.md](contracts/badge-contract.md) governs badge
  template lifecycle and the print/reprint decision order.
- [contracts/dashboard-contract.md](contracts/dashboard-contract.md)
  extends the Phase 2 dashboard contract with kiosk health, badge template
  designer, and manual desk navigation, authorization, and UI-state rules.
- Tenant staff/organizer routes require bearer/session authentication,
  `X-Tenant-ID`, active membership, exact permission, idempotency for
  print/reprint/registration/pairing submission, correlation, locale, and
  bounded rate limits.
- Kiosk device-session routes authenticate via the `KioskSession
  {secret}` scheme documented in `kiosk-contract.md`; they never accept a
  tenant session token as a substitute.
- Unknown fields are rejected on all kiosk, lookup, scan, print, reprint,
  template, and walk-up writes.
- Stable errors extend the Phase 0/1/2 catalog with:
  `kiosk_session_invalid`, `kiosk_session_unconfirmed`, `kiosk_retired`,
  `lookup_too_many_matches`, `lookup_confirmation_required`,
  `lookup_confirmation_invalid`, `badge_template_not_active`,
  `badge_template_invalid_field`, `badge_reprint_reason_required`,
  `badge_reprint_not_permitted`, `badge_no_prior_print_job`,
  `badge_print_not_permitted`, `printer_unavailable`, `printer_error`, and
  `payload_rejected`.
- Printer driver-specific codes/payloads never become API error codes.

## RBAC and Audit Catalog

Permissions:

```text
kiosk.manage
kiosk.health.view
checkin.desk.perform
badge.print
badge.reprint
badge.template.manage
attendee.walkup.register
```

These are workforce-role-scoped like Phase 1/2's permissions; kiosk
devices authenticate through their own paired session (Research Decision
2), never through a human RBAC grant. System Tenant Administrator receives
all Phase 3 permissions through an idempotent role update. The Phase 2
"On-Site Staff / Scanner" system-role template may optionally be extended
with `checkin.desk.perform`; `kiosk.manage`, `badge.reprint`,
`badge.template.manage`, and `attendee.walkup.register` remain separately
grantable least-privilege additions, never bundled by default. Custom
roles remain empty.

Required audit action families:

- `kiosk.*`: registered, paired, session_confirmed, retired,
  status_changed;
- `desk_scan.*`: accepted, rejected, duplicate, revoked, expired,
  manual_override (reuses Phase 2's `scan.*` family with `scanner_type`
  distinguishing kiosk/manual_desk sources);
- `badge_template.*`: created, updated, activated, deactivated;
- `badge_print.*`: created, printed, failed, reprinted, reprint_blocked;
- `walk_up_attendee.*`: registered, registration_blocked.

Every reprint attempt (successful or blocked by missing permission,
reason, or prior job) is individually audited per CR-003/CR-009,
consistent with Phase 2's precedent that every security-relevant decision
is audited even when it is a read-adjacent or convenience action rather
than a persisted write.

## Queues, Scheduling, and Recovery

- `PrintBadgeJob` (or synchronous handling for the desk's interactive
  flow) invokes one pending `BadgePrintJob` through the printer adapter,
  idempotently, recording `printed`/`failed` with a safe reason.
- `ReconcileKioskHealthJob` is a bounded repair job that recomputes a
  kiosk's derived `online`/`offline` status from `last_heartbeat_at`
  against the configured threshold if drift is ever detected; it is not
  strictly on the request path since heartbeat processing already updates
  status inline.
- Check-in, print-job creation, and reprint audit evidence remain
  synchronous within their audited transaction; queue failure cannot
  remove or rewrite required check-in/print evidence.
- Readiness reports safe categories for printer adapter reachability and
  kiosk fleet online/offline/degraded counts, alongside the existing
  Phase 1/2 readiness categories. Public readiness exposes aggregate state
  only.

## Project Structure

### Documentation (this feature)

```text
specs/004-kiosk-badge-printing-manual-desk/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── openapi.yaml
│   ├── printer-adapter.md
│   ├── kiosk-contract.md
│   ├── badge-contract.md
│   └── dashboard-contract.md
└── tasks.md                 # Created later by speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Modules/
│   ├── Kiosk/
│   │   ├── Application/{Actions,Queries,Jobs}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Results,ValueObjects}
│   │   ├── Http/{Controllers/Management,Controllers/Device,Requests,Resources}
│   │   ├── Infrastructure/Persistence/Models/
│   │   └── Providers/
│   ├── BadgePrinting/
│   │   ├── Application/{Actions,Jobs}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Results,ValueObjects}
│   │   ├── Http/{Controllers,Requests,Resources}
│   │   ├── Infrastructure/{Adapters/Fake,Adapters/Generic,Persistence}
│   │   ├── Providers/
│   │   └── Testing/
│   ├── Scanning/
│   │   ├── Application/Queries/LookupAttendeesQuery.php   (new)
│   │   └── Http/Controllers/ManualDesk/                    (new)
│   ├── Attendees/
│   │   └── Application/Actions/RegisterWalkUpAttendeeAction.php   (new)
│   └── AdminConsole/
│       ├── Http/Controllers/Tenant/Kiosk/
│       ├── Http/Controllers/Tenant/BadgeTemplates/
│       └── ViewModels/{Kiosk,BadgeTemplates,ManualDesk}/
├── Console/Commands/
└── Providers/ModuleServiceProvider.php
config/
└── printing.php
database/
├── factories/
├── migrations/
└── seeders/
resources/js/
├── components/{kiosk,badge-templates,manual-desk}/
└── pages/tenant/{kiosk,badge-templates,manual-desk}/
routes/
├── api.php
└── web.php
tests/
├── Architecture/
├── Contract/{Phase3,Printer}/
├── Feature/{Kiosk,BadgePrinting,Scanning,Attendees}/
├── Integration/{MySql,Queue,Security}/
├── Performance/
├── Unit/{Kiosk,BadgePrinting}/
└── Browser/Phase3/
```

**Structure Decision**: Keep the one Laravel deployment and React/Inertia
frontend, adding `Kiosk` and `BadgePrinting` beside the Phase 0/1/2
modules, and extending `Scanning`/`Attendees` with the lookup query, desk
controllers, and walk-up action. Controllers stay thin; application
actions coordinate the Phase 1/2 credential and scan contracts plus the new
provider-neutral printer contract; domain objects own kiosk-session and
badge-print-job lifecycle transitions; infrastructure owns persistence,
adapter HTTP/driver calls, and queues. No generic repository, microservice,
separate frontend deployment, or printer SDK leaks across module
boundaries.

## Migration and Rollback Strategy

Migration order:

1. `kiosks` (depends on Phase 1 `events`);
2. `kiosk_sessions` (depends on `kiosks`);
3. `badge_templates` (depends on Phase 1 `events`);
4. `badge_print_jobs` (depends on Phase 1 `attendees`, `credentials`,
   `badge_templates`, and `kiosks`);
5. walk-up/reprint/lookup/kiosk-threshold columns added to the existing
   Phase 2 `event_check_in_settings` table;
6. `origin` column added to the existing Phase 1 `attendees` table;
7. permission and system-role catalog updates for the Phase 3 permission
   list.

Rules:

- Add tenant/event composite unique keys before dependent composite
  foreign keys, consistent with Phase 0/1/2.
- Add lifecycle/status checks, at-most-one-active-badge-template
  constraint strategy, and tenant-first indexes in the creation migration.
- No migration is required to activate `kiosk`/`manual_desk`
  `ScanEvent.scanner_type` values; Phase 2's migration already reserves
  them in its check constraint.
- Production rollout is expand-first: deploy schema/config/readiness, then
  disabled kiosk/badge modules and the fake printer adapter, validate,
  enable manual desk check-in and badge printing (which do not depend on
  physical kiosk hardware), and enable kiosk device pairing/health and
  live printer adapters only after adapter contract-test and production-
  readiness evidence.
- Rollback disables new kiosk pairing, badge print/reprint, and walk-up
  registration first, drains/reconciles pending print jobs, preserves all
  check-in/print/audit evidence, then rolls application behavior back.
  Production never drops populated Phase 3 tables automatically.
- Upgrade tests start from the accepted Phase 0/1/2 schema and verify
  fresh install, upgrade, repeatable seeders, printer adapter disabled/
  enabled profiles, backup/restore, and on-premise blocked-printer-network
  behavior with kiosk/desk check-in still functional.

## Testing and Documentation Gates

Required tests:

- Unit: kiosk session pairing/confirmation/revocation, badge template
  field-allowlist validation, badge print/reprint decision order, walk-up
  toggle evaluation, and error mapping.
- MySQL integration: tenant/event composite constraints, at-most-one-
  active-badge-template, kiosk session uniqueness, simultaneous kiosk+desk
  check-in concurrency, and audited rollback.
- Feature/API: every OpenAPI success and principal 401/403/404/409/422/429
  path for kiosk management, kiosk device-session, manual desk, badge
  template, and badge print/reprint operations, plus the extended
  `submitScan` `scanner_type` enum.
- Adapter contract: fake and (when available) hardware-backed printer
  adapter matrices including payload-rejected, printer-unavailable,
  printer-error, and secret redaction.
- System: kiosk registration/pairing/confirmation, kiosk check-in and
  badge print, manual desk lookup/check-in/print, reprint with and without
  old-QR revocation, walk-up registration enabled/disabled and payment-
  pending, kiosk/printer health visibility, and badge template create/
  activate/deactivate.
- Security: cross-tenant/event kiosk, lookup, check-in, badge, and walk-up
  matrices, permission allow/deny for every new permission, forged/expired
  kiosk session secret, malformed/tampered lookup/scan payload, secret/
  token leak checks, and template field-allowlist bypass attempts.
- Frontend/browser: Arabic/English, RTL/LTR, accessibility, keyboard,
  responsive layout for the kiosk attendee-facing screens, manual desk UI,
  badge template designer (including bilingual print preview), and kiosk
  health view, plus zero unauthorized props across tenants/events.
- Performance: kiosk/desk check-in-to-print p95 latency at representative
  load, bounded kiosk-health query plans at fleet scale, and printer job
  backlog recovery.
- Deployment parity: native SaaS/on-premise profiles, kiosk/desk check-in
  fully functional with the printer adapter blocked, printer adapter
  disabled/enabled profiles, and no runtime CDN.

Documentation deliverables:

- Phase 3 API and kiosk device-session protocol standards;
- printer adapter onboarding, rotation, and outage guide;
- kiosk pairing/confirmation and badge print/reprint runbook;
- badge template designer field-allowlist and bilingual layout rules;
- walk-up registration and on-site payment configuration guide;
- permission and audit catalog additions;
- migrations, rollback, backup/restore, telemetry/alerts, support, and
  Phase 3 readiness evidence.

All existing Composer/npm, backend/frontend, OpenAPI sync/lint/
compatibility, documentation, phase-boundary, dependency audit, migration,
and security gates remain mandatory.

## Post-Design Constitution Re-check

The completed data model and contracts preserve every pre-design PASS:

- composite tenant/event ownership and kiosk-session/staff-session-derived
  scope resolution close isolation for kiosk, desk, badge, and walk-up
  operations;
- the printer adapter, kiosk, and badge contracts expose no persistence
  bypass and introduce no second credential or entry-decision trust path;
- printer/kiosk unavailable states and blocked reprint/print attempts are
  explicit, auditable, and never silently resolved;
- reusing the Phase 1 credential contract and Phase 2 scan decision order
  without modification preserves signing, rotation, revocation, and
  entry-decision guarantees for kiosk, desk, and badge consumers;
- badge templates' fixed field allowlist and reused Phase 1 retention/
  anonymization policy satisfy PDPL design inputs without inventing new
  legal periods or data categories;
- deployment behavior remains one portable core where the printer adapter
  degrades explicitly and kiosk/desk check-in keep functioning;
- all Phase 4+ capabilities (ACS zones/lanes/anti-passback, identity
  verification, venue marketplace) are expressly absent.

**Result**: PASS. No complexity exception or governance waiver is
required.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
