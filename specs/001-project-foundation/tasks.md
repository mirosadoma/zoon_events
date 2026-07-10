# Tasks: Phase 0 Project Foundation and Governance

**Input**: Design documents from `specs/001-project-foundation/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`

**Tests**: Mandatory. Write the named test before its implementation task and confirm it fails for the expected missing behavior.

**Organization**: Tasks are grouped by user story. Every checkbox is one bounded implementation step suitable for a lower-cost model.

**Product Phase**: Foundation / Phase 0

## Progress Status

**Last verified**: 2026-07-03 (deep Phase 0 remediation and release-gate review)

| Metric | Value |
|--------|-------|
| Tasks complete | **187 / 187** (100%) |
| Phases complete | Setup, Foundational, US1-US7, Governance, Polish, and Release Readiness |
| Next unchecked task | None |
| Phase gate | **COMPLETE** - all Phase 0 tasks and release gates are verified |

**Completed phases**

- **Setup (T001–T012)**: Laravel 13 scaffold, Composer/npm toolchain, module registry, config skeletons, test suites, locale resources, route entry files, private storage roots.
- **Foundational (T013–T025)**: Shared value objects, request context pipeline, Problem Details, API envelopes, signed cursors, framework migrations, redaction utilities, architecture rules, MySQL test helpers, OpenAPI sync, versioned route groups.

**Verification snapshot**:

- 91 backend tests / 553 assertions pass against MySQL.
- React lint, typecheck, three component/accessibility tests, and production build pass.
- OpenAPI lint, sync, compatibility, and all 44 runtime operations pass.
- Fresh migration, repeated isolation/system seeding, dependency audits, documentation, and phase-boundary gates pass.

Checkboxes remain acceptance-based: implemented-but-partially-tested tasks are deliberately left unchecked.

## Execution Contract for Implementers

- Execute tasks in numeric order unless the task is marked `[P]` and all listed dependencies are complete.
- Read the referenced design artifact before editing. Do not invent behavior that contradicts `spec.md`, `data-model.md`, or `contracts/`.
- Do not mark a task complete until its inline acceptance check passes.
- Keep controllers thin; application actions own use cases, policies own authorization, and modules never query another module's persistence directly.
- Use MySQL 8.4 for database integration tests. SQLite may support isolated unit tests but cannot be the acceptance database.
- Do not add Docker, Sail, Redis, public registration, password reset, MFA flows, API-key/service-token issuance, events, tickets, payments, attendees, credentials, wallets, scanning, kiosks, badges, ACS, identity verification, venue marketplace, or production product adapters.
- Use synthetic test data only. Never commit `.env`, secrets, known production passwords, licensed dashboard-template files, or real personal information.
- When a task changes a public endpoint, keep `specs/001-project-foundation/contracts/openapi.yaml` authoritative and add/adjust conformance tests.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel after its stated dependencies because it owns different files.
- **[Story]**: Maps the task to a specification user story.
- Every task includes exact target paths, dependencies, and an observable acceptance check.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create the native Laravel/MySQL project, frontend toolchain, module skeleton, and test commands without Docker.

- [X] T001 Scaffold Laravel 13 into the repository root while preserving `.agents/`, `.specify/`, `specs/`, `all_plan.md`, and `Zonetec_PRD.md`; create `artisan`, `bootstrap/app.php`, `composer.json`, and Laravel default directories (depends: none; accept: `php artisan --version` reports Laravel 13 and preserved artifacts still exist).
- [X] T002 Install and configure Composer dependencies in `composer.json` and `composer.lock`: Laravel Sanctum, Laravel Fortify, Inertia Laravel, and Laravel Pint only as required by `plan.md` (depends: T001; accept: `composer validate --strict` and `composer install` succeed).
- [X] T003 Install React 19, TypeScript, Inertia 3, Tailwind CSS 4, Vite, shadcn/ui prerequisites, ESLint, Vitest, React Testing Library, and axe-core in `package.json` and `package-lock.json` (depends: T001; accept: `npm install` succeeds with no licensed dashboard-template package).
- [X] T004 [P] Register PSR-4 module namespaces and project scripts in `composer.json` for `App\Modules\` and commands `test`, `lint`, and `quality` (depends: T002; accept: `composer dump-autoload` resolves a class under `app/Modules/Shared`).
- [X] T005 [P] Create the module provider registry in `app/Providers/ModuleServiceProvider.php` and register it from `bootstrap/providers.php` for Shared, Identity, Tenancy, Authorization, Audit, FeatureFlags, Operations, Integrations, and AdminConsole (depends: T001; accept: `php artisan about` boots without provider errors).
- [X] T006 [P] Create configuration skeletons in `config/zonetec.php`, `config/tenancy.php`, `config/audit.php`, `config/feature-flags.php`, `config/health.php`, `config/observability.php`, and `config/integrations.php` without reading `env()` outside config files (depends: T001; accept: `php artisan config:cache` succeeds).
- [X] T007 Create `.env.example` and `.env.testing` templates for MySQL, database queues, private storage, deployment mode, locales, audit key identifiers, and local-safe telemetry; include no secret values (depends: T006; accept: every required key is documented and `git grep`/`rg` finds no real credential).
- [X] T008 [P] Configure PHP test suites and MySQL environment in `phpunit.xml`, `tests/TestCase.php`, and `tests/CreatesApplication.php` with Unit, Feature, Integration, Contract, Architecture, and Browser groups (depends: T001, T007; accept: `php artisan test --list-tests` loads all suites against the testing environment).
- [X] T009 [P] Configure frontend build, TypeScript, aliases, Tailwind, lint, and component tests in `vite.config.ts`, `tsconfig.json`, `components.json`, `eslint.config.js`, and `vitest.config.ts` (depends: T003; accept: `npm run typecheck` and an empty `npm run test` suite execute).
- [X] T010 [P] Configure English and Arabic application resources in `lang/en/foundation.php`, `lang/ar/foundation.php`, and `config/app.php` with `en` default and `ar` supported (depends: T001; accept: a smoke test resolves the same message key in both locales).
- [X] T011 Create route entry files `routes/api.php`, `routes/web.php`, `routes/health.php`, and `routes/console.php`, then register them in `bootstrap/app.php` without adding product routes (depends: T001; accept: `php artisan route:list` shows only Laravel/foundation placeholders).
- [X] T012 Create private tenant storage roots and ignore rules in `storage/app/private/tenants/.gitignore` and `.gitignore`; ensure generated audit exports cannot be served from `public/` (depends: T001; accept: storage write test succeeds and `public/` contains no tenant export path).

**Phase acceptance**: `composer validate --strict`, `php artisan about`, `php artisan config:cache`, `npm run typecheck`, and `npm run build` pass; no Docker/Sail file or excluded product module exists.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish shared contracts and framework plumbing required by every story.

**Critical**: Complete this phase before any user-story implementation.

- [X] T013 [P] Create immutable ULID, clock, deployment-mode, and locale value objects/contracts in `app/Modules/Shared/Domain/Identifiers/`, `app/Modules/Shared/Contracts/Clock.php`, and `app/Modules/Shared/Domain/DeploymentMode.php` (depends: T004, T005; accept: unit tests cover validation and deterministic test clock behavior).
- [X] T014 [P] Create correlation/request context objects in `app/Modules/Shared/Domain/Context/CorrelationId.php`, `RequestId.php`, and `RequestContext.php` (depends: T004; accept: invalid external correlation IDs are rejected and generated IDs are bounded to 64 characters).
- [X] T015 Create `AssignRequestContext` middleware in `app/Modules/Shared/Http/Middleware/AssignRequestContext.php` and register/terminate it in `bootstrap/app.php` so context is returned as `X-Correlation-ID` and cleared after every request (depends: T014; accept: consecutive test requests cannot inherit each other's context).
- [X] T016 [P] Create locale negotiation middleware in `app/Modules/Shared/Http/Middleware/ResolveLocale.php` with only `en` and `ar` accepted and stable language-neutral error codes (depends: T010, T014; accept: feature tests select valid locales and safely fall back for unsupported values).
- [X] T017 [P] Implement Problem Details error types and exception rendering in `app/Modules/Shared/Http/Problems/`, `app/Exceptions/FoundationException.php`, and `bootstrap/app.php` (depends: T014; accept: 401/403/404/409/422/429/503 responses match `contracts/api-standards.md` and contain no stack trace).
- [X] T018 [P] Implement API envelope/resource helpers in `app/Modules/Shared/Http/Resources/ApiResource.php`, `ApiCollection.php`, and `ApiMeta.php` (depends: T014; accept: unit tests produce snake_case envelopes with correlation metadata).
- [X] T019 [P] Implement signed cursor primitives in `app/Modules/Shared/Application/Pagination/SignedCursor.php` and `CursorPage.php` using deterministic `(created_at,id)` ordering and filter/scope binding (depends: T013; accept: tampered, expired, or cross-scope cursors fail validation).
- [X] T020 Create framework migrations in `database/migrations/2026_07_02_000000_create_framework_tables.php` for sessions, cache, jobs, job batches, failed jobs, and Sanctum tokens using MySQL-compatible indexes (depends: T002, T007; accept: `php artisan migrate:fresh --env=testing` creates all framework tables).
- [X] T021 [P] Create secret-redaction and safe-metadata utilities in `app/Modules/Shared/Support/Redaction/SecretRedactor.php` and `SafeMetadata.php` with recursive key/value and size limits (depends: T004; accept: unit fixtures remove passwords, tokens, keys, connection strings, and oversized nested values).
- [X] T022 [P] Create base module architecture rules in `tests/Architecture/ModuleBoundaryTest.php` forbidding cross-module Infrastructure imports, Eloquent models in React/Inertia props, and product module namespaces (depends: T005, T008; accept: a deliberate forbidden fixture makes the test fail).
- [X] T023 [P] Create reusable MySQL test helpers and synthetic identity builders in `tests/Support/MySqlTestCase.php`, `tests/Support/ActsAsFoundationUser.php`, and `tests/Support/AssertsProblemDetails.php` (depends: T008, T020; accept: a sample MySQL transaction test and problem assertion pass).
- [X] T024 Copy the authoritative OpenAPI document to generated-doc input at `docs/api/openapi.yaml` using portable `scripts/sync-openapi.php` without modifying `specs/001-project-foundation/contracts/openapi.yaml` (depends: T001; accept: `php scripts/sync-openapi.php --check` exits nonzero on drift and copied files hash identically).
- [X] T025 Register versioned `/api/v1`, explicit `/api/v1/platform`, and tenant `/api/v1/tenant` route groups plus middleware aliases in `bootstrap/app.php` and `routes/api.php`; missing tenant scope must never imply platform scope (depends: T015-T018; accept: route smoke tests distinguish the three groups).

**Phase acceptance**: shared unit/architecture tests pass, a fresh MySQL migration succeeds, standard errors/correlation work, and all later modules can register routes without bypassing the shared pipeline.

---

## Phase 3: User Story 1 — Prove Tenant Isolation (Priority: P1) MVP

**Goal**: Establish trusted tenant context across requests, persistence, jobs, events, files, caches, logs, and explicit privileged paths.

**Independent test**: Create two tenants and similarly shaped records; every missing, forged, inactive, and cross-tenant access attempt is rejected without target disclosure while valid same-tenant work succeeds.

### Tests for User Story 1

- [X] T026 [P] [US1] Write MySQL schema and tenant-ownership tests in `tests/Integration/MySql/TenantSchemaTest.php` for non-null `tenant_id`, tenant-first indexes, lifecycle checks, and cross-tenant uniqueness (depends: T023; accept: tests fail because tenant migrations do not yet exist).
- [X] T027 [P] [US1] Write HTTP tenant-context tests in `tests/Feature/Tenancy/ResolveTenantContextTest.php` covering missing, malformed, inactive, non-member, valid, and forged `X-Tenant-ID` (depends: T025; accept: tests fail at the unimplemented middleware).
- [X] T028 [P] [US1] Write persistence isolation tests in `tests/Integration/Security/TenantPersistenceIsolationTest.php` covering scoped queries, route binding, raw target IDs, and missing context (depends: T023; accept: cross-tenant fixtures currently demonstrate the missing guard).
- [X] T029 [P] [US1] Write async/context isolation tests in `tests/Integration/Queue/TenantJobContextTest.php` and `tests/Integration/Queue/TenantEventContextTest.php` for restore, inactive tenant failure, and cleanup (depends: T023; accept: tests fail because tenant-aware contracts are absent).
- [X] T030 [P] [US1] Write file/cache/log isolation tests in `tests/Integration/Security/TenantBoundaryChannelsTest.php` for tenant-prefixed paths/keys, structured tenant context, and no context leakage (depends: T023; accept: tests fail before boundary helpers exist).

### Implementation for User Story 1

- [X] T031 [P] [US1] Create users migration in `database/migrations/2026_07_02_000001_create_users_table.php` with ULID, canonical unique email, password hash, locale, lifecycle timestamps, and creator reference (depends: T020, T026; accept: migration constraints match `data-model.md`).
- [X] T032 [P] [US1] Create tenants and memberships migration in `database/migrations/2026_07_02_000002_create_tenants_and_memberships_tables.php` with ULIDs, lifecycle checks, non-null ownership, and tenant-first indexes (depends: T020, T026; accept: T026 schema assertions pass).
- [X] T033 [P] [US1] Implement `User`, `Tenant`, and `TenantMembership` Eloquent models in `app/Modules/Identity/Infrastructure/Persistence/Models/User.php` and `app/Modules/Tenancy/Infrastructure/Persistence/Models/` with guarded fields, enum casts, and no cross-module mutation helpers (depends: T031, T032; accept: model lifecycle/unit tests pass).
- [X] T034 [P] [US1] Create synthetic factories in `database/factories/UserFactory.php`, `TenantFactory.php`, and `TenantMembershipFactory.php` that never use real data or known production passwords (depends: T033; accept: factories create two independent tenants under MySQL).
- [X] T035 [US1] Implement immutable `TenantContext`, `TenantContextStore`, and resolution contract in `app/Modules/Tenancy/Domain/Context/` and `app/Modules/Tenancy/Contracts/TenantContextResolver.php` (depends: T033; accept: unit tests reject unset/rebinding/mismatched contexts).
- [X] T036 [US1] Implement `ResolveTenantContext` and `ClearTenantContext` middleware in `app/Modules/Tenancy/Http/Middleware/` and wire them after authentication but before binding/authorization in `bootstrap/app.php` (depends: T027, T035; accept: T027 passes and context clears on exceptions).
- [X] T037 [US1] Implement tenant-owned persistence contracts, defensive scope, and explicit query entry points in `app/Modules/Tenancy/Contracts/TenantOwned.php`, `Infrastructure/Persistence/Scopes/TenantScope.php`, and `Infrastructure/Persistence/Concerns/BelongsToTenant.php` (depends: T028, T035; accept: missing context fails closed and T028 passes).
- [X] T038 [US1] Implement tenant-safe route binding in `app/Modules/Tenancy/Http/Bindings/TenantScopedBinding.php` and register it in `app/Modules/Tenancy/Providers/TenancyServiceProvider.php` (depends: T036, T037; accept: foreign-tenant IDs and random IDs return identical 404 problems).
- [X] T039 [P] [US1] Implement `TenantAwareJob`, serialized `TenantJobContext`, and `RestoreTenantContext` job middleware in `app/Modules/Tenancy/Contracts/Queue/` and `Application/Queue/` (depends: T029, T035; accept: job tests restore trusted state, reject inactive tenants, and clear context).
- [X] T040 [P] [US1] Implement immutable `TenantAwareEvent` and listener context restoration in `app/Modules/Tenancy/Contracts/Events/` and `Application/Events/RunInTenantContext.php` (depends: T029, T035; accept: event/listener tests preserve and clear tenant/correlation context).
- [X] T041 [P] [US1] Implement tenant path, cache-key, and log-context factories in `app/Modules/Tenancy/Application/Boundaries/TenantStoragePath.php`, `TenantCacheKey.php`, and `TenantLogContext.php` (depends: T030, T035; accept: all outputs start with trusted tenant scope and contain no sensitive payload).
- [X] T042 [US1] Implement tenant lifecycle actions in `app/Modules/Tenancy/Application/Actions/CreateTenant.php` and `ChangeTenantStatus.php`, including active/suspended/deactivated transitions and explicit privileged actor/reason context (depends: T033, T035; accept: invalid transitions roll back and valid transitions emit domain events).
- [X] T043 [US1] Implement platform tenant requests/resources/controllers in `app/Modules/Tenancy/Http/Requests/Platform/`, `Http/Resources/TenantResource.php`, and `Http/Controllers/Api/V1/Platform/TenantController.php` for OpenAPI list/create/show/patch operations (depends: T019, T025, T038, T042; accept: operations match schemas/status codes and have no implicit global path).
- [X] T044 [US1] Register tenant routes in `app/Modules/Tenancy/Routes/api.php` and module provider in `app/Modules/Tenancy/Providers/TenancyServiceProvider.php` (depends: T036, T043; accept: `php artisan route:list --path=api/v1/platform/tenants` matches OpenAPI operation coverage).
- [X] T045 [US1] Create `FoundationIsolationSeeder` in `database/seeders/FoundationIsolationSeeder.php` with two tenants, users, and memberships for test/development only (depends: T034; accept: repeated seeding is deterministic/idempotent and production environment refuses this seeder).

**Story acceptance**: `php artisan test --group=tenant-isolation` passes for HTTP, MySQL, bindings, jobs, events/listeners, files, caches, and logs; cross-tenant target existence is never disclosed.

---

## Phase 4: User Story 2 — Govern Access with Least Privilege (Priority: P1)

**Goal**: Deliver admin-provisioned authentication and separate tenant/platform RBAC with deny-by-default policies, immediate revocation, and last-administrator protection.

**Independent test**: Create a no-permission role, grant one permission, assign/revoke it, and verify allow/deny outcomes remain isolated to one tenant; platform roles never combine with tenant roles.

### Tests for User Story 2

- [X] T046 [P] [US2] Write API and browser authentication tests in `tests/Feature/Auth/AuthenticationTest.php` for login, inactive users, throttling, token display-once, revocation, logout, and absent registration/reset/MFA/service-token routes (depends: T025, T033; accept: tests fail before auth endpoints exist).
- [X] T047 [P] [US2] Write RBAC permission-matrix tests in `tests/Feature/Authorization/PermissionMatrixTest.php` with one allow and one deny case for every seeded tenant/platform permission (depends: T045; accept: tests fail before RBAC persistence/evaluator exists).
- [X] T048 [P] [US2] Write cross-scope and immediate-revocation tests in `tests/Integration/Security/RbacIsolationTest.php` for two tenants, platform roles, expired assignments, and no permission cache (depends: T045; accept: tests expose absent enforcement).
- [X] T049 [P] [US2] Write last-administrator and lifecycle conflict tests in `tests/Feature/Authorization/LastAdministratorProtectionTest.php` (depends: T045; accept: ordinary removal currently fails the expected protection assertion).
- [X] T050 [P] [US2] Write OpenAPI endpoint tests for auth, users, memberships, tenant roles, platform roles, and assignments in `tests/Contract/IdentityAuthorizationApiTest.php` (depends: T024; accept: all documented operations are initially missing/failing).

### Implementation for User Story 2

- [X] T051 [US2] Create RBAC migration `database/migrations/2026_07_02_000003_create_authorization_tables.php` for permissions, tenant roles/permissions/assignments, and platform roles/permissions/assignments with scope checks and tenant-first indexes (depends: T032, T047; accept: migration passes MySQL constraints in `data-model.md`).
- [X] T052 [P] [US2] Implement permission and tenant-role Eloquent models in `app/Modules/Authorization/Infrastructure/Persistence/Models/Permission.php`, `TenantRole.php`, `TenantRolePermission.php`, and `TenantRoleAssignment.php` (depends: T051; accept: model tests reject platform permissions and mismatched tenant relationships).
- [X] T053 [P] [US2] Implement platform-role models in `app/Modules/Authorization/Infrastructure/Persistence/Models/PlatformRole.php`, `PlatformRolePermission.php`, and `PlatformRoleAssignment.php` (depends: T051; accept: platform assignments contain no tenant scope and enforce expiry/revocation).
- [X] T054 [US2] Implement `PermissionEvaluator` contract/service in `app/Modules/Authorization/Contracts/PermissionEvaluator.php` and `Application/PermissionEvaluatorService.php` with exact-key, active-assignment, tenant/platform separation, and deny-by-default behavior (depends: T052, T053; accept: T047/T048 evaluator assertions pass without caching).
- [X] T055 [US2] Implement `RequirePermission` middleware and Laravel Gate integration in `app/Modules/Authorization/Http/Middleware/RequirePermission.php` and `Providers/AuthorizationServiceProvider.php` (depends: T054; accept: protected routes return standard 403 and do not trust client-hidden controls).
- [X] T056 [P] [US2] Implement tenant/platform policies in `app/Modules/Authorization/Policies/` for tenant, user, membership, role, assignment, audit, health, feature flags, and configuration operations (depends: T054; accept: every permission key has explicit policy mapping).
- [X] T057 [US2] Create idempotent `PermissionCatalogSeeder` in `database/seeders/PermissionCatalogSeeder.php` with exactly the keys from `data-model.md` and immutable scope/risk metadata (depends: T051; accept: rerunning updates descriptions but neither duplicates nor silently renames keys).
- [X] T058 [US2] Create `SystemRoleSeeder` in `database/seeders/SystemRoleSeeder.php` for Platform Administrator, Security Auditor, Operations Viewer, and per-tenant Tenant Administrator with no grants to custom roles (depends: T052, T053, T057; accept: repeated runs are idempotent and custom roles remain empty).
- [X] T059 [US2] Create production-safe bootstrap command `app/Console/Commands/BootstrapPlatformAdministrator.php` requiring explicit email/password input or secret environment references and prohibiting known defaults (depends: T033, T053, T058; accept: production refuses missing values and command output never echoes password).
- [X] T060 [US2] Configure Fortify session authentication and Sanctum token authentication in `app/Modules/Identity/Providers/IdentityServiceProvider.php`, `config/fortify.php`, and `config/sanctum.php`, disabling registration/reset/email verification/MFA/teams (depends: T002, T046, T033; accept: route list contains only login/logout and documented token endpoints).
- [X] T061 [US2] Implement shared active-user credential validation, password hashing, and throttling in `app/Modules/Identity/Application/AuthenticateUser.php` and `Support/AuthenticationRateLimiter.php` (depends: T060; accept: API and browser paths produce identical safe failures and inactive-user denial).
- [X] T062 [US2] Implement token/me/tenant-choice requests/resources/controllers in `app/Modules/Identity/Http/Controllers/Api/V1/` and `app/Modules/Identity/Http/Resources/` for `/auth/token`, revoke-current, `/auth/me`, and `/auth/tenants` (depends: T036, T060, T061; accept: OpenAPI auth tests pass and plaintext token is returned once).
- [X] T063 [P] [US2] Define unconfigured future auth extension contracts in `app/Modules/Identity/Contracts/MfaAuthenticator.php`, `ApiKeyAuthenticator.php`, and `ServiceTokenAuthenticator.php` with fail-closed null implementations (depends: T004; accept: unit tests prove every unconfigured extension rejects authentication).
- [X] T064 [US2] Implement platform user provisioning/lifecycle actions and API in `app/Modules/Identity/Application/Actions/`, `Http/Requests/Platform/`, and `Http/Controllers/Api/V1/Platform/UserController.php` (depends: T055, T061; accept: list/create/patch operations validate lifecycle, permissions, idempotent intent, and OpenAPI responses).
- [X] T065 [US2] Implement membership actions with last-admin checks in `app/Modules/Tenancy/Application/Actions/CreateMembership.php` and `ChangeMembershipStatus.php` (depends: T054, T049; accept: cross-tenant user IDs are not disclosed and final administrator removal returns 409).
- [X] T066 [US2] Implement membership API requests/resources/controller in `app/Modules/Tenancy/Http/Controllers/Api/V1/Tenant/MembershipController.php` and adjacent `Requests/`/`Resources/` (depends: T036, T055, T065; accept: documented list/create/patch operations pass T050).
- [X] T067 [US2] Implement tenant role/permission/assignment actions in `app/Modules/Authorization/Application/Actions/Tenant/` with same-tenant checks, system-role protection, expiry, and final-admin invariant (depends: T052, T054, T049; accept: transactions reject mixed-tenant inputs and empty roles grant nothing).
- [X] T068 [US2] Implement tenant RBAC requests/resources/controllers in `app/Modules/Authorization/Http/Controllers/Api/V1/Tenant/` for role CRUD, replace permissions, assign, and revoke (depends: T055, T067; accept: all tenant RBAC OpenAPI operations and error branches pass T050).
- [X] T069 [US2] Implement platform role/permission/assignment actions in `app/Modules/Authorization/Application/Actions/Platform/` with privileged reason, expiry, system-role, and recoverable-admin protection (depends: T053, T054; accept: platform privilege is never represented by a null tenant filter).
- [X] T070 [US2] Implement platform RBAC requests/resources/controllers in `app/Modules/Authorization/Http/Controllers/Api/V1/Platform/` for role list/create/patch and assignment create/revoke (depends: T055, T069; accept: platform operations match OpenAPI and T050).
- [X] T071 [US2] Register identity, membership, and authorization routes/providers in `app/Modules/Identity/Routes/api.php`, `app/Modules/Authorization/Routes/api.php`, and module service providers (depends: T062, T064, T066, T068, T070; accept: route coverage contains every documented auth/user/RBAC operation and no excluded route).
- [X] T072 [US2] Emit immutable after-commit identity/tenant/RBAC domain events from actions in `app/Modules/Identity/Domain/Events/`, `app/Modules/Tenancy/Domain/Events/`, and `app/Modules/Authorization/Domain/Events/` (depends: T040, T064-T070; accept: event tests carry actor, scope, target, outcome intent, and correlation without sensitive values).

**Story acceptance**: authentication, permission matrix, cross-scope denial, immediate revocation, last-administrator, and OpenAPI tests pass; no public registration or production MFA/API-key/service-token flow exists.

---

## Phase 5: User Story 3 — Investigate Security-Sensitive Activity (Priority: P1)

**Goal**: Provide append-only, HMAC-verifiable, privacy-minimized audit evidence plus scoped search/export and fail-safe transactional behavior.

**Independent test**: Perform successful, denied, failed, and privileged actions; verify complete scoped evidence, tamper detection, rollback when audit storage fails, and no application mutation path.

### Tests for User Story 3

- [X] T073 [P] [US3] Write audit schema/immutability tests in `tests/Integration/MySql/AuditSchemaTest.php` for scope checks, indexes, no update/delete API, and retained actor/target identifiers (depends: T023; accept: tests fail before audit migrations).
- [X] T074 [P] [US3] Write sanitizer/privacy tests in `tests/Unit/Audit/AuditSanitizerTest.php` covering passwords, tokens, secrets, sensitive fields, raw IP, full user-agent, nested payloads, and redacted changed markers (depends: T021; accept: tests fail before sanitizer exists).
- [X] T075 [P] [US3] Write canonicalization/HMAC tests in `tests/Unit/Audit/AuditIntegrityTest.php` for deterministic payloads, key IDs, rotation, mutation detection, and unknown keys (depends: T013; accept: tests fail before integrity services).
- [X] T076 [P] [US3] Write transactional failure tests in `tests/Integration/MySql/AuditAtomicityTest.php` that force audit insert/HMAC failure during tenant/user/role changes (depends: T042, T064, T067; accept: tests show current changes are not yet rolled back by audit failure).
- [X] T077 [P] [US3] Write audit search/export isolation tests in `tests/Feature/Audit/AuditAccessTest.php` for tenant/platform scope, bounded dates/pages, cross-tenant IDs, export download reauthorization, and self-auditing (depends: T055; accept: tests fail before audit API/jobs).
- [X] T078 [P] [US3] Write audit catalog coverage test in `tests/Contract/AuditEventCatalogTest.php` requiring succeeded/denied/failed evidence for every action category in `data-model.md` (depends: T072; accept: missing listeners/writes are listed).

### Implementation for User Story 3

- [X] T079 [US3] Create append-only audit migration `database/migrations/2026_07_02_000004_create_audit_logs_table.php` with scope checks, tenant/date indexes, fingerprints, bounded JSON fields, integrity columns, and no update timestamp (depends: T073; accept: T073 schema assertions pass).
- [X] T080 [US3] Create audit export migration `database/migrations/2026_07_02_000005_create_audit_exports_table.php` with lifecycle checks, tenant-first indexes, expiry, safe failure code, and private storage path (depends: T079; accept: migration supports pending→processing→completed/failed→expired states).
- [X] T081 [P] [US3] Implement `AuditLog` and `AuditExport` models in `app/Modules/Audit/Infrastructure/Persistence/Models/` with guarded immutable fields and no update/delete service (depends: T079, T080; accept: application-level mutation attempts throw and tests can insert through writer only).
- [X] T082 [P] [US3] Implement typed audit enums/value objects in `app/Modules/Audit/Domain/` for scope, actor, outcome, channel, action, target, and change fields (depends: T004; accept: invalid catalog keys/scopes/outcomes fail before persistence).
- [X] T083 [US3] Implement change-summary and metadata sanitization in `app/Modules/Audit/Application/Sanitization/AuditSanitizer.php` using Shared redaction and allow lists (depends: T074, T082; accept: T074 passes with zero forbidden fixture leakage).
- [X] T084 [US3] Implement canonical payload serialization in `app/Modules/Audit/Application/Integrity/CanonicalAuditPayload.php` with stable field order, UTC microseconds, and normalized JSON (depends: T082, T083; accept: equivalent input orders hash identically).
- [X] T085 [US3] Implement versioned HMAC key ring and verifier in `app/Modules/Audit/Application/Integrity/AuditIntegrityService.php` and `config/audit.php` without logging key material (depends: T075, T084; accept: T075 passes including rotation/unknown-key cases).
- [X] T086 [US3] Implement `AuditWriter` contract/service in `app/Modules/Audit/Contracts/AuditWriter.php` and `Application/AuditWriterService.php` with synchronous insert, sanitized context, fingerprints, and HMAC evidence (depends: T081-T085; accept: one typed write persists complete verifiable evidence).
- [X] T087 [US3] Implement `AuditedTransaction` in `app/Modules/Audit/Application/AuditedTransaction.php` so required state and audit insert commit/rollback together (depends: T076, T086; accept: all T076 forced-failure cases leave no partial state).
- [X] T088 [US3] Replace direct transaction boundaries in tenant, identity, membership, and RBAC actions with `AuditedTransaction` and typed change summaries in their existing `Application/Actions/` files (depends: T087; accept: successful actions and failed writes satisfy T078 without full object snapshots).
- [X] T089 [US3] Implement authentication, authorization-denial, and domain-event audit listeners in `app/Modules/Audit/Application/Listeners/` and register them in `AuditServiceProvider.php` (depends: T072, T086; accept: successful/denied/failed auth and permission outcomes are audited synchronously where required).
- [X] T090 [US3] Implement tenant/platform audit search queries in `app/Modules/Audit/Application/Queries/` with date bounds, indexed filters, signed cursors, and explicit permission scope (depends: T019, T081; accept: T077 search cases pass at 10-million-row-compatible query shapes).
- [X] T091 [P] [US3] Implement audit API requests/resources/controllers in `app/Modules/Audit/Http/Controllers/Api/V1/Tenant/AuditController.php` and `Platform/AuditController.php` (depends: T055, T090; accept: tenant/platform search responses match OpenAPI and audit their own access).
- [X] T092 [US3] Implement `GenerateAuditExportJob` in `app/Modules/Audit/Application/Jobs/GenerateAuditExportJob.php` using tenant-aware middleware, streaming CSV, idempotent state transitions, and private tenant paths (depends: T039, T080, T090; accept: duplicate jobs create one final file and no cross-tenant rows).
- [X] T093 [US3] Implement export request/status/download controllers in `app/Modules/Audit/Http/Controllers/Api/V1/Tenant/AuditExportController.php` with reauthorization and audited download (depends: T055, T092; accept: T077 export/status/download cases pass).
- [X] T094 [P] [US3] Implement integrity command/job in `app/Console/Commands/VerifyAuditIntegrity.php` and `app/Modules/Audit/Application/Jobs/VerifyAuditIntegrityJob.php` for bounded recent/sample/full verification (depends: T039, T085; accept: controlled mutation yields `audit.integrity_failed` without exposing payloads).
- [X] T095 [P] [US3] Implement expired-export cleanup command/job in `app/Console/Commands/CleanupExpiredAuditExports.php` and `app/Modules/Audit/Application/Jobs/CleanupExpiredAuditExportsJob.php` (depends: T080, T092; accept: expired files/rows transition safely while audit evidence remains).
- [X] T096 [US3] Register audit routes, listeners, and schedules in `app/Modules/Audit/Routes/api.php`, `AuditServiceProvider.php`, and `routes/console.php` (depends: T089-T095; accept: route/schedule lists show only documented audit operations and maintenance jobs).

**Story acceptance**: audit unit/integration/contract tests pass; required changes roll back on audit failure; tampering is detected; exports remain private and tenant-scoped; forbidden sensitive fixtures never persist.

---

## Phase 6: User Story 4 — Consume Consistent Application Contracts (Priority: P2)

**Goal**: Enforce versioning, validation, safe errors, correlation, cursor pagination, idempotency, rate limits, and OpenAPI conformance for every foundation operation.

**Independent test**: Exercise representative valid, invalid, unauthorized, repeated, unavailable, and incompatible requests and compare them with the OpenAPI/API standards.

### Tests for User Story 4

- [X] T097 [P] [US4] Write OpenAPI lint and route-operation coverage tests in `tests/Contract/OpenApiDocumentTest.php` and `OpenApiRouteCoverageTest.php` (depends: T024, T071, T096; accept: tests list any undocumented or unimplemented public route).
- [X] T098 [P] [US4] Write response/error conformance tests in `tests/Contract/ApiResponseConformanceTest.php` for envelopes, Problem Details, correlation header, content types, locale-neutral codes, and no internal leakage (depends: T017, T018; accept: each operation class has success and principal failure assertions).
- [X] T099 [P] [US4] Write cursor/idempotency tests in `tests/Feature/Api/PaginationAndIdempotencyTest.php` for tampering, filter/scope binding, replay, request mismatch, in-progress collision, expiry, and raw-key absence (depends: T019; accept: idempotency cases fail before persistence/middleware).
- [X] T100 [P] [US4] Write rate-limit and compatibility tests in `tests/Feature/Api/RateLimitTest.php` and `tests/Contract/BackwardCompatibilityTest.php` (depends: T061; accept: auth/tenant/platform limits and breaking-contract fixture are detected).

### Implementation for User Story 4

- [X] T101 [US4] Create idempotency migration `database/migrations/2026_07_02_000006_create_idempotency_records_table.php` with hashed key, request digest, explicit scope strategy, safe response snapshot, and expiry indexes (depends: T099; accept: raw caller keys cannot be recovered from MySQL).
- [X] T102 [US4] Implement idempotency model/service in `app/Modules/Shared/Infrastructure/Persistence/Models/IdempotencyRecord.php` and `Application/Idempotency/IdempotencyService.php` (depends: T101; accept: replay/mismatch/concurrent cases are transaction-safe).
- [X] T103 [US4] Implement `RequireIdempotencyKey` middleware in `app/Modules/Shared/Http/Middleware/RequireIdempotencyKey.php` and apply it only to OpenAPI-marked writes (depends: T102; accept: T099 passes and `Idempotent-Replayed` is returned on safe replay).
- [X] T104 [US4] Apply deterministic cursor pagination and bounded filters to tenant, user, membership, role, audit, feature-flag, and configuration collection queries in their `Application/Queries/` classes (depends: T019, T090; accept: no unbounded list endpoint remains and page size caps at 100).
- [X] T105 [US4] Configure auth, tenant, platform, and privileged-export rate limiters in `app/Providers/AppServiceProvider.php` and attach them in module route files (depends: T036, T061; accept: T100 returns 429 with `Retry-After` and no account/target disclosure).
- [X] T106 [US4] Normalize exception-to-status mappings in `app/Modules/Shared/Http/Problems/FoundationProblemRenderer.php` for all baseline error codes from `contracts/api-standards.md` (depends: T017, T098; accept: no controller hand-builds divergent error JSON).
- [X] T107 [US4] Create safe OpenAPI documentation endpoint/controller in `app/Modules/Operations/Http/Controllers/ApiDocsController.php` and `routes/web.php`, disabled or authorized by environment policy (depends: T024; accept: generated docs use `docs/api/openapi.yaml` and expose no environment secrets).
- [X] T108 [P] [US4] Add Redocly lint and OpenAPI sync commands to `package.json` and `composer.json` quality scripts (depends: T024; accept: drift or invalid YAML fails the command).
- [X] T109 [US4] Implement contract compatibility checker in `app/Console/Commands/CheckApiCompatibility.php` with baseline file `docs/api/openapi-baseline.yaml` (depends: T097; accept: removing/renaming a required field or operation fails with a clear report).
- [X] T110 [US4] Resolve every failure from T097-T100 across module requests/resources/routes and freeze the validated contract copy in `docs/api/openapi-baseline.yaml` (depends: T101-T109; accept: all Contract and API groups pass with zero undocumented public route).

**Story acceptance**: OpenAPI lint, route coverage, response/error, pagination, idempotency, rate-limit, and compatibility suites pass; the implementation and contract are synchronized.

---

## Phase 7: User Story 5 — Operate a Safe Deployment and Foundation Dashboard (Priority: P2)

**Goal**: Provide validated configuration, health/telemetry, tenant configuration schemas, feature flags, and an accessible React foundation-only admin dashboard.

**Independent test**: Start both deployment profiles with valid/invalid config, degrade dependencies, evaluate tenant flags/configuration, and navigate every permitted dashboard state in English/LTR and Arabic/RTL with zero cross-tenant props.

### Tests for User Story 5

- [X] T111 [P] [US5] Write configuration validation/redaction tests in `tests/Feature/Operations/ConfigurationValidationTest.php` for missing, malformed, conflicting, unsafe production, cached config, and secret-bearing values (depends: T007; accept: tests fail before validator/command).
- [X] T112 [P] [US5] Write liveness/readiness/detailed-health tests in `tests/Feature/Health/HealthCheckTest.php` for database, queue, storage, audit key, dependency recovery, permission, and public redaction (depends: T055; accept: tests fail before health services).
- [X] T113 [P] [US5] Write telemetry propagation/redaction tests in `tests/Integration/Operations/TelemetryTest.php` for HTTP, jobs, events, adapters, exporter failure, tenant/correlation fields, and forbidden payloads (depends: T039, T040; accept: tests fail before telemetry contracts).
- [X] T114 [P] [US5] Write feature-flag unit/isolation/API tests in `tests/Unit/FeatureFlags/FeatureFlagEvaluatorTest.php` and `tests/Feature/FeatureFlags/FeatureFlagApiTest.php` covering types, defaults, overrides, expiry, lifecycle, cross-tenant denial, audit, and non-flaggable controls (depends: T055, T087; accept: tests fail before flag persistence).
- [X] T115 [P] [US5] Write tenant-configuration schema/API tests in `tests/Feature/Tenancy/TenantConfigurationTest.php` for valid/invalid branding/domain/residency/retention values, schema versions, read-only API, and cross-tenant denial (depends: T055; accept: tests fail before schema/model/query).
- [X] T116 [P] [US5] Write dashboard session/authorization/Inertia-prop tests in `tests/Feature/AdminConsole/DashboardAuthorizationTest.php` for login/logout, route absence, permission matrix, tenant switch cleanup, and zero cross-tenant data (depends: T060; accept: tests fail before dashboard routes/pages).
- [X] T117 [P] [US5] Write frontend component/accessibility tests in `resources/js/__tests__/foundation-dashboard.spec.ts` for light/dark/system themes, RTL/LTR, keyboard focus, reduced motion, responsive navigation, and required page states (depends: T009; accept: tests fail before design components).

### Implementation for User Story 5

- [X] T118 [US5] Complete typed configuration definitions and safe defaults in `config/zonetec.php`, `tenancy.php`, `audit.php`, `health.php`, `observability.php`, `feature-flags.php`, and `integrations.php` with ownership/sensitivity/restart documentation comments (depends: T006, T111; accept: application code contains no direct `env()` calls).
- [X] T119 [US5] Implement configuration validator and redacted result types in `app/Modules/Operations/Application/Configuration/ConfigurationValidator.php` and `ConfigurationIssue.php` (depends: T118, T021; accept: T111 validation cases identify keys but never values).
- [X] T120 [US5] Implement `zonetec:config:validate` command in `app/Console/Commands/ValidateZonetecConfiguration.php` and run it from readiness/CI (depends: T119; accept: valid config exits 0; unsafe/missing config exits nonzero before readiness).
- [X] T121 [P] [US5] Implement health-check contracts and checks in `app/Modules/Operations/Contracts/HealthCheck.php` and `Application/Health/Checks/` for database, queue, private storage, audit key, and config (depends: T119, T112; accept: each check returns safe category/status/duration/reason only).
- [X] T122 [US5] Implement aggregate live/ready/detailed health services and transition tracking in `app/Modules/Operations/Application/Health/HealthService.php` (depends: T121; accept: dependency state changes appear within 60 seconds and live remains separate).
- [X] T123 [US5] Implement health controllers/routes/resources in `app/Modules/Operations/Http/Controllers/HealthController.php`, `PlatformHealthController.php`, `Http/Resources/HealthResource.php`, and `routes/health.php` (depends: T055, T122; accept: T112 and OpenAPI health operations pass with no public details).
- [X] T124 [P] [US5] Define provider-neutral telemetry contracts in `app/Modules/Operations/Contracts/Telemetry/` for structured logs, metrics, traces, and error reporting plus local/null exporters in `Infrastructure/Telemetry/` (depends: T021, T113; accept: no cloud/network provider is mandatory).
- [X] T125 [US5] Implement telemetry context middleware/listeners in `app/Modules/Operations/Application/Telemetry/` for requests, jobs, events, and adapter calls with tenant/correlation propagation and redaction (depends: T039-T041, T124; accept: T113 propagation and sensitive-fixture assertions pass).
- [X] T126 [US5] Implement bounded telemetry exporter failure handling and detailed safe status in `app/Modules/Operations/Application/Telemetry/TelemetryPipeline.php` (depends: T124, T125; accept: simulated exporter failure cannot fail core request or synchronous audit).
- [X] T127 [US5] Create tenant configuration migration `database/migrations/2026_07_02_000007_create_tenant_configurations_table.php` with tenant/key uniqueness, schema version, JSON value, lifecycle checks, and tenant-first indexes (depends: T032, T115; accept: MySQL constraints match `data-model.md`).
- [X] T128 [US5] Define versioned schemas in `app/Modules/Tenancy/Domain/Configuration/Schemas/` for branding references, domain references, residency, and retention with secret/cross-tenant rejection (depends: T115; accept: all valid/invalid schema fixtures pass).
- [X] T129 [US5] Implement `TenantConfiguration` model and read query in `app/Modules/Tenancy/Infrastructure/Persistence/Models/TenantConfiguration.php` and `Application/Queries/ListTenantConfiguration.php` (depends: T127, T128, T037; accept: only current-tenant validated values are returned).
- [X] T130 [US5] Implement read-only platform schema and tenant configuration API controllers/resources in `app/Modules/Tenancy/Http/Controllers/Api/V1/Platform/ConfigurationSchemaController.php` and `Tenant/TenantConfigurationController.php` (depends: T055, T129; accept: T115/OpenAPI pass and no write/domain/asset/theme route exists).
- [X] T131 [US5] Create feature-flag migration `database/migrations/2026_07_02_000008_create_feature_flag_tables.php` with global definitions, tenant overrides, type/lifecycle fields, expiry, and tenant-first indexes (depends: T032, T114; accept: MySQL uniqueness/foreign-key checks pass).
- [X] T132 [P] [US5] Implement feature models/value objects in `app/Modules/FeatureFlags/Infrastructure/Persistence/Models/` and `Domain/` for definition, override, value type, lifecycle, and optional security class (depends: T131; accept: retired keys cannot be reused and values match declared type).
- [X] T133 [US5] Implement deterministic uncached evaluator in `app/Modules/FeatureFlags/Application/FeatureFlagEvaluatorService.php` with trusted scope, safe fallback, expiry, and hard-coded non-flaggable security controls (depends: T035, T132; accept: T114 evaluator/security cases pass).
- [X] T134 [US5] Implement platform definition and tenant override actions using audited transactions in `app/Modules/FeatureFlags/Application/Actions/` (depends: T087, T132, T133; accept: every create/change/retire/set/remove action requires permission, reason/idempotency intent, and audit evidence).
- [X] T135 [US5] Implement feature-flag requests/resources/controllers in `app/Modules/FeatureFlags/Http/Controllers/Api/V1/Platform/FeatureFlagController.php` and `Tenant/FeatureFlagController.php` (depends: T055, T103, T134; accept: all platform/tenant OpenAPI flag operations and isolation tests pass).
- [X] T136 [US5] Register FeatureFlags and Operations providers/routes/listeners in `app/Modules/FeatureFlags/Providers/FeatureFlagServiceProvider.php`, `Routes/api.php`, and Operations provider/route files (depends: T123, T126, T130, T135; accept: route list matches OpenAPI and providers boot under cached config).
- [X] T137 [US5] Initialize Inertia/React entry points and shadcn/ui primitives in `resources/js/app.tsx`, `resources/js/ssr.tsx`, `resources/js/components/ui/`, `resources/views/app.blade.php`, and `app/Http/Middleware/HandleInertiaRequests.php` (depends: T003, T009; accept: `npm run build` produces local assets with no CDN or licensed template dependency).
- [X] T138 [US5] Implement dashboard browser login/logout and route guards in `app/Modules/AdminConsole/Http/Controllers/Auth/SessionController.php`, `Http/Requests/LoginRequest.php`, and `routes/web.php` using T060 authentication (depends: T060, T061, T137; accept: T116 auth/absent-route assertions pass).
- [X] T139 [P] [US5] Implement project-owned design tokens, theme modes, RTL logical properties, reduced motion, and WCAG focus/contrast styles in `resources/css/app.css` and `resources/js/hooks/useTheme.ts` (depends: T137, T117; accept: theme/contrast/reduced-motion component tests pass).
- [X] T140 [P] [US5] Implement localized document direction and number/date formatting in `resources/js/hooks/useLocale.ts`, `resources/js/lib/formatters.ts`, and frontend locale files `resources/js/locales/en.ts`/`ar.ts` (depends: T010, T137; accept: Arabic mirrors direction while IDs/emails retain bidi isolation).
- [X] T141 [P] [US5] Implement reusable dashboard shell/state components in `resources/js/layouts/FoundationLayout.tsx` and `resources/js/components/foundation/` for sidebar, top bar, table, filter bar, status chip, skeleton, empty, error, forbidden, conflict, dialog, and queued status (depends: T139, T140; accept: T117 covers every required state at mobile/desktop widths).
- [X] T142 [US5] Implement explicit dashboard view models and authorization middleware in `app/Modules/AdminConsole/ViewModels/` and `Http/Middleware/AuthorizeDashboardPage.php`; never serialize Eloquent models (depends: T055, T137; accept: architecture test forbids model/query objects in Inertia props).
- [X] T143 [P] [US5] Implement platform Overview and Tenants pages/controllers in `resources/js/pages/platform/Overview.tsx`, `Tenants/`, and `app/Modules/AdminConsole/Http/Controllers/Platform/` using existing application actions (depends: T141, T142, T043; accept: list/create/lifecycle UI matches API outcomes and permission states).
- [X] T144 [P] [US5] Implement platform Users and Roles pages/controllers in `resources/js/pages/platform/Users/`, `Roles/`, and corresponding AdminConsole controllers (depends: T141, T142, T064, T070; accept: no UI mutation bypasses validators/policies/audited actions).
- [X] T145 [P] [US5] Implement platform Audit, Health/Telemetry, Feature Flags, and Configuration Reference pages/controllers in `resources/js/pages/platform/Audit/`, `Health/`, `FeatureFlags/`, `Configuration/`, and corresponding controllers (depends: T091, T123, T126, T130, T135, T141, T142; accept: pages expose only safe authorized data).
- [X] T146 [P] [US5] Implement tenant Overview, Memberships, Roles, Audit, Feature Flags, and read-only Configuration pages/controllers in `resources/js/pages/tenant/` and `app/Modules/AdminConsole/Http/Controllers/Tenant/` (depends: T066, T068, T091, T130, T135, T141, T142; accept: tenant switching clears props and cross-tenant targets render no data).
- [X] T147 [US5] Build permission-aware platform/tenant navigation in `resources/js/lib/navigation.ts` and `FoundationLayout.tsx` with no excluded product item or placeholder (depends: T143-T146; accept: navigation snapshot contains only `dashboard-contract.md` entries).
- [X] T148 [US5] Complete dashboard HTTP/Inertia and React component tests plus production build configuration in `tests/Feature/AdminConsole/`, `resources/js/__tests__/`, and `vite.config.ts` (depends: T138-T147; accept: `npm run lint`, `typecheck`, `test`, `build`, and `php artisan test --group=admin-dashboard` all pass).

**Story acceptance**: configuration, health, telemetry, feature-flag, tenant-configuration, dashboard authorization, RTL/LTR, theme, accessibility, responsive, and deployment-profile tests pass; no runtime internet, Docker, licensed dashboard template, or product-feature dependency exists.

---

## Phase 8: User Story 6 — Add External Systems through Governed Boundaries (Priority: P2)

**Goal**: Provide provider-neutral adapter contracts, registry, fake implementation, stable failure mapping, and a reusable contract suite without any production integration.

**Independent test**: Run one fake adapter through success, timeout, duplicate, unavailable, malformed, unknown-outcome, redaction, tenant-context, and offline/recovery cases.

### Tests for User Story 6

- [X] T149 [P] [US6] Write common adapter contract tests in `tests/Contract/Adapters/AdapterContractTestCase.php` and `FakeCapabilityAdapterTest.php` for every case in `contracts/adapter-contract.md` (depends: T023, T125; accept: tests fail before contracts/fake exist).
- [X] T150 [P] [US6] Write adapter tenant/isolation tests in `tests/Integration/Security/AdapterTenantContextTest.php` for missing, forged, inactive, platform, and cross-tenant contexts (depends: T035; accept: tests fail before invocation guard).
- [X] T151 [P] [US6] Write adapter retry/idempotency/telemetry tests in `tests/Integration/Adapters/AdapterResilienceTest.php` for pre-send timeout, unknown outcome, bounded retry/backoff, stable key, redaction, and offline recovery (depends: T102, T125; accept: tests fail before invoker).

### Implementation for User Story 6

- [X] T152 [P] [US6] Implement provider-neutral request/result/status/error/retry value objects in `app/Modules/Integrations/Domain/` exactly matching `adapter-contract.md` (depends: T004; accept: invalid/unknown combinations fail construction).
- [X] T153 [P] [US6] Implement `CapabilityAdapter` and capability metadata contracts in `app/Modules/Integrations/Contracts/CapabilityAdapter.php` and `AdapterDescriptor.php` (depends: T152; accept: interfaces contain no provider SDK or credential type).
- [X] T154 [US6] Implement `AdapterInvocationContext` and validator in `app/Modules/Integrations/Application/AdapterInvocationContext.php` and `ValidateAdapterContext.php` using trusted tenant/platform context only (depends: T035, T150, T152; accept: T150 passes).
- [X] T155 [US6] Implement explicit adapter registry/readiness rules in `app/Modules/Integrations/Application/AdapterRegistry.php` (depends: T153, T154; accept: unknown/disabled/testing-only adapters cannot claim production readiness or silently fall back).
- [X] T156 [P] [US6] Implement deterministic fake adapter in `app/Modules/Integrations/Testing/FakeCapabilityAdapter.php` with configurable success/failure/timeout/offline scenarios (depends: T153; accept: it performs no network access and T149 can drive every state).
- [X] T157 [US6] Implement adapter invoker in `app/Modules/Integrations/Application/AdapterInvoker.php` with timeout budget, bounded retry/backoff, idempotency, reconcile-first unknown outcomes, telemetry, and redaction (depends: T102, T125, T151, T155; accept: T151 passes without duplicate effects).
- [X] T158 [US6] Register only the fake adapter in non-production/testing through `app/Modules/Integrations/Providers/IntegrationServiceProvider.php` and `config/integrations.php` (depends: T155, T156; accept: production readiness fails when only fake is configured).
- [X] T159 [US6] Propagate adapter failures to stable application problem categories and audit events in `app/Modules/Integrations/Application/AdapterErrorMapper.php` and Audit listener registration (depends: T086, T152, T157; accept: provider-specific codes/payloads do not cross the boundary).
- [X] T160 [US6] Complete the reusable adapter contract suite documentation mapping in `docs/standards/adapter-authoring.md` (depends: T149-T159; accept: a new fake implementation can be validated using the documented command and checklist).

**Story acceptance**: adapter contract, tenant isolation, resilience, redaction, telemetry, and readiness tests pass; production has no product provider registered.

---

## Phase 9: User Story 7 — Enforce Delivery Governance (Priority: P3)

**Goal**: Make testing, documentation, scope, migrations, API contracts, and constitutional rules executable release gates.

**Independent test**: Introduce controlled broken test, stale OpenAPI copy, forbidden module/navigation, missing documentation link, and expired exception; each must fail the corresponding gate with a clear reason.

### Tests for User Story 7

- [X] T161 [P] [US7] Write phase-boundary tests in `tests/Architecture/PhaseBoundaryTest.php` forbidding Docker/Sail and excluded backend/frontend module, route, migration, table, adapter, and navigation names (depends: T022, T147; accept: controlled forbidden fixtures fail).
- [X] T162 [P] [US7] Write documentation-link/ownership tests in `tests/Contract/DocumentationCompletenessTest.php` for every public contract, config key, permission, audit event, runbook, and ADR (depends: T057, T078; accept: missing/stale references are listed).
- [X] T163 [P] [US7] Write deployment-parity tests in `tests/Integration/Operations/DeploymentParityTest.php` running SaaS/on-premise profiles without outbound network (depends: T126, T148, T158; accept: differing security/contract behavior fails).
- [X] T164 [P] [US7] Write governance-exception validator tests in `tests/Unit/Governance/GovernanceExceptionTest.php` for owner, rule, risk, controls, approval, expiry/remediation, and expired status (depends: T008; accept: incomplete/expired fixtures fail).

### Implementation for User Story 7

- [X] T165 [P] [US7] Write foundation architecture/module ownership documentation in `docs/architecture/foundation.md` and `docs/architecture/module-boundaries.md` with allowed dependency direction and dashboard/API parity (depends: T022; accept: T162 finds owners/links for every module).
- [X] T166 [P] [US7] Write tenant isolation and RBAC standards in `docs/standards/tenant-isolation.md`, `docs/standards/rbac.md`, and `docs/standards/permission-catalog.md` including safe/unsafe code examples (depends: T045, T057; accept: catalogs match seeders/tests exactly).
- [X] T167 [P] [US7] Write audit and PDPL data documentation in `docs/standards/audit-event-catalog.md`, `docs/standards/data-classification.md`, and `docs/operations/retention-residency.md` (depends: T078, T096, T130; accept: every audit key and Phase 0 data class has purpose/access/retention/residency treatment).
- [X] T168 [P] [US7] Write configuration and operations references in `docs/operations/configuration.md`, `health-observability.md`, `queue-scheduler.md`, and `backup-restore.md` (depends: T120, T123, T126, T096; accept: native Windows/Linux commands contain no Docker step or secret value).
- [X] T169 [P] [US7] Write API and dashboard standards in `docs/standards/api.md`, `docs/standards/dashboard-design-system.md`, and `docs/standards/localization-accessibility.md` (depends: T110, T148; accept: docs map to OpenAPI/dashboard contract and Arabic/English behavior).
- [X] T170 [P] [US7] Write migration/rollback and contributor/review guidance in `docs/operations/migrations-rollbacks.md`, `docs/CONTRIBUTING.md`, and `docs/review-checklist.md` (depends: T020, T031, T051, T079-T080, T101, T127, T131; accept: fresh/upgrade/restore paths and review gates are explicit).
- [X] T171 [P] [US7] Create ADRs in `docs/architecture/decisions/` for Laravel/MySQL, shared-schema tenancy, custom RBAC, HMAC audit, database queue, OpenAPI-first, React/Inertia dashboard, feature flags, and no Docker (depends: T165-T170 and `research.md`; accept: each ADR records decision, rationale, alternatives, and date).
- [X] T172 [US7] Implement `zonetec:docs:check` in `app/Console/Commands/CheckDocumentation.php` using T162 rules (depends: T162, T165-T171; accept: stale/missing catalog or broken internal link exits nonzero).
- [X] T173 [US7] Implement `zonetec:phase-boundary:check` in `app/Console/Commands/CheckPhaseBoundary.php` using T161 rules (depends: T161; accept: forbidden Docker/product fixture exits nonzero and clean foundation exits zero).
- [X] T174 [US7] Create non-container CI workflow in `.github/workflows/ci.yml` that starts native MySQL, installs Composer/npm dependencies, validates config/migrations, runs backend/frontend tests, Pint, type/lint/build, OpenAPI sync/lint/compatibility, docs, and phase-boundary gates (depends: T108-T110, T148, T172, T173; accept: no `services:` container/Docker action and every controlled failure blocks CI).
- [X] T175 [US7] Create governance exception register/template in `docs/governance/exceptions.md` and `docs/governance/exception-template.md` with automated expiry check wired into `zonetec:docs:check` (depends: T164, T172; accept: no active expired exception passes).
- [X] T176 [US7] Run and fix T161-T175 governance tests/scripts across the repository (depends: all US7 tasks; accept: a compliant change passes and each controlled noncompliant fixture fails only its expected gate).

**Story acceptance**: architecture/scope, documentation, parity, exception, CI, OpenAPI, migration, backend, and frontend gates all pass; deliberately broken fixtures are blocked with actionable output.

---

## Phase 10: Polish and Cross-Cutting Release Readiness

**Purpose**: Validate the complete Phase 0 as one safe, documented, portable foundation.

- [X] T177 [P] Run dependency/security audits and record approved results in `docs/release/dependency-audit.md` using `composer audit` and `npm audit`; remediate critical/high findings in `composer.lock`/`package-lock.json` (depends: T176; accept: zero unresolved critical/high dependency finding).
- [X] T178 [P] Add performance fixtures/tests in `tests/Integration/Performance/FoundationPerformanceTest.php` for 1,000 tenants, 100,000 users, representative 10-million-row audit query plans, and 100 concurrent foundation requests without committing huge fixtures (depends: T090, T104, T148; accept: plans use intended indexes and plan targets are met or documented as blocking).
- [X] T179 [P] Add security regression suite in `tests/Integration/Security/FoundationSecurityRegressionTest.php` for SQL injection, XSS escaping, CSRF, security headers, mass assignment, cursor/idempotency tampering, secret leakage, and dashboard props (depends: T110, T148; accept: all attacks fail safely).
- [X] T180 Verify fresh install, migrations T020/T031/T032/T051/T079/T080/T101/T127/T131, seeders T045/T057/T058, safe test rollback, and upgrade rehearsal; record output in `docs/release/migration-evidence.md` (depends: T020, T031, T032, T045, T051, T057-T058, T079-T080, T101, T127, T131; accept: MySQL fresh/seed succeeds twice and rollback guidance causes no undocumented data loss).
- [X] T181 Verify native SaaS/on-premise operation, queue worker, scheduler, compiled dashboard, private storage, and blocked outbound network; record evidence in `docs/release/deployment-parity.md` (depends: T163, T174; accept: core behavior is equivalent and no runtime CDN/cloud dependency exists).
- [X] T182 Run full audit privacy/integrity/atomicity/export verification and record sanitized evidence in `docs/release/audit-evidence.md` (depends: T096, T177; accept: zero forbidden fixture and every catalog category has required outcome coverage).
- [X] T183 Run complete tenant-isolation and RBAC matrices and record counts in `docs/release/security-evidence.md` (depends: T045, T072, T179; accept: 100% cross-tenant attempts denied and every permission has allow/deny evidence).
- [X] T184 Run dashboard accessibility, Arabic/RTL, English/LTR, responsive, theme, forbidden-state, and cross-tenant render checks; record results in `docs/release/dashboard-evidence.md` (depends: T148, T179; accept: zero unauthorized prop/render and all required states pass).
- [X] T185 Run OpenAPI lint/sync/compatibility/route coverage and freeze the Phase 0 contract baseline in `docs/api/openapi-baseline.yaml` (depends: T110, T176; accept: zero warning, drift, undocumented route, or incompatible unapproved change).
- [X] T186 Execute every command in `specs/001-project-foundation/quickstart.md` and update only inaccurate validation instructions in that file (depends: T177-T185; accept: every command/result is reproduced without Docker).
- [X] T187 Create final Phase 1 readiness report in `docs/release/phase-0-readiness.md` linking test, migration, API, security, audit, dashboard, operations, documentation, and exception evidence (depends: T186; accept: all mandatory evidence is linked, no expired exception exists, and no excluded feature is present).

---

## Dependencies and Execution Order

### Phase Dependencies

| Phase | Tasks | Depends on | Completion gate |
|-------|-------|------------|-----------------|
| Setup | T001-T012 | None | Laravel/npm build and native config work without Docker |
| Foundational | T013-T025 | Setup | Shared pipeline, MySQL framework tables, architecture rules pass |
| US1 Tenant Isolation | T026-T045 | Foundational | Full tenant-isolation matrix passes |
| US2 RBAC/Auth | T046-T072 | US1 | Auth and permission matrix pass |
| US3 Audit | T073-T096 | US1 and US2 actions/events | Audit atomicity/integrity/privacy/export pass |
| US4 API Standards | T097-T110 | US1-US3 endpoints | OpenAPI/conformance/idempotency gates pass |
| US5 Operations/Dashboard | T111-T148 | US1-US4 contracts/security | Config, health, flags, telemetry, dashboard pass |
| US6 Adapters | T149-T160 | US1 context, US3 audit, US5 telemetry | Common fake adapter suite passes |
| US7 Governance | T161-T176 | Desired implemented stories | CI/docs/scope/parity gates pass |
| Release Readiness | T177-T187 | All selected stories | Quickstart and readiness evidence approved |

### User Story Dependency Graph

```text
Setup -> Foundational -> US1 Tenant Isolation
                              |
                              v
                         US2 Auth/RBAC
                         /           \
                        v             v
                   US3 Audit      US6 Adapters*
                        |
                        v
                   US4 API Standards
                        |
                        v
             US5 Operations + Dashboard
                        |
                        v
                   US7 Governance
                        |
                        v
                Release Readiness

* US6 also needs telemetry from US5 before its final resilience/observability tasks.
```

### Critical Within-Story Ordering

- Tests precede migrations/services/controllers in every story.
- Migrations precede models; models and contracts precede application actions.
- Application actions/policies precede controllers and dashboard pages.
- Audit writer/transaction tasks T086-T088 precede final security-sensitive integration.
- OpenAPI conformance T097-T110 precedes dashboard/API parity completion.
- Dashboard shared shell T137-T142 precedes page groups T143-T147.
- Governance commands/CI depend on the gates they invoke.

## Parallel Opportunities

- Setup: T004-T010 may run in parallel after their listed bootstrap dependencies.
- Foundational: T013, T014, T016-T019, T021-T024 own different files.
- US1: test tasks T026-T030 run in parallel; migrations T031/T032 and boundary helpers T039-T041 can parallelize at their gates.
- US2: T046-T050 tests run in parallel; tenant/platform model paths T052/T053 and policies/seeding can split after migration.
- US3: T073-T078 tests run in parallel; API, integrity job, and cleanup job split after writer/query foundations.
- US4: T097-T100 tests and T108 tooling can parallelize.
- US5: T111-T117 tests run in parallel; health, telemetry, configuration, flags, design tokens, locale, and page groups have separate paths.
- US6: T149-T151 tests and T152/T153/T156 implementations can parallelize.
- US7: T161-T164 tests and T165-T171 documentation files can parallelize.
- Polish: T177-T179 can run in parallel; evidence tasks T180-T185 can split after fixes.

## Parallel Execution Examples

### US1

```text
T026 Tenant schema tests
T027 HTTP tenant-context tests
T028 Persistence isolation tests
T029 Job/event context tests
T030 File/cache/log boundary tests
```

### US2

```text
T046 Authentication tests
T047 Permission matrix
T048 Cross-scope revocation
T049 Last-administrator protection
T050 OpenAPI endpoint tests
```

### US5

```text
Backend lane: T118-T136 (configuration, health, telemetry, schemas, flags)
Frontend foundation lane: T137-T142 (Inertia, design system, view models)
Page lanes after T142: T143, T144, T145, T146
```

## Implementation Strategy

### MVP First

1. Complete Setup T001-T012.
2. Complete Foundational T013-T025.
3. Complete US1 T026-T045.
4. Stop and validate the entire tenant-isolation matrix.

This MVP proves the non-negotiable tenant boundary before adding authorization, auditing, UI, or integrations.

### Recommended Sequential Delivery

1. Tenant isolation (US1)
2. Authentication and RBAC (US2)
3. Auditability (US3)
4. API standards/conformance (US4)
5. Operations, feature flags, configuration, and dashboard (US5)
6. Adapter boundary (US6)
7. Governance and release gates (US7)
8. Cross-cutting readiness evidence

### Lower-Cost Model Handoff Pattern

For each task:

1. Provide the model only the current task line plus its dependency artifacts/files.
2. Require it to run the inline acceptance check.
3. Review the diff for scope and module-boundary violations.
4. Mark the checkbox only after the check passes.
5. Continue to the next numeric task; do not batch unrelated tasks.

## Notes

- `[P]` never overrides an explicit dependency.
- Test tasks must fail for the intended missing behavior before implementation.
- Exact paths may contain multiple files only when they form one inseparable contract/action/controller concern.
- Do not “helpfully” scaffold excluded product navigation, tables, models, events, or adapters.
- Any constitutional exception requires an approved, unexpired entry in `docs/governance/exceptions.md`; otherwise stop rather than weakening tenant, RBAC, audit, privacy, or API controls.
