# Tasks: ACS and Access Control

**Input**: Design documents from `specs/005-acs-access-control/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/`, `quickstart.md`

**Tests**: Mandatory and test-first. Each named test task must fail for the
expected missing behavior before its implementation task is completed.

**Organization**: Tasks are grouped by user story, in executable dependency
order: US1 → US2 → US4 → US3 → US5 → US6. Note the intra-priority reorder:
US4 (entry/exit ingestion, P2) is implemented before US3 (anti-passback, P2)
because anti-passback state is derived from ingested entry/exit events.

**Product Phase**: Phase 4 ACS-Access-Control

## How to execute this file (read this first)

These tasks are written so a small/cheap LLM can execute each one with
**zero additional lookups** in the common case:

- Every task names the exact file(s) to create or edit.
- Every migration/model task lists the exact column names, types, and enum
  values inline — copy them directly from the task text, do not re-derive
  them from `data-model.md`.
- Every task has `(depends: ...; accept: ...)`. `depends` lists task IDs that
  must be done first. `accept` is the exact, mechanically checkable condition
  that means the task is finished.
- Do exactly one task at a time, in ID order within its phase, respecting
  `depends`. Do not skip ahead or combine tasks.
- Every task that reuses an existing Phase 0/1/2/3 class names its exact path
  in the "Ground-truth integration points" section below or inline; open that
  exact file before writing new code that must match its conventions (naming,
  base classes, response envelopes, audit pattern).
- Never add a second way to validate a credential's signature. Gate
  authorization reuses the **existing** `CredentialValidator::validate()`
  entry point (the same one Phase 2 scanning uses); it never re-implements or
  bypasses signature/expiry/revocation/replay verification.
- The external ACS is reached only through the `AcsAdapter` interface. No ACS
  transport/protocol type (HTTP client, socket, MQTT, etc.) may appear in
  `app/Modules/AccessControl/Application/**` or `Domain/**`; transport lives
  only in `app/Modules/AccessControl/Infrastructure/Adapters/**`.
- Never invent a Phase 5+ concept (identity verification method/provider,
  venue marketplace). The `tests/Architecture/Phase4ModuleBoundaryTest.php`
  (T009) forbids those literal strings and must keep passing after every task.
- A **reason code** is the answer inside a `200` authorization/callback body
  (e.g. `allowed`, `credential_expired`); an **HTTP problem code** is a
  `4xx/5xx` error (e.g. `acs_lane_unmapped`). A denied credential is a
  successful `200` operation whose `decision` is `deny` — it is NOT an HTTP
  error.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel after listed dependencies because it owns
  different files.
- **[Story]**: Maps the task to the corresponding specification user story.
- Every task names an exact file or directory and includes a verifiable
  outcome.

## Ground-truth integration points (read before Foundational/US1+ tasks)

- **Credential validation core** (`app/Modules/Credentials/Application/
  Validation/CredentialValidator.php`, unchanged): open this file and reuse
  its existing `validate()` method exactly as
  `app/Modules/Scanning/Application/Actions/ScanDecisionEvaluatorImpl.php`
  calls it (open that call site first). `validate()` returns
  `['credential_id' => ..., 'status' => 'active', 'event_id' => ...]` and
  throws `App\Modules\Shared\Http\Problems\Phase1Problem` codes
  (`credential_invalid`, `credential_expired`, `credential_{status}`). Map
  those to reason codes in T041. Do NOT add a new validation method.
- **Scan decision / admission check-in** (`app/Modules/Scanning/Application/
  Actions/SubmitScanAction.php`, unchanged signature): `execute(ScanContext
  $context, ?ScanDecision $forcedDecision = null): ScanSubmission`. An
  allowed entry at an admission lane calls this exact class with
  `scannerType = 'acs_gate'` and `scannerId = <lane id>`, building
  `ScanContext` the same way `app/Modules/Kiosk/Http/Controllers/Device/
  KioskScanController.php` does (open it first). Do not fork this class.
- **`ScanContext`** (`app/Modules/Scanning/Domain/ValueObjects/
  ScanContext.php`): reuse the existing constructor fields
  (`tenantId, eventId, scannerId, scannerType, qrPayload, credentialId,
  override, overrideReason, actorCanOverride`) exactly as Phase 3's kiosk
  controller passes them.
- **Audit pattern** (copy exactly, do not invent a new one):
  1. A mutation runs inside `App\Modules\Audit\Application\
     AuditedTransaction::run(callable $mutation, callable $afterCommit)` (see
     `SubmitScanAction`).
  2. The mutation dispatches a domain event (e.g. `event(new
     GateAuthorized(...))`).
  3. A dedicated listener in `app/Modules/Audit/Application/Listeners/
     Phase4/` (mirror `app/Modules/Audit/Application/Listeners/Phase3/
     KioskAuditListener.php` exactly) injects `App\Modules\Audit\Contracts\
     AuditWriter` and calls `$this->audit->write('tenant', $tenantId,
     '<action>', '<outcome>', actor: ..., reasonCode: ..., targetType: ...,
     targetId: ..., metadata: [...])`.
  4. Register the listener in the owning module's `ServiceProvider::boot()`
     via `Event::listen(...)` (see `KioskServiceProvider::boot()`).
  5. For a decision that returns without a persisted mutation-event (rare
     here — every decision writes an `AccessEvent`), call
     `AuditWriter::write(...)` directly, exactly once per attempt.
- **Permission/authorization pattern** (copy exactly): one policy class at
  `app/Modules/Authorization/Policies/Phase4/Phase4Policy.php` with a
  `public const ABILITIES = ['ability' => 'permission.key']` map and one
  `allows(User $user, string $ability): bool` method reading
  `TenantContextStore::currentOrNull()` (see `app/Modules/Authorization/
  Policies/Phase3/Phase3Policy.php`). Register it in `app/Modules/
  Authorization/Providers/AuthorizationServiceProvider.php` the same way
  Phase 3's policy is registered.
- **Stable error pattern** (copy exactly): `app/Modules/Shared/Http/Problems/
  Phase4Problem.php` with a `public const STATUS = ['code' => httpStatus]`
  array and a `make(string $code): FoundationException` static method
  resolving detail from `__("phase4.{$code}")` (see `Phase3Problem.php`).
- **Route/middleware pattern** (copy exactly): tenant operator routes use
  `->middleware(['auth:sanctum', 'throttle:phase1-organizer',
  'tenant.context.clear', 'tenant.context'])` at the group level, then
  `->middleware(['permission:<key>,tenant', 'idempotency'])` per
  state-changing route (see `app/Modules/Scanning/Routes/api.php`). New ACS
  integration routes (`/acs/v1/*`) use a new
  `['acs.integration.clear', 'acs.integration']` pair instead of the tenant
  pair (T024/T026), never `auth:sanctum` (the caller is the external ACS, not
  a logged-in user).
- **Controller response pattern**: use the `RespondsWithApi` trait
  (`app/Modules/Shared/Http/Responses/RespondsWithApi.php`) `$this->
  success($data, $status)` method for every success response, exactly as
  `ScanController` does.
- **Context-store + middleware pattern** for the new ACS integration actor
  (copy exactly from Phase 3's kiosk session, which itself mirrors
  `app/Modules/Tenancy/Domain/Context/TenantContextStore.php`): open
  `app/Modules/Kiosk/Domain/Context/KioskSessionContextStore.php` and
  `app/Modules/Kiosk/Http/Middleware/ResolveKioskSession.php` first and copy
  their shape for `AcsIntegrationContextStore` / `ResolveAcsIntegration`.
- **Adapter + fake pattern** (copy exactly): open `app/Modules/WalletPasses/
  Testing/FakeWalletAdapter.php` for the `calls()`-introspection and forced-
  outcome pattern used by `FakeAcsAdapter` (T019), and
  `specs/001-project-foundation/contracts/adapter-contract.md` for the
  adapter conventions.
- **Existing files you will open but not modify unless a task says so**:
  `app/Modules/Kiosk/Http/Controllers/Device/KioskScanController.php`
  (ScanContext build), `app/Modules/Scanning/Application/Actions/
  ScanDecisionEvaluatorImpl.php` (CredentialValidator call site),
  `app/Providers/ModuleServiceProvider.php` (provider registration).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Register the Phase 4 module, configuration, test groups,
localization, types, and contract tooling without changing product behavior.

- [X] T001 Create empty provider class `app/Modules/AccessControl/Providers/AccessControlServiceProvider.php` extending `Illuminate\Support\ServiceProvider` with empty `register()` and `boot()` methods (depends: Phase 0/1/2/3 complete; accept: `composer dump-autoload` succeeds and the class is autoloadable).
- [X] T002 Register `App\Modules\AccessControl\Providers\AccessControlServiceProvider::class` in the `providers` array of `app/Providers/ModuleServiceProvider.php` (depends: T001; accept: `php artisan about` boots and lists the provider with zero errors).
- [X] T003 [P] Create `config/acs.php` returning an array with keys: `default_acs_adapter` (env `ACS_ADAPTER`, default `mock`), `integration.secret_length` (default `40`), `integration.credential_ttl_hours` (env `ACS_INTEGRATION_TTL_HOURS`, default `168`), `authorization.latency_budget_ms` (env `ACS_LATENCY_BUDGET_MS`, default `500`), `lane.offline_threshold_seconds` (default `120`) (depends: none; accept: `php artisan config:cache` succeeds).
- [X] T004 Add the five new environment keys from T003 with synthetic test-only values to `.env.example` and `.env.testing`, setting `ACS_ADAPTER=mock` in `.env.testing` (depends: T003; accept: `php artisan zonetec:config:validate --env=testing` names every declared key without printing any value).
- [X] T005 [P] Add seven test groups (`acs-config`, `acs-authorization`, `acs-events`, `acs-anti-passback`, `acs-unavailable`, `acs-emergency`, `acs-health`, `phase-4-isolation`, `phase-4`) to `phpunit.xml` and create `tests/Support/Phase4MySqlTestCase.php` extending the existing `tests/Support/MySqlTestCase.php` with no additional logic (depends: none; accept: `php artisan test --list-tests` runs without error).
- [X] T006 [P] Create `lang/en/phase4.php` and `lang/ar/phase4.php` with a matching key for each of: `acs_integration_invalid`, `acs_capability_denied`, `acs_zone_unmapped`, `acs_lane_unmapped`, `acs_event_out_of_scope`, `acs_duplicate_external_id`, `acs_invalid_time_window`, `acs_config_not_permitted`, `acs_events_not_permitted`, `acs_emergency_not_permitted`, and display keys `reason.allowed`, `reason.credential_expired`, `reason.credential_revoked`, `reason.credential_unknown`, `reason.zone_not_permitted`, `reason.lane_not_permitted`, `reason.outside_time_window`, `reason.anti_passback_violation`, `reason.acs_unavailable_fail_open`, `reason.acs_unavailable_fail_closed`, `reason.emergency_fail_open`, and audit keys `access.authorized`, `access.denied`, `acs_emergency.raised`, `acs_emergency.cleared`. Add the same key set to `resources/js/locales/en.ts` and `resources/js/locales/ar.ts` (depends: none; accept: a PHP script/test comparing `array_keys()` of the two `lang/*/phase4.php` files shows zero difference).
- [X] T007 [P] Create `resources/js/types/phase4.ts` exporting TypeScript interfaces `AcsZone` (fields: id, name, external_acs_zone_id, anti_passback_enabled, unavailability_mode, emergency_egress_mode, status), `AcsLane` (fields: id, zone_id, name, external_acs_lane_id, gate_type, access_direction, is_admission_lane, status, health_status, last_seen_at), `AcsRule` (fields: id, ticket_type_id, attendee_type, zone_id, lane_id, access_direction, anti_passback_exempt, valid_from, valid_until, status), `AccessEvent` (fields: id, event_type, decision, reason_code, direction, zone_id, lane_id, credential_id, occurred_at), and a `ACS_REASON_CODES` string-literal-union/array constant listing exactly: `allowed, credential_expired, credential_revoked, credential_unknown, zone_not_permitted, lane_not_permitted, outside_time_window, anti_passback_violation, acs_unavailable_fail_open, acs_unavailable_fail_closed, emergency_fail_open`. Create empty page shells `resources/js/pages/tenant/acs/Index.tsx`, `resources/js/pages/tenant/gate-events/Index.tsx`, and `resources/js/pages/tenant/acs-health/Index.tsx`, each exporting a default React component rendering only a heading (depends: none; accept: `npm run typecheck` succeeds).
- [X] T008 Add `"openapi:phase4": "redocly lint specs/005-acs-access-control/contracts/openapi.yaml"` to the `scripts` object in `package.json` (depends: none; accept: `npm run openapi:phase4` exits 0 with zero warnings).
- [X] T009 [P] Create `tests/Architecture/Phase4ModuleBoundaryTest.php` asserting, using the existing architecture-test conventions in `tests/Architecture/`: (a) no class outside `app/Modules/AccessControl/**` references `App\Modules\AccessControl\Infrastructure\*`; (b) no file under `app/Modules/AccessControl/Application/**` or `app/Modules/AccessControl/Domain/**` imports an HTTP client, socket, or MQTT class (assert absence of the literal strings `GuzzleHttp`, `Http::`, `fsockopen`, `MqttClient` in those two directories); (c) no file anywhere under `app/`, `resources/js/`, `routes/`, or `database/` contains any of the literal strings `IdentityVerification`, `IdentityAssurance`, `Marketplace`, `VenueListing` (case-insensitive) (depends: T001; accept: the test passes immediately since no such code exists yet, and must keep passing after every later task in this feature).

**Checkpoint**: The application boots with Phase 4 scaffolding; no product behavior yet.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Permissions, policy, errors, the ACS adapter contract + mock,
the M2M integration credential store + context + middleware, config health,
and the OpenAPI merge that every user story needs. No user-story work may
start before this phase is complete.

**CRITICAL**: Complete this phase before any user-story implementation.

- [X] T010 [P] Create `tests/Feature/Authorization/Phase4PermissionMatrixTest.php` asserting that each of these four permission strings does not yet exist in the permission catalog: `acs.configure`, `acs.events.view`, `acs.health.view`, `acs.emergency.manage` (depends: T005; accept: the test currently passes because the catalog is empty of these strings, and will be rewritten in T012 to allow/deny assertions).
- [X] T011 Add the four permissions from T010 to `database/seeders/PermissionSeeder.php`'s `definitions()` array with: `acs.configure` (module `access-control`, risk `sensitive`), `acs.events.view` (module `access-control`, risk `standard`), `acs.health.view` (module `access-control`, risk `standard`), `acs.emergency.manage` (module `access-control`, risk `privileged`) — all `scope` `tenant`. Add a new system-role template `'ACS Operator'` to `database/seeders/SystemRoleSeeder.php` granting exactly `acs.configure`, `acs.events.view`, `acs.health.view` (NOT `acs.emergency.manage`) (depends: T010; accept: rerunning `php artisan db:seed --class=PermissionSeeder --env=testing` twice produces no duplicate rows; rewrite T010 to assert all four permissions now exist, `'Tenant Administrator'` has all four, `'ACS Operator'` has exactly the three non-emergency ones, and custom roles remain empty of them).
- [X] T012 Create `app/Modules/Authorization/Policies/Phase4/Phase4Policy.php` following the exact structure of `app/Modules/Authorization/Policies/Phase3/Phase3Policy.php` (open it first), with `public const ABILITIES = ['configureAcs' => 'acs.configure', 'viewGateEvents' => 'acs.events.view', 'viewAcsHealth' => 'acs.health.view', 'manageEmergency' => 'acs.emergency.manage']`. Register it in `app/Modules/Authorization/Providers/AuthorizationServiceProvider.php` the same way `Phase3Policy` is registered (depends: T011; accept: T010's rewritten allow/deny assertions pass for tenant, event, and action scope).
- [X] T013 [P] Create `tests/Contract/Phase4ProblemDetailsTest.php` asserting each of these ten HTTP problem codes has an Arabic and an English message and appears in no test fixture alongside an ACS integration secret or PII field: `acs_integration_invalid`, `acs_capability_denied`, `acs_zone_unmapped`, `acs_lane_unmapped`, `acs_event_out_of_scope`, `acs_duplicate_external_id`, `acs_invalid_time_window`, `acs_config_not_permitted`, `acs_events_not_permitted`, `acs_emergency_not_permitted` (depends: T006; accept: the test currently fails because these codes are not yet mapped to HTTP statuses).
- [X] T014 Create `app/Modules/Shared/Http/Problems/Phase4Problem.php` following the exact structure of `app/Modules/Shared/Http/Problems/Phase3Problem.php` (open it first) with `public const STATUS` mapping: `acs_integration_invalid` => 401, `acs_capability_denied` => 403, `acs_zone_unmapped` => 404, `acs_lane_unmapped` => 404, `acs_event_out_of_scope` => 404, `acs_duplicate_external_id` => 409, `acs_invalid_time_window` => 422, `acs_config_not_permitted` => 403, `acs_events_not_permitted` => 403, `acs_emergency_not_permitted` => 403; `make()` returns a `FoundationException` built the same way `Phase3Problem::make()` does, with detail from `__("phase4.{$code}")` (depends: T013; accept: T013 passes).
- [X] T015 [P] Create the ACS adapter contract types: `app/Modules/AccessControl/Contracts/AcsAdapter.php` (interface with `health(): AcsHealthResult`); `app/Modules/AccessControl/Domain/ValueObjects/AcsDecisionResult.php` (readonly, fields: `string $decision` one of `allow|deny`, `string $reasonCode`); `app/Modules/AccessControl/Domain/Results/AcsHealthResult.php` (readonly, fields: `string $status` one of `online|degraded|offline`, `?string $reasonCode`) (depends: T001; accept: all three types autoload with no fatal error). Note: the adapter's job is transport/health only; the allow/deny decision is computed by `AuthorizeGateAction` (T042), not the adapter.
- [X] T016 [P] Create readonly value object `app/Modules/AccessControl/Domain/ValueObjects/AcsIntegrationContext.php` (fields: `string $tenantId, string $eventId, array $capabilities`) (depends: T001; accept: type autoloads).
- [X] T017 Create `app/Modules/AccessControl/Domain/Context/AcsIntegrationContextStore.php` mirroring the exact `bind()`/`current()`/`currentOrNull()`/`clear()` structure of `app/Modules/Kiosk/Domain/Context/KioskSessionContextStore.php` (open it first): `bind(AcsIntegrationContext $context)` throws `Phase4Problem::make('acs_integration_invalid')` if already bound this request; `current()` throws the same if unbound (depends: T016, T014; accept: types autoload and a throwaway unit test can bind/read/clear the store).
- [X] T018 Register `AcsIntegrationContextStore` as a singleton in `AccessControlServiceProvider::register()` via `$this->app->singleton(AcsIntegrationContextStore::class);` and bind `AcsAdapter::class` via `$this->app->bind(AcsAdapter::class, fn ($app) => match (config('acs.default_acs_adapter', 'mock')) { default => $app->make(FakeAcsAdapter::class) });` (only the `mock` branch exists this phase; do not add a `runa` branch with no approved transport) (depends: T017, T019; accept: `app(AcsIntegrationContextStore::class)` returns the same instance twice in one request and `app(AcsAdapter::class)` resolves to `FakeAcsAdapter` in `testing`).
- [X] T019 [P] Implement `app/Modules/AccessControl/Testing/FakeAcsAdapter.php` implementing `AcsAdapter`: `health()` returns `AcsHealthResult{status:'online'}` unless a public `forceHealth(string $status, ?string $reasonCode = null): void` was previously called on the instance, then returns that; add a public `forceUnavailable(bool $unavailable): void` and `isUnavailable(): bool` used by `AuthorizeGateAction`'s availability step in tests; record every call in an in-memory array inspectable via a public `calls(): array` method — copy the introspection pattern from `app/Modules/WalletPasses/Testing/FakeWalletAdapter.php` (open it first) (depends: T015; accept: a throwaway unit test can toggle forced states and read back recorded calls with no network access).
- [X] T020 [P] Create migration `database/migrations/2026_07_07_000001_create_acs_integration_credentials_table.php` creating table `acs_integration_credentials` with: ULID primary key `id`; `tenant_id` (foreign to `tenants.id`); `event_id` (composite foreign to `events` on `tenant_id,id`); `name` string(120); `secret_hash` string(255); `capabilities` json; `status` string(20) default `active`; `expires_at` timestamp(6); `revoked_at` timestamp(6) nullable; `created_at`/`updated_at`(6). Add check constraint `acs_integration_credentials_status_chk CHECK (status IN ('active','revoked'))` and index `(tenant_id, event_id, status)` (depends: T005; accept: `php artisan migrate --env=testing` succeeds and the table exists with the listed columns).
- [X] T021 [P] Create `app/Modules/AccessControl/Infrastructure/Persistence/Models/AcsIntegrationCredential.php` Eloquent model: fillable = all non-timestamp columns from T020; casts `capabilities` to `array`, `expires_at`/`revoked_at` to `datetime` (depends: T020; accept: a quick tinker/test query against the model succeeds).
- [X] T022 [P] Create `database/factories/AcsIntegrationCredentialFactory.php` (defaults: `status = 'active'`, `capabilities = ['authorize','event.ingest','emergency.ingest']`, `expires_at = now()->addDays(7)`), linked to existing tenant/event factories; add a static helper `withSecret(string $plainSecret)` state setting `secret_hash = hash('sha256', $plainSecret)` so tests can authenticate as the integration (depends: T021; accept: `AcsIntegrationCredential::factory()->create()` succeeds and a `withSecret('x')` state stores the sha256 of `x`).
- [X] T023 Create `app/Modules/AccessControl/Application/Actions/RegisterAcsIntegrationCredentialAction.php`: `execute(string $tenantId, string $eventId, string $name, array $capabilities): array{id: string, secret: string, expiresAt: \DateTimeInterface}` generates a raw secret via `sodium_bin2base64(random_bytes(config('acs.integration.secret_length', 40)), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)`, revokes every existing non-revoked credential for the event (`forceFill(['status' => 'revoked', 'revoked_at' => now()])`), creates a new row with `secret_hash = hash('sha256', $secret)` and `expires_at = now()->addHours(config('acs.integration.credential_ttl_hours', 168))`, validates every requested capability is one of `authorize|event.ingest|emergency.ingest`, and returns the raw secret (never persisted) (depends: T021; accept: a unit test `tests/Unit/AccessControl/RegisterAcsIntegrationCredentialActionTest.php` shows the stored hash matches the returned secret and a prior credential is revoked).
- [X] T024 Create middleware `app/Modules/AccessControl/Http/Middleware/ResolveAcsIntegration.php` and `app/Modules/AccessControl/Http/Middleware/ClearAcsIntegration.php`, copying the shape of `app/Modules/Kiosk/Http/Middleware/ResolveKioskSession.php` and its clear twin (open them first). `ResolveAcsIntegration::handle`: extract the secret from an `Authorization: AcsIntegration {secret}` header (`401 acs_integration_invalid` if missing/malformed); hash it and find an `AcsIntegrationCredential` where `secret_hash` matches, `status = 'active'`, `revoked_at IS NULL`, `expires_at > now()` (`acs_integration_invalid` if none); bind an `AcsIntegrationContext` (tenantId, eventId, capabilities) into `AcsIntegrationContextStore`. `ClearAcsIntegration` just calls `AcsIntegrationContextStore::clear()` then `$next($request)`. Register aliases `'acs.integration' => ResolveAcsIntegration::class` and `'acs.integration.clear' => ClearAcsIntegration::class` in `bootstrap/app.php`'s `$middleware->alias([...])` array (depends: T023, T018; accept: `php artisan route:list` runs with zero errors and both classes autoload).
- [X] T025 Create `app/Modules/AccessControl/Http/Middleware/RequireAcsCapability.php`: a parameterized middleware `handle(Request $request, Closure $next, string $capability)` that throws `Phase4Problem::make('acs_capability_denied')` unless `in_array($capability, AcsIntegrationContextStore::current()->capabilities, true)`. Register alias `'acs.capability' => RequireAcsCapability::class` in `bootstrap/app.php` (depends: T024; accept: a throwaway test asserts a context missing a capability is rejected 403 and one holding it passes).
- [X] T026 [P] Add one health-check category `acs_integration` to `app/Modules/Operations/Application/Health/Checks/` (open an existing Phase 1/2/3 check file in that directory first and copy its structure) reporting `configured`/`degraded`/`unreachable` for the ACS adapter (from `AcsAdapter::health()`), never reading a secret (depends: T003, T015; accept: `php artisan zonetec:config:validate --env=testing` output includes the `acs_integration` category).
- [X] T027 Merge every operation from `specs/005-acs-access-control/contracts/openapi.yaml` into `specs/001-project-foundation/contracts/openapi.yaml` and `docs/api/openapi.yaml`, keeping the Phase 4 review contract unchanged as input (depends: T008; accept: `php scripts/sync-openapi.php --check` and `npx redocly lint specs/001-project-foundation/contracts/openapi.yaml` both pass).
- [X] T028 Register empty route files `app/Modules/AccessControl/Routes/api.php` (containing only `<?php` and `use Illuminate\Support\Facades\Route;`) and require it from `routes/api.php` (depends: T002; accept: `php artisan route:list` runs with zero errors and shows no new routes yet).
- [X] T029 [P] Add `acs_gate` to the `scan_events.scanner_type` allowed values so an admission-lane check-in can persist: open the Phase 2 migration/model that defines `scan_events` (search `database/migrations` for `create_scan_events_table`); if it declares a `scanner_type` CHECK constraint, create migration `database/migrations/2026_07_07_000002_add_acs_gate_scanner_type.php` that drops and re-adds the constraint including `acs_gate`; if no such constraint exists, create the migration file with only a documented no-op comment explaining `scanner_type` is unconstrained at the DB level (depends: none; accept: `php artisan migrate --env=testing` succeeds and inserting a `ScanEvent` with `scanner_type = 'acs_gate'` via factory does not violate any constraint).
- [X] T030 [P] Create `tests/Integration/Security/Phase4ModuleBoundaryQueryTest.php` asserting no class outside `app/Modules/AccessControl/**` references `App\Modules\AccessControl\Infrastructure\Persistence\Models\*` directly, and that no ACS controller references `App\Modules\Credentials\Infrastructure\*` or `App\Modules\Scanning\Infrastructure\*` models directly (they must go through `CredentialValidator` and `SubmitScanAction`) (depends: T028; accept: the test passes now since no ACS controller exists yet; it must keep passing after every later task in this feature).

**Checkpoint**: Permissions, policy, errors, the ACS adapter + mock, the M2M integration credential store/context/middleware, and the merged contract exist; no story route performs product work yet.

---

## Phase 3: User Story 1 - Gate Releases Only When the Credential Is Authorized (Priority: P1) 🎯 MVP

**Goal**: The external ACS calls `/acs/v1/authorize` and receives an
allow/deny decision with a stable reason code, validating the credential
(reusing Phase 2's path) and evaluating zone/lane/direction/time-window
rules, recording an `AccessEvent`, and (for an admission lane) a Phase 2
check-in.

**Independent Test**: Authenticate as a factory-created ACS integration
credential, send authorization requests for valid, expired, revoked,
unknown, and out-of-scope credentials against factory-created zones/lanes/
rules, and confirm allow/deny + the correct reason code and one `AccessEvent`
per request. (Operator-facing zone/lane/rule management UI/API ships in US2;
this story creates them via factory.)

### Tests for User Story 1

- [X] T031 [P] [US1] Create `tests/Integration/MySql/AcsZoneLaneRuleSchemaTest.php` asserting: `acs_zones` has columns `id, tenant_id, event_id, name, external_acs_zone_id, anti_passback_enabled, unavailability_mode, emergency_egress_mode, status, created_at, updated_at`, a unique index on `(tenant_id, event_id, external_acs_zone_id)`, and check constraints for `unavailability_mode IN ('fail_open','fail_closed')`, `emergency_egress_mode IN ('fail_open','fail_closed')`, `status IN ('active','inactive')`; `acs_lanes` has columns `id, tenant_id, event_id, zone_id, name, external_acs_lane_id, gate_type, access_direction, is_admission_lane, status, health_status, last_seen_at, created_at, updated_at`, a unique index on `(tenant_id, event_id, external_acs_lane_id)`, and check constraints for `gate_type IN ('turnstile','door','speedgate','manual')`, `access_direction IN ('entry','exit','bidirectional')`, `status IN ('active','inactive')`, `health_status IN ('online','degraded','offline')`; `acs_authorization_rules` has columns `id, tenant_id, event_id, ticket_type_id, attendee_type, zone_id, lane_id, access_direction, anti_passback_exempt, valid_from, valid_until, status, created_at, updated_at` with an index on `(tenant_id, event_id, zone_id, status)` (depends: T005; accept: the test fails today because the tables do not exist).
- [X] T032 [P] [US1] Create `tests/Integration/MySql/AccessEventSchemaTest.php` asserting `access_events` has columns `id, tenant_id, event_id, event_type, credential_id, zone_id, lane_id, direction, decision, reason_code, source, external_event_id, scan_event_id, occurred_at, created_at`, check constraints for `event_type IN ('decision','entry','exit','emergency')` and `decision IN ('allow','deny','n/a')`, a unique index on `(tenant_id, external_event_id)` (where `external_event_id` is not null), and indexes on `(tenant_id, event_id, occurred_at)` and `(tenant_id, event_id, credential_id, zone_id, occurred_at)` (depends: T005; accept: the test fails today because the table does not exist).
- [X] T033 [P] [US1] Create `tests/Unit/AccessControl/AcsRuleEvaluatorTest.php` asserting a not-yet-existing `App\Modules\AccessControl\Application\Support\AcsRuleEvaluator::evaluate(string $tenantId, string $eventId, string $ticketTypeId, ?string $attendeeType, string $zoneId, string $laneId, string $direction, \DateTimeInterface $now): ?string` returns `null` (allowed) when an active rule permits the tuple, `'zone_not_permitted'` when no active rule matches the zone, `'lane_not_permitted'` when a rule matches the zone but restricts a different lane, and `'outside_time_window'` when the matched rule's `valid_from`/`valid_until` excludes `$now` (depends: T005; accept: the test fails today because the class does not exist).
- [X] T034 [P] [US1] Create `tests/Unit/AccessControl/AuthorizeGateActionTest.php` asserting a not-yet-existing `App\Modules\AccessControl\Application\Actions\AuthorizeGateAction::execute(...)`: returns `allow`/`allowed` for a valid credential + permitting rule and records exactly one `decision` `AccessEvent`; returns `deny`/`credential_expired`, `deny`/`credential_revoked`, `deny`/`credential_unknown` mapping the `CredentialValidator::validate()` exceptions; returns `deny`/`zone_not_permitted` etc. from `AcsRuleEvaluator`; and, when `FakeAcsAdapter::forceUnavailable(true)` is set, returns `allow`/`acs_unavailable_fail_open` for a `fail_open` zone and `deny`/`acs_unavailable_fail_closed` for a `fail_closed` zone (depends: T019; accept: the test fails today because the class does not exist).
- [X] T035 [P] [US1] Create `tests/Feature/AccessControl/GateAuthorizationApiTest.php` covering `POST /api/v1/acs/v1/authorize`: `401 acs_integration_invalid` without a valid `AcsIntegration` secret; `403 acs_capability_denied` for a credential lacking the `authorize` capability; `404 acs_lane_unmapped` for an unknown `external_acs_lane_id`; `200` with `decision`/`reason_code`/`access_event_id` matching `AuthorizationDecisionEnvelope` for a valid request; body never contains signing keys or a raw credential payload (depends: T027; accept: the test fails today with 404 route-not-found).
- [X] T036 [P] [US1] Create `tests/Feature/AccessControl/AdmissionLaneCheckInTest.php` asserting that an allowed `entry` decision at a lane with `is_admission_lane = true` also creates a Phase 2 `ScanEvent` with `scanner_type = 'acs_gate'` linked via `access_events.scan_event_id`, while a non-admission lane creates no `ScanEvent` (depends: T029; accept: the test fails today because the routes/actions do not exist).
- [X] T037 [P] [US1] Create `tests/Integration/Security/GateAuthorizationIsolationTest.php` asserting an authorization request whose credential resolves to, or whose lane belongs to, a different tenant/event than the integration credential's mapped scope is denied identically to an unknown target (`credential_unknown` for a cross-scope credential; `404 acs_lane_unmapped` for a cross-scope lane) (depends: T005; accept: the test fails today because the route does not exist).
- [X] T038 [P] [US1] Create `tests/Integration/Security/Phase4AuditAtomicityTest.php` with two cases: a forced audit-write failure during an allow decision leaves zero `AccessEvent` and zero `ScanEvent`; a forced audit-write failure during a deny decision leaves zero `AccessEvent`. Use the same forced-failure fixture technique as the existing Phase 3 audit-atomicity test (`tests/Integration/Security/Phase3AuditAtomicityTest.php` — open it first) (depends: T005; accept: the test fails today because the action does not exist).
- [X] T039 [P] [US1] Create `tests/Contract/Phase4/GateAuthorizationApiTest.php` running every success and principal `401/403/404/422` case documented in `contracts/openapi.yaml` for the `requestGateAuthorization` operation (depends: T027; accept: the test fails today with 404 route-not-found).

### Implementation for User Story 1

- [X] T040 [US1] Create migration `database/migrations/2026_07_07_000003_create_acs_zones_table.php` creating table `acs_zones` with: ULID `id`; `tenant_id` (foreign to `tenants.id`); `event_id` (composite foreign to `events` on `tenant_id,id`); `name` string(120); `external_acs_zone_id` string(160); `anti_passback_enabled` boolean default `false`; `unavailability_mode` string(20) default `fail_closed`; `emergency_egress_mode` string(20) default `fail_open`; `status` string(20) default `active`; `created_at`/`updated_at`(6). Add check constraints `acs_zones_unavailability_chk CHECK (unavailability_mode IN ('fail_open','fail_closed'))`, `acs_zones_emergency_chk CHECK (emergency_egress_mode IN ('fail_open','fail_closed'))`, `acs_zones_status_chk CHECK (status IN ('active','inactive'))`; add a unique index on `(tenant_id, event_id, external_acs_zone_id)` and an index on `(tenant_id, event_id, status)` (depends: T031; accept: T031's `acs_zones` assertions pass).
- [X] T041 [US1] Create migration `database/migrations/2026_07_07_000004_create_acs_lanes_table.php` creating table `acs_lanes` with: ULID `id`; `tenant_id`; `event_id` (composite foreign to `events` on `tenant_id,id`); `zone_id` (composite foreign to `acs_zones` on `tenant_id,event_id,id`); `name` string(120); `external_acs_lane_id` string(160); `gate_type` string(20); `access_direction` string(20); `is_admission_lane` boolean default `false`; `status` string(20) default `active`; `health_status` string(20) default `offline`; `last_seen_at` timestamp(6) nullable; `created_at`/`updated_at`(6). Add check constraints for `gate_type IN ('turnstile','door','speedgate','manual')`, `access_direction IN ('entry','exit','bidirectional')`, `status IN ('active','inactive')`, `health_status IN ('online','degraded','offline')`; unique index `(tenant_id, event_id, external_acs_lane_id)`, index `(tenant_id, event_id, zone_id, status)` (depends: T031, T040; accept: T031's `acs_lanes` assertions pass).
- [X] T042 [US1] Create migration `database/migrations/2026_07_07_000005_create_acs_authorization_rules_table.php` creating table `acs_authorization_rules` with: ULID `id`; `tenant_id`; `event_id` (composite foreign to `events` on `tenant_id,id`); `ticket_type_id` char(26) nullable (composite foreign to `ticket_types` on `tenant_id,event_id,id`); `attendee_type` string(20) nullable; `zone_id` (composite foreign to `acs_zones` on `tenant_id,event_id,id`); `lane_id` char(26) nullable (composite foreign to `acs_lanes` on `tenant_id,event_id,id`); `access_direction` string(20); `anti_passback_exempt` boolean default `false`; `valid_from` timestamp(6) nullable; `valid_until` timestamp(6) nullable; `status` string(20) default `active`; `created_at`/`updated_at`(6). Add check constraints for `access_direction IN ('entry','exit','bidirectional')`, `status IN ('active','inactive')`; index `(tenant_id, event_id, zone_id, status)` (depends: T031, T040, T041; accept: T031's `acs_authorization_rules` assertions pass).
- [X] T043 [US1] Create migration `database/migrations/2026_07_07_000006_create_access_events_table.php` creating table `access_events` with: ULID `id`; `tenant_id`; `event_id` (composite foreign to `events` on `tenant_id,id`); `event_type` string(20); `credential_id` char(26) nullable (composite foreign to `credentials` on `tenant_id,event_id,id`); `zone_id` char(26) nullable (composite foreign to `acs_zones` on `tenant_id,event_id,id`); `lane_id` char(26) nullable (composite foreign to `acs_lanes` on `tenant_id,event_id,id`); `direction` string(10) default `none`; `decision` string(10) default `n/a`; `reason_code` string(40); `source` string(20) default `acs_gate`; `external_event_id` string(160) nullable; `scan_event_id` char(26) nullable (composite foreign to `scan_events` on `tenant_id,event_id,id`); `occurred_at` timestamp(6); `created_at`(6) only (append-only, no `updated_at`). Add check constraints `access_events_type_chk CHECK (event_type IN ('decision','entry','exit','emergency'))` and `access_events_decision_chk CHECK (decision IN ('allow','deny','n/a'))`; add a unique index `access_events_external_uq` on `(tenant_id, external_event_id)`; add indexes `(tenant_id, event_id, occurred_at)` and `(tenant_id, event_id, credential_id, zone_id, occurred_at)` (depends: T032, T040, T041; accept: T032's assertions pass).
- [X] T044 [P] [US1] Create Eloquent models `app/Modules/AccessControl/Infrastructure/Persistence/Models/AcsZone.php`, `AcsLane.php`, `AcsAuthorizationRule.php`, `AccessEvent.php`: fillable = all non-timestamp columns from T040-T043; casts `anti_passback_enabled`/`is_admission_lane`/`anti_passback_exempt` to `boolean`, `valid_from`/`valid_until`/`last_seen_at`/`occurred_at` to `datetime`; relation `lanes()` (hasMany AcsLane) on `AcsZone` (depends: T040, T041, T042, T043; accept: a quick tinker/test query against each model succeeds).
- [X] T045 [P] [US1] Create factories `database/factories/AcsZoneFactory.php` (defaults: `status='active'`, `anti_passback_enabled=false`, `unavailability_mode='fail_closed'`, `emergency_egress_mode='fail_open'`, unique `external_acs_zone_id`), `AcsLaneFactory.php` (defaults: `gate_type='turnstile'`, `access_direction='entry'`, `is_admission_lane=false`, `status='active'`, `health_status='online'`, unique `external_acs_lane_id`), `AcsAuthorizationRuleFactory.php` (defaults: `access_direction='entry'`, `status='active'`, nulls for ticket/attendee/lane/window = "any"), `AccessEventFactory.php` (defaults: `event_type='decision'`, `decision='allow'`, `reason_code='allowed'`, `occurred_at=now()`), each linked to existing tenant/event factories (depends: T044; accept: `AcsZone::factory()->create()`, `AcsLane::factory()`, `AcsAuthorizationRule::factory()`, `AccessEvent::factory()` all succeed).
- [X] T046 [US1] Create `app/Modules/AccessControl/Application/Support/AcsRuleEvaluator.php` implementing exactly the contract asserted in T033: query active `AcsAuthorizationRule` rows for the tenant/event/zone; return `null` if a rule matches `(ticket_type_id null-or-equal, attendee_type null-or-equal, zone_id, lane_id null-or-equal, access_direction equal-or-bidirectional, now within window)`; otherwise return the most specific failure reason string in the order `zone_not_permitted` → `lane_not_permitted` → `outside_time_window` (depends: T033, T044; accept: T033 passes).
- [X] T047 [US1] Create domain events `app/Modules/AccessControl/Domain/Events/GateAuthorized.php` and `GateDenied.php` (each readonly, fields: `string $tenantId, string $eventId, string $accessEventId, ?string $credentialId, ?string $zoneId, ?string $laneId, string $direction, string $reasonCode`) (depends: T001; accept: types autoload with no fatal error).
- [X] T048 [US1] Create `app/Modules/AccessControl/Application/Actions/AuthorizeGateAction.php` per T034's contract. `execute(AcsIntegrationContext $ctx, string $externalLaneId, ?string $credentialReference, string $direction): AcsDecisionResult`. Steps in this exact order: (1) resolve `AcsLane` by `(tenant_id, event_id, external_acs_lane_id)` within `$ctx` scope, throwing `Phase4Problem::make('acs_lane_unmapped')` if none; load its `AcsZone`. (2) credential validity: call the existing `CredentialValidator::validate()` (open `ScanDecisionEvaluatorImpl` for the exact call), catching `Phase1Problem` and mapping `credential_expired`→`credential_expired`, `credential_revoked`→`credential_revoked`, any other (`credential_invalid`/unknown status)→`credential_unknown`; on a mapped failure record a `deny` `AccessEvent` and return. (3) call `AcsRuleEvaluator::evaluate(...)`; if it returns a non-null reason, record a `deny` `AccessEvent` and return. (4) availability: if `app(AcsAdapter::class)` reports unavailable (in tests, `FakeAcsAdapter::isUnavailable()`), record and return `allow`/`acs_unavailable_fail_open` or `deny`/`acs_unavailable_fail_closed` per `zone.unavailability_mode`. (5) otherwise record an `allow`/`allowed` `AccessEvent`; if `direction === 'entry'` and `lane.is_admission_lane`, build a `ScanContext` (copy from `KioskScanController`) with `scannerType='acs_gate'`, `scannerId=$lane->id`, `credentialId=<validated credential_id>`, call `SubmitScanAction::execute()`, and set `access_events.scan_event_id`. All record+return branches run inside `AuditedTransaction::run()` and dispatch `GateAuthorized`/`GateDenied` in the after-commit callback. Leave clearly-marked extension points `// anti-passback: see T098` (before step 4 for entry) and `// emergency short-circuit: see T111` (before step 2) as comments only for now (depends: T034, T046, T047, T019; accept: T034 passes).
- [X] T049 [US1] Create `app/Modules/Audit/Application/Listeners/Phase4/GateDecisionAuditListener.php` mirroring `app/Modules/Audit/Application/Listeners/Phase3/KioskAuditListener.php` (open it first), with `handleAuthorized`/`handleDenied` writing actions `access.authorized`/`access.denied`, `outcome` = `allow`/`deny`, `reasonCode = $event->reasonCode`, `targetType = 'access_event'`, `targetId = $event->accessEventId`, `metadata = ['event_id'=>..., 'zone_id'=>..., 'lane_id'=>..., 'direction'=>...]`. Register both in `AccessControlServiceProvider::boot()` via `Event::listen(...)` (depends: T047; accept: T038 passes — a decision produces exactly one matching audit row, and a forced audit failure rolls back the `AccessEvent`).
- [X] T050 [US1] Create `app/Modules/AccessControl/Http/Requests/AuthorizeGateRequest.php` (rules: `external_acs_lane_id` required string max:160, `direction` required in:`entry,exit`, `credential_reference` sometimes string max:512; add a `withValidator` unknown-fields check copying the pattern from `app/Modules/Scanning/Http/Requests/SubmitScanRequest.php`) and `app/Modules/AccessControl/Http/Resources/AuthorizationDecisionResource.php` exposing exactly `decision`, `reason_code`, `access_event_id`, `scan_event_id` per the `AuthorizationDecisionEnvelope` schema (depends: T001; accept: a quick unit test instantiates both with no error).
- [X] T051 [US1] Create `app/Modules/AccessControl/Http/Controllers/Integration/GateAuthorizationController.php::store`: read `AcsIntegrationContext` from `AcsIntegrationContextStore::current()`, call `AuthorizeGateAction::execute(...)`, respond via `RespondsWithApi::success(...)` with `AuthorizationDecisionResource` and HTTP `200` for both allow and deny (depends: T048, T050; accept: `GateAuthorizationController` autoloads).
- [X] T052 [US1] Register the route in `app/Modules/AccessControl/Routes/api.php`: `Route::prefix('acs/v1')->middleware(['acs.integration.clear', 'acs.integration'])->group(function () { Route::post('/authorize', [GateAuthorizationController::class, 'store'])->middleware(['acs.capability:authorize', 'idempotency']); });` (never add `auth:sanctum`) (depends: T051, T025; accept: T035, T036, T037, T039 pass).
- [X] T053 [US1] Run `php artisan test --group=acs-authorization`; fix any failing assertion from T031-T039 (depends: T031-T052; accept: the command exits 0).

**Checkpoint**: US1 is independently demonstrable — the ACS integration can request an allow/deny decision with a correct reason code, and admission lanes record a Phase 2 check-in, all against factory-seeded zones/lanes/rules.

---

## Phase 4: User Story 2 - Operator Maps Ticket Types to Zones and Lanes (Priority: P1)

**Goal**: An authorized operator creates/edits zones, lanes, and
authorization rules via the API/UI (the same rows US1 exercised via factory),
and registers/rotates the ACS integration credential.

**Independent Test**: Create a zone, a lane, and a ticket-type rule via the
API; confirm a matching credential is then allowed and a non-matching one
denied; confirm cross-tenant/event config is rejected; register an
integration credential and see its secret exactly once.

### Tests for User Story 2

- [X] T054 [P] [US2] Create `tests/Feature/AccessControl/AcsZoneApiTest.php` covering `POST/GET /api/v1/tenant/events/{event_id}/acs/zones` and `PATCH .../zones/{zone_id}`: `201`/`200` for an `acs.configure` user; `403 acs_config_not_permitted` without the permission; `409 acs_duplicate_external_id` for a duplicate `external_acs_zone_id` in the same event; unknown request fields rejected; response matches `AcsZoneEnvelope` (depends: T027; accept: fails today with 404 route-not-found).
- [X] T055 [P] [US2] Create `tests/Feature/AccessControl/AcsLaneApiTest.php` covering `POST/GET .../acs/lanes`: `201`/`200` for `acs.configure`; `409 acs_duplicate_external_id` for a duplicate `external_acs_lane_id`; a lane referencing a zone in a different event is rejected as unknown; response matches `AcsLaneEnvelope` (depends: T027; accept: fails today with 404).
- [X] T056 [P] [US2] Create `tests/Feature/AccessControl/AcsRuleApiTest.php` covering `POST/GET .../acs/rules`: `201`/`200` for `acs.configure`; `422 acs_invalid_time_window` when `valid_from` > `valid_until`; response matches `AcsRuleEnvelope` (depends: T027; accept: fails today with 404).
- [X] T057 [P] [US2] Create `tests/Feature/AccessControl/AcsIntegrationCredentialApiTest.php` covering `POST .../acs/integration-credentials`: `201` returns the raw secret exactly once for `acs.configure`; a second registration revokes the prior credential; `403` without the permission (depends: T027; accept: fails today with 404).
- [X] T058 [P] [US2] Create `tests/Integration/Security/AcsConfigIsolationTest.php` asserting zone/lane/rule/credential create/list/update requests naming a different tenant/event than the caller's context return the same response as an unknown target (depends: T005; accept: fails today because the routes do not exist).
- [X] T059 [P] [US2] Create `tests/Contract/Phase4/AcsConfigApiTest.php` running every success and principal `401/403/404/409/422` case documented in `contracts/openapi.yaml` for `createAcsZone`, `listAcsZones`, `updateAcsZone`, `createAcsLane`, `listAcsLanes`, `createAcsAuthorizationRule`, `listAcsAuthorizationRules`, `registerAcsIntegrationCredential` (depends: T027; accept: fails today with 404).

### Implementation for User Story 2

- [X] T060 [US2] Create `app/Modules/AccessControl/Application/Actions/CreateAcsZoneAction.php` and `UpdateAcsZoneAction.php`: create validates uniqueness of `external_acs_zone_id` per `(tenant_id, event_id)` (throw `Phase4Problem::make('acs_duplicate_external_id')` on conflict) and persists the row; update mutates only `name`, `anti_passback_enabled`, `unavailability_mode`, `emergency_egress_mode`, `status`. Wrap each in `AuditedTransaction::run()` dispatching `AcsZoneCreated`/`AcsZoneUpdated` domain events (create these two events in `app/Modules/AccessControl/Domain/Events/`) (depends: T044; accept: a unit test creates a zone and rejects a duplicate external id).
- [X] T061 [US2] Create `app/Modules/AccessControl/Application/Actions/CreateAcsLaneAction.php`: validates the referenced `zone_id` belongs to the same tenant/event (reject as unknown otherwise), validates `external_acs_lane_id` uniqueness per event, persists; wrap in `AuditedTransaction::run()` dispatching `AcsLaneCreated` (depends: T044; accept: a unit test creates a lane and rejects a cross-event zone reference and a duplicate external id).
- [X] T062 [US2] Create `app/Modules/AccessControl/Application/Actions/CreateAcsRuleAction.php`: validates `valid_from <= valid_until` when both present (throw `Phase4Problem::make('acs_invalid_time_window')`), validates referenced zone/lane/ticket-type belong to the same tenant/event, persists; wrap in `AuditedTransaction::run()` dispatching `AcsRuleCreated` (depends: T044; accept: a unit test creates a rule and rejects an inverted time window).
- [X] T063 [US2] Create `app/Modules/Audit/Application/Listeners/Phase4/AcsConfigAuditListener.php` mirroring `KioskAuditListener`, writing actions `acs_zone.created`/`acs_zone.updated`/`acs_lane.created`/`acs_rule.created` and (for T064) `acs_integration.credential_registered`. Register listeners in `AccessControlServiceProvider::boot()`; dispatch the events from T060-T062 and T064 (depends: T060, T061, T062; accept: creating each entity produces exactly one matching audit row).
- [X] T064 [US2] Wrap `RegisterAcsIntegrationCredentialAction` (T023) in `AuditedTransaction::run()` dispatching a new `AcsIntegrationCredentialRegistered` domain event (`app/Modules/AccessControl/Domain/Events/`), writing audit action `acs_integration.credential_registered` with `targetType='acs_integration_credential'` and NO secret in metadata (depends: T023, T063; accept: registering a credential produces exactly one audit row containing no secret substring).
- [X] T065 [P] [US2] Create request classes `app/Modules/AccessControl/Http/Requests/AcsZoneRequest.php`, `AcsLaneRequest.php`, `AcsRuleRequest.php`, `AcsIntegrationCredentialRequest.php` matching the corresponding request schemas in `contracts/openapi.yaml` (field names, required/optional, enums), each with a `withValidator` unknown-fields check copying `SubmitScanRequest.php`'s pattern (depends: T001; accept: a quick unit test instantiates each with no error).
- [X] T066 [P] [US2] Create resource classes `app/Modules/AccessControl/Http/Resources/AcsZoneResource.php`, `AcsLaneResource.php`, `AcsRuleResource.php` exposing exactly the fields in the `AcsZone`/`AcsLane`/`AcsRule` schemas in `contracts/openapi.yaml` (depends: T001; accept: instantiates with no error).
- [X] T067 [US2] Create `app/Modules/AccessControl/Http/Controllers/Management/AcsZoneController.php` (`store`, `index`, `update`), `AcsLaneController.php` (`store`, `index`), `AcsRuleController.php` (`store`, `index`), and `AcsIntegrationCredentialController.php` (`store`), each checking `Phase4Policy::allows($user, 'configureAcs')` (throw `Phase4Problem::make('acs_config_not_permitted')` if not) and calling the corresponding action; responses via `RespondsWithApi` (depends: T060, T061, T062, T064, T065, T066, T012; accept: T054-T057 pass).
- [X] T068 [US2] Register the management routes in `app/Modules/AccessControl/Routes/api.php` inside a `Route::prefix('tenant/events/{event_id}')->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])->group(...)` block: `POST/GET /acs/zones`, `PATCH /acs/zones/{zone_id}`, `POST/GET /acs/lanes`, `POST/GET /acs/rules`, `POST /acs/integration-credentials`; each state-changing POST/PATCH uses `['permission:acs.configure,tenant', 'idempotency']`; GET routes use `['permission:acs.configure,tenant']` (depends: T067; accept: T054-T059 pass).
- [X] T069 [P] [US2] Create `resources/js/components/acs/ZoneLaneEditor.tsx` and `resources/js/components/acs/RuleEditor.tsx` — presentational components only, rendering the `AcsZone`/`AcsLane`/`AcsRule` shapes from `resources/js/types/phase4.ts`; no data-fetching inside these components (depends: T007; accept: `npm run typecheck` succeeds).
- [X] T070 [US2] Wire `resources/js/pages/tenant/acs/Index.tsx` (from T007) to the zone/lane/rule and integration-credential endpoints using the existing fetch/axios wrapper (open `resources/js/pages/tenant/kiosk/Index.tsx` first and copy its data-fetching pattern), rendering the T069 editors and displaying the integration secret exactly once on registration (depends: T069, T068; accept: `npm run typecheck` and `npm run build` succeed).
- [X] T071 [US2] Run `php artisan test --group=acs-config`; fix any failing assertion from T054-T059 (depends: T054-T070; accept: exits 0).

**Checkpoint**: US1 + US2 form the MVP — operators configure zones/lanes/rules and the ACS integration credential, and gate authorization enforces them.

---

## Phase 5: User Story 4 - Entry and Exit Events Are Logged and Reconciled (Priority: P2)

**Goal**: The ACS posts entry/exit event callbacks; the system records them as
`AccessEvent` rows idempotently, reconciles out-of-order arrival, and updates
lane `last_seen_at`/health. (Implemented before US3 because anti-passback
state is derived from these events.)

**Independent Test**: Post an entry then an exit callback; confirm two
`AccessEvent` rows with correct direction/zone/lane/credential; post a
duplicate `external_event_id` and confirm an idempotent no-op; post a
cross-scope callback and confirm rejection.

### Tests for User Story 4

- [X] T072 [P] [US4] Create `tests/Feature/AccessControl/AccessEventCallbackApiTest.php` covering `POST /api/v1/acs/v1/events`: `202` for an `event.ingest`-capable integration; records an `AccessEvent` with `event_type` from the request, `direction`, `lane_id`/`zone_id` resolved from `external_acs_lane_id`, and `occurred_at`; a repeated `external_event_id` returns `202` and creates no second row; `404 acs_event_out_of_scope` for a lane outside the integration's mapped event; `403 acs_capability_denied` when the credential lacks `event.ingest` (depends: T027; accept: fails today with 404 route-not-found).
- [X] T073 [P] [US4] Create `tests/Unit/AccessControl/IngestAccessEventActionTest.php` asserting a not-yet-existing `App\Modules\AccessControl\Application\Actions\IngestAccessEventAction::execute(...)`: creates one `AccessEvent`; a second call with the same `(tenant_id, external_event_id)` is a no-op returning the existing row; updates the lane's `last_seen_at` to the event's `occurred_at` when newer (depends: T005; accept: fails today because the class does not exist).
- [X] T074 [P] [US4] Create `tests/Integration/Security/AccessEventIngestIsolationTest.php` asserting a callback naming a lane/zone/credential outside the integration's mapped event is rejected `404 acs_event_out_of_scope` and creates no row (depends: T005; accept: fails today).
- [X] T075 [P] [US4] Create `tests/Contract/Phase4/AccessEventApiTest.php` running every success and principal failure case documented in `contracts/openapi.yaml` for `ingestAccessEvent` (depends: T027; accept: fails today with 404).

### Implementation for User Story 4

- [X] T076 [US4] Create `app/Modules/AccessControl/Application/Actions/IngestAccessEventAction.php` per T073's contract: resolve `AcsLane` by `(tenant_id, event_id, external_acs_lane_id)` within the integration scope (throw `Phase4Problem::make('acs_event_out_of_scope')` if none); short-circuit to the existing row if an `AccessEvent` with the same `(tenant_id, external_event_id)` exists (idempotency); otherwise, inside `AuditedTransaction::run()`, create an `AccessEvent` (`event_type` = request `entry|exit`, `reason_code` = `event_type`, `source='acs_gate'`, `occurred_at` from request), update `AcsLane.last_seen_at` if the event is newer, dispatch an `AccessEventIngested` domain event in the after-commit callback. Add a clearly-marked extension point `// anti-passback state update: see T099` (depends: T073, T044; accept: T073 passes).
- [X] T077 [US4] Create `app/Modules/AccessControl/Domain/Events/AccessEventIngested.php` (readonly: `string $tenantId, $eventId, $accessEventId, $laneId, $zoneId, ?string $credentialId, string $direction`) and `app/Modules/Audit/Application/Listeners/Phase4/AccessEventAuditListener.php` mirroring `KioskAuditListener`, writing action `access.entry`/`access.exit` (choose by direction), `targetType='access_event'`. Register in `AccessControlServiceProvider::boot()` (depends: T076; accept: ingesting an event produces exactly one matching audit row).
- [X] T078 [US4] Create `app/Modules/AccessControl/Http/Requests/AccessEventCallbackRequest.php` (rules per the `AccessEventCallbackRequest` schema: `external_event_id` required string max:160, `external_acs_lane_id` required string max:160, `credential_reference` sometimes nullable string max:512, `event_type` required in:`entry,exit`, `occurred_at` required date; unknown-fields check) and `app/Modules/AccessControl/Http/Resources/AccessEventResource.php` matching the `AccessEvent` schema (depends: T001; accept: instantiate with no error).
- [X] T079 [US4] Create `app/Modules/AccessControl/Http/Controllers/Integration/AccessEventController.php::store`: read `AcsIntegrationContext`, call `IngestAccessEventAction`, respond `202` via `RespondsWithApi` with `AccessEventResource` (depends: T076, T078; accept: controller autoloads).
- [X] T080 [US4] Register the route in `app/Modules/AccessControl/Routes/api.php` inside the existing `acs/v1` group: `Route::post('/events', [AccessEventController::class, 'store'])->middleware(['acs.capability:event.ingest', 'idempotency']);` (depends: T079, T025; accept: T072, T074, T075 pass).
- [X] T081 [US4] Run `php artisan test --group=acs-events`; fix any failing assertion from T072-T075 (depends: T072-T080; accept: exits 0).

**Checkpoint**: Entry/exit callbacks are recorded idempotently and reconciled; lane `last_seen_at` updates from real events.

---

## Phase 6: User Story 3 - Anti-Passback Rejects Duplicate Re-Entry (Priority: P2)

**Goal**: With anti-passback enabled for a zone, re-entry is denied unless an
exit was recorded; state is derived from ingested entry/exit events
(US4).

**Independent Test**: Enable anti-passback on a zone; authorize an entry, post
an entry event, attempt a second entry authorization → `anti_passback_violation`;
post an exit event, re-attempt → allowed; an `anti_passback_exempt` rule never
triggers the violation.

### Tests for User Story 3

- [X] T082 [P] [US3] Create `tests/Integration/MySql/AntiPassbackStateSchemaTest.php` asserting `anti_passback_states` has columns `id, tenant_id, event_id, credential_id, zone_id, state, last_access_event_id, last_transition_at, created_at, updated_at`, a check constraint `state IN ('inside','outside')`, and a unique index on `(tenant_id, event_id, credential_id, zone_id)` (depends: T005; accept: fails today because the table does not exist).
- [X] T083 [P] [US3] Create `tests/Unit/AccessControl/AntiPassbackServiceTest.php` asserting a not-yet-existing `App\Modules\AccessControl\Application\Support\AntiPassbackService`: `isInside(string $tenantId, $eventId, $credentialId, $zoneId): bool` reads the materialized state; `applyEvent(AccessEvent $event): void` sets `inside` on an entry and `outside` on an exit, ignoring an out-of-order event whose `occurred_at` is older than `last_transition_at` (depends: T005; accept: fails today because the class does not exist).
- [X] T084 [P] [US3] Create `tests/Feature/AccessControl/AntiPassbackAuthorizationTest.php` asserting: with `AcsZone.anti_passback_enabled = true`, after an ingested entry the next entry authorization returns `deny`/`anti_passback_violation`; after an ingested exit the next entry is allowed; with a rule marked `anti_passback_exempt = true` no violation ever occurs; with anti-passback disabled the second entry is allowed (depends: T005; accept: fails today).

### Implementation for User Story 3

- [X] T085 [US3] Create migration `database/migrations/2026_07_07_000007_create_anti_passback_states_table.php` creating table `anti_passback_states` with: ULID `id`; `tenant_id`; `event_id` (composite foreign to `events` on `tenant_id,id`); `credential_id` (composite foreign to `credentials` on `tenant_id,event_id,id`); `zone_id` (composite foreign to `acs_zones` on `tenant_id,event_id,id`); `state` string(10) default `outside`; `last_access_event_id` char(26) nullable; `last_transition_at` timestamp(6) nullable; `created_at`/`updated_at`(6). Add check constraint `anti_passback_states_state_chk CHECK (state IN ('inside','outside'))` and a unique index on `(tenant_id, event_id, credential_id, zone_id)` (depends: T082; accept: T082 passes).
- [X] T086 [P] [US3] Create `app/Modules/AccessControl/Infrastructure/Persistence/Models/AntiPassbackState.php` (fillable = all non-timestamp columns; casts `last_transition_at` to `datetime`) and `database/factories/AntiPassbackStateFactory.php` (default `state='outside'`) (depends: T085; accept: `AntiPassbackState::factory()->create()` succeeds).
- [X] T087 [US3] Create `app/Modules/AccessControl/Application/Support/AntiPassbackService.php` per T083's contract, using an upsert keyed on `(tenant_id, event_id, credential_id, zone_id)` and comparing `occurred_at` against `last_transition_at` before applying (depends: T083, T086; accept: T083 passes).
- [X] T088 [US3] Edit `app/Modules/AccessControl/Application/Actions/IngestAccessEventAction.php` at the `// anti-passback state update: see T099` extension point (T076): after creating the `AccessEvent`, call `AntiPassbackService::applyEvent(...)` inside the same transaction when the event has a `credential_id` (depends: T076, T087; accept: an ingested entry sets the state row to `inside`; an ingested exit sets it to `outside`).
- [X] T089 [US3] Edit `app/Modules/AccessControl/Application/Actions/AuthorizeGateAction.php` at the `// anti-passback: see T098` extension point (T048): for `direction === 'entry'`, after rule evaluation passes and before the availability step, if the lane's `AcsZone.anti_passback_enabled` is true AND no matched rule is `anti_passback_exempt` AND `AntiPassbackService::isInside(...)` is true, record a `deny` `AccessEvent` with `anti_passback_violation` and return (depends: T048, T087, T046; accept: T084 passes).
- [X] T090 [US3] Run `php artisan test --group=acs-anti-passback`; fix any failing assertion from T082-T084 (depends: T082-T089; accept: exits 0).

**Checkpoint**: Anti-passback denies re-entry without a recorded exit, configurable per event/zone/ticket type.

---

## Phase 7: User Story 5 - Emergency Egress Fails Open and Is Recorded (Priority: P2)

**Goal**: An emergency signal (operator- or ACS-initiated) raises/clears an
`EmergencyEvent`; while active, affected `fail_open` zones allow entry with
`emergency_fail_open`; every emergency is recorded and auditable.

**Independent Test**: Raise an emergency for a `fail_open` zone; confirm
subsequent entry authorizations at its lanes return `allow`/`emergency_fail_open`
and an `EmergencyEvent`/`AccessEvent` are recorded and visible; clear it and
confirm normal decisioning resumes.

### Tests for User Story 5

- [X] T091 [P] [US5] Create `tests/Integration/MySql/EmergencyEventSchemaTest.php` asserting `emergency_events` has columns `id, tenant_id, event_id, zone_id, signal_source, behavior_applied, raised_at, cleared_at, created_at, updated_at`, check constraints `signal_source IN ('operator','acs','fire_alarm','system')` and `behavior_applied IN ('fail_open','fail_closed')`, and an index on `(tenant_id, event_id, cleared_at)` (depends: T005; accept: fails today because the table does not exist).
- [X] T092 [P] [US5] Create `tests/Feature/AccessControl/OperatorEmergencyApiTest.php` covering `POST /api/v1/tenant/events/{event_id}/acs/emergency`: `200` raise/clear for an `acs.emergency.manage` user; `403 acs_emergency_not_permitted` without the permission; raising sets an active `EmergencyEvent` (`cleared_at` null), clearing sets `cleared_at` (depends: T027; accept: fails today with 404).
- [X] T093 [P] [US5] Create `tests/Feature/AccessControl/AcsEmergencyCallbackApiTest.php` covering `POST /api/v1/acs/v1/emergency`: `202` for an `emergency.ingest`-capable integration; raises/clears an `EmergencyEvent`; `403 acs_capability_denied` without the capability (depends: T027; accept: fails today with 404).
- [X] T094 [P] [US5] Create `tests/Feature/AccessControl/EmergencyEgressAuthorizationTest.php` asserting: while an emergency is active for a `fail_open` zone, entry authorizations at its lanes return `allow`/`emergency_fail_open` even when a rule or anti-passback would otherwise deny; after clearing, normal decisioning resumes (depends: T005; accept: fails today).

### Implementation for User Story 5

- [X] T095 [US5] Create migration `database/migrations/2026_07_07_000008_create_emergency_events_table.php` creating table `emergency_events` with: ULID `id`; `tenant_id`; `event_id` (composite foreign to `events` on `tenant_id,id`); `zone_id` char(26) nullable (composite foreign to `acs_zones` on `tenant_id,event_id,id`); `signal_source` string(20); `behavior_applied` string(20); `raised_at` timestamp(6); `cleared_at` timestamp(6) nullable; `created_at`/`updated_at`(6). Add check constraints `emergency_events_source_chk CHECK (signal_source IN ('operator','acs','fire_alarm','system'))` and `emergency_events_behavior_chk CHECK (behavior_applied IN ('fail_open','fail_closed'))`; index `(tenant_id, event_id, cleared_at)` (depends: T091; accept: T091 passes).
- [X] T096 [P] [US5] Create `app/Modules/AccessControl/Infrastructure/Persistence/Models/EmergencyEvent.php` (fillable = non-timestamp columns; casts `raised_at`/`cleared_at` to `datetime`) and `database/factories/EmergencyEventFactory.php` (defaults: `signal_source='operator'`, `behavior_applied='fail_open'`, `raised_at=now()`, `cleared_at=null`) (depends: T095; accept: `EmergencyEvent::factory()->create()` succeeds).
- [X] T097 [US5] Create `app/Modules/AccessControl/Application/Support/EmergencyStateService.php`: `isActiveForZone(string $tenantId, $eventId, ?string $zoneId): bool` returns true when an `emergency_events` row with `cleared_at IS NULL` exists for that zone OR event-wide (`zone_id` null) (depends: T096; accept: a unit test confirms zone-specific and event-wide active detection).
- [X] T098 [US5] Create `app/Modules/AccessControl/Application/Actions/RaiseEmergencyAction.php` and `ClearEmergencyAction.php`: raise creates an `EmergencyEvent` (deriving `behavior_applied` from the target zone's `emergency_egress_mode`, or `fail_open` for event-wide) inside `AuditedTransaction::run()` dispatching `EmergencyRaised`; clear sets `cleared_at = now()` on active rows for the target inside `AuditedTransaction::run()` dispatching `EmergencyCleared` (create both events in `app/Modules/AccessControl/Domain/Events/`) plus one `AccessEvent` with `event_type='emergency'` (depends: T096; accept: a unit test raises then clears and sees `cleared_at` populated).
- [X] T099 [US5] Create `app/Modules/Audit/Application/Listeners/Phase4/EmergencyAuditListener.php` mirroring `KioskAuditListener`, writing actions `acs_emergency.raised`/`acs_emergency.cleared`, `targetType='emergency_event'`. Register in `AccessControlServiceProvider::boot()` (depends: T098; accept: raising/clearing produces exactly one matching audit row each).
- [X] T100 [US5] Edit `app/Modules/AccessControl/Application/Actions/AuthorizeGateAction.php` at the `// emergency short-circuit: see T111` extension point (T048): after resolving the lane/zone and before credential validation, if `direction === 'entry'` and `EmergencyStateService::isActiveForZone(...)` is true and `zone.emergency_egress_mode === 'fail_open'`, record an `allow` `AccessEvent` with `emergency_fail_open` and return immediately (depends: T048, T097; accept: T094 passes).
- [X] T101 [US5] Create `app/Modules/AccessControl/Http/Requests/OperatorEmergencyRequest.php` (rules per `OperatorEmergencyRequest`: `action` required in:`raise,clear`, `zone_id` sometimes nullable string) and `app/Modules/AccessControl/Http/Requests/EmergencySignalRequest.php` (rules per `EmergencySignalRequest`: `action` required in:`raise,clear`, `external_acs_zone_id` sometimes nullable string, `signal_source` sometimes in:`acs,fire_alarm,system`, `occurred_at` required date) and `app/Modules/AccessControl/Http/Resources/EmergencyEventResource.php` matching the `EmergencyEvent` schema (depends: T001; accept: instantiate with no error).
- [X] T102 [US5] Create `app/Modules/AccessControl/Http/Controllers/Management/EmergencyController.php::store` (checks `Phase4Policy::allows($user,'manageEmergency')` else `Phase4Problem::make('acs_emergency_not_permitted')`; calls Raise/Clear by `action`) and `app/Modules/AccessControl/Http/Controllers/Integration/EmergencyCallbackController.php::store` (reads `AcsIntegrationContext`, resolves the zone by `external_acs_zone_id` when present, calls Raise/Clear) (depends: T098, T101, T012; accept: T092, T093 pass).
- [X] T103 [US5] Register routes: in the tenant group add `Route::post('/acs/emergency', [EmergencyController::class,'store'])->middleware(['permission:acs.emergency.manage,tenant','idempotency']);`; in the `acs/v1` group add `Route::post('/emergency', [EmergencyCallbackController::class,'store'])->middleware(['acs.capability:emergency.ingest','idempotency']);` (depends: T102, T025; accept: T092, T093 pass).
- [X] T104 [US5] Run `php artisan test --group=acs-emergency`; fix any failing assertion from T091-T094 (depends: T091-T103; accept: exits 0).

**Checkpoint**: Emergency egress raises/clears correctly, fails open where configured, and is auditable.

---

## Phase 8: User Story 6 - Operator Monitors Gate Events and Health (Priority: P3)

**Goal**: Authorized viewers see a bounded gate-events feed and per-lane +
ACS-integration health, scoped to their event.

**Independent Test**: Generate decisions/events, then read the gate-events feed
and health endpoints as an authorized viewer; confirm reason codes and
online/degraded/offline statuses appear and no other tenant's/event's data is
visible.

### Tests for User Story 6

- [X] T105 [P] [US6] Create `tests/Feature/AccessControl/GateEventsFeedApiTest.php` covering `GET /api/v1/tenant/events/{event_id}/acs/gate-events`: `200` for an `acs.events.view` user returning `AccessEvent` rows (newest first, bounded by `limit`, filterable by `since`); `403 acs_events_not_permitted` without the permission; only the caller's event/tenant rows appear (depends: T027; accept: fails today with 404).
- [X] T106 [P] [US6] Create `tests/Feature/AccessControl/AcsHealthApiTest.php` covering `GET /api/v1/tenant/events/{event_id}/acs/health`: `200` for an `acs.health.view` user returning `integration_status`, `active_emergency`, and per-lane `health_status`; a lane whose `last_seen_at` is older than `config('acs.lane.offline_threshold_seconds')` is reported `offline`; `403` without the permission (depends: T027; accept: fails today with 404).
- [X] T107 [P] [US6] Create `tests/Integration/Security/GateEventsHealthIsolationTest.php` asserting the gate-events feed and health views never return another tenant's or event's rows/lanes (depends: T005; accept: fails today).
- [X] T108 [P] [US6] Create `tests/Contract/Phase4/GateEventsHealthApiTest.php` running every success and principal failure case documented in `contracts/openapi.yaml` for `listGateEvents` and `getAcsHealth` (depends: T027; accept: fails today with 404).

### Implementation for User Story 6

- [X] T109 [US6] Create `app/Modules/AccessControl/Domain/AcsLaneHealthDeriver.php`: `derive(AcsLane $lane, int $thresholdSeconds, \DateTimeInterface $now): string` returns `'offline'` if `last_seen_at` is null or older than `$thresholdSeconds` before `$now`; else the lane's stored `health_status` (or `'online'`) — mirror the shape of `app/Modules/Kiosk/Domain/KioskStatusDeriver.php` (open it first) (depends: T044; accept: a unit test returns `offline` past the threshold and `online` within it).
- [X] T110 [US6] Create `app/Modules/AccessControl/Application/Queries/GateEventsQuery.php`: `list(string $tenantId, $eventId, ?\DateTimeInterface $since, int $limit): array` returns tenant/event-scoped `AccessEvent` rows ordered by `occurred_at` desc, bounded by `$limit` (max 200), optionally filtered by `$since` (depends: T044; accept: a unit test returns only scoped rows, newest-first, respecting the limit).
- [X] T111 [US6] Create `app/Modules/AccessControl/Application/Queries/AcsHealthQuery.php`: `summary(string $tenantId, $eventId): array` returns `integration_status` (from `AcsAdapter::health()`), `active_emergency` (from `EmergencyStateService`), and a `lanes` array of `{lane_id, health_status (via AcsLaneHealthDeriver), last_seen_at}` for the event (depends: T109, T097, T015; accept: a unit test returns the expected shape scoped to the event).
- [X] T112 [US6] Create `app/Modules/AccessControl/Http/Controllers/Management/GateEventsController.php::index` (checks `Phase4Policy::allows($user,'viewGateEvents')` else `Phase4Problem::make('acs_events_not_permitted')`; calls `GateEventsQuery`) and `AcsHealthController.php::index` (checks `viewAcsHealth`; calls `AcsHealthQuery`), responses via `RespondsWithApi` matching `GateEventListEnvelope`/`AcsHealthEnvelope` (depends: T110, T111, T012; accept: T105, T106 pass).
- [X] T113 [US6] Register routes in the tenant group: `Route::get('/acs/gate-events', [GateEventsController::class,'index'])->middleware(['permission:acs.events.view,tenant']);` and `Route::get('/acs/health', [AcsHealthController::class,'index'])->middleware(['permission:acs.health.view,tenant']);` (depends: T112; accept: T105, T106, T107, T108 pass).
- [X] T114 [P] [US6] Create `resources/js/components/gate-events/GateEventRow.tsx` and `resources/js/components/acs-health/LaneHealthCard.tsx` — presentational only, rendering `AccessEvent`/lane-health shapes with localized reason text and an active-emergency banner (depends: T007; accept: `npm run typecheck` succeeds).
- [X] T115 [US6] Wire `resources/js/pages/tenant/gate-events/Index.tsx` and `resources/js/pages/tenant/acs-health/Index.tsx` (from T007) to poll `/acs/gate-events` and `/acs/health` on the same short fixed interval pattern as the Phase 3 kiosk-health page (open `resources/js/pages/tenant/kiosk/Index.tsx` first), rendering the T114 components (depends: T114, T113; accept: `npm run typecheck` and `npm run build` succeed).
- [X] T116 [US6] Run `php artisan test --group=acs-health`; fix any failing assertion from T105-T108 (depends: T105-T115; accept: exits 0).

**Checkpoint**: All six user stories are independently functional.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Cross-story verification, performance, parity, docs, and the
quickstart gate.

- [X] T117 [P] Create `tests/Integration/Security/Phase4IsolationSweepTest.php` (group `phase-4-isolation`) exercising cross-tenant/cross-event denial across every Phase 4 endpoint (zones, lanes, rules, integration credential, authorize, events, emergency, gate-events, health) plus the M2M capability confinement (a credential with only `authorize` cannot post events or emergencies) (depends: T053, T071, T081, T090, T104, T116; accept: `php artisan test --group=phase-4-isolation` exits 0).
- [X] T118 [P] Create `tests/Performance/GateAuthorizationLatencyTest.php` asserting the p50 `AuthorizeGateAction` decision (allow path, no admission check-in) completes within `config('acs.authorization.latency_budget_ms')` under a representative seeded rule set (SC-011) (depends: T048; accept: the test passes at the configured budget in the testing environment).
- [X] T119 [P] Add a deployment-parity test in `tests/Integration/` asserting authorization functions with the mock ACS marked unreachable (local-ACS/on-premise profile): a `fail_open` zone allows and a `fail_closed` zone denies, each recorded, with no outbound network call (depends: T048; accept: the test passes with the adapter forced unavailable).
- [X] T120 [P] Create the Phase 4 documentation set under `docs/`: ACS M2M integration protocol + credential rotation guide, zone/lane/rule + anti-passback runbook, emergency-egress runbook, and the permission/audit catalog additions (actions `acs_zone.*`, `acs_lane.*`, `acs_rule.*`, `access.*`, `acs_emergency.*`, `acs_integration.*`); include the explicit blocking assumption that the real Runa transport is unconfirmed (`all_plan.md` §38.2/§39.2) (depends: T053, T071, T081, T090, T104, T116; accept: `php artisan zonetec:docs:check` passes).
- [X] T121 Update `tests/Architecture/Phase4ModuleBoundaryTest.php` scope check (from T009) is still green after all code exists, and add an assertion that no `app/Modules/AccessControl/**` file adds a second credential-signature validation path (assert the only credential validation call is to `App\Modules\Credentials\Application\Validation\CredentialValidator::validate`) (depends: T048; accept: the test passes and fails if a second validator call site is introduced).
- [X] T122 Run the full Phase 4 quickstart (`specs/005-acs-access-control/quickstart.md`) steps 1-13 end to end (depends: T117-T121; accept: `composer run quality`, `npm run lint`, `npm run typecheck`, `npm run test`, and `npm run build` all exit 0, and every Phase 0/1/2/3 suite remains green alongside the new Phase 4 groups).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories.
- **US1 (Phase 3)**: Depends on Foundational. MVP part 1.
- **US2 (Phase 4)**: Depends on Foundational; complements US1 (MVP part 2).
- **US4 (Phase 5)**: Depends on Foundational + US1 (needs zones/lanes and the
  `AccessEvent` model/table from US1).
- **US3 (Phase 6)**: Depends on US4 (anti-passback state is derived from
  ingested entry/exit events).
- **US5 (Phase 7)**: Depends on US1 (edits `AuthorizeGateAction`).
- **US6 (Phase 8)**: Depends on US1 (reads `AccessEvent`s and lane health).
- **Polish (Phase 9)**: Depends on all desired user stories.

### Product Delivery Dependencies

Phase 4 builds strictly on the accepted Phase 0 foundation, Phase 1 signed
-credential core, Phase 2 scan decision order, and Phase 3 on-site increment.
It MUST NOT bypass their contracts or add a second credential trust path.

### Within Each User Story

- Required tests are written first and must FAIL before implementation.
- Migrations → models/factories → support services → actions → controllers →
  routes → frontend.
- Story complete before moving to the next.

### Parallel Opportunities

- All `[P]` Setup tasks run in parallel.
- All `[P]` Foundational tasks run in parallel within Phase 2.
- Within a story, all `[P]` test tasks run in parallel, and `[P]`
  migrations/models/factories owning different files run in parallel.
- US1 and US2 can be built in parallel by two developers after Foundational
  (US2's management endpoints and US1's integration endpoint own different
  files); they meet at the shared zone/lane/rule models (US1 owns those
  migrations/models, so US2 waits on T040-T045).

---

## Parallel Example: User Story 1

```bash
# Launch all US1 tests together (all [P], different files):
Task: "Schema test for acs_zones/lanes/rules in tests/Integration/MySql/AcsZoneLaneRuleSchemaTest.php"
Task: "Schema test for access_events in tests/Integration/MySql/AccessEventSchemaTest.php"
Task: "Unit test for AcsRuleEvaluator in tests/Unit/AccessControl/AcsRuleEvaluatorTest.php"
Task: "Unit test for AuthorizeGateAction in tests/Unit/AccessControl/AuthorizeGateActionTest.php"
Task: "Feature test for gate authorization API in tests/Feature/AccessControl/GateAuthorizationApiTest.php"

# Then launch the four migrations' models/factory together (after migrations):
Task: "Create AcsZone/AcsLane/AcsAuthorizationRule/AccessEvent models"
Task: "Create AcsZone/Lane/Rule/AccessEvent factories"
```

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories).
3. Complete Phase 3: US1 (gate authorization decision).
4. Complete Phase 4: US2 (operator configuration).
5. **STOP and VALIDATE**: operators configure zones/lanes/rules and the ACS
   receives correct allow/deny decisions with reasons.
6. Deploy/demo against the mock ACS if ready.

### Incremental Delivery

1. Setup + Foundational → foundation ready.
2. US1 + US2 → configurable gate authorization (MVP).
3. US4 → trustworthy entry/exit logging and reconciliation.
4. US3 → anti-passback enforcement.
5. US5 → emergency egress.
6. US6 → operator gate-events and health visibility.

### Parallel Team Strategy

After Foundational: Developer A takes US1 then US4/US3; Developer B takes US2
then US5; Developer C takes US6 once US1's `AccessEvent` model exists.

---

## Notes

- `[P]` tasks = different files, no dependencies on incomplete tasks.
- `[Story]` label maps each task to its specification user story for
  traceability; execution order reorders US4 before US3 by dependency.
- Verify each test fails before implementing its target.
- Commit after each task or logical group.
- Stop at any checkpoint to validate a story independently.
- Never add a Phase 5+ concept (identity verification, marketplace) or a
  second credential-signature validation path.
