# Phase 0 Research and Decisions

## Decision 1: Framework and Runtime Baseline

**Decision**: Use Laravel 13.x on PHP 8.3, with Composer constraints that accept backward-compatible Laravel 13 updates.

**Rationale**: Laravel 13 is the current major line, requires PHP 8.3, and receives security fixes through March 17, 2028. The local environment already provides PHP 8.3 and Composer. Starting a new foundation on the current supported major avoids an immediate framework upgrade.

**Alternatives considered**:

- Laravel 12: still supported, but begins a new codebase on the prior major and has a shorter remaining support window.
- PHP 8.4/8.5 as the minimum: supported by Laravel 13 but not required by the current local environment; raising the floor would add deployment friction without Phase 0 value.

**Sources**: [Laravel 13 release and support policy](https://laravel.com/docs/13.x/releases)

## Decision 2: Primary Database

**Decision**: Use MySQL 8.4 LTS as the required production and integration-test database.

**Rationale**: The user selected MySQL, and the 8.4 line is an LTS series suited to SaaS and stable on-premise deployments. It provides enforced constraints, transactional writes, JSON fields, and indexing needed by the foundation.

**Alternatives considered**:

- MySQL Innovation releases: newer features are not needed and their cadence is less appropriate for on-premise support.
- SQLite for tests: useful only for fast isolated unit tests; it cannot be the acceptance database because tenant constraints and transactional behavior must match MySQL.
- Database-per-tenant: stronger physical isolation but creates unacceptable provisioning, migration, connection-pool, and on-premise complexity for the foundation's target scale. The constitution requires enforced isolation, not a particular physical topology.

**Sources**: [MySQL 8.4 LTS guidance](https://dev.mysql.com/doc/refman/8.4/en/which-version.html), [MySQL 8.4 reference manual](https://dev.mysql.com/doc/refman/8.4/en/)

## Decision 3: Multi-Tenancy Model

**Decision**: Use a shared schema with non-null tenant ownership, explicit immutable tenant context, separate platform routes, and defense-in-depth query conventions.

**Rationale**: This preserves one portable code/schema for SaaS and on-premise, supports efficient cross-tenant platform operations through approved paths, and makes isolation testable at middleware, application, persistence, job, event, file, and adapter boundaries. A missing tenant context always fails; it never selects platform mode.

**Alternatives considered**:

- Relying only on an Eloquent global scope: too easy to bypass in raw queries, jobs, or platform operations.
- Tenant selected only from token metadata: makes multi-tenant users switch tokens and obscures per-operation context.
- Tenant selected only from route IDs: workable, but a required `X-Tenant-ID` gives one uniform context carrier across tenant endpoints and future clients.

## Decision 4: API Authentication

**Decision**: Use Laravel Sanctum bearer tokens for Phase 0, with admin-provisioned users, token login/revocation, coarse token abilities, and custom RBAC for authorization.

**Rationale**: Sanctum is Laravel's preferred first-party choice for straightforward API tokens and future first-party web/mobile clients. OAuth2 authorization-server behavior is not required in Phase 0. Keeping token abilities coarse avoids duplicating the tenant-aware permission model.

**Alternatives considered**:

- Laravel Passport: appropriate only if full OAuth2 behavior becomes a later explicit requirement.
- JWT package: adds third-party token lifecycle and revocation complexity without a present need.
- Session-only authentication: insufficient for an API-first backend and machine/API validation.

**Sources**: [Laravel 13 authentication and Sanctum guidance](https://laravel.com/docs/13.x/authentication)

## Decision 5: RBAC Implementation

**Decision**: Implement project-owned RBAC using Laravel policies/gates and separate tenant-role and platform-role persistence.

**Rationale**: Zonetec needs explicit tenant boundaries, separate platform privilege, last-tenant-admin protection, auditable assignment changes, and denial evidence. Owning the small foundation model makes these invariants visible and avoids adapting a generic package's global assumptions.

**Alternatives considered**:

- A generic permissions package: mature and convenient, but its polymorphic/global caches and team support would still require careful adaptation and could hide critical tenant invariants.
- Permissions embedded in tokens: stale until token replacement and unsuitable for immediate role revocation.
- Attribute-based access control: useful later for resource rules, but needlessly broad for the initial named-permission foundation.

**Sources**: [Laravel 13 authorization policies and gates](https://laravel.com/docs/13.x/authorization)

## Decision 6: Audit Integrity

**Decision**: Store audit records synchronously with required state changes and sign a canonical record payload with a versioned HMAC key. Provide verification jobs and deny application updates/deletes.

**Rationale**: Transactional persistence meets the fail-safe requirement. Per-record HMAC evidence detects unauthorized changes without the write contention and ordering fragility of a single global hash chain. A key identifier supports rotation and verification of older records.

**Alternatives considered**:

- Asynchronous audit writes: can lose evidence or permit state changes while audit storage is unavailable.
- One global chained hash: establishes ordering but serializes concurrent writes and complicates multi-node recovery.
- Database timestamps and permissions only: useful controls but not tamper evidence.

## Decision 7: Queue and Event Baseline

**Decision**: Use Laravel's database queue by default, after-commit dispatch, immutable tenant-aware events, tenant-context job middleware, bounded retries, and scheduled audit/export maintenance.

**Rationale**: A database queue requires no extra service, works offline on-premise, and exercises the context propagation required before later features. Security-critical audit records remain synchronous.

**Alternatives considered**:

- Redis as a mandatory queue: higher throughput but adds an infrastructure dependency not justified by Phase 0.
- Synchronous-only queue: fails to prove tenant-aware asynchronous processing.
- Queued audit writes: violates the audit failure requirement.

**Sources**: [Laravel 13 queues](https://laravel.com/docs/13.x/queues), [Laravel 13 events](https://laravel.com/docs/13.x/events)

## Decision 8: OpenAPI Contract Workflow

**Decision**: Maintain a reviewed OpenAPI 3.1.1 YAML document as the contract source, lint it in the quality gate, and test route/response conformance. Generated interactive documentation is derived output.

**Rationale**: A contract-first document satisfies API-first governance and remains independent of controller annotations or implementation discovery. OpenAPI is language-agnostic and suitable for clients and reviewers.

**Alternatives considered**:

- Generate the contract only from controllers: encourages implementation before contract review and can omit policy/error semantics.
- Maintain only prose API docs: not machine-verifiable.
- OpenAPI 3.0: widely supported, but 3.1 aligns schema behavior more closely with modern JSON Schema. The specific 3.1.1 document is stable and sufficient even though newer specification revisions exist.

**Sources**: [OpenAPI Specification 3.1.1](https://spec.openapis.org/oas/v3.1.1.html)

## Decision 9: Identifier, Pagination, and Idempotency Standards

**Decision**: Use ULIDs for externally visible records, UTC timestamps, cursor pagination ordered by `(created_at, id)`, and hashed idempotency keys bound to tenant, actor, operation, and request digest.

**Rationale**: ULIDs are URL-safe and index-friendly without exposing sequential counts. Stable compound ordering prevents pagination gaps. Binding idempotency records to full execution context prevents a key from being replayed across tenants or operations.

**Alternatives considered**:

- Auto-increment IDs: operationally simple but enumerable and awkward for distributed import/export.
- Offset pagination: degrades and shifts under concurrent audit writes.
- Store raw idempotency keys: unnecessarily exposes caller secrets.

## Decision 10: Modular Monolith Layout

**Decision**: Keep one Laravel application and deployment unit with modules under `app/Modules`, public module contracts, application actions, owned persistence, events, and adapter interfaces.

**Rationale**: This follows the constitution, preserves Laravel conventions, and prevents premature distributed-system costs. It also leaves clear extraction seams if measured scale or deployment needs later justify a service.

**Alternatives considered**:

- Conventional controllers/models/services without module ownership: quicker initially but permits later features to bypass foundation rules.
- Separate Composer packages per module: stronger physical boundaries but excessive release/autoload overhead at this stage.
- Microservices: unjustified operational and consistency complexity.

## Decision 11: Health and Configuration

**Decision**: Provide public minimal live/ready checks, an authorized detailed platform check, and centralized startup/readiness configuration validation with redaction.

**Rationale**: Operators need safe diagnosis, while public endpoints must not expose dependencies or secrets. Laravel configuration caching requires environment values to be consumed in configuration files rather than application code.

**Alternatives considered**:

- One detailed public health endpoint: leaks operational topology.
- Fail only when a bad setting is first used: creates partial readiness and difficult incident diagnosis.
- Cloud-vendor-specific health tooling: violates deployment parity.

## Decision 12: No Docker and No Product Features

**Decision**: Document native PHP/Composer/MySQL setup and host-managed queue/scheduler processes. Do not install or generate Docker, Sail, Kubernetes, registration, ticketing, wallet, kiosk, ACS, identity, marketplace, payment, notification, or hardware integration code.

**Rationale**: This is an explicit user constraint and Phase 0 scope boundary. Adapter fakes prove interfaces without implying product readiness.

**Alternatives considered**: None within authorized scope.

## Decision 13: Foundation Admin Dashboard Stack

**Decision**: Use Laravel's React starter architecture with React 19, TypeScript, Inertia 3, Tailwind CSS 4, shadcn/ui, and Vite. Build a project-owned visual system and do not depend on paid dashboard-template source/assets.

**Rationale**: The Laravel React starter stack keeps backend and frontend in one modular-monolith deployment, supports responsive navigation and light/dark modes, and leaves all application code under project control. React's ecosystem and first-party Inertia adapter fit the planned dashboard complexity while a project-owned design system avoids commercial licensing and compatibility risk.

**Alternatives considered**:

- Commercial React dashboard packages: may accelerate visual work, but require a license decision and compatibility verification before adoption.
- Filament: excellent for rapid administration, but model-centric resources can encourage direct persistence access and make the API/application-contract boundary less obvious.
- Livewire/Flux: cohesive PHP-first stack, but React/TypeScript offers a stronger fit for the requested interaction model and future dashboard complexity.
- Separate SPA/deployment: unnecessary operational complexity for Phase 0.

**Sources**: [Laravel 13 starter kits](https://laravel.com/docs/13.x/starter-kits), [Laravel starter-kit overview](https://laravel.com/starter-kits), [Inertia 3 documentation](https://inertiajs.com/docs/v3/getting-started), [React documentation](https://react.dev/)

## Decision 14: Dashboard Authentication and Contract Boundary

**Decision**: Use Fortify-backed browser sessions for the admin console and Sanctum bearer tokens for API clients. Disable public registration, password reset, email verification, teams, MFA, API-key issuance, and service-token issuance in Phase 0. Both channels use the same users, lifecycle rules, throttling, tenant context, policies, application actions, and audit writer.

**Rationale**: Browser sessions provide secure same-origin dashboard authentication while Sanctum remains appropriate for the API. Keeping controllers thin and routing both channels through public application contracts avoids duplicate business rules and satisfies API-first governance.

**Alternatives considered**:

- Store bearer tokens in browser storage: increases token-exposure risk and is unnecessary for a same-origin admin console.
- Inertia pages querying Eloquent directly: faster scaffolding but bypasses module and authorization boundaries.
- Enable all starter-kit authentication routes: violates the clarified scope and would accidentally add public registration or unfinished MFA/reset workflows.

**Sources**: [Laravel 13 starter-kit authentication controls](https://laravel.com/docs/13.x/starter-kits), [Laravel 13 authentication guidance](https://laravel.com/docs/13.x/authentication)

## Decision 15: Feature-Flag Foundation

**Decision**: Implement project-owned, typed feature-flag definitions with safe platform defaults and optional tenant overrides. Evaluate through trusted context, do not cache in Phase 0, and make mandatory security controls permanently non-flaggable.

**Rationale**: The clarified specification requires tenant-aware rollout control, immediate consistency, auditability, and safe fallback. A small owned model keeps tenant invariants explicit and avoids introducing a remote flag service that would weaken offline/on-premise behavior.

**Alternatives considered**:

- Laravel Pennant: useful evaluation primitives, but the project still needs governed definitions, ownership, tenant override constraints, permissions, and audit records; Phase 0 keeps these rules explicit.
- Remote feature-flag SaaS: adds network, residency, secret, and on-premise dependencies.
- Environment-only flags: cannot provide governed tenant overrides or runtime auditability.

## Decision 16: Tenant Configuration Schema Boundary

**Decision**: Persist versioned tenant configuration values for branding references, domain references, residency, and retention against project-owned schemas. Expose inspection and controlled foundation storage, but do not implement domain provisioning, brand assets, a theme editor, or product rendering.

**Rationale**: This creates stable data/validation contracts for later white-label and compliance work without smuggling product workflows into Phase 0. Schema versions support migration and prevent unvalidated JSON from becoming a permanent compatibility burden.

**Alternatives considered**:

- Arbitrary JSON configuration: flexible but weakly validated and difficult to migrate or audit.
- Fully normalized branding/domain product models: premature because workflows are excluded.
- Documentation-only schemas: insufficient to prove validation, tenant isolation, and audit behavior.

## Decision 17: Telemetry Baseline

**Decision**: Establish structured logs, metrics, distributed trace context, error tracking, and health transitions behind provider-neutral exporters. Use correlation and trusted tenant scope, redact sensitive values, and bound exporter failure so it cannot block core behavior or replace audit evidence.

**Rationale**: This resolves the master-plan operational-readiness requirement while preserving SaaS/on-premise parity. Provider-neutral contracts allow local or centrally managed backends later without changing module behavior.

**Alternatives considered**:

- Logs and health only: insufficient for the clarified telemetry foundation.
- Mandatory cloud observability provider: violates offline/on-premise requirements.
- Put audit events in the telemetry pipeline: loses transactional and integrity guarantees.

## Decision 18: Audit Detail and Privacy

**Decision**: Store allow-listed, size-bounded field-level change summaries plus privacy-preserving source/client fingerprints. Never store full before/after objects, raw IP addresses, full user-agent strings, passwords, tokens, secrets, or designated sensitive values.

**Rationale**: Reviewers retain actor/action/target/outcome/correlation and sanitized changed-field evidence while data minimization reduces breach and PDPL exposure. Secret fields can record only that a value changed.

**Alternatives considered**:

- Full before/after snapshots: simpler to implement but duplicates sensitive data and makes retention/redaction harder.
- Raw IP and user-agent fields: may aid investigations but add personal data and fingerprinting risk without an approved policy.
- No change summary: minimizes data but weakens administrative investigation value.
