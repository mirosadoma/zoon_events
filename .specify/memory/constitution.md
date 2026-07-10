<!--
Sync Impact Report
- Version change: unversioned template -> 1.0.0
- Modified principles:
  - Template placeholders -> I. API-First Contracts
  - Template placeholders -> II. Tenant-Isolated Security (NON-NEGOTIABLE)
  - Template placeholders -> III. SaaS and On-Premise Parity
  - Template placeholders -> IV. GCC/KSA and PDPL Readiness
  - Template placeholders -> V. White-Label, Arabic, and English by Design
  - Added VI. Modular Backend and Adapter Boundaries
  - Added VII. Test-Gated, Phased Delivery
- Added sections:
  - Security, Data, and Integration Constraints
  - Development Workflow and Quality Gates
- Removed sections: none; template placeholders were resolved
- Templates:
  - ✅ updated: .specify/templates/plan-template.md
  - ✅ updated: .specify/templates/spec-template.md
  - ✅ updated: .specify/templates/tasks-template.md
  - ✅ reviewed: .specify/templates/commands/ (directory absent; no command files)
  - ✅ reviewed: all_plan.md (already aligned; no change required)
- Deferred items: none
-->
# Zonetec Constitution

## Core Principles

### I. API-First Contracts

Every backend capability MUST be exposed through a documented, versioned API
contract. API behavior, validation, authorization, errors, idempotency, and
tenant context MUST be defined before implementation. Contract changes MUST be
backward compatible within a published version or introduced through a new
version with a migration path. User interfaces, kiosks, scanners, jobs, and
external consumers MUST use the same application contracts rather than bypass
domain rules.

Rationale: stable contracts let Zonetec support web, on-site, partner, and
on-premise clients without duplicating business logic.

### II. Tenant-Isolated Security (NON-NEGOTIABLE)

Tenant data MUST never cross tenant boundaries. Every tenant-owned record MUST
carry a `tenant_id`, every request and background job MUST establish trusted
tenant context, and every query, cache key, event, file, and integration call
MUST enforce that context. Automated tests MUST attempt cross-tenant access and
prove denial.

Authorization MUST use RBAC and least privilege at every entry point, including
administrative, staff, kiosk, scanner, and machine-to-machine flows.
Security-sensitive actions MUST create tamper-evident audit records containing
actor, tenant, action, target, timestamp, and outcome. This includes
authentication, authorization changes, payments, credential lifecycle,
identity verification, ACS operations, overrides, exports, and administrative
actions.

QR credentials and wallet credential payloads MUST be uniquely identifiable,
signed with managed keys, expiry-aware, revocable, and resistant to replay.
Secrets MUST never be committed, logged, or returned to clients; sensitive data
MUST be encrypted in transit and at rest.

Rationale: a failure in isolation, authorization, auditing, or credential
integrity compromises the entire multi-tenant access platform.

### III. SaaS and On-Premise Parity

Zonetec MUST deliver the same core registration, ticketing, credential,
authorization, audit, and integration behavior in multi-tenant SaaS and
supported on-premise deployments. Deployment-specific concerns MUST be
isolated behind configuration and infrastructure boundaries; business rules
MUST NOT fork by deployment mode.

On-premise operation MUST support local secrets, storage, queues, observability,
backups, migrations, and external adapters. Features that require cloud
connectivity MUST declare degraded, offline, and recovery behavior. Any
intentional capability difference MUST be documented in the specification,
approved in the plan's Constitution Check, and covered by acceptance tests.

Rationale: one portable core reduces security drift and preserves the product
promise for residency-sensitive and high-security customers.

### IV. GCC/KSA and PDPL Readiness

Every feature handling personal, identity, biometric, payment, CCTV, or access
data MUST define purpose, lawful basis or consent, collection minimum,
retention, deletion, export, residency, and access controls before build.
PDPL-sensitive data MUST be minimized; biometric templates MUST be preferred
over raw images where feasible, and cross-border movement MUST be explicit and
configurable.

KSA integrations, regional payment methods, local time zones, currencies,
phone formats, identity types, and regulatory dependencies MUST be represented
through validated domain rules and adapters. Unknown government API access or
legal interpretation MUST remain an explicit blocking assumption and MUST NOT
be disguised as a production-ready integration.

Rationale: compliance and regional fit are architectural inputs, not release
checklist items.

### V. White-Label, Arabic, and English by Design

Organizer-facing and attendee-facing experiences MUST support tenant-controlled
branding without forks or tenant-specific code. Brand assets, domains,
templates, colors, sender identity, and event presentation MUST be configured
within secure, validated boundaries.

All user-visible content MUST be localizable in Arabic and English. Layouts
MUST support RTL and LTR, locale-aware dates, times, numbers, and currencies,
and equivalent validation and accessibility in both languages. Persisted
business data MUST remain language-neutral where possible; translated display
content MUST use explicit locale fields or resources.

Rationale: white-label ownership and bilingual parity are core product
capabilities for GCC customers.

### VI. Modular Backend and Adapter Boundaries

The backend MUST begin as a modular monolith with explicit domain boundaries,
owned data, application services, and public module contracts. Modules MUST NOT
read or mutate another module's persistence internals; cross-module behavior
MUST use declared application interfaces or domain events. A boundary may be
split into a service only when measured scale, security, ownership, or
deployment needs justify the operational cost.

Payments, notifications, Apple/Google Wallet, government identity, ACS,
printers, storage, and other external systems MUST be accessed through
adapter interfaces. Development and tests MAY use mocks or fakes behind those
interfaces, but production MUST use validated adapters with explicit timeout,
retry, idempotency, error mapping, and observability behavior.

Rationale: modularity protects the core domain while adapters prevent vendor
and hardware concerns from leaking into product logic.

### VII. Test-Gated, Phased Delivery

Automated tests are mandatory for every behavior change. Domain rules MUST have
unit tests; APIs and module boundaries MUST have integration tests; external
adapters MUST have contract tests; and critical registration, payment,
credential, tenant-isolation, RBAC, and audit journeys MUST have end-to-end or
equivalent system tests. Security fixes MUST include regression tests. CI MUST
block merging when required tests, static checks, migrations, or API contract
validation fail.

Delivery MUST proceed in independently releasable phases. Foundation work
establishes tenant context, RBAC, audit logging, API documentation, adapter
interfaces, migrations, configuration, and test/CI infrastructure. The first
product phase MUST deliver registration, ticketing/orders, payments through an
adapter, attendee records, and signed credential issuance, validation,
revocation, and reissue. Later kiosk, wallet, ACS, identity, marketplace, and
on-premise extensions MUST build on this accepted core and MUST NOT weaken its
contracts or controls.

Rationale: test gates keep security claims executable, while phased delivery
puts the validated credential core ahead of optional complexity.

## Security, Data, and Integration Constraints

- Tenant scope MUST be explicit in database schemas, unique constraints,
  indexes, cache keys, object storage paths, queues, domain events, exports,
  logs, metrics, and adapter requests.
- Global or platform administration MUST use explicit privileged paths and
  MUST NOT rely on missing tenant filters. Privileged access MUST be audited.
- Audit records MUST be append-only to application users and retain enough
  context for security, payment, credential, identity, and access
  investigations without storing secrets or unnecessary sensitive payloads.
- Credential signatures MUST use approved asymmetric or keyed algorithms,
  versioned key identifiers, rotation procedures, constant-time verification
  where applicable, and server-side status checks for revocation and replay.
- Each integration MUST define an adapter contract, authentication mechanism,
  data classification, tenant mapping, timeout, retry policy, idempotency
  behavior, observability, sandbox/test strategy, and production-readiness
  evidence.
- Specifications and plans MUST record data retention and residency decisions,
  Arabic/English behavior, white-label effects, SaaS/on-premise behavior, and
  offline/degraded modes whenever applicable.

## Development Workflow and Quality Gates

1. A specification MUST identify the product phase, prioritized user journeys,
   tenant and role boundaries, data sensitivity, audit events, localization,
   deployment modes, integration contracts, and measurable acceptance criteria.
2. An implementation plan MUST pass the Constitution Check before research and
   again after design. Any exception MUST identify the violated rule, business
   necessity, risk owner, compensating controls, and removal or review date.
3. Tasks MUST include required tests alongside implementation, with foundational
   isolation, RBAC, auditing, signing, adapter, localization, and CI work made
   explicit rather than deferred to a generic polish phase.
4. Code review MUST verify module ownership, API compatibility, tenant scoping,
   permission checks, audit coverage, sensitive-data handling, Arabic/English
   behavior, deployment parity, and test evidence.
5. A phase or feature is complete only when its acceptance tests pass, API
   documentation and migrations are current, observability is sufficient for
   operations, and rollback or recovery behavior is documented.

## Governance

This constitution is the highest engineering standard for Zonetec and
supersedes conflicting practices in plans, specifications, tasks, and code.
Every pull request and design review MUST demonstrate compliance with the
applicable principles.

Amendments require a written proposal describing the change, rationale,
affected artifacts, migration impact, and rollout plan. Approval requires the
designated product and engineering owners; changes affecting security,
privacy, residency, identity, payments, or access control also require the
appropriate security or compliance owner.

Constitution versions follow semantic versioning: MAJOR for incompatible
principle removals or redefinitions, MINOR for new principles or materially
expanded obligations, and PATCH for non-semantic clarification. Every amendment
MUST update the Sync Impact Report, dependent templates, version, and amendment
date.

Compliance MUST be reviewed during specification, planning, task generation,
code review, and release readiness. Exceptions are time-bounded governance
records, not silent deviations, and MUST include an owner, risk assessment,
compensating controls, and expiry or remediation milestone.

**Version**: 1.0.0 | **Ratified**: 2026-07-01 | **Last Amended**: 2026-07-01
