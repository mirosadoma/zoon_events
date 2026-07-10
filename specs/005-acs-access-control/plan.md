# Implementation Plan: ACS and Access Control

**Branch**: `005-acs-access-control` | **Date**: 2026-07-07 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from
`/specs/005-acs-access-control/spec.md`

**Product Phase**: Phase 4 ACS-Access-Control

**Deployment Modes**: SaaS and on-premise

## Summary

Extend the accepted Phase 0 tenant/RBAC/audit/adapter foundation, Phase 1
registration/ticketing/credential core, Phase 2 wallet-pass/scanning core,
and Phase 3 kiosk/badge/manual-desk increment with the fourth product
increment: a credential-to-ACS physical access-control contract. The system
answers a gate authorization request (allow/deny + reason) from the external
Runa ACS, enforces organizer-configured zone, lane, direction, and
time-window rules, enforces anti-passback from recorded entry/exit events,
ingests ACS entry/exit event callbacks idempotently, supports emergency
egress (fail-open per zone), and surfaces gate events and ACS/lane health on
a bounded-polling dashboard.

The design adds one new owned module — `AccessControl` — that owns ACS
zones, lanes, authorization rules, access events, anti-passback state,
emergency events, and the provider-neutral `AcsAdapter` contract with a mock
ACS behind it. The authorization decision reuses the Phase 1 credential
validation and Phase 2 scan decision order as the single credential trust
path (signature, expiry, revocation, replay, key rotation) and layers zone/
lane/direction/time-window and anti-passback rules on top; it never
introduces a second credential-validity path or a cached entry decision. The
external ACS authenticates as a machine-to-machine (M2M) integration actor
scoped to its mapped event/lanes, never a human RBAC identity. Because the
exact Runa ACS transport is an open integration question in `all_plan.md`
(§38.2, §39.2), the ACS is isolated entirely behind the `AcsAdapter`
boundary, following the Phase 1/2 payment/wallet adapter pattern; the phase
ships against a mock ACS and adds the real transport later without changing
`AccessControl`'s domain logic. Identity verification (Phase 5), venue
marketplace (Phase 6), and enterprise/on-premise hardening (Phase 7) remain
explicitly out of scope.

## Technical Context

**Language/Version**: PHP 8.3 and TypeScript 5.9

**Primary Dependencies**: Laravel 13, Sanctum, Fortify, Inertia 3, React 19,
Tailwind CSS 4, shadcn/ui prerequisites, Laravel HTTP client, queue, and
event facilities reused for the ACS adapter, event-callback ingestion, and
anti-passback state updates; no new vendor SDK is required in domain code
(the ACS adapter's real transport depends on the Runa protocol confirmed at
integration time and is isolated behind the adapter interface)

**Storage**: MySQL 8.4 shared schema with tenant-first composite
constraints, consistent with Phase 0/1/2/3; no new storage technology
introduced

**Testing**: PHPUnit/Laravel test runner with MySQL integration, queue/
event and HTTP fakes, a mock ACS adapter, adapter contract suites, OpenAPI
lint/conformance, Vitest/React Testing Library/axe, browser/system tests,
Pint, ESLint, TypeScript, and Vite build

**Target Platform**: Native Windows or Linux web/worker/scheduler processes
for multi-tenant SaaS and supported on-premise deployments, including a
local ACS integration with no outbound cloud dependency; no Docker or Sail

**Project Type**: API-first modular web application extending the existing
same-origin organizer/operations dashboard with ACS configuration surfaces,
plus M2M integration endpoints the external ACS calls for authorization and
event callbacks

**Performance Goals**:

- p50 gate authorization decision returned within a documented turnstile
  latency budget (target under 500 ms at the application boundary) under
  representative load (SC-011);
- zero false accepts for expired/revoked/unknown/out-of-scope credentials
  in automated security testing (SC-001);
- an ACS/lane status change visible to authorized viewers within a short,
  bounded polling delay (SC-009), consistent with the Phase 2/3 dashboard
  bound;
- idempotent event-callback processing with zero double-counted entries/
  exits/occupancy under duplicate/replay (SC-005).

**Constraints**: Fail-closed tenant/event scope resolved from the credential
and the lane's registration/mapping, never a client-supplied identifier; no
ACS-specific second credential trust path around the Phase 1 credential and
Phase 2 scan decision order; the ACS receives only an allow/deny decision and
reason code, never signing keys, raw secrets, or a re-scoped credential;
authorization decisions, event callbacks, anti-passback state changes,
emergency events, and their audit evidence commit or fail together; ACS
unavailability applies and records a documented, per-zone configurable
fail-open/fail-closed behavior rather than silently dropping requests;
emergency-egress fail-open is configured per zone independently of the
unavailability behavior; anti-passback and access-event data store only the
minimum access-control fields, never national identifiers, biometric
templates, or payment data; Arabic/English and RTL/LTR parity for operator
configuration and dashboards, with language-neutral machine-readable reason
codes; no Phase 5+ feature (identity verification methods/providers, venue
marketplace) or placeholder

**Scale/Scope**: Existing Phase 0/1/2/3 target of 1,000 tenants and up to
100,000 attendees per event; one new domain module, about six new owned
tables (`acs_zones`, `acs_lanes`, `acs_authorization_rules`,
`access_events`, `anti_passback_states`, `emergency_events`), roughly a
dozen new review API operations (zone/lane/rule CRUD, authorization request,
entry/exit event callback, emergency egress raise/clear, gate event and ACS/
lane health views), an organizer/operations ACS configuration surface, and a
gate events/health dashboard page

## Constitution Check

*GATE: PASS before research; PASS after design.*

| Gate | Design evidence | Status |
|---|---|---|
| API-first | `contracts/openapi.yaml` defines zone/lane/rule management, the ACS authorization request, entry/exit event callbacks, emergency-egress operations, and gate-event/health views with auth/context, validation, idempotency, envelopes, errors, and compatibility notes for merge into the authoritative contract. | PASS |
| Tenant isolation | Every new table carries `tenant_id`; event-owned tables also carry `event_id`; authorization requests and event callbacks resolve tenant/event from the credential and the lane's registration/mapping, never a client header. Composite foreign keys and negative isolation tests are required (`data-model.md` invariant 1). | PASS |
| RBAC and auditability | New human permissions cover ACS configuration, gate-event/health viewing, and emergency management; the external ACS authenticates as an M2M integration actor limited to authorization and event ingestion for its mapped event/lanes. Every allow/deny decision, config change, event ingestion, and emergency event is append-only audited in the same transaction as the state change. | PASS |
| Credential security | `contracts/authorization-contract.md` fixes the rule that gate authorization reuses the unmodified Phase 1 credential validation and Phase 2 scan decision order for credential validity; the ACS receives only allow/deny + reason, never keys or a re-scoped credential; replayed requests/callbacks are detected and handled idempotently. No second signing key or entry-decision trust path is introduced. | PASS |
| Deployment parity | Authorization, rules, anti-passback, event ingestion, and emergency egress remain fully operable in SaaS and on-premise, including a local ACS with no outbound cloud dependency; the per-zone fail-open/fail-closed behavior on ACS unavailability is documented and tested for both modes (`acs-adapter.md`). | PASS |
| GCC/KSA and PDPL | Access events and anti-passback state store only credential reference, zone/lane, direction, reason, and timestamp — no national ID, biometric, or payment data (`data-model.md`). Any face/biometric lane references an identity verified upstream in Phase 5, never stored here. Retention/residency/anonymization reuse Phase 1's approved tenant policy and preserve non-identifying access evidence. | PASS |
| White-label/localization | ACS configuration surfaces, the gate-event/health dashboard, and human-readable reason messages support Arabic/English and RTL/LTR (`dashboard-contract.md`); machine-readable reason codes remain language-neutral, localized only for display. | PASS |
| Modularity/adapters | `AccessControl` is a new owned module; the external ACS is reached only through the stable `AcsAdapter` interface (`acs-adapter.md`) with a mock implementation for tests; core domain code contains no ACS transport/protocol types. | PASS |
| Automated tests | Unit, MySQL concurrency (simultaneous entries, anti-passback state), contract (ACS adapter, authorization, event callback), isolation, RBAC (config/view/emergency + M2M scope), audit atomicity, fail-open/fail-closed, and health suites are release gates per CR-009. | PASS |
| Phased delivery | The design implements only the `all_plan.md` Phase 4 scope. Identity verification, venue marketplace, and enterprise hardening remain forbidden by architecture tests, matching Phase 1/2/3's enforcement pattern. | PASS |

No constitution exception is required. Live Runa ACS transport integration
remains gated on adapter contract-test evidence and production-readiness
review, matching the Phase 1/2/3 pattern for live payment/wallet/printer
adapters; the mock ACS adapter does not misrepresent readiness, and the
unknown transport is an explicit blocking integration assumption per
`all_plan.md` §38.2/§39.2, not a disguised production integration.

## Research Decisions

Detailed decisions and alternatives are in [research.md](research.md):

1. Add `AccessControl` as the only new owned module; it owns zones, lanes,
   rules, access events, anti-passback state, and emergency events.
2. The authorization decision reuses the Phase 1 credential validation and
   Phase 2 scan decision order as the single credential trust path; zone/
   lane/direction/time-window and anti-passback rules layer on top.
3. The external Runa ACS is isolated behind one `AcsAdapter` contract with a
   mock implementation; the unknown transport is a planning-phase decision
   at the adapter boundary.
4. The ACS authenticates as an M2M integration actor scoped to its mapped
   event/lanes, never a human RBAC identity.
5. Anti-passback state is derived and materialized from recorded entry/exit
   `AccessEvent` rows, evaluated on entry, configurable per event/zone/
   ticket type.
6. Entry/exit event callbacks are ingested idempotently by external event
   id and reconciled for out-of-order arrival.
7. ACS unavailability applies a per-zone `fail_open`/`fail_closed` mode with
   a documented safe default; emergency-egress fail-open is a separate
   per-zone signal path.
8. Gate events and ACS/lane health reuse the Phase 2/3 bounded-polling
   dashboard pattern; no new persistent-connection infrastructure.
9. A gate that also represents event admission records a Phase 2 check-in
   via the unchanged `SubmitScanAction`; zone/lane movement is recorded as an
   `AccessEvent` — the two records reference each other, satisfying
   `all_plan.md` §19.3 step 7 without a second trust path.
10. All ACS entities are tenant/event-scoped exactly like Phase 1/2/3
    configuration.

## Architecture and Module Ownership

### Access Control

Owns ACS zones, lanes, authorization rules, access events (decisions +
entry/exit/emergency), anti-passback state, emergency events, and the
provider-neutral `AcsAdapter` contract with its mock and (later) real Runa
implementations. It consumes the Phase 1 `Credentials` validation contract
and the Phase 2 `Scanning` scan decision order for the credential-validity
portion of every authorization decision; it never persists or re-derives
credential validity itself, and it never talks to the physical gate hardware
directly (the external ACS releases the gate).

### Existing modules (extended)

- Scanning (Phase 2) is consumed unchanged: when a lane represents event
  admission, `AccessControl` calls the unmodified `SubmitScanAction`
  (`scanner_type = 'acs_gate'`, already reserved by Phase 2's check
  constraint) so a gate admission also records a check-in; no new
  `ScanEvent.result` value is introduced.
- Credentials (Phase 1) is extended with no new fields or behavior; Phase 4
  only consumes its existing validation contract.
- Events (Phase 1) is referenced for `ticket_types` and event scope; no
  Event field changes meaning.
- Tenancy, Authorization, Audit, Shared, Operations, and AdminConsole
  provide the same trusted context, RBAC, audited transactions, errors/
  envelopes/idempotency, telemetry, and presentation conventions
  established in Phase 0/1/2/3.

## Core Execution Flows

### Zone, lane, and rule configuration

```text
authorized operator (acs.configure)
  -> create AcsZone scoped to tenant/event, link external_acs_zone_id,
     set anti_passback_enabled, unavailability_mode, emergency_egress_mode
  -> create AcsLane in a zone, link external_acs_lane_id, set gate_type
     and supported access_direction
  -> create AcsAuthorizationRule mapping ticket_type/attendee_type ->
     zone/lane/direction with valid_from/valid_until window
```

### Gate authorization decision

```text
external ACS (M2M integration actor: acs.authorize)
  -> POST authorization request: credential/identity reference + lane ref
  -> resolve AcsLane by external_acs_lane_id within the actor's mapped scope
     (reject unmapped lane / cross-scope as unknown target)
  -> Credentials.validate() + Phase 2 scan decision order (unchanged):
        signature, expiry, revocation, replay, key rotation
     -> not valid: deny with the mapped reason (expired/revoked/unknown)
  -> evaluate AcsAuthorizationRule set for (ticket_type, attendee_type,
     zone, lane, direction, now): no permitting rule -> deny
     (zone_not_permitted / lane_not_permitted / outside_time_window)
  -> if entry and zone.anti_passback_enabled and credential already inside:
        deny (anti_passback_violation)
  -> allow/deny returned to ACS with a stable reason code
  -> record AccessEvent(decision) + (if admission lane) Phase 2 check-in
  -> after commit: audit the decision
```

### ACS unavailable / latency exceeded

```text
authorization path cannot reach/complete ACS-side dependency in budget
  -> apply AcsZone.unavailability_mode:
        fail_open  -> allow, reason = acs_unavailable_fail_open
        fail_closed -> deny,  reason = acs_unavailable_fail_closed
  -> record AccessEvent with the applied mode; never silently drop
```

### Entry/exit event callback ingestion

```text
external ACS (acs.event.ingest) posts entry|exit event with external_event_id
  -> resolve lane/zone within mapped scope (reject out-of-scope)
  -> dedupe by (tenant_id, external_event_id): duplicate -> idempotent no-op
  -> record AccessEvent(entry|exit), direction, lane, zone, credential, time
  -> update AntiPassbackState for (credential, zone): inside on entry,
     outside on exit; reconcile out-of-order arrival by occurred_at
  -> update occupancy/reporting; after commit: audit ingestion
```

### Emergency egress

```text
emergency signal (acs.emergency.manage or ACS emergency callback)
  -> resolve target zone(s) (or event-wide)
  -> if zone.emergency_egress_mode = fail_open: reflect fail-open state,
     subsequent presentations at affected lanes allow with reason
     = emergency_fail_open
  -> record EmergencyEvent(raised) + AccessEvent(emergency)
  -> surface on dashboard; after commit: audit
  -> clear: EmergencyEvent(cleared) restores normal decisioning
```

### Gate events and ACS/lane health

```text
authorized viewer (acs.events.view / acs.health.view) polls bounded,
tenant/event-scoped endpoints (contracts/dashboard-contract.md, same
short-interval pattern as Phase 2/3 dashboards)
  -> gate events: allowed/denied/entry/exit/emergency with reasons
  -> health: AcsLane.status and ACS integration status
     (online/degraded/offline) derived from adapter heartbeat/last contact
```

## Access, Integration, and M2M Context

- ACS configuration (zones, lanes, rules, anti-passback, unavailability/
  emergency modes) and gate-event/health viewing and emergency management
  require authenticated tenant session/bearer auth, `X-Tenant-ID`, active
  membership, and an exact permission (`acs.configure`, `acs.events.view`,
  `acs.health.view`, `acs.emergency.manage`); none trust a client-supplied
  event/tenant identifier beyond what the authenticated context resolves.
- ACS integration routes (`/acs/v1/*`: authorization request, event
  callback, emergency callback) are not tenant-session authenticated (the
  caller is the external ACS, not a logged-in user); they authenticate via
  the M2M integration credential scheme documented in `acs-adapter.md`,
  resolving tenant/event/lane scope from the mapped integration record and
  the referenced lane, never from a request-supplied tenant/event id.
- An authorization request, event callback, or emergency callback naming a
  lane/zone/credential outside the integration actor's mapped tenant/event
  returns the same rejected/unknown response as an unknown target; the
  specific mismatch is never disclosed.

## Data Protection and Key Management

- ACS integration credentials (M2M secret, transport tokens) are
  `secret_reference` values resolved only inside adapter infrastructure,
  hashed at rest where stored, and never logged, audited, queued, or
  returned by any endpoint, following the Phase 1 payment/notification
  pattern.
- Access events and anti-passback state store only a credential reference,
  zone/lane, direction, reason code, source, and timestamp — never a raw
  credential payload, national identifier, biometric template, or payment
  data.
- Signing keys and credential validity remain owned solely by Phase 1
  `Credentials`; the ACS and the `AcsAdapter` never receive signing material
  or a re-scoped credential, only an allow/deny decision and reason code.
- Retention, residency, and anonymization reuse Phase 1's tenant-approved
  policy; anonymizing an attendee preserves `AccessEvent` and audit rows
  with identity fields redacted rather than deleting required access
  evidence, matching Phase 2/3's credential/print anonymization cascade.

## API and Contract Strategy

- [contracts/openapi.yaml](contracts/openapi.yaml) is the Phase 4 review
  contract. Implementation merges it into the authoritative
  `specs/001-project-foundation/contracts/openapi.yaml` and generated docs.
  Event admission at an ACS gate reuses the existing Phase 2 `submitScan`
  operation via `scanner_type = 'acs_gate'` rather than a new check-in
  operation.
- [contracts/acs-adapter.md](contracts/acs-adapter.md) governs the ACS
  adapter's operations, transport-agnostic boundary, error taxonomy, fail-
  open/fail-closed behavior, and contract test matrix against the mock ACS,
  extending `specs/001-project-foundation/contracts/adapter-contract.md`.
- [contracts/authorization-contract.md](contracts/authorization-contract.md)
  governs the gate authorization decision order, reason-code set, and its
  reuse of `specs/003-wallet-passes-scanning/contracts/scan-contract.md`
  exactly at its documented Phase 2/Phase 4 boundary.
- [contracts/dashboard-contract.md](contracts/dashboard-contract.md) extends
  the Phase 2/3 dashboard contract with gate events, ACS/lane health, and
  ACS configuration navigation, authorization, and UI-state rules.
- Tenant operator routes require bearer/session authentication,
  `X-Tenant-ID`, active membership, exact permission, idempotency for
  configuration and emergency writes, correlation, locale, and bounded rate
  limits.
- ACS integration routes authenticate via the M2M scheme documented in
  `acs-adapter.md`; they never accept a tenant session token as a
  substitute, and authorization/event/emergency writes are idempotent by
  request idempotency key / external event id.
- Unknown fields are rejected on all configuration, authorization, event,
  and emergency writes.
- Stable errors extend the Phase 0/1/2/3 catalog with:
  `credential_invalid`, `credential_expired`, `credential_revoked`,
  `credential_unknown`, `zone_not_permitted`, `lane_not_permitted`,
  `outside_time_window`, `anti_passback_violation`, `acs_zone_unmapped`,
  `acs_lane_unmapped`, `acs_unavailable`, `acs_event_out_of_scope`, and
  `emergency_active`. (An idempotent duplicate callback is a success no-op,
  not an error.)
- ACS transport-specific codes/payloads never become API error codes or
  decision reason codes.

## RBAC and Audit Catalog

Permissions:

```text
acs.configure
acs.events.view
acs.health.view
acs.emergency.manage
```

These are workforce-role-scoped like Phase 1/2/3 permissions; the external
ACS authenticates through its own M2M integration credential (Research
Decision 4), never through a human RBAC grant, and is capability-limited to
authorization requests and event/emergency callbacks for its mapped event/
lanes. System Tenant Administrator receives all Phase 4 human permissions
through an idempotent role update. A dedicated "ACS Operator" system-role
template receives `acs.configure`, `acs.events.view`, and `acs.health.view`;
`acs.emergency.manage` remains a separately grantable least-privilege
addition, never bundled by default. Custom roles remain empty.

Required audit action families:

- `acs_zone.*`: created, updated, deactivated;
- `acs_lane.*`: created, updated, deactivated;
- `acs_rule.*`: created, updated, deactivated;
- `access.*`: authorized, denied (with reason code), entry, exit;
- `acs_emergency.*`: raised, cleared;
- `acs_integration.*`: credential_authenticated, health_changed,
  unavailable_fail_open, unavailable_fail_closed.

Every allow and deny decision is individually audited with its reason code
per CR-003/CR-009, consistent with Phase 2/3's precedent that every
security-relevant decision is audited even when it is a read-adjacent
authorization rather than a persisted state change.

## Queues, Scheduling, and Recovery

- The authorization decision is synchronous on the request path (it gates a
  physical turnstile) and must complete within the latency budget (SC-011);
  the ACS adapter enforces the timeout and applies the per-zone
  fail-open/fail-closed mode when the budget is exceeded.
- `IngestAccessEventJob` (or synchronous handling for low latency) records
  an entry/exit callback and updates anti-passback state idempotently;
  duplicate external event ids are safe no-ops.
- `ReconcileAntiPassbackStateJob` is a bounded repair job that recomputes a
  credential's per-zone inside/outside state from ordered `AccessEvent` rows
  if drift is detected; it is not strictly on the request path since event
  ingestion updates state inline.
- Decision, event-ingestion, emergency, and their audit evidence remain
  synchronous within their audited transaction; queue failure cannot remove
  or rewrite required access evidence.
- Readiness reports safe categories for ACS adapter reachability and lane
  online/degraded/offline counts, alongside the existing Phase 1/2/3
  readiness categories. Public readiness exposes aggregate state only.

## Project Structure

### Documentation (this feature)

```text
specs/005-acs-access-control/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── openapi.yaml
│   ├── acs-adapter.md
│   ├── authorization-contract.md
│   └── dashboard-contract.md
└── tasks.md                 # Created later by speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Modules/
│   ├── AccessControl/
│   │   ├── Application/{Actions,Queries,Jobs}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Results,ValueObjects}
│   │   ├── Http/{Controllers/Management,Controllers/Integration,Requests,Resources}
│   │   ├── Infrastructure/{Adapters/Mock,Adapters/Runa,Persistence/Models}
│   │   ├── Providers/
│   │   └── Testing/
│   └── AdminConsole/
│       ├── Http/Controllers/Tenant/Acs/
│       └── ViewModels/{Acs,GateEvents,AcsHealth}/
├── Console/Commands/
└── Providers/ModuleServiceProvider.php
config/
└── acs.php
database/
├── factories/
├── migrations/
└── seeders/
resources/js/
├── components/{acs,gate-events,acs-health}/
└── pages/tenant/{acs,gate-events,acs-health}/
routes/
├── api.php
└── web.php
tests/
├── Architecture/
├── Contract/{Phase4,Acs}/
├── Feature/{AccessControl}/
├── Integration/{MySql,Queue,Security}/
├── Performance/
├── Unit/{AccessControl}/
└── Browser/Phase4/
```

**Structure Decision**: Keep the one Laravel deployment and React/Inertia
frontend, adding `AccessControl` beside the Phase 0/1/2/3 modules and
consuming `Scanning`/`Credentials` through their existing public contracts.
Controllers stay thin; application actions coordinate the Phase 1/2
credential and scan contracts plus the new provider-neutral ACS contract;
domain objects own the authorization decision, anti-passback state, and
emergency lifecycle transitions; infrastructure owns persistence, the ACS
adapter transport, and queues. No generic repository, microservice, separate
frontend deployment, or ACS transport type leaks across module boundaries.

## Migration and Rollback Strategy

Migration order:

1. `acs_zones` (depends on Phase 1 `events`);
2. `acs_lanes` (depends on `acs_zones`);
3. `acs_authorization_rules` (depends on Phase 1 `events`, `ticket_types`,
   and `acs_zones`, `acs_lanes`);
4. `access_events` (depends on Phase 1 `events`, `credentials`, and
   `acs_zones`, `acs_lanes`);
5. `anti_passback_states` (depends on Phase 1 `credentials` and `acs_zones`);
6. `emergency_events` (depends on Phase 1 `events` and `acs_zones`);
7. ACS M2M integration credential registration table/columns (or reuse the
   Phase 0 integration-credential store if present);
8. permission and system-role catalog updates for the Phase 4 permission
   list.

Rules:

- Add tenant/event composite unique keys before dependent composite foreign
  keys, consistent with Phase 0/1/2/3.
- Add lifecycle/status checks, external-identifier uniqueness per event, the
  `access_events` idempotency key on `(tenant_id, external_event_id)`, and
  tenant-first indexes in the creation migration.
- No migration is required to activate the `acs_gate` `ScanEvent.scanner_type`
  value if Phase 2's migration already reserves it; otherwise a single
  check-constraint extension migration adds it, mirroring Phase 3's
  `manual_desk` activation note.
- Production rollout is expand-first: deploy schema/config/readiness, then
  the disabled `AccessControl` module and the mock ACS adapter, validate,
  enable ACS configuration and dashboards, and enable the live Runa ACS
  transport only after adapter contract-test and production-readiness
  evidence.
- Rollback disables ACS authorization/event ingestion and new configuration
  first, drains/reconciles pending event-ingestion jobs, preserves all
  access/emergency/audit evidence, then rolls application behavior back.
  Production never drops populated Phase 4 tables automatically.
- Upgrade tests start from the accepted Phase 0/1/2/3 schema and verify
  fresh install, upgrade, repeatable seeders, ACS adapter mock/live
  profiles, backup/restore, and on-premise local-ACS (no outbound cloud)
  behavior with authorization still functional.

## Testing and Documentation Gates

Required tests:

- Unit: authorization decision order, reason-code mapping, rule evaluation
  (zone/lane/direction/time window), anti-passback state transitions,
  fail-open/fail-closed selection, and emergency-egress mode evaluation.
- MySQL integration: tenant/event composite constraints, external-identifier
  uniqueness, `access_events` idempotency uniqueness, simultaneous
  entry-at-two-lanes concurrency, anti-passback state under concurrent
  entry/exit, and audited rollback.
- Feature/API: every OpenAPI success and principal 401/403/404/409/422/429
  path for zone/lane/rule management, authorization request, entry/exit
  event callback, emergency egress, and gate-event/health views, plus the
  reused `submitScan` `acs_gate` scanner source.
- Adapter contract: mock and (when available) Runa-backed ACS adapter
  matrices including authorization allow/deny, unmapped zone/lane,
  acs_unavailable with fail-open and fail-closed, event-callback idempotency,
  emergency callback, and secret/transport redaction.
- System: zone/lane/rule configuration, allow/deny decisions across every
  reason code, anti-passback entry/blocked-re-entry/exit-then-entry/disabled,
  entry/exit ingestion including duplicate and out-of-order, ACS unavailable
  fail-open/fail-closed, and emergency egress raise/clear.
- Security: cross-tenant/event zone, lane, rule, authorization, event, and
  emergency matrices; permission allow/deny for every new permission; M2M
  integration scope confinement; forged/expired/replayed authorization
  request and event callback; secret/token leak checks; and reason-code
  disclosure minimization (no mismatch specifics leaked).
- Frontend/browser: Arabic/English, RTL/LTR, accessibility, keyboard,
  responsive layout for the ACS configuration surface, gate-events view, and
  ACS/lane health view, plus zero unauthorized props across tenants/events.
- Performance: gate authorization p50 latency at representative load
  (SC-011), bounded gate-event/health query plans at scale, and
  event-callback backlog recovery.
- Deployment parity: native SaaS/on-premise profiles, authorization fully
  functional with a local ACS (no outbound cloud), ACS adapter mock/live
  profiles, and documented fail-open/fail-closed behavior in both modes.

Documentation deliverables:

- Phase 4 API and ACS M2M integration protocol standards;
- ACS adapter onboarding, credential rotation, transport, and outage guide;
- zone/lane/rule configuration and anti-passback runbook;
- emergency-egress configuration and test runbook;
- permission and audit catalog additions;
- migrations, rollback, backup/restore, telemetry/alerts, support, and
  Phase 4 readiness evidence, including the explicit blocking assumption for
  the unconfirmed Runa transport.

All existing Composer/npm, backend/frontend, OpenAPI sync/lint/compatibility,
documentation, phase-boundary, dependency audit, migration, and security
gates remain mandatory.

## Post-Design Constitution Re-check

The completed data model and contracts preserve every pre-design PASS:

- composite tenant/event ownership and credential/lane-mapping-derived scope
  resolution close isolation for authorization, event ingestion,
  configuration, and emergency operations;
- the ACS adapter and authorization contracts expose no persistence bypass
  and introduce no second credential or entry-decision trust path — the ACS
  receives only allow/deny + reason;
- ACS-unavailable and emergency states are explicit, per-zone configurable,
  auditable, and never silently resolved;
- reusing the Phase 1 credential contract and Phase 2 scan decision order
  without modification preserves signing, rotation, revocation, and
  replay-resistance guarantees for the access-control consumer;
- access events and anti-passback state's minimal fields and reused Phase 1
  retention/anonymization policy satisfy PDPL design inputs without inventing
  new legal periods or data categories, and reference (never store) any
  Phase 5 identity data;
- deployment behavior remains one portable core with a local-ACS on-premise
  profile and explicit degraded behavior;
- all Phase 5+ capabilities (identity verification methods/providers, venue
  marketplace) are expressly absent.

**Result**: PASS. No complexity exception or governance waiver is required.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
