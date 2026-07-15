# Tasks: Phase 6 — Venue Marketplace

**Input**: Design artifacts in specs/009-venue-marketplace/
**Required references**: spec.md, plan.md, research.md, data-model.md, contracts/openapi.yaml, contracts/marketplace-boundaries.md, contracts/dashboard-contract.md, quickstart.md
**Testing policy**: Tests are mandatory. Create each listed test before its implementation task, confirm that it fails for the intended reason, then implement the smallest complete change that makes it pass.

## Execution protocol for a cheaper implementation model

- [M:H] means highly mechanical or pattern-based. A cheaper model may execute these tasks and may run independent [P] tasks in separate clean contexts.
- [M:S] means security-, tenancy-, concurrency-, money-, audit-, or cross-module-sensitive. A cheaper model may execute it only one task at a time, must read every named contract/reference first, and must attach the targeted test output before marking it complete.
- Never batch two [M:S] tasks into one edit. Never weaken an assertion, remove a tenant predicate, bypass an authorization check, or replace a transactional test with a mock to make a task pass.
- For every task: inspect the named source files, preserve existing user changes, implement only that task, run its targeted tests, run the nearest existing regression suite, and change [ ] to [x] only when its acceptance text is true.
- If the codebase contradicts a task, stop that task and record the exact conflict in specs/009-venue-marketplace/quickstart.md under Validation Evidence; do not invent a new architecture.

## Task line legend

- [P] = may run in parallel after all listed dependencies are complete because it owns different files and has no unfinished prerequisite.
- [US1] through [US5] = the user story whose independently testable increment owns the task.
- [Test] = test-first task; it must fail for the expected missing behavior before corresponding implementation begins.
- Every depends clause is a hard prerequisite. Every accept clause is the minimum evidence required to mark the task complete.

---

## Phase 1: Setup

**Purpose**: Establish the module shell, configuration, routing, and test harness without implementing marketplace behavior.

- [x] T001 [M:H] Create app/Modules/VenueMarketplace/README.md documenting the planned Domain, Application, Infrastructure, Http, Routes, Providers, and Testing namespaces and the rule that this module never imports operational infrastructure classes directly; accept when the file names each allowed port from contracts/marketplace-boundaries.md.
- [x] T002 [P] [M:H] Create config/marketplace.php with disabled-by-default cache TTL, activation batch size, statement batch size, export chunk size, and feature-observability settings sourced from environment variables; accept when all values have typed, local-safe defaults and no remote service is required.
- [x] T003 [M:H] Create app/Modules/VenueMarketplace/Providers/VenueMarketplaceServiceProvider.php and register it from app/Providers/ModuleServiceProvider.php; accept when the provider loads module API routes, bindings, listeners, and console commands conditionally without changing other module boot order. (depends: T001)
- [x] T004 [M:H] Create app/Modules/VenueMarketplace/Routes/api.php and require it from routes/api.php using the repository’s existing module-route convention; accept when an empty authenticated phase6 route group boots without duplicate names or middleware changes. (depends: T003)
- [x] T005 [P] [M:H] Create app/Modules/VenueMarketplace/Http/Problems/Phase6Problem.php with stable reason-code constants for validation, eligibility, publication, quote, conflict, delegation, statement, and dispute failures; accept when constants match contracts/openapi.yaml and contain no translated prose.
- [x] T006 [P] [M:S] [Test] Create tests/Architecture/Phase6ModuleBoundaryTest.php with initially passing namespace/import allowlists derived from contracts/marketplace-boundaries.md and explicit forbidden direct infrastructure imports; accept when the test fails if VenueMarketplace imports an operational Eloquent model or operational module imports a VenueMarketplace model.
- [x] T007 [P] [M:H] Add scripts openapi:phase6 and test:phase6 to package.json without changing existing scripts; accept when openapi:phase6 validates specs/009-venue-marketplace/contracts/openapi.yaml and test:phase6 invokes the Phase 6 frontend test directory.
- [x] T008 [P] [M:H] Add documented MARKETPLACE_* defaults from config/marketplace.php to .env.example; accept when secrets are absent, values work in SaaS and on-premise modes, and every new variable is referenced by config/marketplace.php. (depends: T002)
- [x] T009 [P] [M:H] Create tests/Support/CreatesMarketplaceFixture.php with deterministic tenant, user, event, clock, ULID, and money builders but no persisted marketplace records yet; accept when the trait compiles and does not bypass public application actions.
- [x] T010 [M:H] [Test] Create tests/Feature/VenueMarketplace/VenueMarketplaceModuleBootTest.php covering provider boot, route registration, configuration defaults, and missing-route authentication; accept when php artisan test tests/Feature/VenueMarketplace/VenueMarketplaceModuleBootTest.php passes. (depends: T003, T004, T005, T008)

**Checkpoint**: The application boots with an empty VenueMarketplace module and enforceable module boundaries.

---

## Phase 2: Foundational Prerequisites

**Purpose**: Add organization eligibility, permissions, shared contracts, common value objects, and test doubles required by every user story.

**Critical**: No user-story implementation starts until T011–T035 are complete.

- [x] T011 [P] [M:S] [Test] Create tests/Feature/Tenancy/OrganizationEligibilityTest.php covering organizer, venue_owner, hybrid, invalid values, existing-tenant backfill, and SaaS/on-premise parity; accept when it fails only because organization_type and the eligibility service do not yet exist.
- [x] T012 [M:S] Create database/migrations/2026_07_14_000001_add_organization_type_to_tenants_table.php with organizer, venue_owner, and hybrid values, organizer backfill, indexed non-null default, and reversible down migration; accept when T011 migration/backfill assertions pass on MySQL. (depends: T011)
- [x] T013 [M:H] Update app/Modules/Tenancy/Infrastructure/Persistence/Models/Tenant.php, app/Modules/Tenancy/Http/Requests/Platform/StoreTenantRequest.php, app/Modules/Tenancy/Http/Requests/Platform/UpdateTenantRequest.php, and app/Modules/Tenancy/Http/Resources/TenantResource.php to persist, validate, cast, and expose organization_type; accept when invalid values return 422 and existing platform tenant tests remain green. (depends: T012)
- [x] T014 [M:S] Create app/Modules/Tenancy/Application/Contracts/OrganizationEligibility.php and app/Modules/Tenancy/Application/Services/DatabaseOrganizationEligibility.php, then bind them in app/Modules/Tenancy/Providers/TenancyServiceProvider.php; accept when T011 proves organizer access, venue-owner access, and hybrid access independently without trusting request input. (depends: T012, T013)
- [x] T015 [P] [M:S] [Test] Create tests/Feature/VenueMarketplace/MarketplacePermissionMatrixTest.php covering venue.manage, marketplace.manage, rentals.approve, reports.view, audit.view, platform.marketplace.view, and platform.marketplace.disputes.manage with deny-by-default behavior; accept when it initially fails for the missing Phase 6 permission rows.
- [x] T016 [M:S] Add the seven Phase 6 tenant/platform permission keys and scopes to database/seeders/PermissionSeeder.php; accept when T015 proves tenant permissions cannot satisfy platform checks and platform permissions do not silently grant tenant operations. (depends: T015)
- [x] T017 [M:S] Update database/seeders/FoundationSeeder.php with least-privilege Venue Owner Admin, Venue Asset Manager, Venue Rental Approver, and Venue Finance Manager role assignments while preserving existing organizer roles; accept when repeated seeding is idempotent and T015 passes the documented role matrix. (depends: T016)
- [x] T018 [P] [M:H] Update resources/js/lib/permissionCatalog.ts with typed Phase 6 permission constants grouped by tenant and platform scope; accept when TypeScript rejects an unknown key and existing navigation permission tests still pass. (depends: T016)
- [x] T019 [P] [M:H] Create app/Modules/VenueMarketplace/Domain/Enums/MarketplaceEnums.php and app/Modules/VenueMarketplace/Domain/ValueObjects/Money.php, RentalWindow.php, and OpaqueMarketplaceId.php for the statuses and immutable scalar rules in data-model.md; accept when constructors reject invalid currency, negative amounts, invalid windows, and non-ULID public IDs.
- [x] T020 [P] [M:S] Create app/Modules/VenueMarketplace/Domain/Services/MarketplaceCapabilityRegistry.php defining the eight supported asset types, per-type capabilities, control modes, and camera as catalog_only; accept when unknown capability/type combinations fail closed and no binding credential fields are part of the registry.
- [x] T021 [P] [M:S] Create app/Modules/Events/Application/Contracts/MarketplaceEventReader.php with event-ownership, immutable snapshot, and local-time-window result types; accept when the contract exposes no Events Eloquent model and includes tenant, event public ID, timezone, start, and end.
- [x] T022 [M:S] Create app/Modules/Events/Application/Services/DatabaseMarketplaceEventReader.php and bind it in app/Modules/Events/Providers/EventsServiceProvider.php; accept when it tenant-scopes every lookup and returns only the contract projection defined in T021. (depends: T021)
- [x] T023 [P] [M:S] Create app/Modules/Authorization/Application/Contracts/DelegatedControlGuard.php plus app/Modules/Authorization/Application/Services/DenyingDelegatedControlGuard.php and bind the denying implementation by default; accept when missing marketplace wiring denies delegated operations but does not affect ordinary owner operations.
- [x] T024 [P] [M:S] Create app/Modules/AccessControl/Application/Contracts/DelegatedAcsAssetPort.php and its provision/release request-result value objects; accept when the contract uses opaque tenant/event/delegation/asset IDs and never accepts marketplace models or raw credentials.
- [x] T025 [P] [M:S] Create app/Modules/Kiosk/Application/Contracts/DelegatedKioskAssetPort.php and its provision/release request-result value objects; accept when the contract is idempotency-keyed and exposes no pairing secret.
- [x] T026 [P] [M:S] Create app/Modules/BadgePrinting/Application/Contracts/DelegatedPrinterAssetPort.php and its provision/release request-result value objects; accept when it supports only the capability subset declared in T020 and exposes no printer secret.
- [x] T027 [P] [M:S] Create app/Modules/Scanning/Application/Contracts/DelegatedScannerAssetPort.php and its provision/release request-result value objects; accept when it supports bounded event/window context and exposes no device credential.
- [x] T028 [M:H] Create app/Modules/VenueMarketplace/Testing/Fakes/FakeOrganizationEligibility.php, FakeMarketplaceEventReader.php, FakeDelegatedControlGuard.php, FakeDelegatedAcsAssetPort.php, FakeDelegatedKioskAssetPort.php, FakeDelegatedPrinterAssetPort.php, and FakeDelegatedScannerAssetPort.php; accept when each fake records opaque calls, supports deterministic failure, and does not subclass production infrastructure. (depends: T014, T021, T023, T024, T025, T026, T027)
- [x] T029 [P] [M:S] Create app/Modules/VenueMarketplace/Application/Audit/MarketplaceAuditEvent.php and MarketplaceAuditWriter.php contract with owner, organizer, and platform sanitized payload projections; accept when the contract requires correlation_id and prohibits binding payloads, secrets, free-form request bodies, and raw credentials.
- [x] T030 [P] [M:H] Extend resources/js/Components/StatusBadge.tsx with venue, asset, rental, delegation, statement, and dispute status variants while preserving existing variants; accept when every new enum maps to text plus a non-color-only visual distinction.
- [x] T031 [P] [M:H] Add shared Phase 6 navigation labels, status labels, reason-code labels, empty states, and generic validation messages to resources/js/locales/en/translation.json and resources/js/locales/ar/translation.json; accept when key sets are identical and Arabic strings contain no untranslated English placeholders.
- [x] T032 [M:S] Expand tests/Architecture/Phase6ModuleBoundaryTest.php to assert the exact port ownership, allowed namespace edges, no reverse model dependencies, and no payments/federation imports described in contracts/marketplace-boundaries.md; accept when mutation fixtures for each forbidden edge are detected. (depends: T006, T021, T023, T024, T025, T026, T027, T029)
- [x] T033 [P] [M:S] [Test] Create tests/Contract/VenueMarketplace/MarketplaceBoundaryContractsTest.php covering opaque identifiers, idempotency keys, no-secret serialization, camera catalog-only behavior, and denying guard defaults; accept when every contract can be exercised without booting an operational infrastructure adapter. (depends: T020, T021, T023, T024, T025, T026, T027)
- [x] T034 [P] [M:H] [Test] Create tests/Feature/VenueMarketplace/Phase6ProblemTest.php asserting stable HTTP status, reason code, safe details, English fallback, and no secret leakage for every constant in app/Modules/VenueMarketplace/Http/Problems/Phase6Problem.php; accept when the reason-code set matches contracts/openapi.yaml. (depends: T005)
- [x] T035 [M:S] [Test] Create tests/Feature/VenueMarketplace/Phase6FoundationSmokeTest.php proving organization eligibility, scoped permissions, event reads, default delegated denial, audit payload validation, and all operational ports resolve in one application boot; accept when the complete foundational suite passes on MySQL. (depends: T014, T017, T022, T023, T028, T029, T032, T033, T034)

**Checkpoint**: Organization eligibility, authorization, cross-module ports, safe identifiers, and common test infrastructure are stable.

---

## Phase 3: User Story 1 — Publish a Venue and Rentable Assets (Priority: P1) — MVP

**Goal**: A venue-owner or hybrid organization can manage private venue inventory and publish an allowlisted catalog projection for selected assets.

**Independent test**: Create a venue, add all eight supported asset types, define capabilities/availability/pricing, and publish selected assets. An organizer sees only the allowlisted published projection; drafts, private notes, bindings, credentials, retired assets, and unpublished fields remain invisible.

### Tests for User Story 1

- [x] T036 [P] [US1] [M:S] [Test] Create tests/Feature/VenueMarketplace/VenueCatalogSchemaTest.php asserting every US1 table, tenant key, organizer-safe public ULID, unique/index/check constraint, currency field, timestamp, soft-delete rule, and reversible migration from data-model.md; accept when it fails because the tables do not exist. (depends: T035)
- [x] T037 [P] [US1] [M:H] [Test] Create tests/Unit/VenueMarketplace/VenueLifecycleTest.php covering draft, active, suspended, archived transitions, required address/timezone/contact fields, and terminal archive behavior; accept when each invalid transition has the expected Phase6Problem reason code. (depends: T035)
- [x] T038 [P] [US1] [M:S] [Test] Create tests/Unit/VenueMarketplace/VenueAssetCapabilityTest.php covering all eight asset types, valid capability sets, capacity/unit/pricing validation, camera catalog-only mode, opaque operational bindings, and explicit rejection of secrets; accept when invalid combinations fail closed. (depends: T020, T035)
- [x] T039 [P] [US1] [M:S] [Test] Create tests/Feature/VenueMarketplace/AssetAvailabilityWindowTest.php covering venue-local timezone conversion, overlap rejection, adjacent windows, blackout windows, optimistic version conflict, cross-tenant IDs, and retired assets; accept when writes are atomic and preserve the previous schedule on failure. (depends: T035)
- [x] T040 [P] [US1] [M:S] [Test] Create tests/Feature/VenueMarketplace/MarketplacePublicationProjectionTest.php covering readiness, allowlisted fields/capabilities, withdrawal, suspended venues, retired assets, reservation conflicts, stable opaque IDs, and absence of private/binding columns; accept when raw private values cannot be found in serialized output or query logs. (depends: T035)
- [x] T041 [P] [US1] [M:H] [Test] Create tests/Feature/VenueMarketplace/OwnerVenueApiContractTest.php for every owner venue, asset, availability, publish, and withdraw operation in contracts/openapi.yaml; accept when authentication, validation, status codes, reason codes, pagination, and response shapes are asserted. (depends: T035)
- [x] T042 [P] [US1] [M:S] [Test] Create tests/Feature/VenueMarketplace/OwnerVenueAuthorizationTest.php covering organizer denial, venue-owner/hybrid eligibility, permission denial, cross-tenant opaque IDs returning 404, and platform-role non-escalation; accept when each database assertion remains tenant-scoped. (depends: T035)
- [x] T043 [P] [US1] [M:S] [Test] Create tests/Feature/VenueMarketplace/OwnerVenueAuditTest.php covering atomic audit writes for create/update/status/archive/asset/availability/publish/withdraw, correlation IDs, before/after summaries, rollback on audit failure, and credential/private-note redaction; accept when no committed mutation lacks its audit row. (depends: T029, T035)
- [x] T044 [P] [US1] [M:H] [Test] Create resources/js/Pages/Tenant/Venues/__tests__/VenueManagement.test.tsx covering venue list/detail/create/edit, asset editor, availability editor, readiness errors, publish/withdraw, permission-hidden actions, and server validation display; accept when tests fail on the missing pages rather than test setup. (depends: T030, T031)
- [x] T045 [P] [US1] [M:H] [Test] Create resources/js/Pages/Tenant/Venues/__tests__/VenueManagementAccessibility.test.tsx covering Arabic/English keys, RTL ordering, keyboard dialogs, focus return, labels, error announcements, and status text independent of color; accept when axe reports no serious violations once UI is implemented. (depends: T030, T031)

### Implementation for User Story 1

- [x] T046 [US1] [M:S] Create database/migrations/2026_07_14_000002_create_venues_table.php exactly from Venue in data-model.md, including tenant-scoped uniqueness, venue timezone, private contact fields, public ULID, status, optimistic version, and reversible indexes; accept when the venue portion of T036 passes. (depends: T036)
- [x] T047 [US1] [M:S] Create database/migrations/2026_07_14_000003_create_venue_assets_and_bindings_tables.php exactly from VenueAsset and VenueAssetBinding in data-model.md, with tenant/venue integrity and opaque operational references but no secret columns; accept when asset/binding schema assertions in T036 pass. (depends: T046)
- [x] T048 [US1] [M:S] Create database/migrations/2026_07_14_000004_create_asset_availability_windows_table.php with asset/tenant integrity, venue-local source values, normalized UTC values, versioning, blackout semantics, and overlap-supporting indexes; accept when availability schema assertions in T036 pass. (depends: T047)
- [x] T049 [US1] [M:S] Create database/migrations/2026_07_14_000005_create_marketplace_catalog_publications_tables.php for MarketplaceCatalogPublication and MarketplacePublicationCapability, copying only allowlisted public fields and supporting withdrawal without deleting history; accept when all T036 schema and rollback assertions pass. (depends: T047)
- [x] T050 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/Venue.php with tenant scope, casts, guarded fields, public-ID lookup, lifecycle helpers, and no catalog serialization shortcut; accept when model assertions in T037 and T042 pass. (depends: T046)
- [x] T051 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/VenueAsset.php with type/status/pricing/currency/capacity casts, tenant scope, public-ID lookup, and relations constrained by tenant; accept when asset model assertions in T038 and T042 pass. (depends: T047)
- [x] T052 [P] [US1] [M:S] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/VenueAssetBinding.php exposing only opaque operational references and encrypted-at-rest metadata only if allowed by the contract; accept when mass assignment and serialization cannot return a credential-shaped field. (depends: T047)
- [x] T053 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/AssetAvailabilityWindow.php with tenant/asset scope, UTC and local casts, version handling, and blackout helpers; accept when model-level T039 assertions pass. (depends: T048)
- [x] T054 [P] [US1] [M:S] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/MarketplaceCatalogPublication.php with immutable public projection casts, tenant ownership, withdrawal state, and organizer-safe serialization only; accept when it has no relation that exposes VenueAssetBinding. (depends: T049)
- [x] T055 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/MarketplacePublicationCapability.php with publication-scoped allowlisted key/value casts and deterministic ordering; accept when unknown capability keys cannot be persisted through application actions. (depends: T049)
- [x] T056 [US1] [M:S] Create app/Modules/VenueMarketplace/Domain/Services/VenueLifecyclePolicy.php implementing T037 transitions and organization eligibility requirements without reading HTTP state; accept when all lifecycle unit tests pass. (depends: T037, T050)
- [x] T057 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Application/Actions/CreateVenueAction.php using OrganizationEligibility, tenant context, AuditedTransaction, public ULID generation, and VenueLifecyclePolicy; accept when create API/audit tests pass without a controller-owned transaction. (depends: T014, T043, T050, T056)
- [x] T058 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Application/Actions/UpdateVenueAction.php with tenant-scoped lookup, optimistic version check, immutable tenant/public ID, and AuditedTransaction; accept when stale updates return the contract reason code and preserve prior data. (depends: T043, T050, T056)
- [x] T059 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Application/Actions/ChangeVenueStatusAction.php implementing active/suspended/draft transitions and publication visibility effects inside AuditedTransaction; accept when T037 and T043 status assertions pass. (depends: T043, T050, T056)
- [x] T060 [P] [US1] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ArchiveVenueAction.php preventing archive with disallowed active obligations, withdrawing catalog projections atomically, and retaining historical records; accept when archive failure changes neither venue nor publications. (depends: T043, T049, T050, T056)
- [x] T061 [US1] [M:S] Create app/Modules/VenueMarketplace/Domain/Services/VenueAssetBindingPolicy.php that validates asset type, capabilities, operational reference ownership through ports, camera catalog-only behavior, and secret-shaped input rejection; accept when T038 passes with no direct operational model import. (depends: T020, T038, T051, T052)
- [x] T062 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Application/Actions/CreateVenueAssetAction.php and UpdateVenueAssetAction.php using T061, tenant-scoped venue lookup, integer minor-unit pricing, version checks, and AuditedTransaction; accept when invalid type/currency/capability combinations roll back. (depends: T043, T051, T052, T061)
- [x] T063 [P] [US1] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/RetireVenueAssetAction.php to prevent new publication, withdraw current projection, retain historical bindings, and reject retirement when contract obligations forbid it; accept when no private row is deleted and audit is atomic. (depends: T043, T049, T051, T061)
- [x] T064 [US1] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ReplaceAssetAvailabilityAction.php that locks the tenant-scoped asset, validates local/UTC windows and overlaps, checks expected version, replaces the schedule atomically, and writes one summarized audit event; accept when T039 and T043 pass. (depends: T039, T043, T053)
- [x] T065 [US1] [M:S] Create app/Modules/VenueMarketplace/Domain/Services/PublicationReadinessPolicy.php checking venue/asset status, required public fields, capability allowlist, pricing, availability, bindings for controllable types, and camera catalog-only rules; accept when every T040 readiness case returns a stable reason code. (depends: T038, T040, T050, T051, T052, T053)
- [x] T066 [US1] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/PublishVenueAssetAction.php and WithdrawVenueAssetPublicationAction.php to copy a new immutable allowlisted projection or withdraw it inside AuditedTransaction; accept when T040 proves private-row edits do not silently mutate an existing projection. (depends: T043, T054, T055, T065)
- [x] T067 [P] [US1] [M:S] Create app/Modules/VenueMarketplace/Application/Queries/OwnerVenueQuery.php and OwnerVenueAssetQuery.php with participant tenant predicates, pagination, stable ordering, and aggregate counts that do not cross tenant boundaries; accept when T042 returns 404 for foreign public IDs and query logs contain tenant predicates. (depends: T050, T051, T053, T054)
- [x] T068 [P] [US1] [M:H] Create app/Modules/VenueMarketplace/Http/Requests/CreateVenueRequest.php, UpdateVenueRequest.php, ChangeVenueStatusRequest.php, CreateVenueAssetRequest.php, UpdateVenueAssetRequest.php, ReplaceAvailabilityRequest.php, and PublishVenueAssetRequest.php matching contracts/openapi.yaml; accept when validation normalizes no money or timezone values in controllers. (depends: T005)
- [x] T069 [P] [US1] [M:S] Create app/Modules/VenueMarketplace/Http/Resources/OwnerVenueResource.php, OwnerVenueAssetResource.php, AvailabilityWindowResource.php, and PublicationReadinessResource.php with explicit allowlists; accept when resources expose private owner data only on owner endpoints and never expose bindings or credentials. (depends: T050, T051, T052, T053, T054)
- [x] T070 [US1] [M:H] Create app/Modules/VenueMarketplace/Http/Controllers/OwnerVenueController.php and OwnerVenueAssetController.php as thin authorization/request/action/resource adapters for every US1 operation; accept when no controller starts a transaction, builds a query, or reads tenant_id from request input. (depends: T057, T058, T059, T060, T062, T063, T064, T066, T067, T068, T069)
- [x] T071 [US1] [M:H] Register named, authenticated, tenant-context, permission-protected US1 endpoints in app/Modules/VenueMarketplace/Routes/api.php exactly as contracts/openapi.yaml; accept when T041 and T042 pass and route:list shows no unprotected mutation endpoint. (depends: T070)
- [x] T072 [US1] [M:S] Create app/Modules/VenueMarketplace/Domain/Events/VenueCatalogEvents.php and app/Modules/VenueMarketplace/Application/Listeners/WriteVenueCatalogAudit.php, then register the listener in VenueMarketplaceServiceProvider.php; accept when T043 proves synchronous rollback on audit failure and secret-free correlated payloads. (depends: T029, T057, T058, T059, T060, T062, T063, T064, T066)
- [x] T073 [P] [US1] [M:H] Create app/Modules/AdminConsole/Application/ViewModels/TenantVenueIndexViewModel.php and TenantVenueDetailViewModel.php with permission-filtered owner data and translation-ready enums; accept when no Eloquent model reaches Inertia props. (depends: T067, T069)
- [x] T074 [US1] [M:H] Create app/Modules/AdminConsole/Http/Controllers/TenantVenuePageController.php, register /tenant/venues and /tenant/venues/{venue_public_id} in routes/web.php, and add the permission-aware entry to resources/js/Components/DashboardLayout.tsx; accept when organizer-only tenants cannot see or open the pages. (depends: T018, T073)
- [x] T075 [P] [US1] [M:H] Create resources/js/Pages/Tenant/Venues/Index.tsx and Show.tsx using DashboardLayout, server pagination, StatusBadge, empty/loading/error states, and owner actions; accept when T044 list/detail assertions pass. (depends: T030, T031, T074)
- [x] T076 [P] [US1] [M:H] Create resources/js/Pages/Tenant/Venues/Components/VenueForm.tsx, AssetEditor.tsx, AvailabilityEditor.tsx, and PublicationPanel.tsx with typed API payloads and server reason-code handling; accept when T044 mutation/readiness tests pass without duplicating backend policy logic. (depends: T068, T074)
- [x] T077 [US1] [M:H] Add all US1 page, asset-type, capability, pricing, availability, and publication strings to resources/js/locales/en/translation.json and resources/js/locales/ar/translation.json; accept when T045 passes in LTR and RTL and no literal user-facing English remains in US1 components. (depends: T075, T076)
- [x] T078 [US1] [M:H] Extend tests/Support/CreatesMarketplaceFixture.php and create database/factories/VenueMarketplaceFactory.php with valid private venue, eight asset types, bindings, schedules, and published projections created through application actions; accept when US1 feature tests use deterministic public IDs and the complete US1 suite passes. (depends: T057, T062, T064, T066, T071, T072, T077)

**Checkpoint**: US1 is a deployable MVP: venue owners can publish safe inventory and organizers can never observe private inventory data.

---

## Phase 4: User Story 2 — Discover Assets and Request Them for an Event (Priority: P2)

**Goal**: An organizer can search safe published inventory, obtain a deterministic quote, and submit one idempotent rental request for assets from one venue.

**Independent test**: As an organizer, filter by city, venue, asset type, capability, capacity, and date; select available assets from one venue; request a quote; submit once and retry with the same idempotency key. Exactly one request exists with immutable event, window, publication, capability, and price snapshots.

### Tests for User Story 2

- [x] T079 [P] [US2] [M:S] [Test] Create tests/Feature/VenueMarketplace/MarketplaceCatalogSearchTest.php covering city/venue/type/capability/capacity/date filters, pagination, stable ordering, withdrawal/suspension visibility, no private fields, no binding joins, and participant-independent public IDs; accept when it fails on missing catalog endpoints only. (depends: T078)
- [x] T080 [P] [US2] [M:S] [Test] Create tests/Feature/VenueMarketplace/MarketplaceCatalogAvailabilityTest.php covering UTC queries against venue-local availability, blackout windows, adjacent boundaries, DST spring/fall transitions, existing reservations, stale publications, and all-assets-must-fit semantics; accept when every boundary is specified with a frozen clock. (depends: T078)
- [x] T081 [P] [US2] [M:S] [Test] Create tests/Unit/VenueMarketplace/MarketplaceQuoteServiceTest.php covering per_hour started-hour rounding, per_day started venue-local calendar-day rounding, per_rental once, quantity, integer minor-unit totals, mixed pricing units, zero/negative rejection, and deterministic quote digest/version; accept when no floating-point arithmetic appears in assertions. (depends: T019, T078)
- [x] T082 [P] [US2] [M:S] [Test] Create tests/Feature/VenueMarketplace/MarketplaceQuoteValidationTest.php covering foreign events, mixed venues, mixed currencies, unpublished/retired/unavailable assets, changed projections, expired quotes, invalid windows, unsupported capabilities, and quote_changed responses; accept when errors reveal no foreign asset or event details. (depends: T078)
- [x] T083 [P] [US2] [M:S] [Test] Create tests/Feature/VenueMarketplace/SubmitRentalRequestTest.php covering event ownership, immutable snapshots, one-venue enforcement, request-level currency/totals, selected capabilities, idempotent replay, idempotency-payload mismatch, and all-or-nothing persistence; accept when duplicate retries create one request and one set of lines. (depends: T022, T078)
- [x] T084 [P] [US2] [M:S] [Test] Create tests/Feature/VenueMarketplace/RentalParticipantIsolationTest.php covering organizer and owner projections, tenant-scoped list/detail, opaque foreign IDs returning 404, owner private fields excluded from organizer output, and unrelated tenant denial; accept when query logs show explicit owner or organizer participant predicates. (depends: T078)
- [x] T085 [P] [US2] [M:S] [Test] Create tests/Feature/VenueMarketplace/RentalRequestAuditNotificationTest.php covering correlated sanitized owner/organizer audit rows, rollback when either audit write fails, queued notification only after commit, retry deduplication, and no private catalog fields in messages; accept when notification failure cannot undo a committed audited request. (depends: T029, T078)
- [x] T086 [P] [US2] [M:H] [Test] Create tests/Feature/VenueMarketplace/OrganizerMarketplaceApiContractTest.php for catalog list/detail, quote, rental create/list/detail operations from contracts/openapi.yaml; accept when authentication, eligibility, permission, idempotency header, pagination, status, reason code, and response schemas are asserted. (depends: T078)
- [x] T087 [P] [US2] [M:H] [Test] Create resources/js/Pages/Tenant/Marketplace/__tests__/CatalogQuote.test.tsx covering filters, pagination, selection restricted to one venue, capability choices, event/window input, quote totals/rounding disclosure, quote_changed recovery, and request success; accept when it fails for missing components only. (depends: T031)
- [x] T088 [P] [US2] [M:H] [Test] Create resources/js/Pages/Tenant/Marketplace/__tests__/OrganizerRentals.test.tsx covering request list/detail, participant-safe fields, status timeline, empty/error states, English/Arabic, RTL, keyboard/focus behavior, and permission-hidden actions; accept when axe setup is shared with T045. (depends: T031)

### Implementation for User Story 2

- [x] T089 [US2] [M:S] Create database/migrations/2026_07_14_000006_create_rental_requests_table.php exactly from RentalRequest in data-model.md with owner tenant_id, organizer_tenant_id, event/window snapshot, venue timezone, currency/totals, quote digest/version, status/version, public ULID, and idempotency uniqueness; accept when schema and rollback assertions added to T083 pass. (depends: T083)
- [x] T090 [US2] [M:S] Create database/migrations/2026_07_14_000007_create_rental_assets_table.php exactly from RentalAsset in data-model.md with immutable publication/asset/capability/pricing snapshots, quantity, line total, stable order, and request/owner/organizer integrity; accept when mixed-tenant and duplicate-line constraints reject invalid rows. (depends: T089)
- [x] T091 [P] [US2] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/RentalRequest.php with owner scope, organizer participant scope helpers, immutable snapshot casts, status/version, idempotency fields, and public-ID lookup; accept when it has no global unscoped find-by-public-ID method. (depends: T089)
- [x] T092 [P] [US2] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/RentalAsset.php with immutable money/capability/publication snapshots and participant-safe relations; accept when post-request publication or private asset edits do not alter serialized rental lines. (depends: T090)
- [x] T093 [US2] [M:S] Create app/Modules/VenueMarketplace/Application/Queries/MarketplaceCatalogReader.php that queries MarketplaceCatalogPublication only, applies every contract filter and availability predicate, uses stable cursor/page ordering, and projects explicit fields; accept when T079 proves no Venue/VenueAsset/Binding table access occurs. (depends: T054, T055, T079)
- [x] T094 [P] [US2] [M:S] Create app/Modules/VenueMarketplace/Application/Services/MarketplaceCatalogCache.php using publication/version/filter/locale scoped keys and local cache storage, with invalidation on publish/withdraw/status changes; accept when disabled cache behavior is identical and cross-tenant/private data cannot enter a cache value. (depends: T002, T066, T093)
- [x] T095 [US2] [M:S] Create app/Modules/VenueMarketplace/Domain/Services/MarketplaceQuoteService.php implementing all pricing units using Money, venue-local time, deterministic line ordering, quote expiry, digest, and version; accept when T081 passes and a source scan finds no float, round on decimal, or client-provided total trust. (depends: T019, T081)
- [x] T096 [US2] [M:S] Create app/Modules/VenueMarketplace/Application/Services/RentalEventSnapshotResolver.php using MarketplaceEventReader to prove organizer ownership and capture immutable event ID/name/timezone/window; accept when foreign or missing events return a non-enumerating 404 and T082 event cases pass. (depends: T022, T082)
- [x] T097 [US2] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/SubmitRentalRequestAction.php with participant eligibility, one-venue/currency enforcement, publication re-read, quote digest/version recheck, event snapshot, owner-scoped AuditedTransaction, idempotency replay, and immutable line insertion; accept when T082, T083, and T085 pass. (depends: T085, T090, T091, T092, T095, T096)
- [x] T098 [US2] [M:S] Create app/Modules/VenueMarketplace/Application/Queries/RentalParticipantScope.php that requires current tenant plus owner-or-organizer role and returns a typed projection selector; accept when T084 proves every list, count, detail, and relation query carries a participant predicate. (depends: T084, T091, T092)
- [x] T099 [P] [US2] [M:S] Create app/Modules/VenueMarketplace/Application/Queries/ListParticipantRentalsQuery.php and GetParticipantRentalQuery.php using RentalParticipantScope, permission filters, stable pagination, and explicit owner/organizer projections; accept when foreign IDs are indistinguishable from missing IDs. (depends: T098)
- [x] T100 [P] [US2] [M:H] Create app/Modules/VenueMarketplace/Domain/Events/RentalRequested.php with opaque request/event/participant IDs, safe status/total metadata, and correlation/idempotency identifiers; accept when serialization contains no private venue fields, binding references, or notification text. (depends: T091, T092)
- [x] T101 [US2] [M:S] Create app/Modules/VenueMarketplace/Application/Listeners/WriteRentalRequestedAudit.php using MarketplaceAuditWriter to synchronously write correlated owner and organizer rows before commit; accept when T085 rollback, correlation, redaction, and deduplication assertions pass. (depends: T029, T085, T097, T100)
- [x] T102 [P] [US2] [M:H] Create app/Modules/VenueMarketplace/Application/Listeners/SendRentalRequestedNotifications.php and resources/views/mail/marketplace/rental-requested.blade.php with after-commit queueing, dedupe key, safe deep link, and bilingual translation keys; accept when T085 notification assertions pass without including private fields. (depends: T085, T100)
- [x] T103 [P] [US2] [M:H] Create app/Modules/VenueMarketplace/Http/Requests/CatalogSearchRequest.php, MarketplaceQuoteRequest.php, and SubmitRentalRequest.php matching contracts/openapi.yaml, including idempotency header extraction and typed filters; accept when requests never accept tenant IDs, calculated totals, publication private IDs, or arbitrary capabilities. (depends: T005)
- [x] T104 [P] [US2] [M:S] Create app/Modules/VenueMarketplace/Http/Resources/MarketplaceCatalogResource.php, MarketplaceQuoteResource.php, ParticipantRentalResource.php, and ParticipantRentalLineResource.php with role-specific explicit allowlists; accept when T079 and T084 secret/private-field scans pass. (depends: T093, T095, T099)
- [x] T105 [US2] [M:H] Create app/Modules/VenueMarketplace/Http/Controllers/MarketplaceCatalogController.php, MarketplaceQuoteController.php, and ParticipantRentalController.php as thin adapters using eligibility, authorization, queries/actions, and resources; accept when no controller reads a model directly or calculates money. (depends: T014, T093, T095, T097, T099, T103, T104)
- [x] T106 [US2] [M:H] Register catalog, quote, and participant rental endpoints with named middleware/permissions in app/Modules/VenueMarketplace/Routes/api.php exactly as contracts/openapi.yaml; accept when T086 passes and idempotency is required only for request creation. (depends: T105)
- [x] T107 [P] [US2] [M:H] Create app/Modules/AdminConsole/Application/ViewModels/TenantMarketplaceCatalogViewModel.php and TenantRentalViewModel.php plus app/Modules/AdminConsole/Http/Controllers/TenantMarketplacePageController.php; accept when Inertia props use the same participant-safe resource projections and contain no ORM objects. (depends: T093, T099, T104)
- [x] T108 [US2] [M:H] Register /tenant/marketplace, /tenant/marketplace/rentals, and /tenant/marketplace/rentals/{rental_public_id} in routes/web.php and add eligibility/permission-aware navigation in resources/js/Components/DashboardLayout.tsx; accept when venue-owner-only tenants see owner rentals but cannot create organizer requests. (depends: T018, T107)
- [x] T109 [P] [US2] [M:H] Create resources/js/Pages/Tenant/Marketplace/Index.tsx and Components/CatalogFilters.tsx and CatalogAssetCard.tsx with server filters, stable pagination, safe fields, selection reset rules, and accessible empty/loading/error states; accept when catalog portions of T087 pass. (depends: T108)
- [x] T110 [P] [US2] [M:H] Create resources/js/Pages/Tenant/Marketplace/Components/RentalQuotePanel.tsx with event/window/capability selection, one-venue guard, quote expiry/digest tracking, minor-unit formatting, rounding disclosure, idempotency key lifecycle, and quote_changed recovery; accept when quote/request portions of T087 pass. (depends: T095, T103, T108)
- [x] T111 [P] [US2] [M:H] Create resources/js/Pages/Tenant/Marketplace/Rentals/Index.tsx and Show.tsx with participant-aware labels, immutable snapshot display, status timeline, totals, pagination, and safe deep links; accept when T088 functional assertions pass. (depends: T108)
- [x] T112 [US2] [M:H] Add US2 catalog/filter/quote/pricing/rental/timeline/notification strings to both locale files and extend database/factories/VenueMarketplaceFactory.php with organizer events and submitted requests created through T097; accept when T087, T088, and the entire US2 backend suite pass in English and Arabic. (depends: T077, T097, T102, T109, T110, T111)

**Checkpoint**: US1 and US2 work independently; organizers can discover safe projections and create deterministic, idempotent requests.

---

## Phase 5: User Story 3 — Approve or Reject a Conflict-Safe Rental (Priority: P3)

**Goal**: A venue approver can decide requests without double-booking, partial reservation, audit gaps, or participant data leakage.

**Independent test**: Submit two overlapping requests for the same asset. Approve the first and concurrently attempt to approve the second. Exactly one succeeds; the loser receives a stable conflict reason. Reject the remaining request with a reason. Both parties see correct status and correlated audit history.

### Tests for User Story 3

- [x] T113 [P] [US3] [M:S] [Test] Extend tests/Feature/VenueMarketplace/VenueCatalogSchemaTest.php with AssetReservation, ControlDelegation, and DelegatedAssetResource schema assertions, including unique active reservation strategy, owner/request/asset integrity, opaque public IDs, indexes, revocation timestamps, and reversible migrations; accept when it fails only for missing US3 tables. (depends: T112)
- [x] T114 [P] [US3] [M:S] [Test] Create tests/Unit/VenueMarketplace/RentalStateMachineTest.php covering submitted, approved, rejected, cancelled, revoked, active, completed, and terminal transitions with actor/reason requirements; accept when impossible transitions are rejected before any persistence access. (depends: T112)
- [x] T115 [P] [US3] [M:S] [Test] Create tests/Unit/VenueMarketplace/ReservationConflictDetectorTest.php covering half-open intervals, adjacent windows, overlap directions, released reservations, multiple assets, sorted lock order, and venue-local/UTC normalization; accept when conflict results use safe opaque IDs and no participant-private details. (depends: T112)
- [x] T116 [US3] [M:S] [Test] Create tests/Integration/VenueMarketplace/ConcurrentRentalApprovalTest.php using two independent MySQL connections and a barrier to approve overlapping requests simultaneously; accept when exactly one commits, one returns reservation_conflict, no deadlock escapes as 500, and one active reservation exists. (depends: T113, T115)
- [x] T117 [P] [US3] [M:S] [Test] Create tests/Feature/VenueMarketplace/MultiAssetApprovalRollbackTest.php covering one conflicting line among several, deterministic sorted locks, stale request version, inactive publication, and simulated delegation-row failure; accept when no reservation or status change remains after any failed approval. (depends: T113, T115)
- [x] T118 [P] [US3] [M:S] [Test] Create tests/Feature/VenueMarketplace/RentalDecisionAuditTest.php covering approve/reject/cancel/revoke decisions, required sanitized reason, correlated owner/organizer rows, one owner-only read audit, rollback on either audit failure, and no loser audit claiming success; accept when business and audit writes share one transaction. (depends: T112)
- [x] T119 [P] [US3] [M:S] [Test] Create tests/Feature/VenueMarketplace/RentalDecisionAuthorizationTest.php covering owner participant plus rentals.approve, organizer cancel ownership, cross-tenant IDs, venue-owner/hybrid eligibility, platform role non-escalation, and terminal-state denial; accept when foreign requests return 404 before state details are evaluated. (depends: T112)
- [x] T120 [P] [US3] [M:S] [Test] Create tests/Feature/VenueMarketplace/RentalDecisionLifecycleTest.php covering approve, reject with reason, organizer cancel before approval, owner revoke before activation, reservation release, idempotent retry, stale version, and immutable request snapshots; accept when every participant sees the same canonical status through its own projection. (depends: T112, T114)
- [x] T121 [P] [US3] [M:H] [Test] Create tests/Feature/VenueMarketplace/RentalDecisionApiContractTest.php for approve, reject, cancel, and revoke operations in contracts/openapi.yaml; accept when permission, expected_version, idempotency, reason validation, conflict status/reason, and participant-safe response schemas are asserted. (depends: T112)
- [x] T122 [P] [US3] [M:H] [Test] Create resources/js/Pages/Tenant/Marketplace/__tests__/RentalDecision.test.tsx covering approver actions, reason dialog, expected-version conflict refresh, reservation conflict message, organizer cancel, disabled terminal actions, timeline, English/Arabic, RTL, focus, and axe; accept when it fails for missing decision UI only. (depends: T088)

### Implementation for User Story 3

- [x] T123 [US3] [M:S] Create database/migrations/2026_07_14_000008_create_asset_reservations_table.php exactly from data-model.md with owner tenant, asset, rental, UTC half-open window, active/released state, uniqueness/indexing for conflict scans, and reversible constraints; accept when reservation portions of T113 pass. (depends: T113)
- [x] T124 [US3] [M:S] Create database/migrations/2026_07_14_000009_create_control_delegations_and_resources_tables.php exactly from ControlDelegation and DelegatedAssetResource, initially pending and unprovisioned, with request/window/revocation/version/idempotency fields; accept when all remaining T113 and rollback assertions pass. (depends: T123)
- [x] T125 [P] [US3] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/AssetReservation.php with owner scope, half-open UTC casts, active/released state, participant-safe public references, and no global lookup; accept when T115 model assertions pass. (depends: T123)
- [x] T126 [P] [US3] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/ControlDelegation.php with owner scope, organizer/event/window/status/revocation/version casts, and pending lifecycle helpers; accept when no method treats scheduler status as authorization truth. (depends: T124)
- [x] T127 [P] [US3] [M:H] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/DelegatedAssetResource.php with delegation/rental-line/capability/opaque operational resource references and idempotent provision/release markers; accept when raw operational secrets cannot be persisted or serialized. (depends: T124)
- [x] T128 [US3] [M:S] Create app/Modules/VenueMarketplace/Domain/Services/RentalStateMachine.php implementing T114 with actor type, required reason, version, timestamp, and terminal-state rules; accept when all unit cases pass without database or HTTP dependencies. (depends: T114)
- [x] T129 [US3] [M:S] Create app/Modules/VenueMarketplace/Domain/Services/ReservationConflictDetector.php that normalizes half-open UTC intervals, sorts asset IDs before locking, and returns safe conflict metadata; accept when T115 passes and the service has no unlocked approval shortcut. (depends: T115, T125)
- [x] T130 [US3] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ApproveRentalAction.php using owner eligibility/permission, AuditedTransaction, request version lock, sorted venue_asset row locks, publication/availability revalidation, T129 conflict scan, all-line reservation insert, pending delegation/resource insert, state transition, and correlated audit; accept when T116–T121 approval cases pass. (depends: T101, T116, T117, T118, T119, T124, T126, T127, T128, T129)
- [x] T131 [P] [US3] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/RejectRentalAction.php with owner participant scope, submitted-only transition, required sanitized reason, expected version, idempotency, correlated audit, and after-commit notifications; accept when T118–T121 reject cases pass. (depends: T118, T119, T128)
- [x] T132 [P] [US3] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/CancelRentalAction.php with organizer participant scope, allowable pre-terminal states, expected version, reservation release when applicable, correlated audit, and after-commit notifications; accept when cancellation never releases another rental’s reservation. (depends: T118, T119, T125, T128)
- [x] T133 [US3] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/RevokeRentalAction.php with owner participant scope, required reason, immediate revoked_at inside the canonical transaction, status/version update, reservation release policy, correlated audit, and an after-commit release request hook; accept when pre-activation T118–T121 cases pass and late workers cannot clear revoked_at. (depends: T118, T119, T125, T126, T128)
- [x] T134 [US3] [M:S] Create app/Modules/VenueMarketplace/Application/Services/ReservationReleaseService.php using request-scoped owner predicates, row locks, idempotent release timestamps, and reason metadata; accept when cancel/revoke retries release only matching active rows and preserve history. (depends: T125, T132, T133)
- [x] T135 [US3] [M:S] Implement app/Modules/VenueMarketplace/Infrastructure/Audit/DatabaseMarketplaceAuditWriter.php using the existing Audit module’s AuditedTransaction and correlated tenant/platform APIs; accept when T043, T085, and T118 all prove synchronous rollback, participant sanitization, correlation, and no duplicate rows on idempotent replay. (depends: T029, T118)
- [x] T136 [P] [US3] [M:H] Create app/Modules/VenueMarketplace/Domain/Events/RentalDecisionEvents.php and register safe after-commit dispatch from T130–T133; accept when events contain opaque IDs/status/reason code only and never substitute for synchronous audit. (depends: T130, T131, T132, T133, T135)
- [x] T137 [P] [US3] [M:H] Create app/Modules/VenueMarketplace/Application/Listeners/SendRentalDecisionNotifications.php and resources/views/mail/marketplace/rental-decision.blade.php with participant-specific bilingual text, dedupe key, and safe deep links; accept when retries send once per actual transition and contain no owner-private fields. (depends: T136)
- [x] T138 [P] [US3] [M:H] Create app/Modules/VenueMarketplace/Http/Requests/ApproveRentalRequest.php, RejectRentalRequest.php, CancelRentalRequest.php, and RevokeRentalRequest.php with expected_version, idempotency key, and bounded reason fields exactly as contracts/openapi.yaml; accept when tenant/status/total inputs are impossible. (depends: T005)
- [x] T139 [US3] [M:H] Add decision methods to app/Modules/VenueMarketplace/Http/Controllers/ParticipantRentalController.php and register permission-protected named routes in app/Modules/VenueMarketplace/Routes/api.php; accept when the controller delegates all state logic and T121 passes. (depends: T130, T131, T132, T133, T138)
- [x] T140 [P] [US3] [M:S] Create app/Modules/VenueMarketplace/Application/Queries/RentalDecisionContextQuery.php returning only authorized action flags, expected version, safe conflict summary, and participant-specific timeline; accept when clients cannot infer foreign reservation details and flags match T128. (depends: T098, T125, T128)
- [x] T141 [US3] [M:S] Extend app/Modules/VenueMarketplace/Http/Resources/ParticipantRentalResource.php with T140 action flags, reservation-safe status, delegation pending summary, and correlated timeline entries; accept when owner/organizer projections differ only where the contract requires. (depends: T126, T140)
- [x] T142 [US3] [M:H] Extend app/Modules/AdminConsole/Application/ViewModels/TenantRentalViewModel.php and app/Modules/AdminConsole/Http/Controllers/TenantMarketplacePageController.php with T141 decision context; accept when page authorization cannot be bypassed by a forged Inertia request. (depends: T141)
- [x] T143 [P] [US3] [M:H] Create resources/js/Pages/Tenant/Marketplace/Components/RentalDecisionActions.tsx and DecisionReasonDialog.tsx with server action flags, expected-version refresh, idempotency keys, safe reason-code messages, focus management, and no optimistic terminal state; accept when action portions of T122 pass. (depends: T138, T142)
- [x] T144 [P] [US3] [M:H] Extend resources/js/Pages/Tenant/Marketplace/Rentals/Show.tsx with participant timeline, reservation/conflict-safe summaries, pending delegation status, decision actions, and immutable quote snapshot; accept when display and accessibility portions of T122 pass. (depends: T142, T143)
- [x] T145 [US3] [M:H] Add US3 decision, conflict, reason, timeline, cancellation, revocation, and notification strings to both locale files and extend database/factories/VenueMarketplaceFactory.php with overlapping requests and decisions through application actions; accept when T116–T122 and the full US3 suite pass. (depends: T137, T139, T144)

**Checkpoint**: Concurrent approvals are conflict-safe, all-or-nothing, tenant-scoped, and synchronously audited for both participants.

---

## Phase 6: User Story 4 — Use Time-Boxed Delegated Control (Priority: P4)

**Goal**: An approved rental grants only the selected capabilities on selected operational resources during the canonical window, with immediate synchronous denial after revocation or expiry.

**Independent test**: With scheduler workers stopped, attempt the same delegated operation before start, during the active window, immediately after owner revocation, and after end. It is allowed only during the active window when the actor also holds the ordinary operation permission, and only for the rented event, asset, and capability.

### Tests for User Story 4

- [x] T146 [P] [US4] [M:S] [Test] Create tests/Unit/Authorization/DelegatedControlGuardMatrixTest.php covering participant, actor permission, event, asset, capability, start/end boundary, revoked_at, rental/delegation status, missing context, forged opaque IDs, and owner ordinary-operation behavior; accept when authorization truth is derived from persisted canonical fields plus current time, never scheduler materialization. (depends: T145)
- [x] T147 [P] [US4] [M:S] [Test] Create tests/Feature/VenueMarketplace/DelegatedPermissionCompositionTest.php proving delegation never grants a base permission: acs.configure, kiosk.manage, badge.print, checkin.scan.submit, and equivalent capabilities must be held normally; accept when revoked/expired delegation denies even a wildcard organizer role if participant context is wrong. (depends: T145)
- [x] T148 [P] [US4] [M:S] [Test] Create tests/Contract/AccessControl/DelegatedAcsAssetPortTest.php covering idempotent provision/release, opaque references, event/window binding, selected zones/lanes only, deterministic failure, and no credential exposure; accept when it exercises only the port and its adapter contract fixtures. (depends: T024, T145)
- [x] T149 [P] [US4] [M:S] [Test] Create tests/Contract/Kiosk/DelegatedKioskAssetPortTest.php covering idempotent provision/release, selected kiosk only, event/window binding, safe degraded result, and pairing-secret exclusion; accept when a late provision request cannot extend the canonical window. (depends: T025, T145)
- [x] T150 [P] [US4] [M:S] [Test] Create tests/Contract/BadgePrinting/DelegatedPrinterAssetPortTest.php covering idempotent allocation/release, selected printer/capability only, event/window binding, print permission composition, and credential exclusion; accept when cross-event job creation is denied. (depends: T026, T145)
- [x] T151 [P] [US4] [M:S] [Test] Create tests/Contract/Scanning/DelegatedScannerAssetPortTest.php covering idempotent allocation/release, selected scanner only, event/window binding, scan permission composition, duplicate replay safety, and credential exclusion; accept when ordinary nondelegated scanning behavior remains unchanged. (depends: T027, T145)
- [x] T152 [P] [US4] [M:S] [Test] Create tests/Feature/VenueMarketplace/DelegationActivationTest.php covering pending before start, active materialization, selected capability provisioning, partial adapter failure as degraded, retry convergence, idempotent jobs, canonical end unchanged, and after-commit notifications; accept when no activation job can authorize by itself. (depends: T145)
- [x] T153 [US4] [M:S] [Test] Create tests/Integration/VenueMarketplace/DelegationClockRevocationTest.php with frozen time and stopped queue/scheduler workers covering one instant before start, exact start, exact end, owner revocation racing a delegated operation, and late release; accept when revoked_at causes immediate denial and no post-end write commits. (depends: T146, T147)
- [x] T154 [P] [US4] [M:S] [Test] Create tests/Feature/VenueMarketplace/DelegationRecoveryTest.php covering provision timeout, partial resources, retry/backoff, duplicate delivery, owner revoke during recovery, release failure, expired recovery, and operator-visible degraded reason; accept when retries never recreate access after revoke/end. (depends: T145)
- [x] T155 [P] [US4] [M:S] [Test] Create tests/Feature/AccessControl/MarketplaceDelegatedAcsIntegrationTest.php covering delegated zone/lane/rule changes plus emergency override, credential validation, anti-passback, tenant isolation, and ordinary owner paths; accept when delegation cannot weaken any existing safety invariant. (depends: T148)
- [x] T156 [P] [US4] [M:S] [Test] Create tests/Feature/Kiosk/MarketplaceDelegatedKioskIntegrationTest.php covering selected kiosk management, event binding, pairing/retire denial outside scope, revocation, expiry, tenant isolation, and unchanged owner path; accept when pairing credentials never cross the port. (depends: T149)
- [x] T157 [P] [US4] [M:S] [Test] Create tests/Feature/BadgePrinting/MarketplaceDelegatedPrinterIntegrationTest.php covering selected allocation, event-bound jobs, ordinary print permission, reprint policy, revocation, expiry, tenant isolation, and unchanged owner path; accept when no delegated request chooses an unallocated printer. (depends: T150)
- [x] T158 [P] [US4] [M:S] [Test] Create tests/Feature/Scanning/MarketplaceDelegatedScannerIntegrationTest.php covering selected allocation, event-bound scans, ordinary submit permission, offline replay timestamp handling, duplicate scan idempotency, revocation, expiry, and unchanged owner path; accept when replay after expiry cannot create a new authorized scan. (depends: T151)
- [x] T159 [P] [US4] [M:S] [Test] Create tests/Feature/VenueMarketplace/MarketplaceOnPremiseDelegationParityTest.php proving local database/cache/queue operation with external network disabled, same guard decisions, same recovery, and no federation/API dependency; accept when the test passes under the on-premise configuration profile. (depends: T145)
- [x] T160 [P] [US4] [M:H] [Test] Create tests/Feature/VenueMarketplace/DelegationApiContractTest.php for the participant delegation status operation in contracts/openapi.yaml; accept when response fields, action links, degraded/revoked reasons, participant projection, permissions, and foreign-ID 404 behavior are asserted. (depends: T145)
- [x] T161 [P] [US4] [M:H] [Test] Create resources/js/Pages/Tenant/Marketplace/__tests__/DelegationStatus.test.tsx covering pending/active/degraded/revoked/expired status, exact localized window, provisioned resource links, unavailable action explanations, owner revoke refresh, organizer operational links, RTL, keyboard, and axe; accept when it fails for missing UI only. (depends: T122)

### Implementation for User Story 4

- [x] T162 [US4] [M:S] Create database/migrations/2026_07_14_000010_add_marketplace_delegation_refs_to_acs_resources.php adding nullable opaque delegation, venue-asset, organizer-tenant, and event references plus scoped indexes to acs_zones and acs_lanes without a reverse foreign key to marketplace tables; accept when AccessControl rollback and isolation tests pass. (depends: T148, T155)
- [x] T163 [US4] [M:S] Create database/migrations/2026_07_14_000011_add_marketplace_delegation_refs_to_kiosks.php adding the same bounded opaque context to kiosks without storing marketplace models, credentials, or changing ordinary uniqueness; accept when Kiosk rollback and existing pairing tests pass. (depends: T149, T156)
- [x] T164 [P] [US4] [M:S] Create database/migrations/2026_07_14_000012_create_delegated_printer_allocations_table.php owned by BadgePrinting with organizer tenant, event, opaque delegation/asset/printer references, capability/window/released timestamps, and idempotency uniqueness; accept when printer allocation isolation and rollback tests pass. (depends: T150, T157)
- [x] T165 [P] [US4] [M:S] Create database/migrations/2026_07_14_000013_create_delegated_scanner_allocations_table.php owned by Scanning with organizer tenant, event, opaque delegation/asset/scanner references, capability/window/released timestamps, and idempotency uniqueness; accept when scanner allocation isolation and rollback tests pass. (depends: T151, T158)
- [x] T166 [US4] [M:S] Update app/Modules/AccessControl/Infrastructure/Persistence/Models/AcsZone.php, AcsLane.php, app/Modules/Kiosk/Infrastructure/Persistence/Models/Kiosk.php and create app/Modules/BadgePrinting/Infrastructure/Persistence/Models/DelegatedPrinterAllocation.php and app/Modules/Scanning/Infrastructure/Persistence/Models/DelegatedScannerAllocation.php with tenant/event/delegation casts and ordinary-path defaults; accept when no model imports VenueMarketplace. (depends: T162, T163, T164, T165)
- [x] T167 [US4] [M:S] Create app/Modules/VenueMarketplace/Application/Authorization/DatabaseDelegatedControlGuard.php implementing Authorization’s port with participant-scoped locked reads, base-permission callback, exact event/asset/capability match, start-inclusive/end-exclusive clock checks, and immediate revoked_at/status denial; bind it in VenueMarketplaceServiceProvider.php and accept when T146, T147, and T153 pass. (depends: T023, T126, T127, T146, T147, T153)
- [x] T168 [US4] [M:S] Create app/Modules/VenueMarketplace/Application/Services/DelegatedAssetProvisionerRegistry.php mapping supported controllable asset types to their operational ports and camera to no provisioner; accept when unknown type/capability combinations fail closed and registry resolution has no infrastructure model dependency. (depends: T020, T024, T025, T026, T027)
- [x] T169 [P] [US4] [M:S] Create app/Modules/AccessControl/Application/Services/DatabaseDelegatedAcsAssetPort.php and bind it in app/Modules/AccessControl/Providers/AccessControlServiceProvider.php; accept when T148 and T155 prove idempotent selected-resource provisioning/release and preserved emergency/credential/anti-passback behavior. (depends: T162, T166)
- [x] T170 [P] [US4] [M:S] Create app/Modules/Kiosk/Application/Services/DatabaseDelegatedKioskAssetPort.php and bind it in app/Modules/Kiosk/Providers/KioskServiceProvider.php; accept when T149 and T156 prove idempotent selected-kiosk provisioning/release with no pairing-secret exposure. (depends: T163, T166)
- [x] T171 [P] [US4] [M:S] Create app/Modules/BadgePrinting/Application/Services/DatabaseDelegatedPrinterAssetPort.php and bind it in app/Modules/BadgePrinting/Providers/BadgePrintingServiceProvider.php; accept when T150 and T157 prove event/window-bounded allocation and release. (depends: T164, T166)
- [x] T172 [P] [US4] [M:S] Create app/Modules/Scanning/Application/Services/DatabaseDelegatedScannerAssetPort.php and bind it in app/Modules/Scanning/Providers/ScanningServiceProvider.php; accept when T151 and T158 prove event/window-bounded allocation and release including offline replay rules. (depends: T165, T166)
- [x] T173 [P] [US4] [M:H] Create app/Modules/VenueMarketplace/Application/Services/CatalogOnlyCameraProvisioner.php that returns a stable not_controllable result and performs no write; accept when camera can be rented/cataloged but never produces a DelegatedAssetResource operational reference. (depends: T020, T168)
- [x] T174 [US4] [M:S] Update app/Modules/AccessControl/Application/Actions/CreateAcsZoneAction.php, UpdateAcsZoneAction.php, CreateAcsLaneAction.php, and CreateAcsRuleAction.php to accept optional delegated context and call DelegatedControlGuard before any mutation while preserving owner paths; accept when T155 and existing AccessControl suites pass. (depends: T167, T169)
- [x] T175 [US4] [M:S] Update app/Modules/Kiosk/Application/Actions/RegisterKioskAction.php, PairKioskAction.php, and RetireKioskAction.php to validate optional delegated context before mutation and prohibit delegated pairing/retirement unless explicitly contracted; accept when T156 and existing Kiosk suites pass. (depends: T167, T170)
- [x] T176 [US4] [M:S] Update app/Modules/BadgePrinting/Application/Actions/CreateBadgePrintJobAction.php and ReprintBadgeAction.php to validate optional delegated printer/event context plus ordinary print permission before enqueueing; accept when T157 and existing print/reprint suites pass. (depends: T167, T171)
- [x] T177 [US4] [M:S] Update app/Modules/Scanning/Application/Actions/SubmitScanAction.php to validate optional delegated scanner/event/capability context at the authoritative event timestamp without weakening idempotency, offline replay, or owner scans; accept when T158 and existing scanning suites pass. (depends: T167, T172)
- [x] T178 [US4] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ProvisionDelegatedAssetsAction.php that locks the delegation, rechecks canonical time/status/revoked_at, calls T168 adapters in deterministic order, persists safe resource results idempotently, and records active or degraded state without extending end time; accept when T152 and T154 provisioning cases pass. (depends: T168, T169, T170, T171, T172, T173)
- [x] T179 [US4] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ReleaseDelegatedAssetsAction.php that sets/observes denial truth before calling adapters, releases in reverse deterministic order, records retryable failures, and never restores delegation status; accept when T153 and T154 release/retry cases pass. (depends: T167, T178)
- [x] T180 [US4] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ActivateRentalAction.php that locks eligible approved rentals at start, refuses revoked/ended records, invokes T178 idempotently, transitions approved to active only under T128, and emits safe after-commit events; accept when T152 activation cases pass. (depends: T128, T152, T178)
- [x] T181 [US4] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ExpireRentalAction.php that synchronously makes expired delegations deny, transitions eligible rentals, releases reservations/resources idempotently, and supports recovery after worker downtime; accept when exact-end, late-worker, and duplicate-run cases in T153/T154 pass. (depends: T128, T134, T179)
- [x] T182 [US4] [M:S] Extend app/Modules/VenueMarketplace/Application/Actions/RevokeRentalAction.php so the canonical transaction commits revoked_at/status/audit before dispatching T179, and a failed/late release remains denied and retryable; accept when owner revocation racing an operation in T153 always denies the post-commit operation. (depends: T133, T167, T179)
- [x] T183 [P] [US4] [M:H] Create app/Modules/VenueMarketplace/Application/Jobs/ProvisionMarketplaceDelegation.php and ReleaseMarketplaceDelegation.php with unique idempotency keys, bounded retries/backoff, safe failure classification, and fresh canonical rechecks through actions; accept when T152/T154 duplicate and late delivery cases pass. (depends: T178, T179)
- [x] T184 [P] [US4] [M:H] Create app/Modules/VenueMarketplace/Console/Commands/ActivateMarketplaceRentals.php and ExpireMarketplaceRentals.php with chunking, skip-locked/concurrency-safe claiming, per-record failure isolation, and structured counts; accept when reruns converge and commands do not calculate authorization decisions. (depends: T180, T181)
- [x] T185 [US4] [M:H] Register minute-frequency activation/expiration commands and safe overlap locks in routes/console.php; accept when scheduler:list shows both tasks, on-premise configuration works locally, and stopped-scheduler T153 still denies by synchronous clock checks. (depends: T184)
- [x] T186 [US4] [M:S] Create app/Modules/VenueMarketplace/Application/Queries/GetParticipantDelegationQuery.php, app/Modules/VenueMarketplace/Http/Resources/ParticipantDelegationResource.php, and app/Modules/VenueMarketplace/Http/Controllers/ParticipantDelegationController.php, then register the contract route; accept when T160 returns participant-safe status/resources/actions and no operational secret. (depends: T127, T167, T178, T179)
- [x] T187 [US4] [M:S] Create app/Modules/VenueMarketplace/Application/Listeners/WriteDelegationAudit.php for provision/degraded/recovered/revoked/expired/release outcomes with correlated sanitized participant records and owner-only adapter diagnostics; accept when audit failure cannot re-authorize access and adapter secrets never enter audit payloads. (depends: T135, T178, T179, T180, T181, T182)
- [x] T188 [P] [US4] [M:H] Create app/Modules/VenueMarketplace/Application/Listeners/SendDelegationNotifications.php and resources/views/mail/marketplace/delegation-status.blade.php for active/degraded/revoked/expired participant-specific, deduplicated, after-commit messages; accept when messages use canonical window and safe deep links only. (depends: T180, T181, T182, T187)
- [x] T189 [US4] [M:H] Create resources/js/Pages/Tenant/Marketplace/Components/DelegationStatusPanel.tsx and OperationalResourceLinks.tsx, and extend Rentals/Show.tsx with server-provided action/link capabilities; accept when T161 functional/accessibility assertions pass and no client clock is treated as authorization truth. (depends: T186)
- [x] T190 [US4] [M:H] Add US4 delegation/provisioning/degraded/recovery/revocation/expiry/operational-link strings to both locale files, update navigation badges in resources/js/Components/DashboardLayout.tsx, and extend marketplace factories with active/degraded/revoked/expired scenarios; accept when T146–T161, operational regression suites, and full US4 suite pass. (depends: T174, T175, T176, T177, T185, T188, T189)

**Checkpoint**: Delegated control is narrow, time-boxed, permission-composed, revocation-safe, worker-independent for denial, and locally operable on-premise.

---

## Phase 7: User Story 5 — Review Settlement Activity and Manage Disputes (Priority: P5)

**Goal**: Completed rentals yield immutable factual statements, participant-visible streamed exports, append-only disputes, and audited platform outcomes without moving funds.

**Independent test**: Complete a rental and generate its statement twice; exactly one revision 1 statement exists with lines summing to the request snapshot. Both participants see the same totals, CSV streams without a shared file, an organizer opens one dispute, and a platform operator records an outcome/revision without editing the original statement or moving money.

### Tests for User Story 5

- [x] T191 [P] [US5] [M:S] [Test] Extend tests/Feature/VenueMarketplace/VenueCatalogSchemaTest.php with SettlementStatement, SettlementStatementLine, MarketplaceDispute, and MarketplaceDisputeEvent assertions for immutable revisions, participant keys, amount/currency fields, visibility, append-only events, one-open-dispute support, indexes, and reversible migrations; accept when it fails only for missing US5 tables. (depends: T190)
- [x] T192 [P] [US5] [M:S] [Test] Create tests/Unit/VenueMarketplace/SettlementStatementImmutabilityTest.php covering revision chains, immutable rental/event/window/line/total facts, integer money, exact sum, currency consistency, generated timestamp, no update/delete after issue, and no payment/payout/refund/penalty/VAT fields; accept when mutations fail at domain and persistence boundaries. (depends: T190)
- [x] T193 [P] [US5] [M:S] [Test] Create tests/Feature/VenueMarketplace/GenerateSettlementStatementTest.php covering completed/revoked outcomes, one initial statement per rental, idempotent concurrent generation, snapshot-based lines, zero/partial factual outcomes, exact totals, revision 1, retry, and no live-price recalculation; accept when two MySQL workers create one statement. (depends: T190)
- [x] T194 [P] [US5] [M:S] [Test] Create tests/Feature/VenueMarketplace/StatementFinancialBoundaryTest.php scanning schema, resources, CSV, notifications, actions, and copy for fund movement, payment, payout, refund, penalty, tax/VAT calculation, or accounting claims; accept when statements record facts only and funds_moved is always false/nonexistent as specified by the contract. (depends: T190)
- [x] T195 [P] [US5] [M:S] [Test] Create tests/Feature/VenueMarketplace/ParticipantStatementAccessExportTest.php covering owner/organizer list/detail, reports.view, opaque foreign IDs, same canonical totals, participant-safe fields, streamed CSV chunks, formula-injection escaping, UTF-8 Arabic, download audit, and no shared temporary file; accept when unrelated tenants get 404. (depends: T190)
- [x] T196 [P] [US5] [M:S] [Test] Create tests/Feature/VenueMarketplace/ReviseSettlementStatementTest.php covering platform-only linked revision, required reason, copied immutable facts, allowed factual corrections, recomputed exact total, original unchanged, latest pointer semantics, concurrent revision conflict, and participant visibility; accept when revisions are append-only. (depends: T190, T192)
- [x] T197 [P] [US5] [M:S] [Test] Create tests/Unit/VenueMarketplace/MarketplaceDisputeStateMachineTest.php covering open, under_review, resolved, rejected, actor/participant rules, required reason/outcome, one active dispute per statement/participant rule, terminal behavior, and append-only event visibility; accept when invalid transitions fail before persistence. (depends: T190)
- [x] T198 [P] [US5] [M:S] [Test] Create tests/Feature/VenueMarketplace/MarketplaceDisputeVisibilityTest.php covering participant opening, owner/organizer shared events, platform-internal notes, participant-visible notes, evidence metadata without unsafe files, resolution visibility, foreign IDs, and original statement immutability; accept when internal notes never serialize to tenant APIs. (depends: T190)
- [x] T199 [P] [US5] [M:S] [Test] Create tests/Feature/VenueMarketplace/PlatformMarketplaceAuthorizationAuditTest.php covering platform.marketplace.view versus platform.marketplace.disputes.manage, tenant-role non-escalation, audited rental/statement/dispute reads, audited notes/resolutions/revisions, reason requirements, and rollback on audit failure; accept when platform access never relies on a tenant context. (depends: T190)
- [x] T200 [P] [US5] [M:S] [Test] Create tests/Feature/VenueMarketplace/MarketplaceStatementDisputeIsolationTest.php seeding multiple owners, organizers, platform users, shared events, and opaque IDs; accept when every tenant query has participant predicates, every platform query uses explicit platform authorization, and aggregate counts cannot leak unrelated records. (depends: T190)
- [x] T201 [P] [US5] [M:H] [Test] Create tests/Feature/VenueMarketplace/StatementDisputeApiContractTest.php for all tenant statement/export/dispute and platform marketplace operations in contracts/openapi.yaml; accept when schemas, permissions, reason codes, pagination, CSV headers, note visibility, revisions, and conflict responses are asserted. (depends: T190)
- [x] T202 [P] [US5] [M:H] [Test] Create resources/js/Pages/Tenant/Marketplace/__tests__/StatementsDisputes.test.tsx covering statement list/detail/lines/totals/revisions, streamed export trigger, dispute open/timeline, visible notes/outcome, permission-hidden actions, errors, English/Arabic, RTL, keyboard, and axe; accept when it fails for missing UI only. (depends: T161)
- [x] T203 [P] [US5] [M:H] [Test] Create resources/js/Pages/Platform/Marketplace/__tests__/PlatformMarketplace.test.tsx covering overview filters, rental/statement/dispute detail, view/manage permission split, internal/participant-visible notes, resolution/revision dialogs, immutable original, audit reason, RTL, keyboard, and axe; accept when it fails for missing platform pages only. (depends: T031)

### Implementation for User Story 5

- [x] T204 [US5] [M:S] Create database/migrations/2026_07_14_000014_create_settlement_statements_tables.php exactly from SettlementStatement and SettlementStatementLine in data-model.md with owner/organizer/rental/revision chain, immutable snapshots, integer totals, currency, unique revision constraints, and no fund-movement fields; accept when statement schema/rollback assertions in T191 pass. (depends: T191, T192)
- [x] T205 [US5] [M:S] Create database/migrations/2026_07_14_000015_create_marketplace_disputes_tables.php exactly from MarketplaceDispute and MarketplaceDisputeEvent with participant/platform actors, visibility, append-only sequence, one-open-dispute enforcement strategy, timestamps, and no unsafe evidence blob; accept when remaining T191 assertions pass. (depends: T204)
- [x] T206 [P] [US5] [M:S] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/SettlementStatement.php and SettlementStatementLine.php with immutable guards, revision relation, participant scopes, Money casts, and blocked update/delete paths after issue; accept when T192 passes at model and database layers. (depends: T204)
- [x] T207 [P] [US5] [M:S] Create app/Modules/VenueMarketplace/Infrastructure/Persistence/Models/MarketplaceDispute.php and MarketplaceDisputeEvent.php with participant/platform scopes, ordered append-only events, visibility casts, and no event update/delete API; accept when isolation and immutability assertions in T197/T198 pass. (depends: T205)
- [x] T208 [US5] [M:S] Create app/Modules/VenueMarketplace/Domain/Services/MarketplaceDisputeStateMachine.php and SettlementRevisionPolicy.php implementing T192, T196, and T197 without HTTP/database dependencies; accept when all related unit tests pass with stable reason codes. (depends: T192, T196, T197)
- [x] T209 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/GenerateSettlementStatementAction.php using completed canonical rental snapshots, deterministic lines, exact Money sum, owner-scoped AuditedTransaction, unique idempotency key, revision 1, and no live catalog/payment logic; accept when T193 and T194 pass. (depends: T193, T194, T206)
- [x] T210 [P] [US5] [M:H] Create app/Modules/VenueMarketplace/Application/Jobs/GenerateRentalSettlementStatement.php with unique rental/revision key, bounded retry, fresh terminal-state check, and safe failure reporting; accept when duplicate/concurrent dispatch converges through T209. (depends: T209)
- [x] T211 [P] [US5] [M:H] Create app/Modules/VenueMarketplace/Console/Commands/FinalizeMarketplaceStatements.php to chunk eligible terminal rentals, skip already stated records, dispatch T210, and emit structured counts; accept when reruns are idempotent and per-rental failure does not abort the batch. (depends: T210)
- [x] T212 [US5] [M:H] Register FinalizeMarketplaceStatements in routes/console.php with overlap prevention and local queue compatibility; accept when scheduler:list shows it after expiration processing and on-premise tests require no external scheduler service. (depends: T185, T211)
- [x] T213 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Queries/ListParticipantStatementsQuery.php and GetParticipantStatementQuery.php with RentalParticipantScope, reports.view, latest/all revision semantics, stable pagination, and explicit line projection; accept when T195 and T200 prove owner/organizer totals match and foreign IDs return 404. (depends: T098, T195, T206)
- [x] T214 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Exports/StreamSettlementStatementCsv.php using chunked database reads, direct streamed response rows, UTF-8 BOM policy, localized headings, formula-injection escaping, stable line order, and audited download intent; accept when T195 proves no shared temporary file or cross-request residue exists. (depends: T195, T213)
- [x] T215 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/OpenMarketplaceDisputeAction.php with participant statement scope, bounded summary/evidence metadata, one-open-dispute lock, initial participant-visible event, immutable statement link, and correlated audit; accept when T197–T200 open cases pass. (depends: T197, T198, T199, T207, T208)
- [x] T216 [P] [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/StartMarketplaceDisputeReviewAction.php with platform manage permission, locked state transition, required reason, append-only participant-visible event, and tenant plus platform audit projections; accept when retry is idempotent and tenant roles cannot invoke it. (depends: T199, T207, T208)
- [x] T217 [P] [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/AddMarketplaceDisputeNoteAction.php with platform manage permission, explicit internal or participant visibility, bounded text, append-only sequence lock, and audited reason; accept when internal notes are absent from every tenant projection/export/notification. (depends: T198, T199, T207)
- [x] T218 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ResolveMarketplaceDisputeAction.php with platform manage permission, locked state transition, required outcome/reason, participant-visible final event, no fund movement, correlated audits, and after-commit notification; accept when T197–T200 resolution cases pass. (depends: T216, T217)
- [x] T219 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Actions/ReviseSettlementStatementAction.php with platform manage permission, original/revision locks, SettlementRevisionPolicy, immutable copied facts, exact recomputation, linked next revision, required reason, and correlated audit; accept when T196, T199, and T200 pass under concurrent revision attempts. (depends: T199, T206, T208)
- [x] T220 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Queries/PlatformMarketplaceQuery.php for explicit platform-authorized rental/statement/dispute lists/details, safe filters/counts, note visibility, stable pagination, and no implicit tenant scope; accept when T199/T200 prove view/manage separation and no unrelated private venue inventory joins. (depends: T199, T206, T207)
- [x] T221 [US5] [M:H] Create app/Modules/VenueMarketplace/Http/Requests/OpenDisputeRequest.php, app/Modules/VenueMarketplace/Http/Resources/ParticipantStatementResource.php and ParticipantDisputeResource.php, and app/Modules/VenueMarketplace/Http/Controllers/ParticipantStatementController.php; register tenant statement/detail/export/dispute routes and accept when tenant portions of T201 pass. (depends: T213, T214, T215)
- [x] T222 [US5] [M:S] Create app/Modules/VenueMarketplace/Http/Requests/PlatformDisputeActionRequest.php and ReviseStatementRequest.php, platform resources with explicit internal-note allowlists, and app/Modules/VenueMarketplace/Http/Controllers/PlatformMarketplaceController.php; register platform routes and accept when platform portions of T201 pass. (depends: T216, T217, T218, T219, T220)
- [x] T223 [P] [US5] [M:H] Create app/Modules/VenueMarketplace/Domain/Events/SettlementDisputeEvents.php with opaque IDs, status/revision/outcome codes, participant visibility, correlation, and dedupe metadata; accept when no event carries arbitrary evidence, internal notes to tenant listeners, or payment semantics. (depends: T209, T215, T216, T217, T218, T219)
- [x] T224 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Listeners/WriteSettlementDisputeAudit.php for generation/view/export/open/review/note/resolve/revise with owner, organizer, and platform sanitized projections as applicable; accept when T195, T196, T198, and T199 prove required audit, correlation, redaction, and transactional rollback. (depends: T135, T223)
- [x] T225 [P] [US5] [M:H] Create app/Modules/VenueMarketplace/Application/Listeners/SendSettlementDisputeNotifications.php and resources/views/mail/marketplace/dispute-status.blade.php with deduplicated after-commit participant messages, safe revision/outcome facts, and no internal notes or fund claims; accept when visibility tests pass. (depends: T218, T219, T223)
- [x] T226 [P] [US5] [M:H] Create app/Modules/AdminConsole/Application/ViewModels/TenantStatementViewModel.php and app/Modules/AdminConsole/Http/Controllers/TenantStatementPageController.php using participant resources and permission flags; accept when no ORM model or internal dispute note reaches Inertia props. (depends: T213, T221)
- [x] T227 [P] [US5] [M:S] Create app/Modules/AdminConsole/Application/ViewModels/PlatformMarketplaceViewModel.php and app/Modules/AdminConsole/Http/Controllers/PlatformMarketplacePageController.php with explicit platform view/manage authorization and audited read reason context; accept when tenant roles cannot render platform props. (depends: T220, T222, T224)
- [x] T228 [US5] [M:H] Register /tenant/marketplace/statements, statement detail, /platform/marketplace, and platform dispute detail in routes/web.php and add permission-aware entries to resources/js/Components/DashboardLayout.tsx; accept when route middleware and UI visibility match contracts/dashboard-contract.md. (depends: T018, T226, T227)
- [x] T229 [P] [US5] [M:H] Create resources/js/Pages/Tenant/Marketplace/Statements/Index.tsx and Show.tsx with revision chain, immutable factual lines/totals, CSV action, participant-safe status, responsive tables, and empty/error states; accept when statement portions of T202 pass. (depends: T228)
- [x] T230 [P] [US5] [M:H] Create resources/js/Pages/Tenant/Marketplace/Components/DisputePanel.tsx with server action flags, open form, visible append-only timeline, outcome/revision links, idempotent submission, focus/error handling, and no internal-note rendering path; accept when dispute portions of T202 pass. (depends: T228)
- [x] T231 [P] [US5] [M:H] Create resources/js/Pages/Platform/Marketplace/Index.tsx with authorized rental/statement/dispute filters, stable pagination, counts, explicit read-audit reason flow where required, and view/manage action separation; accept when overview portions of T203 pass. (depends: T228)
- [x] T232 [P] [US5] [M:H] Create resources/js/Pages/Platform/Marketplace/Disputes/Show.tsx with internal/participant note distinction, review/resolve/revise dialogs, immutable original/revision comparison, required reason, keyboard/focus handling, and no fund-movement controls; accept when detail portions of T203 pass. (depends: T228)
- [x] T233 [US5] [M:H] Add US5 statement/line/revision/export/dispute/note/visibility/outcome/platform strings to both locale files and update status labels; accept when T202/T203 pass in English and Arabic with RTL and no literal user-facing English in new pages. (depends: T229, T230, T231, T232)
- [x] T234 [US5] [M:H] Extend database/factories/VenueMarketplaceFactory.php with completed rentals, statements, linked revisions, open/reviewed/resolved disputes, and internal/participant events created only through application actions; accept when fixture public IDs and clock values are deterministic and the full US5 suite passes. (depends: T209, T215, T216, T217, T218, T219, T233)
- [x] T235 [US5] [M:S] Create app/Modules/VenueMarketplace/Application/Services/MarketplaceRetentionPolicy.php and config entries in config/marketplace.php for statement/dispute/audit-reference retention and evidence-metadata minimization, without deleting immutable records still under legal/contract retention; accept when policy tests added to tests/Feature/VenueMarketplace/MarketplaceRetentionPolicyTest.php prove tenant isolation and no shared-file cleanup risk. (depends: T002, T206, T207)

**Checkpoint**: All five stories work independently; factual settlement and disputes are immutable, tenant-safe, platform-audited, and explicitly outside payment processing.

---

## Phase 8: Polish and Cross-Cutting Validation

**Purpose**: Merge contracts, document the security/operations model, run cross-feature gates, and record reproducible evidence.

- [x] T236 [P] [M:S] Merge every Phase 6 operation/schema/security definition from specs/009-venue-marketplace/contracts/openapi.yaml into docs/api/openapi.yaml and update scripts/check-openapi-baseline.mjs only for intentional new Phase 6 surface; accept when npm run openapi:phase6 and the repository OpenAPI baseline check pass with no weakened existing rule. (depends: T235)
- [x] T237 [P] [M:H] Create docs/security/permissions-phase6.md documenting organization eligibility, all tenant/platform permissions, default roles, delegated base-permission composition, route/action mapping, deny behavior, and test references; accept when every permission in PermissionSeeder appears exactly once with its scope and owner. (depends: T017, T147)
- [x] T238 [P] [M:S] Create docs/security/audit-catalog-phase6.md documenting each owner/organizer/platform audited action/read, synchronous versus after-commit behavior, correlation, payload allowlist, redaction, failure semantics, and retention link; accept when every mutating API operation and sensitive platform/export read is mapped to an implemented audit event. (depends: T224, T235)
- [x] T239 [P] [M:S] Create docs/security/data-classification-phase6.md classifying private venue data, public catalog projection, operational bindings, immutable snapshots, participant data, dispute notes, audit metadata, exports, and prohibited secrets/fund data; accept when collection, exposure, storage, retention, and redaction rules match implementation. (depends: T235)
- [x] T240 [P] [M:H] Create docs/operations/venue-marketplace.md with migration order, seed commands, scheduler/queue commands, activation/expiration/statement recovery, degraded delegation handling, cache invalidation, export behavior, health checks, rollback limits, SaaS/on-premise parity, and no-federation statement; accept when commands are copy-paste runnable. (depends: T185, T212, T235)
- [x] T241 [P] [M:H] Update docs/demo-users-ar.md with least-privilege Arabic demo personas for organizer, venue owner, hybrid, venue approver, finance viewer, platform viewer, and dispute manager; accept when credentials remain development-only placeholders and role permissions match T237. (depends: T237)
- [x] T242 [P] [M:S] Expand tests/Architecture/Phase6ModuleBoundaryTest.php to scan the finished implementation for direct operational model imports, reverse marketplace model imports, secret-shaped fields, remote federation clients, payment modules, unapproved filesystem exports, and controller business logic; accept when the final boundary suite passes. (depends: T236)
- [x] T243 [P] [M:S] Create tests/Feature/VenueMarketplace/Phase6TenantIsolationSweepTest.php exercising every Phase 6 query/action/API with foreign owner, organizer, event, venue, asset, rental, delegation, statement, dispute, and opaque IDs; accept when foreign objects return safe 404/denial and query-log sampling proves participant/platform predicates. (depends: T235)
- [x] T244 [P] [M:S] Expand tests/Feature/VenueMarketplace/MarketplacePermissionMatrixTest.php to cover every Phase 6 named API/web route, organization type, seeded role, base delegated permission, platform view/manage split, and wildcard edge; accept when route-to-permission coverage is complete and deny-by-default remains true. (depends: T190, T235, T237)
- [x] T245 [P] [M:S] Create tests/Performance/VenueMarketplace/MarketplaceCatalogScaleTest.php seeding 10,000 published assets across tenants and asserting agreed query-count, p95 test-runtime, memory, pagination, filter-index, and no-private-join budgets from plan.md; accept when budgets are recorded and pass on MySQL without disabling isolation assertions. (depends: T093, T094)
- [x] T246 [P] [M:S] Create tests/Feature/VenueMarketplace/Phase6DeploymentParityTest.php running publish, request, approve, activate, revoke/expire, statement, export, and dispute scenarios under SaaS and on-premise configuration profiles with network disabled; accept when canonical behavior/reason codes match and only infrastructure configuration differs. (depends: T235)
- [x] T247 [P] [M:H] Create resources/js/Pages/Tenant/Marketplace/__tests__/Phase6AccessibilityI18n.test.tsx and resources/js/Pages/Platform/Marketplace/__tests__/Phase6AccessibilityI18n.test.tsx covering all pages at common breakpoints, Arabic RTL/LTR switch, keyboard-only flows, focus/error announcements, tables/dialogs, status non-color cues, and axe; accept when no serious violations or missing keys remain. (depends: T233)
- [x] T248 [P] [M:S] Create tests/Integration/VenueMarketplace/Phase6MigrationRollbackTest.php that migrates from the pre-Phase-6 schema, validates backfills/indexes/FKs, rolls back in reverse order on an empty Phase 6 dataset, and re-migrates; accept when existing tenant/operational records survive and production-data rollback limitations are documented in docs/operations/venue-marketplace.md. (depends: T235, T240)
- [x] T249 [M:S] Create app/Modules/Operations/Application/Health/VenueMarketplaceHealthCheck.php and wire structured metrics/logs for approval conflicts, activation lag, degraded provisioning, release retries, statement lag, dispute backlog, export failures, and audit failures through existing Operations conventions; accept when metrics contain opaque IDs/counts only and health checks do not expose tenant data. (depends: T240)
- [x] T250 [P] [M:S] Create tests/Feature/VenueMarketplace/Phase6EphemeralIsolationTest.php covering cache-key scoping, queued job payload minimization, idempotency isolation, streamed export cleanup, notification dedupe, and retry behavior across tenants/processes; accept when no shared cache/file/job state can disclose another tenant’s data. (depends: T094, T183, T210, T214, T225)
- [x] T251 [P] [M:S] Create tests/Security/VenueMarketplaceSecretRedactionTest.php scanning API, Inertia props, logs, exceptions, audits, notifications, jobs, cache, CSV, database columns, and serialized events for binding credentials, pairing secrets, private contacts, internal notes, or raw request bodies; accept when seeded canary secrets never escape their authorized storage. (depends: T238, T239)
- [x] T252 [M:H] Execute every manual scenario in specs/009-venue-marketplace/quickstart.md and append dated Validation Evidence entries with command, environment, result, and artifact/test reference; accept when US1–US5 independent tests, stopped-worker delegation checks, CSV review, Arabic/RTL, 10k catalog, and on-premise parity are evidenced. (depends: T236, T241, T243, T244, T245, T246, T247, T248, T249, T250, T251)
- [x] T253 [M:S] Run composer quality, php artisan test, npm run lint, npm run typecheck, npm test, npm run build, npm run openapi:phase6, and the repository OpenAPI check; fix only Phase 6-caused failures in their owning files and record exact outputs in specs/009-venue-marketplace/quickstart.md; accept when all gates pass without skipped/newly quarantined tests. (depends: T252)
- [x] T254 [M:S] Perform the final Phase 6 definition-of-done review against spec.md FR-001–FR-040, SC-001–SC-010, contracts, constitution gates, and scope exclusions; append a requirement-to-test/task evidence table to specs/009-venue-marketplace/quickstart.md and accept only when no payment, federation, tax, penalty, camera-control, or cross-tenant shortcut entered scope. (depends: T253)

---

## Dependencies and Execution Order

### Phase dependency graph

~~~text
Phase 1 Setup (T001-T010)
  -> Phase 2 Foundation (T011-T035)
      -> US1 Publish Inventory / MVP (T036-T078)
          -> US2 Discover and Request (T079-T112)
              -> US3 Decide and Reserve (T113-T145)
                  -> US4 Delegated Control (T146-T190)
                      -> US5 Statements and Disputes (T191-T235)
                          -> Polish and Validation (T236-T254)
~~~

The stories are independently demonstrable after their prerequisites, but their implementation order is intentionally sequential because later stories consume canonical records created by earlier stories. Do not start a later story by fabricating alternate persistence or bypassing earlier application actions.

### User-story dependencies

- **US1** depends on Foundation only and is the recommended MVP delivery boundary.
- **US2** depends on US1’s published projection, availability, pricing, and owner records.
- **US3** depends on US2’s canonical requests and immutable rental-line snapshots.
- **US4** depends on US3’s approved rental, reservations, pending delegation, and delegated-resource rows.
- **US5** depends on terminal canonical rental outcomes and participant scoping; it must not introduce payments or recalculate catalog prices.

### Within each story

1. Complete every [Test] task and confirm its expected failure.
2. Apply migrations and models in dependency order.
3. Implement domain policy/value objects before application actions.
4. Implement queries/actions and synchronous audit before controllers/routes.
5. Implement pages after stable resource/view-model contracts.
6. Finish locale/factory tasks and run the entire story suite before crossing its checkpoint.

---

## Parallel Execution Examples

Parallel work is allowed only for [P] tasks after their depends clauses are satisfied. A cheap model should still use separate clean contexts and merge one task at a time.

### Foundation example

~~~text
After T010:
  T011 organization eligibility tests
  T015 permission matrix tests
  T019 common value objects
  T020 capability registry
  T021 event-reader contract
  T023-T027 authorization/operational port contracts
  T029 audit contract
  T030-T031 shared UI/locales
~~~

### US1 example

~~~text
After T035:
  T036-T045 test files can be authored independently.
After their migrations:
  T050-T055 model files can be implemented independently.
After stable APIs/view models:
  T075 venue pages and T076 editors can proceed independently.
~~~

### US2 example

~~~text
After T078:
  T079-T088 test files can be authored independently.
After T090:
  T091 and T092 models can proceed independently.
After T108:
  T109 catalog UI, T110 quote UI, and T111 rental UI can proceed independently.
~~~

### US3 example

~~~text
After T112:
  T113-T115 and T117-T122 test files can be authored independently.
After T128 and participant/audit prerequisites:
  T131 reject and T132 cancel may proceed independently; T133 revoke remains sequential because US4 extends it.
After T142:
  T143 action controls and T144 detail presentation can proceed independently.
~~~

### US4 example

~~~text
After T145:
  T146-T152 and T154-T161 tests can be authored independently.
After T166:
  T169-T172 operational adapters can proceed in parallel in their owning modules.
After T180-T182:
  T183 jobs and T184 commands may proceed independently.
~~~

### US5 example

~~~text
After T190:
  T191-T203 test files can be authored independently.
After T208 and their test prerequisites:
  T216 review and T217 note actions may proceed independently.
After T228:
  T229-T232 tenant/platform pages can proceed independently.
~~~

---

## Implementation Strategy

### MVP first

1. Complete T001-T035.
2. Complete T036-T078.
3. Demonstrate US1’s independent test and run the isolation, audit, English/Arabic, and OpenAPI checks that apply to US1.
4. Deploy or review the inventory/catalog projection boundary before adding organizer requests.

### Incremental delivery

1. **MVP**: US1 publishes safe venue inventory.
2. **Increment 2**: US2 adds organizer discovery, quote, and idempotent request submission.
3. **Increment 3**: US3 adds transactional approval, reservations, rejection, cancellation, and revocation truth.
4. **Increment 4**: US4 adds operational provisioning and synchronous time/revocation enforcement.
5. **Increment 5**: US5 adds factual statements, exports, disputes, and platform review.
6. **Release gate**: T236-T254 merges contracts and proves cross-cutting quality.

### Sensitive-task review gates

- After T066, inspect a real catalog response and SQL query log for private-table/field leakage.
- After T097, inspect an idempotent replay and confirm immutable event/quote snapshots.
- After T130, run T116 at least ten times on MySQL and inspect reservation counts after every run.
- After T167 and T182, run T153 with workers stopped and inspect the committed revoked_at before any adapter release.
- After T219, compare original and revised statement rows directly and confirm no update timestamp/data changed on the original.
- Before T254, search the Phase 6 diff for payment, payout, refund, penalty, VAT, federation, remote marketplace, credential, secret, and camera control concepts and resolve every hit against the explicit scope.

---

## Task Summary

- **Total tasks**: 254
- **Setup**: 10 tasks (T001-T010)
- **Foundation**: 25 tasks (T011-T035)
- **US1**: 43 tasks (T036-T078)
- **US2**: 34 tasks (T079-T112)
- **US3**: 33 tasks (T113-T145)
- **US4**: 45 tasks (T146-T190)
- **US5**: 45 tasks (T191-T235)
- **Polish and validation**: 19 tasks (T236-T254)
- **Cheap-model-safe [M:H] tasks**: 97
- **Sensitive sequential [M:S] tasks**: 157
- **Test-first tasks**: 66
- **Explicit parallel opportunities [P]**: 159
- **Recommended MVP scope**: T001-T078
