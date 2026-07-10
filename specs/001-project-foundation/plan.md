# Implementation Plan: Phase 0 Project Foundation and Governance

**Branch**: `not-created (workspace is not a Git repository)` | **Date**: 2026-07-01 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-project-foundation/spec.md`

**Product Phase**: Foundation / Phase 0

**Deployment Modes**: SaaS and on-premise

## Summary

Create a new Laravel 13 application at the repository root, running on PHP 8.3 and MySQL 8.4 LTS, as an API-first modular monolith. Phase 0 establishes API/browser authentication, explicit tenant context, custom tenant-aware RBAC, transactional tamper-evident audit logs with sanitized diffs, configuration validation and tenant configuration schemas, feature flags, health/telemetry foundations, database-backed queues and tenant-aware jobs/events, contract-first OpenAPI 3.1 documentation, test and documentation gates, provider-neutral adapter interfaces, and a minimal foundation admin dashboard.

The dashboard uses Laravel's React starter architecture (React 19, TypeScript, Inertia 3, Tailwind 4, and shadcn/ui) with a project-owned visual system rather than a paid dashboard-template dependency. It is limited to Phase 0 administration and calls the same application actions, policies, tenant context, and API capabilities used by other clients. The application uses one shared schema with mandatory `tenant_id` ownership for tenant data. Platform operations use separate routes and authorization paths; missing tenant context never implies platform scope. The plan intentionally excludes event registration, ticketing/orders, payments, credentials and wallet passes, kiosks/scanners, ACS, identity verification, marketplace behavior, and production adapters for those domains. Docker and Laravel Sail are not used.

## Technical Context

**Language/Version**: PHP 8.3; Laravel 13.x

**Primary Dependencies**: Laravel Framework 13.x, Laravel Sanctum, Laravel Fortify with public registration/reset features disabled, Laravel queue/events/authorization facilities, React 19, TypeScript, Inertia 3, Tailwind CSS 4, shadcn/ui, Vite, Composer; Redocly CLI as a development-only OpenAPI lint command

**Storage**: MySQL 8.4 LTS as the primary and queue database; Laravel filesystem abstraction for generated audit exports, with tenant-scoped paths and local storage as the Phase 0 default

**Testing**: PHPUnit through Laravel's test runner; Laravel HTTP/database/queue/event fakes; MySQL-backed integration tests for isolation and constraints; OpenAPI lint and contract-conformance tests; React component tests; browser/system tests for dashboard authorization, RTL/LTR, responsive states, and cross-tenant rendering; Laravel Pint and frontend lint/type checks

**Target Platform**: PHP-capable Linux or Windows hosts for SaaS and on-premise deployments; no container runtime required

**Project Type**: API-first web service plus a tightly scoped administrative web application in one modular monolith

**Performance Goals**: 95th-percentile foundation API reads under 300 ms and state changes under 500 ms at the application boundary under expected Phase 0 load; initial dashboard content visible within 2 seconds on a typical broadband connection; health state changes visible within 60 seconds; RBAC and feature-flag changes effective immediately because those decisions are uncached in Phase 0

**Constraints**: Fail-closed tenant resolution; no query, job, event, file, cache key, log context, dashboard page, or adapter invocation may lose tenant scope; audit-required state changes and their audit records commit atomically; public health output is non-sensitive; secrets are never persisted in source, logs, traces, metrics, API errors, or audit metadata; feature flags cannot disable mandatory security controls; core controls and compiled dashboard assets remain operational without internet access; no Docker/Sail; no licensed dashboard-template code

**Scale/Scope**: Initial design target of 1,000 tenants, 100,000 workforce/service users, 10 million audit records, 100 concurrent foundation API requests, and bounded asynchronous audit exports; only Phase 0 modules and endpoints

## Constitution Check

*GATE: Passed before research and re-checked after design.*

| Principle | Design Evidence | Gate |
|-----------|-----------------|------|
| API-first | `contracts/openapi.yaml` is the source contract for `/api/v1`; `contracts/api-standards.md` defines versioning, errors, tenant context, idempotency, pagination, correlation, and retirement rules. Dashboard controllers call the same public application actions/policies and every dashboard capability remains exposed through the versioned API. | PASS |
| Tenant isolation | Tenant routes require authenticated user plus `X-Tenant-ID`; `ResolveTenantContext` verifies active tenant and membership before route binding or application work. Tenant models carry `tenant_id`, tenant queries begin from resolved context, jobs/events implement tenant-aware contracts, file paths and log context include tenant IDs, and negative MySQL integration tests cover every path. Platform routes are separate and explicit. | PASS |
| RBAC and auditability | Custom permissions, tenant roles/assignments, platform roles/assignments, policies, and `RequirePermission` middleware deny by default across API and dashboard routes. Security-sensitive successful, denied, and failed actions use the Audit module; required state changes write audit evidence in the same transaction with sanitized change summaries. | PASS |
| Credential security | Not applicable: attendee QR and wallet credentials are out of scope. Sanctum API token secrets are hashed by the authentication package and shown only once. | PASS |
| Deployment parity | One codebase, schema, migrations, queue semantics, and contracts serve SaaS and on-premise. The database queue and local filesystem default avoid cloud dependencies. External-network loss degrades adapters only, not auth, tenancy, RBAC, audit, or health. | PASS |
| GCC/KSA and PDPL | Phase 0 stores tenant administration, workforce/service identity, authorization, and audit data only. Data is minimized, access-controlled, residency/retention are deployment policy, exports expire, and secrets/raw sensitive payloads are forbidden from audit metadata. No government or identity integration is claimed. | PASS |
| White-label and localization | The foundation dashboard is a platform administration surface, not a branded attendee product. It has equivalent Arabic/RTL and English/LTR behavior, light/dark themes, accessible responsive states, and locale-aware dates. Tenant branding/domain schemas exist without a theme editor or branded product rendering. | PASS |
| Modularity and adapters | Tenancy, Identity, Authorization, Audit, FeatureFlags, Operations, Integrations, Shared, and AdminConsole have explicit ownership. AdminConsole owns presentation only and cannot query module persistence. Cross-module calls use public contracts or domain events. Integrations exposes provider-neutral interfaces and a fake adapter only. | PASS |
| Automated tests | Unit, feature, MySQL integration, contract, security, queue/event, OpenAPI, React component, accessibility, RTL/LTR, responsive, and end-to-end dashboard tests are mandatory; backend/frontend formatting, type checks, builds, migrations, tests, and contract validation are release gates. | PASS |
| Phased delivery | Every source module, route, migration, and contract in this plan is foundation-only. Product modules and production product adapters are absent. | PASS |

### Post-Design Re-check

The data model makes tenant ownership and platform scope structurally distinct, includes governed tenant configuration and feature-flag overrides, and stores sanitized audit change summaries. The OpenAPI contract separates tenant and platform operations, the dashboard contract restricts presentation to those same capabilities, the adapter contract carries tenant and correlation context, and the quickstart proves isolation, RBAC, audit failure, health/telemetry, queue, localization, dashboard authorization, and deployment behavior. No constitutional exception or complexity waiver is required.

## Architecture and Boundaries

### Request Pipeline

Tenant API requests use this order:

1. Assign or validate `X-Correlation-ID`.
2. Negotiate supported JSON and locale behavior.
3. Authenticate with Sanctum.
4. Resolve `X-Tenant-ID` to an active tenant and active membership.
5. Bind immutable `TenantContext` and structured log context.
6. Rate-limit by actor, tenant, and operation class.
7. Authorize through policy or named permission.
8. Validate input and execute an application action.
9. Persist required audit evidence in the same transaction as security-sensitive state.
10. Return the standard response/error shape and correlation ID.
11. Clear tenant/log context after the request, including exceptional paths.

Platform requests use a distinct `/api/v1/platform` route group with Sanctum authentication and platform permission middleware. They never obtain privilege from a missing `X-Tenant-ID`; tenant-targeting platform actions accept an explicit target ID and reason and emit privileged audit evidence.

### Module Ownership

- **Shared**: identifiers, clocks, correlation, locale-neutral errors, pagination, idempotency primitives, tenant-aware marker contracts, and base API resources.
- **Identity**: users, password authentication, browser sessions, Sanctum token lifecycle, user state, admin provisioning, and extension contracts for future MFA/API-key/service-token methods. No self-registration, production extension issuance, or attendee identity.
- **Tenancy**: tenants, memberships, tenant configuration schemas/values, `TenantContext`, request/job/event scope propagation, tenant lifecycle, and tenant-safe query conventions.
- **Authorization**: permission catalog, tenant/platform roles and assignments, policies, gates, and denial auditing. No third-party RBAC table model is used because tenant and platform scopes need distinct invariants.
- **Audit**: immutable audit writer, sanitized change summaries and client/source fingerprints, HMAC integrity verifier, search, bounded asynchronous exports, retention hooks, and audit event catalog.
- **FeatureFlags**: governed flag definitions, safe platform defaults, tenant overrides, deterministic evaluation, permissions, and audit events. Mandatory security controls are not flaggable.
- **Operations**: configuration validation, structured logs, metrics, distributed trace context, error tracking, live/ready/detailed health, scheduled integrity checks, and operational status.
- **Integrations**: adapter contracts, tenant-aware invocation context, stable result/error types, registry, and test fake. No production product integration.
- **AdminConsole**: React/Inertia presentation, navigation, design tokens, locale/direction handling, and typed view models for Phase 0 capabilities. It owns no domain persistence and cannot bypass module contracts or policies.

Each module exposes an application-level contract under `Contracts/`. A module may use its own persistence classes but must not query another module's tables directly. Cross-module behavior uses the owning module's contract or an immutable event.

### Tenant Isolation Strategy

- A single MySQL schema is used, with non-null `tenant_id` foreign keys on every tenant-owned table.
- Tenant uniqueness constraints always begin with `tenant_id`.
- HTTP tenant context comes only from `X-Tenant-ID`, then is checked against authenticated active membership before protected route binding.
- Tenant-owned models expose explicit `forTenant(TenantId)` query entry points. A defensive global scope may reject missing context, but it is not the sole control; application actions must receive `TenantContext`.
- Platform models and routes are separate. Cross-tenant platform queries live in narrowly named application actions and require platform permission plus reason.
- Queued jobs serialize tenant ID, actor/correlation metadata, and re-resolve an active tenant before handling. Missing/inactive context fails the job without product effects.
- Domain events carrying tenant-owned facts implement `TenantAwareEvent`; queued listeners restore and clear context.
- Cache keys, future file paths, structured logs, and adapter invocation context begin with `tenant:{tenantId}`. Phase 0 does not cache authorization decisions.
- Cross-tenant target IDs produce the same not-found response as absent IDs and create a denial audit record without leaking target details.
- Dashboard tenant selection resolves through the same `TenantContext`; SSR/Inertia props and client caches contain only the current authorized scope and are cleared on tenant switch/logout.

### Authentication and Authorization

- Laravel Sanctum issues revocable API tokens after email/password authentication. Fortify supplies browser session login/logout for the admin console with public registration, password-reset, email-verification, and MFA features disabled in Phase 0. Both paths use the same users, throttling, lifecycle checks, audit outcomes, and RBAC.
- Phase 0 exposes token login, current-token revocation, current actor, active tenant membership discovery, and browser session login/logout. It defines interfaces for future MFA, API keys, and service tokens but exposes no production enrollment, issuance, challenge, exchange, or verification flows for them.
- Platform administrators provision users and tenants. Tenant administrators manage memberships, roles, and assignments only within resolved tenant context.
- Permissions are immutable seeded keys such as `tenant.view`, `membership.manage`, `role.manage`, `audit.view`, and `audit.export`. Seeders are idempotent and never grant new permissions implicitly to custom roles.
- Tenant and platform roles use different tables and policies. Default access is none. The final active tenant administrator cannot be removed or disabled through ordinary tenant operations.
- Authorization denials map to the standard `forbidden` error and are audited with permission key, scope, actor, correlation, and a sanitized reason code.

### Foundation Admin Dashboard

- Use the official Laravel React starter architecture: React 19, TypeScript, Inertia 3, Tailwind 4, shadcn/ui, and Vite. Remove registration, password reset, email verification, team scaffolding, and any product demo pages.
- Build a project-owned visual language without copying or depending on licensed dashboard-template assets: collapsible sidebar, compact top bar, cards, data tables, command/search affordance, polished empty/loading/error states, subtle elevation, generous spacing, and restrained motion.
- Provide light, dark, and system themes. Design tokens use CSS custom properties so future tenant branding can map through validated schemas without forking components.
- Mirror navigation and direction for Arabic RTL; use logical CSS properties, locale-aware dates/numbers, accessible focus order, keyboard navigation, semantic headings, and WCAG 2.2 AA contrast targets.
- Platform navigation: Overview, Tenants, Users, Platform Roles, Platform Audit, Health/Telemetry, Feature Flags, Configuration Reference.
- Tenant navigation: Overview, Memberships, Roles & Permissions, Tenant Audit, Tenant Feature Flags, Configuration Schema/Values.
- Do not render registration, events, tickets, payments, attendees, credentials, wallet, scanning, kiosk, ACS, identity, marketplace, or placeholder navigation for them.
- Dashboard routes use session authentication, `ResolveTenantContext`, policies, and application queries/actions. Inertia props use explicit resource/view-model classes; Eloquent models are never passed directly.
- Every mutating dashboard operation has API-equivalent validation, idempotency where applicable, confirmation for destructive lifecycle changes, and audit evidence.

### Audit Integrity and Failure Semantics

- `AuditWriter` accepts typed audit data and an allow-listed metadata map; it rejects secret-like keys and oversized payloads.
- State changes include an allow-listed, size-bounded field-level change summary. Secret/sensitive fields record only a redacted “changed” marker; full before/after objects, raw IP addresses, and full user-agent strings are not stored. Privacy-preserving source/client fingerprints support investigation.
- Every record contains a canonicalized payload HMAC, key identifier, and algorithm version. This makes unauthorized database changes detectable without serializing all writes through one global hash chain.
- Application code exposes no update/delete operation for audit logs. Database credentials for the runtime receive insert/select as needed but no application path for audit mutation.
- Security-sensitive state mutations call the audit writer inside the same MySQL transaction. An audit write or integrity-generation failure rolls back the state change.
- Denied and failed attempts that have no state transaction are recorded synchronously. Failure to record them returns a safe service-unavailable outcome and an out-of-band operational error.
- Audit searches are permission-checked and bounded by tenant, date range, page size, and indexed fields. Audit exports create `audit_exports`, run on the database queue after commit, write to `tenants/{tenantId}/audit-exports/...`, expire, and audit both request and download.
- A scheduled integrity job verifies samples and recent records; full verification is available to authorized operators.

### API and OpenAPI Standards

- Base path is `/api/v1`; breaking changes require a new major path.
- JSON requests and responses use stable snake_case fields and UTC RFC 3339 timestamps; locale affects human-readable messages, not error codes or persisted business values.
- Errors use a Problem Details-compatible object with `type`, `title`, `status`, `code`, `detail`, `instance`, `correlation_id`, and optional field errors.
- `X-Correlation-ID` is accepted only when syntactically valid, otherwise generated; it is returned on every response and propagated to jobs, events, audit, logs, and adapters.
- Collection endpoints use cursor pagination with deterministic `(created_at, id)` ordering and a maximum page size of 100.
- Retriable state-changing endpoints require `Idempotency-Key`; the server binds its hash to tenant, actor, operation, and request digest and rejects conflicting reuse.
- `contracts/openapi.yaml` is linted and tested against route names, auth, tenant header, responses, examples, and schemas. Generated HTML may be served only in approved environments; the YAML remains the review source.

### Configuration and Health

- Laravel configuration files consume environment values once; application code reads configuration, never calls environment access directly.
- A startup/readiness validator checks app key, environment/debug safety, base URL, database, queue, audit integrity key ring/current key, filesystem, supported locales, and retention/export bounds. Secret values are redacted.
- SaaS and on-premise profiles differ only in infrastructure values, never in tenant/RBAC/audit rules.
- `GET /health/live` confirms the process can respond and returns no dependency detail.
- `GET /health/ready` returns a minimal ready/not-ready result based on required database, queue, storage, and audit-key checks.
- `GET /api/v1/platform/health` requires `operations.health.view` and returns categorized checks, durations, and last transition without secrets or connection details.
- Versioned tenant configuration schemas cover branding references, domain references, residency, and retention. Phase 0 dashboard/API surfaces schema inspection and validated stored values but no domain provisioning, asset upload, theme editor, or branded product rendering.
- Telemetry propagates tenant/correlation context through structured logs, metrics, traces, and error reports. Exporter failure is bounded and cannot block core work or replace mandatory audit evidence.

### Feature Flags

- Store stable flag keys, descriptions, owners, lifecycle status, safe platform defaults, and optional tenant overrides.
- Evaluate with explicit tenant or platform context and deterministic type validation; missing/invalid overrides use the documented safe default.
- Flag and override changes require named permissions, idempotency, validation, and transactional audit evidence.
- Tenant isolation, authentication, authorization, audit integrity, secret protection, data-residency enforcement, and other mandatory controls are hard-coded non-flaggable invariants.
- Phase 0 dashboard/API supports listing definitions/effective values and authorized management of platform definitions and tenant overrides only.

### Queues, Jobs, Events, and Listeners

- MySQL-backed queues are the Phase 0 default for portable SaaS/on-premise behavior; queue selection remains configurable.
- Jobs implement a tenant-aware contract and middleware that restores tenant, actor, locale, and correlation context and always clears it.
- Security-critical audit persistence is synchronous and transactional, never delegated to a queue.
- Domain events are immutable past-tense facts dispatched after transaction commit. Listeners may trigger audit export processing or operational follow-up but may not bypass module application contracts.
- `GenerateAuditExportJob`, `VerifyAuditIntegrityJob`, and expired-export cleanup provide concrete Phase 0 queue/scheduler coverage.
- Jobs declare timeout, retry/backoff, idempotency behavior, and permanent failure mapping; failed jobs retain sanitized context only.

## Project Structure

### Documentation (this feature)

```text
specs/001-project-foundation/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── openapi.yaml
│   ├── api-standards.md
│   ├── adapter-contract.md
│   └── dashboard-contract.md
└── tasks.md                 # Created later by speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Modules/
│   ├── Shared/
│   │   ├── Application/
│   │   ├── Contracts/
│   │   ├── Domain/
│   │   ├── Http/
│   │   └── Support/
│   ├── Identity/
│   ├── Tenancy/
│   ├── Authorization/
│   ├── Audit/
│   ├── FeatureFlags/
│   ├── Operations/
│   ├── Integrations/
│   │   ├── Contracts/
│   │   ├── Infrastructure/
│   │   ├── Providers/
│   │   └── Testing/
│   └── AdminConsole/
│       ├── Http/
│       └── ViewModels/
├── Providers/
└── Console/
bootstrap/
config/
├── auth.php
├── audit.php
├── feature-flags.php
├── health.php
├── integrations.php
├── observability.php
├── tenancy.php
└── zonetec.php
database/
├── factories/
├── migrations/
└── seeders/
docs/
├── api/
│   └── openapi.yaml
├── architecture/
├── operations/
└── standards/
lang/
├── ar/
└── en/
routes/
├── api.php
├── console.php
├── health.php
└── web.php
resources/
├── css/
│   └── app.css
└── js/
    ├── components/
    ├── composables/
    ├── layouts/
    ├── lib/
    ├── pages/
    │   ├── auth/
    │   ├── platform/
    │   └── tenant/
    ├── types/
    └── app.ts
storage/app/private/tenants/
tests/
├── Architecture/
├── Contract/
├── Feature/
│   ├── Api/
│   ├── Audit/
│   ├── Auth/
│   ├── Authorization/
│   ├── Health/
│   └── Tenancy/
├── Integration/
│   ├── MySql/
│   ├── Queue/
│   └── Security/
├── Support/
├── Unit/
└── Browser/
    └── AdminConsole/
```

**Structure Decision**: Use Laravel's standard root application and official React starter structure, with explicit domain modules under `app/Modules`. Each module repeats only the layers it needs and owns its migrations through namespaced, ordered migration files in the central Laravel migration directory. API and dashboard controllers remain thin, application actions own use cases, domain objects own invariants, infrastructure owns Eloquent/queue/filesystem details, providers bind public contracts, and React pages receive explicit view models. This preserves Laravel conventions without introducing multiple deployables, a generic repository layer, a separate frontend deployment, or a paid dashboard dependency.

## Migration and Rollback Strategy

- Create framework/session/cache/queue/Sanctum tables first, then Identity, Tenancy, Authorization, Audit, feature-flag, idempotency, and audit-export tables in foreign-key order.
- Use ULIDs for externally visible entity identifiers and composite tenant-first indexes for tenant-owned access paths.
- Use explicit status strings plus application enums and database check constraints for lifecycle values.
- Seed the permission catalog, baseline platform roles, and test-only demo tenants/users through separate idempotent seeders. Production seeding requires explicit administrator credentials and never uses known defaults.
- Migrations are forward-only in production; each migration supplies a safe local/test `down()` path. Destructive rollback in production uses a reviewed restore/migration procedure, not automatic data loss.
- Before later schema changes, test upgrade from the prior supported schema with realistic audit volume and verify tenant constraints and OpenAPI compatibility.

## Testing and Documentation Gates

Required automated suites:

- Unit tests for value objects, lifecycle transitions, permission evaluation, error mapping, canonical audit payloads, HMAC verification, adapter results, and configuration rules.
- Feature tests for every OpenAPI operation, validation branch, permission allow/deny pair, locale, pagination, idempotency, and safe errors.
- React component tests for layouts, design tokens, theme/direction switching, tables/forms, state components, and permission-aware navigation; browser tests verify that server authorization remains authoritative.
- MySQL integration tests for foreign keys, check/unique constraints, transaction rollback on audit failure, tenant-scoped queries, and concurrent role/audit behavior. SQLite is not accepted as the sole database test.
- Cross-tenant security matrices covering HTTP, model query entry points, jobs, events/listeners, exports/files, logs, idempotency records, and adapter context.
- Contract tests for OpenAPI lint/conformance and the common fake adapter suite.
- System journeys for login/context selection, tenant provisioning, membership/role assignment, last-admin protection, privileged platform action, audit search/export, audit failure, readiness degradation/recovery, and queued context restoration.
- Dashboard system journeys for platform/tenant navigation, tenant switching, role-based visibility, feature-flag/configuration views, audit/health states, Arabic RTL, English LTR, light/dark themes, keyboard access, responsive widths, and zero cross-tenant props/rendered data.
- Static/format gates: Composer validation, Laravel Pint, frontend lint/type check/build, migration status/fresh seed, automated tests, OpenAPI lint, documentation-link checks, and a scan for forbidden product-module names/routes/navigation.

Documentation deliverables:

- Foundation architecture and module ownership.
- Tenant-context and isolation rules with safe/unsafe examples.
- Permission and audit-event catalogs.
- API standards and generated OpenAPI reference.
- Configuration reference with sensitivity, defaults, and restart behavior.
- SaaS/on-premise operations, health, queue worker, scheduler, backup/restore, migration, and audit-integrity guides.
- Adapter authoring and contract-test guide.
- Dashboard design-system, accessibility, localization/RTL, authorization, and view-model rules.
- English/Arabic message-key policy, PDPL data inventory/retention/residency policy, contributor checklist, decision records, and exception register.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
