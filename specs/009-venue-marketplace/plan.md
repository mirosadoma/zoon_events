# Implementation Plan: Venue Marketplace

**Branch**: N/A (feature label: 009-venue-marketplace) | **Date**: 2026-07-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from /specs/009-venue-marketplace/spec.md

**Note**: The planning setup returned no active Git branch. The feature directory
is the authoritative planning context.

**Product Phase**: Phase 6 — Venue Marketplace

**Deployment Modes**: SaaS and on-premise

## Summary

Add the sixth product increment: a private business-to-business marketplace in
which a venue-owner organization publishes an allowlisted catalog projection of
fixed infrastructure, an organizer requests selected assets for one owned event
and operating window, the owner approves the request without double-booking,
and a time-bounded delegation permits only already-authorized operational users
to configure the rented resources. Delegation checks remain authoritative at
request time, owner revocation is immediate, and expiry cannot be extended by a
late worker. Finalized rentals produce immutable commercial statements and an
audited dispute trail; the phase does not collect, hold, refund, or pay funds.

The design adds one new owned module, **VenueMarketplace**, plus narrow public
contracts in Tenancy, Events, Authorization, AccessControl, Kiosk,
BadgePrinting, Scanning, Audit, Notifications, and AdminConsole. Owner-private
tables are never queried as a global catalog. Publication creates an
allowlisted catalog snapshot addressed by opaque public identifiers. Shared
rental records retain an owner tenant and an organizer participant tenant and
are accessed only through a participant-scope query that produces role-specific
projections. Existing operational modules provision event-scoped resources
through their own application contracts and consult one delegated-control guard;
the marketplace never mutates their persistence or bypasses credential, device,
anti-passback, or emergency rules.

## Technical Context

**Language/Version**: PHP 8.3.16 runtime (Composer constraint PHP 8.2+) and
TypeScript 5.9.3 with React 19.2.7

**Primary Dependencies**: Laravel 12.63, Sanctum, Fortify, Inertia Laravel
2.0.24 with @inertiajs/react 3.6, React 19, Tailwind CSS 4, react-i18next,
Laravel database/queue/event/scheduler/notification facilities, and the
existing shared idempotency, Problem Details, cursor, audit, tenant-context,
adapter and observability boundaries. No new vendor SDK or external marketplace
service is required.

**Storage**: Existing MySQL shared schema with tenant-first composite keys,
integer minor-unit money, UTC microsecond timestamps plus venue timezone
snapshots, JSON only for validated capability/publication snapshots, and Redis
only through existing tenant-aware cache/queue boundaries where configured. No
new storage technology.

**Testing**: PHPUnit 11.5/Laravel tests with real MySQL integration for locking
and foreign-key behavior, queue/event/notification fakes, module contract fakes,
cross-tenant and platform-scope security matrices, OpenAPI sync/lint, Vitest
3.2/React Testing Library/axe for dashboard journeys, performance tests for the
10,000-asset catalog, Pint, ESLint, TypeScript no-emit, and Vite build.

**Target Platform**: Native Windows or Linux web, worker, and scheduler
processes for multi-tenant SaaS and supported on-premise installations. Docker
is not introduced. A single installation can serve multiple organizer and
venue-owner tenants; cross-deployment marketplace federation remains Phase 7.

**Project Type**: API-first modular web application extending the existing
same-origin Inertia/React dashboard and versioned /api/v1 contracts.

**Performance Goals**: At least 95% of searches over 10,000 published assets
show first useful results within 2 seconds; approval admits zero overlapping
approved/active reservations; delegation becomes usable within 60 seconds of
start and unusable within 60 seconds of scheduled end or immediately on owner
revocation; a finalized rental has at most one initial statement within 5
minutes; all list endpoints remain cursor-bounded.

**Constraints**: Only an allowlisted publication projection is discoverable
across tenants; every shared rental read requires trusted actor context and an
owner/organizer participant match; platform access uses a separate privileged
path. Approval serializes on the selected asset rows in deterministic order and
commits reservations, lifecycle state, and audit evidence atomically. Delegated
control requires both the existing operational permission and a current grant,
checks time/revocation synchronously, and never exposes resource bindings or
secrets. Camera assets are catalog-only. Prices use ISO 4217 currency and minor
units; no automated payment, payout, tax, refund, penalty, or proration claim is
created. Arabic/English, RTL/LTR, accessibility, tenant branding, residency,
retention, degraded adapters, and on-premise local expiry are mandatory.

**Scale/Scope**: Existing platform target of approximately 1,000 tenants plus
up to 10,000 published assets in the first marketplace catalog. One new module,
one Tenancy classification field, about fifteen owned tables, four operational
provisioner contracts, one delegated-control guard, roughly thirty review API
operations, six tenant dashboard areas plus platform oversight, three scheduled
lifecycle commands, and Phase 6 permission/audit/data-classification updates.

## Constitution Check

*GATE: PASS before research; PASS after Phase 1 design.*

| Gate | Design evidence | Status |
|---|---|---|
| API-first | contracts/openapi.yaml defines authenticated tenant catalog, owner inventory, quote, rental decision, delegation status, statement/dispute, and explicit platform oversight operations. All writes define validation, permission, idempotency, Problem Details, correlation and compatibility behavior before implementation. | PASS |
| Tenant isolation | Every owned row carries owner tenant_id; shared top-level records also carry organizer_tenant_id. Owner writes use normal trusted tenant context. Cross-tenant catalog reads use only MarketplaceCatalogPublication allowlists; rental/statement reads require ParticipantScope; platform reads use a separate audited query. Jobs, events, caches, notifications, exports and adapter invocations carry both relevant scopes and never infer trust from request data. | PASS |
| RBAC and auditability | Tenant permissions are venue.manage, marketplace.manage, rentals.approve, reports.view and audit.view, composed with existing operational permissions. Platform oversight/dispute permissions are explicit. Publication, price/availability, request/decision, reservation conflict, delegation/control, statement/export and dispute actions write sanitized append-only evidence; shared transitions write correlated participant audit entries in the same transaction. | PASS |
| Credential security | The feature never mints, validates or exposes attendee/device credentials. Provisioned ACS/kiosk/scanner/printer resources continue to use existing credential, device-session, anti-passback and emergency paths. Delegation is an additional configuration authorization guard, not a second entry trust path. | PASS |
| Deployment parity | The same module, schema, APIs, scheduler and UI run in SaaS and on-premise. An on-premise installation supports local tenant-to-tenant marketplace operation. Remote catalog federation is explicitly excluded; disconnected remote discovery fails clearly while local grants synchronously honor stored start/end/revocation. | PASS |
| GCC/KSA and PDPL | Data is classified as public catalog, internal operations, confidential business contact/commercial data, or secret binding references. Purpose is B2B discovery, contracting, operations and reconciliation under the approved contractual/business basis. Collection and publication are minimized; retention/deletion/residency are policy-driven; camera metadata grants no feed, recording, identity or biometric access. SAR and other enabled ISO currencies use minor units and locale-aware display. | PASS |
| White-label and localization | Venue descriptions, catalog, decisions, notifications, status/reason labels, statements and disputes support Arabic/English, RTL/LTR, locale-aware venue times/numbers/currencies, accessibility, and the viewing tenant's branding. Shared facts remain language-neutral and identical across projections. | PASS |
| Modularity and adapters | VenueMarketplace owns marketplace persistence and exposes ParticipantScope/DelegatedControl services. It consumes Events and operational-module public contracts only. AccessControl/Kiosk/BadgePrinting/Scanning own provisioning of their event resources and consult Authorization's DelegatedControlGuard. No module reads or mutates another module's persistence; camera stays catalog-only. | PASS |
| Automated tests | Unit, MySQL concurrency, integration, contract, participant-isolation, RBAC, audit-atomicity, adapter degradation, scheduler, OpenAPI, UI accessibility/localization, performance and SaaS/on-premise parity coverage are required and enumerated below. | PASS |
| Phased delivery | Phase 6 builds on accepted Foundation, Events/credentials, kiosk/printer/scanner, ACS and identity phases and does not weaken them. It excludes Phase 7 federation/enterprise hardening and Phase 8 launch/scale work. | PASS |

No constitution exception or governance waiver is required.

## Architecture and Module Ownership

### VenueMarketplace (new, owned)

Owns venue profiles, asset inventory, secure resource bindings, availability,
prices, catalog publications, quotes, rental requests and lines, reservations,
control delegations, statements/lines, disputes/events, lifecycle jobs, API
resources, catalog and participant-scope readers, audit-triggering domain events,
and the implementation of the delegated-control guard.

The module follows the existing layout:

- Domain: enums, money/time value objects, quote and control results, state
  machines, domain events.
- Application: owner actions, catalog/participant/platform queries, quote and
  reservation services, activation/expiry/statement jobs, control guard.
- Contracts: published catalog reader, participant rental reader, binding
  validator and control result contracts exposed to other modules.
- Infrastructure: persistence models, operational provisioner registry and
  fakes; no provider credential material is stored in publication snapshots.
- HTTP: tenant/platform controllers, requests, resources and versioned routes.
- Providers/Testing: module registration, event/audit bindings and deterministic
  fakes.

### Existing modules (extended through narrow contracts)

- **Tenancy**: adds organization_type (organizer, venue_owner, hybrid; existing
  rows backfill organizer) and an OrganizationEligibility contract. Membership
  and role assignment remain unchanged.
- **Events**: exposes MarketplaceEventReader to prove an event belongs to the
  organizer tenant and return a minimized event/schedule snapshot. Marketplace
  code never imports the Event persistence model.
- **Authorization**: owns the DelegatedControlGuard interface. Its default
  behavior treats ordinary tenant resources as local; VenueMarketplace binds
  the implementation used when a provisioned resource carries a delegation
  reference.
- **AccessControl**: exposes DelegatedAcsAssetPort for turnstile, security-gate,
  access-lane and access-zone provisioning/release; event-scoped management
  actions consult DelegatedControlGuard while retaining emergency and
  anti-passback precedence.
- **Kiosk**: exposes DelegatedKioskAssetPort for event-scoped kiosk allocation and
  release; device-session authentication is unchanged.
- **BadgePrinting**: exposes DelegatedPrinterAssetPort for a printer binding and
  release; existing PrinterAdapter remains the only hardware transport.
- **Scanning**: exposes DelegatedScannerAssetPort for physical scanner allocation
  metadata where applicable; scan decisions and credential validation are
  unchanged.
- **Audit**: Phase 6 listeners write owner-only or correlated owner/organizer
  audit evidence synchronously. Platform dispute actions additionally write
  platform evidence. Secrets, raw bindings and private contact data are omitted.
- **Notifications**: consumes marketplace events after commit and renders
  recipient-localized, recipient-branded messages without copying private
  owner/organizer fields.
- **AdminConsole**: supplies tenant venue/catalog/rental/statement pages and the
  platform oversight/dispute pages on the existing DashboardLayout.
- **Shared/Operations**: reuse idempotency, signed cursors, Problem Details,
  correlation, tenant cache/queue/log boundaries, health/telemetry and feature
  flags. New Phase6Problem reason codes remain stable and language-neutral.

## Tenant Sharing and Authorization Boundaries

Three explicit read modes replace any generic cross-tenant query:

1. **Owner scope**: standard tenant context reads/writes venues, assets,
   availability, bindings and decisions where tenant_id equals the active venue
   owner.
2. **Catalog scope**: MarketplaceCatalogReader searches only active
   marketplace_catalog_publications. Publications contain a versioned,
   allowlisted snapshot and opaque public IDs; private source rows and binding
   references cannot be selected by the reader or serialized by its resource.
3. **Participant scope**: RentalParticipantScope accepts trusted actor tenant
   context and returns a projection only when it matches tenant_id (owner) or
   organizer_tenant_id. Owner internal notes, platform notes and raw bindings are
   projection-specific. PlatformMarketplaceQuery is a fourth, separate
   privileged path requiring platform scope and an audit record.

Shared jobs restore the owner tenant as the execution context and carry the
organizer tenant as an explicit participant. Shared domain events carry owner
tenant, organizer tenant, rental public ID and correlation ID. Cache keys include
actor tenant, publication version/filter digest and locale. A statement export is
streamed per authorized requester and is not stored in a shared file.

## Reservation, Quote and Lifecycle Strategy

- Search availability is advisory and does not reserve assets. Pending requests
  may overlap.
- Quote uses the current published asset versions, requested UTC window and venue
  timezone. Per-hour price bills each started hour; per-day bills each started
  venue-local calendar day; per-rental bills once. Values are integer minor units
  in one ISO 4217 currency. The response carries a quote digest/version; submit
  rejects a changed quote instead of silently accepting new terms.
- Submission stores the event, venue, time, asset capability, price, currency,
  timezone and catalog-version snapshots. The existing hashed idempotency layer
  prevents duplicate requests.
- Approval starts one AuditedTransaction, locks all selected venue_asset rows in
  ascending ID order, revalidates publication/operational/availability state and
  overlapping reserved/active intervals, inserts all reservations, changes the
  rental to approved and writes participant audit evidence. Any failure rolls
  back the whole set. MySQL integration tests use concurrent connections.
- A control delegation is created with the approved rental. Schedulers materialize
  active/expired status and notifications each minute, but authorization always
  compares current time, revocation and rental state synchronously. A late job can
  never extend access.
- Activation provisions event-scoped resources only through their owning module
  contracts. Those resources carry opaque delegation/asset public references, no
  cross-module foreign key or secret. Guard denial is fail-closed. Revocation and
  expiry deny synchronously first, then release/deprovision idempotently.
- Finalization dispatches an idempotent statement job. A unique rental/revision
  key guarantees at most one initial statement. The statement records agreed
  commercial facts and lifecycle outcome but never claims funds moved.

## Data, Privacy, Retention and Residency

- **Public catalog**: explicitly published bilingual venue/asset description,
  city/country, capabilities, capacity, price/currency and bookable windows.
- **Internal**: operational status, reservation/delegation state, event/resource
  references and audit correlation.
- **Confidential**: named business contacts, request/rejection/dispute text and
  settlement facts. Responses and audit metadata minimize these values.
- **Secret**: external device/provider binding references and all adapter/device
  credentials. Bindings are encrypted or reference the existing secret store and
  are never returned through catalog, rental, audit or telemetry APIs.

Venue/contact/request/dispute data follows tenant policy. Withdrawn publications
are removed from discovery immediately but retained privately while referenced
by an active/retained rental. Statements and audit evidence follow approved
contractual/accounting/legal holds; deletion produces tombstones/minimized
evidence rather than rewriting immutable facts. The same rules and local
scheduler operate on-premise. No camera feed, recording, attendee identity or
biometric data is introduced.

## Cross-Cutting UX Strategy

- Tenant navigation shows Venues to venue-owner/hybrid tenants, Marketplace and
  My Rentals to organizer/hybrid tenants, and Statements to reports.view users.
  Platform navigation shows Marketplace Oversight only with the platform key.
- Owner forms use bilingual fields, validated asset-type capability templates,
  timezone-aware availability, money inputs in minor-unit-safe formatting,
  publication readiness, and conflict warnings.
- Organizer catalog uses bounded filters, explicit empty/degraded results,
  opaque IDs, quote-expired handling, and an event selector limited to owned
  events.
- Rental detail uses one shared timeline with actor-specific actions, itemized
  facts, conflict/degraded states and clear delegation countdown/status.
- Statement/dispute views distinguish agreed amount from payment/payout state and
  never imply Zonetec moved funds.
- All pages reuse DashboardLayout, DataTable, StatusBadge, skeleton, toast,
  confirm/reason modal and error boundary components; Arabic/RTL and English/LTR
  receive equivalent validation, keyboard, focus and screen-reader behavior.

## Project Structure

### Documentation (this feature)

~~~text
specs/009-venue-marketplace/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── openapi.yaml
│   ├── marketplace-boundaries.md
│   └── dashboard-contract.md
├── checklists/
│   └── requirements.md
└── tasks.md                    # created later by /speckit-tasks
~~~

### Source Code (repository root)

~~~text
app/
├── Modules/
│   ├── VenueMarketplace/
│   │   ├── Domain/{Enums,ValueObjects,Results,Events}
│   │   ├── Application/{Actions,Queries,Jobs,Support,Authorization}
│   │   ├── Contracts/
│   │   ├── Infrastructure/{Persistence,Provisioning}
│   │   ├── Http/{Controllers,Requests,Resources}
│   │   ├── Providers/VenueMarketplaceServiceProvider.php
│   │   ├── Routes/api.php
│   │   └── Testing/
│   ├── Tenancy/**                         # organization_type + eligibility contract
│   ├── Events/Contracts/**                # MarketplaceEventReader
│   ├── Authorization/Contracts/**         # DelegatedControlGuard
│   ├── AccessControl/Contracts/**         # DelegatedAcsAssetPort
│   ├── Kiosk/Contracts/**                 # DelegatedKioskAssetPort
│   ├── BadgePrinting/Contracts/**         # DelegatedPrinterAssetPort
│   ├── Scanning/Contracts/**              # DelegatedScannerAssetPort
│   ├── Audit/Application/Listeners/Phase6/**
│   └── AdminConsole/**                    # page controllers/view models
├── Console/Commands/
│   ├── ActivateMarketplaceRentals.php
│   ├── ExpireMarketplaceRentals.php
│   └── FinalizeMarketplaceStatements.php
└── Providers/ModuleServiceProvider.php
config/marketplace.php
database/
├── migrations/**                          # organization type + marketplace tables/bindings
└── seeders/{PermissionSeeder.php,FoundationSeeder.php}
resources/js/
├── pages/tenant/marketplace/**
├── pages/tenant/venues/**
├── pages/platform/marketplace/**
├── components/marketplace/**
├── lib/{navigation.ts,tenant-navigation.ts,permissionCatalog.ts}
└── locales/{en,ar}.ts
routes/{web.php,console.php}
tests/
├── Unit/VenueMarketplace/**
├── Feature/VenueMarketplace/**
├── Integration/{VenueMarketplace,Security,DeploymentParity}/**
├── Contract/Phase6/**
├── Performance/Phase6MarketplacePerformanceTest.php
└── Architecture/Phase6ModuleBoundaryTest.php
docs/
├── api/openapi.yaml
├── standards/{permission-catalog.md,audit-event-catalog.md,data-classification.md}
└── security/{permissions-phase6.md,audit-catalog-phase6.md}
~~~

**Structure Decision**: Keep the single Laravel + Inertia/React modular
application. VenueMarketplace is the sole owner of marketplace state. Existing
modules gain only contracts and guarded resource metadata needed to provision
and enforce delegated resources; they retain their own persistence and rules.
No service split, global catalog query over private tables, direct cross-module
persistence read/mutation, operational-resource foreign key into marketplace
tables, or second credential/control path is introduced.

## Testing and Documentation Gates

- **Unit**: organization eligibility; asset capability schemas; money and quote
  rounding; timezone/day boundaries; venue/asset/rental/delegation/dispute state
  transitions; participant projections; reason codes; statement revision rules.
- **MySQL integration**: composite tenant/participant foreign keys; deterministic
  multi-asset row locking; two concurrent approvals for one interval; rollback on
  one conflicting asset/audit failure; unique idempotency and statement keys;
  index-backed catalog/participant queries.
- **Feature/API**: owner CRUD/publication; availability/pricing; catalog filters;
  quote-change and mixed venue/currency validation; request/list/show; every
  decision transition; activation/revocation/expiry; statement/export/dispute;
  platform oversight; Problem Details and signed cursors.
- **Security**: owner/organizer/unrelated tenant matrix for every source/projection;
  hidden unpublished/private/binding fields; platform permission isolation;
  RBAC composition; job/event/cache/file scope; audit sanitization/atomicity;
  credential/device secret regression.
- **Module contracts**: Events ownership reader, four operational provisioners,
  DelegatedControlGuard and fake/degraded/timeout/idempotent release behavior.
- **End-to-end UI**: owner publishes, organizer searches/quotes/requests, owner
  conflict-safe approves/rejects, organizer sees active/expired control, finance
  views/exports/disputes, platform resolves; all with loading/empty/error/
  conflict/forbidden/degraded states and Arabic/RTL axe coverage.
- **Performance**: 10,000 active publications, representative availability and
  reservation density, 95th-percentile search under 2 seconds; bounded lists and
  no N+1; activation/expiry/statement scheduler service-level targets.
- **Deployment parity**: same tests with SaaS and on-premise configuration,
  remote catalog unavailable behavior, local authorization expiry while queues
  are stopped, and recovery without duplicate activation/statements.
- **Quality**: composer quality, php artisan test, OpenAPI sync/lint, phase
  boundary check, Pint, npm test, ESLint with zero warnings, tsc --noEmit and
  Vite build.

Documentation updates are mandatory for the authoritative OpenAPI contract,
permission catalog, Phase 6 permission matrix, audit catalogs, data
classification, operations schedule/health, and on-premise degraded behavior.

## Post-Design Constitution Re-check

Phase 1 design preserves every pre-research PASS. The data model gives every row
an owner tenant, gives shared records an explicit organizer participant, uses
composite keys, and confines cross-tenant discovery to a versioned publication
snapshot. The OpenAPI review contract separates tenant and platform security,
requires idempotency on writes, and publishes stable conflict/control reason
codes. Marketplace boundaries prevent persistence reads across modules;
provisioned event resources keep existing device, credential, anti-passback and
emergency paths. Reservation locking, synchronous time/revocation checks,
correlated participant audit, statement immutability, privacy/residency,
Arabic/English, on-premise degradation and the full automated test matrix are
designed before implementation.

**Result**: PASS. No complexity exception or governance waiver is required.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
