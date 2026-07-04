# Phase 1 release readiness

Run date: 2026-07-04  
Decision owner: Release Engineering

Phase 1 covers bilingual branded events and forms, ticket inventory/pricing,
atomic free and recoverable paid registration, organizer order/attendee/refund
operations, signed credential lifecycle, and durable localized confirmations.

Evidence:

- API and compatibility: the 29-operation OpenAPI contract, sync check, and
  `Phase1OpenApiCoverageTest`.
- Migration/restore: `phase1-migration-evidence.md`.
- Isolation, RBAC, audit, and privacy: the Phase 1 security group matrices,
  permission/audit catalogs, and `phase1-data-governance.md`.
- Payments, credentials, and notifications: their focused integration,
  contract, replay, leakage, recovery, and operations runbooks.
- Accessibility/frontend: Vitest suites, TypeScript, ESLint, and production
  Vite build.
- Performance/parity: Phase 1 performance fixtures and SaaS/on-premise
  blocked-network parity tests.
- Supply chain: `phase1-dependency-audit.md`.

Release is ready only after the current clean-gate results recorded in this
change remain green, enabled live adapters have approved secret references and
provider onboarding evidence, an environment-specific retention/residency
policy is approved, and backup restore evidence is attached. There are no
active expired governance exceptions and the automated boundary gate excludes
wallet, scanning/check-in, kiosk, badge, ACS, identity verification,
marketplace, hardware, venue asset, and rental implementation.

Clean-gate result: backend 204 tests / 1,543 assertions; frontend 17 tests;
Composer and npm advisories 0 high/critical; OpenAPI lint/sync, PHP/TypeScript/
ESLint formatting, production build, documentation, migration, and phase
boundary checks passed.
