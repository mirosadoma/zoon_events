# Implementation Plan: Wallet Passes and QR Scanning

**Branch**: `003-wallet-passes-scanning` | **Date**: 2026-07-06 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from
`/specs/003-wallet-passes-scanning/spec.md`

**Product Phase**: Phase 2 Wallet-Passes-And-Scanning

**Deployment Modes**: SaaS and on-premise

## Summary

Extend the accepted Phase 0 tenant/RBAC/audit foundation and Phase 1
registration/ticketing/credential core with the second product increment:
Apple Wallet and Google Wallet pass generation referencing the existing
Ed25519-signed credential, provider-driven pass update/revocation
synchronized with event and credential changes, authoritative staff QR
scanning with duplicate/revoked/expired rejection and optional single-entry
enforcement, an append-only scan-event log feeding attendee check-in state,
a polled real-time check-in dashboard, and a documented (and minimally
implemented) offline-tolerant scanning design with allowlist export and
conflict-aware reconciliation.

The design adds two new owned modules — `WalletPasses` and `Scanning` — that
consume the Phase 1 `Credentials` validation contract as their only source of
truth; neither introduces a second signing key, a cached trust decision, or
a bypass of live credential state. Apple integration follows PassKit's
signed-bundle-plus-web-service-and-APNs-push protocol; Google integration
follows the Generic pass object model with a signed-JWT save link and
authenticated REST updates. Kiosk/badge/manual-desk (Phase 3), zone/lane/ACS
and anti-passback (Phase 4), identity verification (Phase 5), and venue
marketplace (Phase 6) remain explicitly out of scope.

## Technical Context

**Language/Version**: PHP 8.3 and TypeScript 5.9

**Primary Dependencies**: Laravel 13, Sanctum, Fortify, Inertia 3, React 19,
Tailwind CSS 4, shadcn/ui prerequisites, PHP Sodium/OpenSSL extensions (PKCS#7
pass signing), Laravel HTTP client and database queue facilities, Firebase
JWT (or equivalent Laravel-compatible JWT signer) for Google Wallet save
links; no Apple/Google vendor SDK is required in domain code

**Storage**: MySQL 8.4 shared schema with tenant-first composite constraints,
consistent with Phase 0/1; no new storage technology introduced

**Testing**: PHPUnit/Laravel test runner with MySQL integration, queue/event
and HTTP fakes, fake Apple/Google wallet adapters, adapter contract suites,
OpenAPI lint/conformance, Vitest/React Testing Library/axe, browser/system
tests, Pint, ESLint, TypeScript, and Vite build

**Target Platform**: Native Windows or Linux web/worker/scheduler processes
for multi-tenant SaaS and supported on-premise deployments; no Docker or Sail

**Project Type**: API-first modular web application extending the existing
same-origin organizer dashboard and public attendee experience with a
check-in surface and staff scanning API

**Performance Goals**:

- p95 staff scan submission returns an accepted/rejected result under 2
  seconds under normal network conditions in at least 95% of scans (SC-002);
- wallet pass generation completes or reports a safe pending/degraded state
  without blocking the underlying registration/credential operation
  (FR-006);
- dashboard check-in counts reflect an accepted scan within a short,
  documented bounded polling delay (SC-006);
- a wallet pass reflects a material event-detail change within a bounded,
  documented time window across all tested update scenarios (SC-004);
- zero false accepts across revoked/expired/duplicate automated security
  scans (SC-003).

**Constraints**: Fail-closed tenant and event scope resolved from the
credential and scanning context, never a client-supplied identifier; no
wallet-specific signing key or second trust path around the Phase 1
credential; no raw device push token, provider certificate, service-account
key, or provider payload in logs/audit/errors/responses; scan result, check-in
state update, and audit evidence commit or fail together; wallet push/update
failures degrade explicitly and remain retryable without blocking check-in;
offline-tolerant scanning must not require continuous cloud connectivity to
validate a locally synced allowlist; Arabic/English and RTL/LTR parity; no
Phase 3+ feature (kiosk, badge, manual desk, ACS zones/lanes/anti-passback,
identity, marketplace) or placeholder

**Scale/Scope**: Existing Phase 0/1 target of 1,000 tenants and up to 100,000
attendees per event; two new domain modules, about six new owned tables,
roughly a dozen new review API operations (public wallet issuance, Apple
PassKit device-web-service callbacks, staff scan submission, dashboard
summary, offline allowlist export/reconciliation), an organizer check-in
dashboard section, and a staff scanning UI surface

## Constitution Check

*GATE: PASS before research; PASS after design.*

| Gate | Design evidence | Status |
|---|---|---|
| API-first | `contracts/openapi.yaml` defines public wallet issuance, Apple PassKit web-service callbacks, staff scan submission, dashboard, and offline reconciliation operations with auth/context, validation, idempotency, envelopes, errors, and compatibility notes for merge into the authoritative contract. | PASS |
| Tenant isolation | Every new table carries `tenant_id`; event-owned tables also carry `event_id`; scan and wallet requests resolve tenant/event from the credential and trusted scanning context, never a client header; composite foreign keys and negative isolation tests are required. | PASS |
| RBAC and auditability | New permissions cover wallet generation/administration, scan submission, scan override, and dashboard viewing. Wallet generation/update/revocation/failure, every scan result (including duplicate/override), and offline reconciliation conflicts are append-only audited in the same transaction as the state change. | PASS |
| Credential security | `contracts/scan-contract.md` and `contracts/wallet-adapter.md` fix the rule that wallet passes and scans reuse the Phase 1 Ed25519 credential contract unmodified; every wallet and scan decision re-reads authoritative credential state; no second signing key is introduced. | PASS |
| Deployment parity | Staff scanning, check-in state, scan logging, and the dashboard remain fully operable offline from wallet providers in both SaaS and on-premise; wallet generation/update reports explicit degraded/pending state when Apple/Google services are unreachable rather than blocking check-in. | PASS |
| GCC/KSA and PDPL | Wallet passes and scan events store only the operational/display fields listed in `data-model.md`; no national ID or biometric data; retention/residency/anonymization reuse Phase 1's approved tenant policy and preserve non-identifying scan/audit evidence after anonymization. | PASS |
| White-label/localization | Wallet pass content, scan result messaging, and the dashboard support Arabic/English and RTL/LTR; documented fallback where a wallet platform limits localization (`research.md` Decision 4/dashboard contract). | PASS |
| Modularity/adapters | `WalletPasses` and `Scanning` are new owned modules; Apple and Google implement one stable `wallet-adapter.md` contract; core domain code contains no Apple/Google SDK types. | PASS |
| Automated tests | Unit, MySQL concurrency (duplicate/simultaneous scan), contract (both wallet providers), adapter, isolation, RBAC override, audit atomicity, offline reconciliation, and dashboard authorization/accuracy suites are release gates per CR-009. | PASS |
| Phased delivery | The design implements only the `all_plan.md` Phase 2 scope. Kiosk, badge printing, manual desk, ACS zone/lane/anti-passback, identity verification, and marketplace remain forbidden by architecture tests, matching Phase 1's enforcement pattern. | PASS |

No constitution exception is required. Live Apple/Google wallet issuance
remains gated on certificate/service-account onboarding, approved secret
references, and contract-test evidence, matching the Phase 1 pattern for
live payment/SMS adapters; disabled live wallet adapters do not misrepresent
readiness.

## Research Decisions

Detailed decisions and alternatives are in [research.md](research.md):

1. Add `WalletPasses` and `Scanning` as separate owned modules consuming the
   Phase 1 `Credentials` contract.
2. A wallet pass stores only an opaque reference to the existing credential;
   no second trust path.
3. Apple Wallet uses the signed `.pkpass` bundle plus PassKit web-service
   device-registration/push/pull update protocol.
4. Google Wallet uses the Generic pass object model with a signed-JWT save
   link and authenticated REST `PATCH` updates.
5. Revocation/reissue invalidate wallet passes through each provider's
   documented state-transition mechanism, never by inventing a new signal.
6. Single-entry/duplicate enforcement is a per-event (optional per-ticket-
   type) configuration flag evaluated at scan time; anti-passback stays
   deferred to Phase 4.
7. Scan validation, single-entry evaluation, scan-event recording, and
   check-in state update happen in one audited local transaction.
8. The real-time dashboard uses short-interval polling of an aggregated read
   model rather than a new persistent push channel.
9. Offline-tolerant scanning ships as a documented, testable design plus a
   minimal reference allowlist/reconciliation implementation.
10. Wallet provider certificates/keys are secret references resolved only in
    adapter infrastructure, matching the Phase 1 payment/notification
    pattern.

## Architecture and Module Ownership

### Wallet Passes

Owns wallet pass records, the provider-neutral wallet adapter contract, the
Apple and Google adapter implementations, Apple device-registration
bookkeeping, and the wallet push/update job pipeline. It consumes the Phase 1
`Credentials` validation contract and Phase 1 `Events`/`Attendees` read data;
it never persists or re-derives credential validity itself.

### Scanning

Owns scan events, the check-in-state update, single-entry configuration and
evaluation, the check-in dashboard read model, and the offline-allowlist
export/reconciliation contract. It consumes the Phase 1 `Credentials`
validation contract as its sole authority for whether a credential is valid,
and the Phase 1 `Attendees` module for the attendee record it updates.

### Existing modules

- Credentials (Phase 1) is extended with no new fields or behavior; Phase 2
  only consumes its existing validation contract and revoke/reissue domain
  events.
- Attendees (Phase 1) gains `checkin_status`, `first_checked_in_at`, and
  `last_scan_event_id` columns, owned and written only by `Scanning`.
- Events (Phase 1) gains the `EventCheckInSetting` companion row, owned and
  written only by `Scanning`.
- Tenancy, Authorization, Audit, Shared, Operations, and AdminConsole provide
  the same trusted context, RBAC, audited transactions, errors/envelopes/
  idempotency, telemetry, and presentation conventions established in
  Phase 0/1.

## Core Execution Flows

### Wallet pass generation

```text
authenticated attendee via public order access token
  -> resolve order, attendee, and active credential in scope
  -> reject if credential is not active (fail closed, no provider call)
  -> build provider-neutral pass content from Events/Attendees read data
  -> invoke Apple or Google adapter (outside any inventory/order lock)
  -> persist WalletPass row (created -> active) with provider response reference
  -> return pkpass bundle (Apple) or save-link envelope (Google)
```

### Event-change wallet synchronization

```text
authorized organizer changes event date/time/location/branding
  -> Events module publishes an after-commit domain event
  -> WalletPasses listener loads affected active/updated passes for the event
  -> queued job invokes the adapter's update operation per pass
  -> job records last_pushed_at / last_push_reason_code
  -> unreachable provider leaves pass visibly "pending update" to staff,
     retried on bounded backoff, never blocking the event change itself
```

### Credential revoke/reissue wallet synchronization

```text
Phase 1 credential revoke or reissue action commits
  -> after-commit domain event (already defined in Phase 1)
  -> WalletPasses listener locates the credential's active wallet pass(es)
  -> revoke: adapter revoke/expire operation; pass marked revoked
  -> reissue: prior pass superseded; new pass may be generated on next
     attendee wallet request referencing the replacement credential
```

### Staff QR scan

```text
authenticated staff actor + trusted event scanning context + raw QR payload
  -> Credentials.validate() using the unmodified Phase 1 validation order
  -> non-valid result mapped to a stable scan result (revoked/expired/rejected)
  -> valid result: confirm event match, evaluate single-entry configuration
  -> audited transaction:
       insert ScanEvent
       update Attendee.checkin_status / first_checked_in_at (if accepted/override)
       update EventCheckInSummary counters
  -> after-commit: audit evidence, telemetry
  -> return stable result + safe display fields to the scanning device
```

### Offline scan and reconciliation

```text
staff device (online) requests signed, time-windowed allowlist for one event
  -> device scans locally against the allowlist while offline, applying local
     duplicate prevention before any server contact
  -> device reconnects and submits its locally recorded scan batch
  -> server replays each scan through the same scan decision order using
     current authoritative state
  -> conflicting simultaneous offline acceptances resolve to one accepted
     and the rest flagged duplicate-with-conflict, incrementing
     OfflineScanReconciliationBatch.conflict_count
```

## Staff Scanning Context and Access

- Scan submission, override, and dashboard routes require authenticated
  tenant session/bearer auth, `X-Tenant-ID`, active membership, and an exact
  permission (`checkin.scan.submit`, `checkin.scan.override`,
  `checkin.dashboard.view`); none of these routes trust a client-supplied
  event or tenant identifier beyond what the authenticated context and
  credential resolve.
- Public wallet issuance routes reuse the exact Phase 1 public order access
  token pattern (`public_reference` + token); they never introduce a new
  public identity mechanism.
- Apple PassKit web-service callback routes are unauthenticated by tenant
  session (the caller is the device/Apple, not a logged-in user) but
  authenticate every call via the per-pass `authenticationToken` or the
  device's already-registered identity, and resolve tenant/event scope from
  the stored pass record, never from the request path alone.
- A scan targeting a credential outside the authenticated scanning context's
  tenant/event returns the same rejected response as an unknown credential;
  the specific mismatch is never disclosed to the caller.

## Data Protection and Key Management

- Apple Pass Type ID certificates/private keys and Google service-account
  keys are `secret_reference` values resolved only inside adapter
  infrastructure, following the Phase 1 payment/notification pattern; they
  are never logged, audited, queued, or returned to a client.
- Apple device push tokens and per-pass `authenticationToken` values are
  stored but treated as sensitive: excluded from logs, telemetry, and audit
  metadata, and never exposed through any API response.
- Wallet pass content is limited to the fields in `data-model.md` §Wallet
  Pass and never includes attendee contact details, national identifiers,
  biometric data, or payment data.
- Scan events store attendee display fields already permitted under Phase 1
  policy plus scan-specific metadata (result, reason, scanner, timestamps);
  no new personal-data category is introduced.
- Retention, residency, and anonymization reuse Phase 1's tenant-approved
  policy; anonymizing an attendee preserves scan-event and audit rows with
  identity fields redacted rather than deleting required evidence.

## API and Contract Strategy

- [contracts/openapi.yaml](contracts/openapi.yaml) is the Phase 2 review
  contract. Implementation merges it into the authoritative
  `specs/001-project-foundation/contracts/openapi.yaml` and generated docs
  without weakening Phase 0/1 standards.
- [contracts/wallet-adapter.md](contracts/wallet-adapter.md) governs the
  Apple and Google wallet adapters' operations, error taxonomy, and contract
  test matrix, extending
  `specs/001-project-foundation/contracts/adapter-contract.md`.
- [contracts/scan-contract.md](contracts/scan-contract.md) governs the scan
  decision order and stable result set, extending
  `specs/002-registration-ticketing-credentials/contracts/credential-contract.md`
  exactly at its documented Phase 1/Phase 2 boundary.
- [contracts/dashboard-contract.md](contracts/dashboard-contract.md) extends
  the Phase 1 dashboard contract with check-in and wallet status navigation,
  authorization, and real-time polling rules.
- Tenant staff routes require bearer/session authentication, `X-Tenant-ID`,
  active membership, exact permission, idempotency for scan/override
  submission, correlation, locale, and bounded rate limits.
- Public wallet routes reuse the Phase 1 public order access token; Apple
  web-service routes use the PassKit-mandated `ApplePass` authentication
  scheme documented in `wallet-adapter.md`.
- Unknown fields are rejected on all scan, override, and wallet-generation
  writes.
- Stable errors extend the Phase 0/1 catalog with: `credential_not_active`,
  `wallet_provider_unavailable`, `wallet_pass_not_found`,
  `scan_context_invalid`, `override_reason_required`, and
  `offline_batch_conflict`.
- Provider-specific codes/payloads (Apple/Google) never become API error
  codes.

## RBAC and Audit Catalog

Permissions:

```text
wallet.pass.view
wallet.pass.generate
wallet.pass.manage
checkin.scan.submit
checkin.scan.override
checkin.dashboard.view
```

`wallet.pass.generate` is implicitly available to the authenticated public
attendee journey through the order access token, not through a workforce
role; the remaining permissions are workforce-role-scoped like Phase 1.
System Tenant Administrator receives all Phase 2 permissions through an
idempotent role update. A new On-Site Staff / Scanner system-role template
may be seeded with only `checkin.scan.submit` and `checkin.dashboard.view`
as a documented least-privilege default; `checkin.scan.override` and
`wallet.pass.manage` remain separately grantable. Custom roles remain empty.

Required audit action families:

- `wallet_pass.*`: generated, generation denied, updated, update failed,
  revoked, revocation failed;
- `scan.*`: accepted, rejected, duplicate, revoked, expired, manual_override;
- `offline_scan_batch.*`: received, processed, conflict_flagged;
- `checkin_dashboard.*`: viewed (only where tenant policy requires access
  logging beyond standard request telemetry).

High-volume accepted-scan telemetry is still individually audited per
CR-003 (unlike Phase 1's optional telemetry-only path for successful
availability reads) because every scan result is itself a security- and
entry-relevant decision, not a read-only convenience query.

## Queues, Scheduling, and Recovery

- `PushWalletPassUpdateJob` restores tenant/correlation context and applies
  one pending update/revocation to one wallet pass through its provider
  adapter, idempotently.
- `ReconcileOfflineScanBatchJob` (or synchronous handling for small batches)
  replays one offline batch's scans through the authoritative scan decision
  order and records conflicts.
- `RefreshEventCheckInSummaryJob` is a bounded repair job that recomputes
  `EventCheckInSummary` from `ScanEvent`/`Attendee` history if drift is ever
  detected; it is not on the request-path.
- Scan audit evidence remains synchronous within the scan's audited
  transaction; queue failure cannot remove or rewrite required scan
  evidence.
- Readiness reports safe categories for Apple/Google wallet adapter
  reachability and wallet push backlog, alongside the existing Phase 1
  readiness categories. Public readiness exposes aggregate state only.

## Project Structure

### Documentation (this feature)

```text
specs/003-wallet-passes-scanning/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── openapi.yaml
│   ├── wallet-adapter.md
│   ├── scan-contract.md
│   └── dashboard-contract.md
└── tasks.md                 # Created later by speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Modules/
│   ├── WalletPasses/
│   │   ├── Application/{Actions,Jobs}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Results,ValueObjects}
│   │   ├── Http/{Controllers/Public,Controllers/AppleWebService,Requests,Resources}
│   │   ├── Infrastructure/{Adapters/Apple,Adapters/Google,Persistence}
│   │   ├── Providers/
│   │   └── Testing/
│   ├── Scanning/
│   │   ├── Application/{Actions,Queries,Jobs}
│   │   ├── Contracts/
│   │   ├── Domain/{Events,Results,ValueObjects}
│   │   ├── Http/{Controllers,Requests,Resources}
│   │   ├── Infrastructure/Persistence/Models/
│   │   └── Providers/
│   └── AdminConsole/
│       ├── Http/Controllers/Tenant/CheckIn/
│       └── ViewModels/CheckIn/
├── Console/Commands/
└── Providers/ModuleServiceProvider.php
config/
└── wallet.php
database/
├── factories/
├── migrations/
└── seeders/
resources/js/
├── components/{wallet,checkin}/
└── pages/tenant/checkin/
routes/
├── api.php
└── web.php
tests/
├── Architecture/
├── Contract/{Phase2,Wallet}/
├── Feature/{WalletPasses,Scanning}/
├── Integration/{MySql,Queue,Security}/
├── Performance/
├── Unit/{WalletPasses,Scanning}/
└── Browser/Phase2/
```

**Structure Decision**: Keep the one Laravel deployment and React/Inertia
frontend, adding `WalletPasses` and `Scanning` beside the Phase 0/1 modules.
Controllers stay thin; application actions coordinate the Phase 1
`Credentials` contract and the new provider-neutral wallet contract; domain
objects own scan-result and pass-lifecycle transitions; infrastructure owns
persistence, provider HTTP/signing, and queues. No generic repository,
microservice, separate frontend deployment, or provider SDK leaks across
module boundaries.

## Migration and Rollback Strategy

Migration order:

1. `event_check_in_settings` (depends on Phase 1 `events`);
2. `wallet_passes` (depends on Phase 1 `attendees`, `credentials`);
3. `wallet_pass_apple_device_registrations` (depends on `wallet_passes`);
4. `scan_events` (depends on Phase 1 `attendees`, `credentials`, `events`);
5. `event_check_in_summaries` (depends on Phase 1 `events`);
6. `offline_scan_reconciliation_batches` (depends on Phase 1 `events`);
7. attendee check-in columns (`checkin_status`, `first_checked_in_at`,
   `last_scan_event_id`) added to the existing Phase 1 `attendees` table;
8. permission and system-role catalog updates for the Phase 2 permission
   list.

Rules:

- Add tenant/event composite unique keys before dependent composite foreign
  keys, consistent with Phase 0/1.
- Add lifecycle/status checks, at-most-one-active-wallet-pass constraint
  strategy, and tenant-first indexes in the creation migration.
- Production rollout is expand-first: deploy schema/config/readiness, then
  disabled wallet modules and fake/test adapters, validate, enable staff
  scanning and the dashboard (which do not depend on live wallet
  providers), and enable each live wallet provider only after
  certificate/service-account onboarding evidence.
- Rollback disables new wallet issuance and staff scan submission first,
  drains/reconciles pending wallet push jobs and offline batches, preserves
  all scan/audit/wallet evidence, then rolls application behavior back.
  Production never drops populated Phase 2 tables automatically.
- Upgrade tests start from the accepted Phase 0/1 schema and verify fresh
  install, upgrade, repeatable seeders, wallet adapter disabled/enabled
  profiles, backup/restore, and on-premise blocked-wallet-network behavior
  with scanning still functional.

## Testing and Documentation Gates

Required tests:

- Unit: scan decision order (accepted/duplicate/revoked/expired/rejected),
  single-entry evaluation, wallet pass lifecycle transitions, and error
  mapping.
- MySQL integration: tenant/event composite constraints, at-most-one-active-
  wallet-pass, immutable scan events, simultaneous-scan concurrency, and
  audited rollback.
- Feature/API: every OpenAPI success and principal 401/403/404/409/422/429
  path for wallet, scan, dashboard, and offline-batch operations, plus Apple
  web-service callback authentication.
- Adapter contract: fake, Apple, and Google matrices including credential-
  not-active rejection, update push/patch, revocation, invalid device token,
  provider unavailable, and secret redaction.
- System: attendee adds Apple/Google pass, event-change sync, credential
  revoke/reissue sync, staff scan accepted/duplicate/revoked/expired,
  authorized override, dashboard count accuracy, and offline scan/
  reconciliation including a genuine conflict.
- Security: cross-tenant/event scan and wallet matrices, permission
  allow/deny for scan/override/wallet/dashboard actions, forged Apple
  authentication token, malformed/tampered QR payload, secret/token leak
  checks, and public enumeration on wallet issuance routes.
- Frontend/browser: Arabic/English, RTL/LTR, accessibility, keyboard,
  responsive layout for the wallet add-to-wallet actions, staff scan UI, and
  check-in dashboard, plus zero unauthorized props across tenants/events.
- Performance: scan p95 latency under 2 seconds at representative load,
  bounded dashboard query plans at 100,000-attendee scale, and wallet push
  job backlog recovery.
- Deployment parity: native SaaS/on-premise profiles, scanning/dashboard
  fully functional with wallet push services blocked, wallet adapter
  disabled/enabled profiles, and no runtime CDN.

Documentation deliverables:

- Phase 2 API and Apple web-service protocol standards;
- wallet adapter onboarding (Apple Pass Type ID certificate, Google service
  account), rotation, and outage guide;
- scan decision order, single-entry configuration, and override runbook;
- check-in dashboard design and localization/accessibility rules;
- offline-tolerant scanning design document (allowlist export format, local
  dedupe rule, reconciliation and conflict-flagging behavior) per FR-024;
- permission and audit catalog additions;
- migrations, rollback, backup/restore, telemetry/alerts, support, and
  Phase 2 readiness evidence.

All existing Composer/npm, backend/frontend, OpenAPI sync/lint/compatibility,
documentation, phase-boundary, dependency audit, migration, and security
gates remain mandatory.

## Post-Design Constitution Re-check

The completed data model and contracts preserve every pre-design PASS:

- composite tenant/event ownership and credential-derived scope resolution
  close isolation for wallet and scan operations;
- the wallet adapter and scan contracts expose no persistence bypass and
  introduce no second credential trust path;
- wallet push/update failures and offline reconciliation conflicts are
  explicit, auditable, and never silently resolved;
- reusing the Phase 1 Ed25519 credential contract without modification
  preserves signing, rotation, and revocation guarantees for both wallet and
  scan consumers;
- documented offline design and tenant-approved retention/anonymization
  reuse satisfy PDPL design inputs without inventing new legal periods;
- deployment behavior remains one portable core where wallet providers
  degrade explicitly and scanning/check-in keep functioning;
- all Phase 3+ capabilities (kiosk, badge, manual desk, ACS zones/lanes/
  anti-passback, identity, marketplace) are expressly absent.

**Result**: PASS. No complexity exception or governance waiver is required.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
