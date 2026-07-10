# Tasks: Wallet Passes and QR Scanning

**Input**: Design documents from `specs/003-wallet-passes-scanning/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/`, `quickstart.md`

**Tests**: Mandatory and test-first. Each named test task must fail for the
expected missing behavior before its implementation task is completed.

**Organization**: Tasks are grouped by user story, in executable dependency
order: US1 → US2 → US3 → US4 → US5 (this also matches priority order
P1, P1, P2, P2, P3).

**Product Phase**: Phase 2 Wallet-Passes-And-Scanning

## How to execute this file (read this first)

These tasks are written so a small/cheap LLM can execute each one with
**zero additional lookups** in the common case:

- Every task names the exact file(s) to create or edit.
- Every migration/model task lists the exact column names, types, and enum
  values inline — do not re-derive them from `data-model.md`; copy them
  directly from the task text.
- Every task has `(depends: ...; accept: ...)`. `depends` lists task IDs
  that must be done first. `accept` is the exact, mechanically checkable
  condition that means the task is finished — usually a command to run or
  a specific behavior to verify.
- Do exactly one task at a time, in ID order within its phase, respecting
  `depends`. Do not skip ahead or combine tasks.
- If a task references a Phase 0/Phase 1 file or class, its exact path is
  given; open that exact file before writing new code that must match its
  conventions (naming, base classes, response envelopes).
- Never invent a Phase 3+ concept (kiosk, badge, manual desk, ACS zone/lane,
  anti-passback, identity verification, marketplace). If a column or enum
  value is marked "reserved", declare it but do not write any code path
  that produces or reads it in this phase.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel after listed dependencies because it owns
  different files.
- **[Story]**: Maps the task to the corresponding specification user story.
- Every task names an exact file or directory and includes a verifiable
  outcome.

## Ground-truth integration points (read before Phase 2/US2 tasks)

- The Phase 1 credential validator to call is
  `App\Modules\Credentials\Application\Validation\CredentialValidator::validate(string $token, ?string $expectedTenantId = null, ?string $expectedEventId = null): array`.
  It returns `['credential_id' => ..., 'status' => 'active', 'event_id' => ...]`
  on success, or throws `App\Modules\Shared\Http\Problems\Phase1Problem`
  with one of these codes on failure: `credential_invalid`,
  `credential_expired`, `credential_revoked`, `credential_superseded`. Do
  **not** use the deprecated `App\Modules\Credentials\Application\CredentialValidator`
  wrapper class.
- Map those Problem codes to scan results exactly as follows:
  `credential_expired` → scan result `expired`;
  `credential_revoked` → scan result `revoked`;
  `credential_invalid` and `credential_superseded` → scan result `rejected`
  (this deliberately collapses malformed/unknown/wrong-tenant/wrong-event/
  superseded into one generic `rejected` result so nothing about the real
  reason is disclosed to the scanning device, per FR-017/FR-018).
- Pass the scanning context's authenticated tenant ID and event ID as
  `$expectedTenantId`/`$expectedEventId` so cross-tenant/cross-event
  credentials fail inside the validator itself as `credential_invalid`,
  never as a separate branch of your own code.
- The existing Phase 1 attendee model is at
  `app/Modules/Attendees/Infrastructure/Persistence/Models/Attendee.php`.
- The existing Phase 1 audited-transaction helper, domain event dispatch
  pattern, and Problem-Details renderer are the same ones used throughout
  `specs/002-registration-ticketing-credentials/` — reuse them, do not
  create new equivalents.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Register Phase 2 modules, configuration, test groups,
localization, and contract tooling without changing product behavior.

- [X] T001 Register `App\Modules\WalletPasses\Providers\WalletPassesServiceProvider::class` and `App\Modules\Scanning\Providers\ScanningServiceProvider::class` in the `providers` array of `app/Providers/ModuleServiceProvider.php` (depends: Phase 0/1 complete; accept: `php artisan about` boots and lists both providers with zero errors).
- [X] T002 [P] Create empty provider classes `app/Modules/WalletPasses/Providers/WalletPassesServiceProvider.php` and `app/Modules/Scanning/Providers/ScanningServiceProvider.php`, each extending `Illuminate\Support\ServiceProvider` with empty `register()` and `boot()` methods (depends: none; accept: `composer dump-autoload` succeeds and both classes are autoloadable).
- [X] T003 [P] Create `config/wallet.php` returning an array with keys: `default_apple_adapter` (env `WALLET_APPLE_ADAPTER`, default `fake`), `default_google_adapter` (env `WALLET_GOOGLE_ADAPTER`, default `fake`), `apple.pass_type_identifier` (env `WALLET_APPLE_PASS_TYPE_IDENTIFIER`), `apple.team_identifier` (env `WALLET_APPLE_TEAM_IDENTIFIER`), `apple.certificate_secret_reference` (env `WALLET_APPLE_CERT_SECRET_REF`), `apple.private_key_secret_reference` (env `WALLET_APPLE_KEY_SECRET_REF`), `apple.web_service_base_url` (env `WALLET_APPLE_WEB_SERVICE_URL`), `google.issuer_id` (env `WALLET_GOOGLE_ISSUER_ID`), `google.service_account_secret_reference` (env `WALLET_GOOGLE_SERVICE_ACCOUNT_SECRET_REF`), `single_entry_default_enabled` (default `true`), `offline_allowlist_default_window_minutes` (default `240`) (depends: none; accept: `php artisan config:cache` succeeds).
- [X] T004 Add the nine new environment keys listed in T003 with synthetic test-only values to `.env.example` and `.env.testing`, setting both adapter keys to `fake` in `.env.testing` (depends: T003; accept: `php artisan zonetec:config:validate --env=testing` names every declared key without printing any value).
- [X] T005 [P] Add five new test groups (`wallet-passes`, `check-in`, `offline-scanning`, `phase-2-isolation`, `phase-2`) to `phpunit.xml` and create `tests/Support/Phase2MySqlTestCase.php` that extends the existing `tests/Support/MySqlTestCase.php` with no additional logic (depends: none; accept: `php artisan test --list-tests` runs without error).
- [X] T006 [P] Create `lang/en/phase2.php` and `lang/ar/phase2.php` with a matching key for each: `wallet_pass.added`, `wallet_pass.generation_failed`, `wallet_pass.revoked`, `scan.accepted`, `scan.duplicate`, `scan.revoked`, `scan.expired`, `scan.rejected`, `scan.manual_override`, `checkin.dashboard.title`. Add the same key set to `resources/js/locales/en.ts` and `resources/js/locales/ar.ts` (depends: none; accept: a simple PHP script/test comparing `array_keys()` of the two `lang/*/phase2.php` files shows zero difference).
- [X] T007 [P] Create `resources/js/types/phase2.ts` exporting TypeScript interfaces `WalletPass` (fields: id, provider, status, pass_url, last_pushed_at), `ScanEvent` (fields: id, result, reason, scanned_at), and `CheckInSummary` (fields: registered_count, checked_in_count, rejected_count, duplicate_count, last_scan_at). Create empty page shells `resources/js/pages/tenant/checkin/Dashboard.tsx` and `resources/js/pages/tenant/checkin/Scanner.tsx`, each exporting a default React component that renders only a heading (depends: none; accept: `npm run typecheck` succeeds).
- [X] T008 Add `"openapi:phase2": "redocly lint specs/003-wallet-passes-scanning/contracts/openapi.yaml"` to the `scripts` object in `package.json` (depends: none; accept: `npm run openapi:phase2` exits 0 with zero warnings).

**Checkpoint**: The application boots with Phase 2 scaffolding; no product behavior yet.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Permissions, policies, errors, and the wallet adapter contract
that every user story needs. No user-story work may start before this
phase is complete.

**CRITICAL**: Complete this phase before any user-story implementation.

- [X] T009 [P] Create `tests/Architecture/Phase2ModuleBoundaryTest.php` asserting, using the existing architecture-test conventions in `tests/Architecture/`: (a) no class outside `app/Modules/WalletPasses/**` references `App\Modules\WalletPasses\Infrastructure\*`, and no class outside `app/Modules/Scanning/**` references `App\Modules\Scanning\Infrastructure\*`; (b) no file anywhere under `app/`, `resources/js/`, `routes/`, or `database/` contains any of the literal strings `Kiosk`, `BadgePrint`, `ManualDesk`, `AntiPassback`, `AcsLane`, `IdentityVerification`, `Marketplace` (case-insensitive) (depends: T001; accept: the test passes immediately since no such code exists yet, and it must keep passing after every later task in this feature).
- [X] T010 [P] Create `tests/Feature/Authorization/Phase2PermissionMatrixTest.php` asserting that each of these six permission strings does not yet exist in the permission catalog: `wallet.pass.view`, `wallet.pass.generate`, `wallet.pass.manage`, `checkin.scan.submit`, `checkin.scan.override`, `checkin.dashboard.view` (depends: T005; accept: the test currently passes because the catalog is empty of these strings, and will be repurposed to allow/deny assertions once T011 lands).
- [X] T011 Add the six permissions from T010 to `database/seeders/PermissionCatalogSeeder.php`. Add a new "On-Site Staff" system-role template to `database/seeders/SystemRoleSeeder.php` granting only `checkin.scan.submit` and `checkin.dashboard.view`. Give the existing "System Tenant Administrator" role all six new permissions via the same idempotent role-update pattern already used for Phase 1 permissions in that seeder (depends: T010; accept: rerunning `php artisan db:seed --class=PermissionCatalogSeeder --env=testing` twice produces no duplicate rows, and rewrite T010 to assert all six permissions now exist and custom roles remain empty of them).
- [X] T012 Create policy classes in `app/Modules/Authorization/Policies/Phase2/` (one per permission group: `WalletPassPolicy.php`, `ScanPolicy.php`, `CheckInDashboardPolicy.php`) following the exact structure of an existing file in `app/Modules/Authorization/Policies/Phase1/`, and register them in `app/Modules/Authorization/Providers/AuthorizationServiceProvider.php` (depends: T011; accept: T010's rewritten allow/deny assertions pass for tenant, event, and action scope).
- [X] T013 [P] Create `tests/Contract/Phase2ProblemDetailsTest.php` asserting that each of these six error codes has an Arabic and an English message and appears in no test fixture alongside a provider name, certificate value, or PII field: `credential_not_active`, `wallet_provider_unavailable`, `wallet_pass_not_found`, `scan_context_invalid`, `override_reason_required`, `offline_batch_conflict` (depends: T008; accept: the test currently fails because these codes are not yet mapped).
- [X] T014 Add the six error codes from T013 to `app/Modules/Shared/Http/Problems/FoundationProblemRenderer.php` (or the exact file where Phase 1 added `credential_invalid`, `credential_expired`, etc. — open that file first and follow its pattern exactly) and add matching Arabic/English entries to the locale files it reads from (depends: T013; accept: T013 passes).
- [X] T015 [P] Create the wallet adapter contract in `app/Modules/WalletPasses/Contracts/WalletAdapter.php` (interface with methods `generate(WalletPassGenerationRequest $request): WalletAdapterResult`, `update(WalletPassUpdateRequest $request): WalletAdapterResult`, `revoke(WalletPassRevocationRequest $request): WalletAdapterResult`), plus request/result value objects in `app/Modules/WalletPasses/Domain/ValueObjects/` (`WalletPassGenerationRequest`, `WalletPassUpdateRequest`, `WalletPassRevocationRequest`) and `app/Modules/WalletPasses/Domain/Results/WalletAdapterResult.php` (fields: `status` one of `created|updated|revoked|unavailable|failed`, `passUrl` nullable string, `reasonCode` nullable string), matching the operations described in `contracts/wallet-adapter.md` (depends: T002; accept: `php artisan test --filter=WalletAdapterContractTest` can at least autoload these types with no fatal error, even though the test itself is written later in T025).
- [X] T016 [P] Implement `app/Modules/WalletPasses/Testing/FakeWalletAdapter.php` implementing `WalletAdapter` for both `apple` and `google` providers: `generate()` always succeeds with a deterministic fake `passUrl` unless the request's credential status is not `active` (return `failed` with reason `credential_not_active`); `update()`/`revoke()` always succeed and record the call in an in-memory array inspectable by tests via a public `calls()` method (depends: T015; accept: a throwaway unit test can call all three methods against the fake with no network access).
- [X] T017 [P] Create `app/Modules/Scanning/Contracts/ScanDecisionEvaluator.php` as an interface with one method `evaluate(ScanContext $context): ScanDecision`, plus `app/Modules/Scanning/Domain/ValueObjects/ScanContext.php` (fields: tenantId, eventId, scannerId, scannerType, qrPayload, override bool, overrideReason nullable) and `app/Modules/Scanning/Domain/Results/ScanDecision.php` (fields: result one of `accepted|manual_override|duplicate|revoked|expired|rejected`, reasonCode string, credentialId nullable, attendeeId nullable) (depends: T002; accept: types autoload with no fatal error; the concrete implementation is built later in T062).
- [X] T018 Add two new health-check categories, `apple_wallet` and `google_wallet`, to `app/Modules/Operations/Application/Health/Checks/` (open an existing Phase 1 check in that directory first and copy its structure) reporting `configured`/`degraded`/`unreachable` based on the resolved adapter and secret-reference presence, without ever reading the secret value itself (depends: T003; accept: `php artisan zonetec:config:validate --env=testing` output includes both new categories).
- [X] T019 Merge every operation from `specs/003-wallet-passes-scanning/contracts/openapi.yaml` into `specs/001-project-foundation/contracts/openapi.yaml` and `docs/api/openapi.yaml`, keeping `specs/003-wallet-passes-scanning/contracts/openapi.yaml` unchanged as review input (depends: T008; accept: `php scripts/sync-openapi.php --check` and `npx redocly lint specs/001-project-foundation/contracts/openapi.yaml` both pass).
- [X] T020 Register empty route files `app/Modules/WalletPasses/Routes/api.php` and `app/Modules/Scanning/Routes/api.php` (each containing only `<?php` and a `use Illuminate\Support\Facades\Route;` line, no routes yet), require them from `routes/api.php`, and add any new middleware aliases needed for Apple's `ApplePass` auth scheme as a named middleware alias `apple-pass-auth` (empty passthrough middleware for now) in `bootstrap/app.php` (depends: T001; accept: `php artisan route:list` runs with zero errors and shows no new routes yet).

**Checkpoint**: Permissions, policies, errors, and the wallet/scan contracts
exist; no story route performs product work yet.

---

## Phase 3: User Story 1 - Add Event Credential to Apple or Google Wallet (Priority: P1) 🎯 MVP

**Goal**: An attendee with an active credential can generate an Apple Wallet
pass and a Google Wallet pass containing the same QR credential.

**Independent Test**: Generate both pass types for a completed order with an
active credential; verify pass content and reject a non-active credential.

### Tests for User Story 1

- [X] T021 [P] [US1] Create `tests/Integration/MySql/WalletPassSchemaTest.php` asserting the `wallet_passes` table has columns `id, tenant_id, event_id, attendee_id, credential_id, provider, pass_serial_number, pass_url, status, last_pushed_at, last_push_reason_code, superseded_by_id, created_at, updated_at`, a unique index on `(tenant_id, provider, pass_serial_number)`, and that the `wallet_pass_apple_device_registrations` table has columns `id, tenant_id, wallet_pass_id, device_library_identifier, push_token, registered_at, unregistered_at` with a unique index on `(wallet_pass_id, device_library_identifier)` (depends: T005; accept: the test fails today because neither table exists).
- [X] T022 [P] [US1] Create `tests/Unit/WalletPasses/WalletPassLifecycleTest.php` asserting these exact transitions succeed: `created`→`active`, `active`→`updated`, `updated`→`active`, `active`→`revoked`, `updated`→`revoked`, `active`→`expired`, `updated`→`expired`, `created`→`failed`; and these fail: `revoked`→`active`, `expired`→`active`, `failed`→`active` (depends: T005; accept: the test fails today because no lifecycle service exists).
- [X] T023 [P] [US1] Create `tests/Unit/WalletPasses/ApplePassBuilderTest.php` asserting a built pass bundle's `pass.json` contains `webServiceURL`, a non-empty `authenticationToken`, the event name/date/location, the attendee name, the ticket type, a barcode whose message equals the raw credential token, and contains none of: national ID, biometric field, or payment card fields; and that `manifest.json` contains a SHA-1 or SHA-256 digest for every file in the bundle (depends: T005; accept: the test fails today because `ApplePassBuilder` does not exist).
- [X] T024 [P] [US1] Create `tests/Unit/WalletPasses/GoogleWalletObjectBuilderTest.php` asserting the built `GenericObject` JSON has `id` equal to `{issuer_id}.{object_suffix}`, `classId` equal to `{issuer_id}.{class_suffix}`, contains the same event/ticket/QR fields as the Apple test, and that the signed JWT decodes with the configured service-account key and produces a save link starting with `https://pay.google.com/gp/v/save/` (depends: T005; accept: the test fails today because `GoogleWalletObjectBuilder` does not exist).
- [X] T025 [P] [US1] Create `tests/Contract/Wallet/WalletAdapterContractTest.php` that runs the same 10-item assertion list from `contracts/wallet-adapter.md`'s "Contract Test Matrix" against `FakeWalletAdapter` (from T016), and add two more test classes in the same file/namespace that will run the identical assertion list against `AppleWalletAdapter` and `GoogleWalletAdapter` once T035/T037 exist (depends: T016; accept: the Fake-adapter assertions pass now; the Apple/Google assertions are marked `@group wallet-passes` and fail until T035/T037 land).
- [X] T026 [P] [US1] Create `tests/Contract/Phase2/PublicWalletApiTest.php` covering `GET /api/v1/public/orders/{public_reference}/wallet-passes/apple` and `GET /api/v1/public/orders/{public_reference}/wallet-passes/google` for their `200`, `404`, `409`, `422` responses exactly as documented in `contracts/openapi.yaml` (depends: T019; accept: the test fails today with 404 route-not-found).
- [X] T027 [P] [US1] Create `tests/Integration/Security/WalletPassIsolationTest.php` asserting that requesting a wallet pass for an order/credential belonging to a different tenant or event than the resolved public context returns the same response as an unknown `public_reference` (depends: T005; accept: the test fails today because the route does not exist).
- [X] T028 [P] [US1] Create `tests/Feature/WalletPasses/AppleWebServiceTest.php` covering: device registration returns `201` first time and `200` on repeat; unregistration returns `200`; updated-serial-numbers query returns `200` with the tag when passes changed and `204` when none changed; updated-pass fetch returns `200`/`304`; every one of these four endpoints returns `401` when the `ApplePass` authorization header does not match the pass's stored `authenticationToken` (depends: T020; accept: the test fails today with 404 route-not-found).

### Implementation for User Story 1

- [X] T029 [US1] Create migration `database/migrations/2026_07_06_000001_create_wallet_passes_table.php` creating table `wallet_passes` with: ULID primary key `id`; `tenant_id` (foreign to `tenants.id`); `event_id` (composite foreign to `events` on `tenant_id,id`); `attendee_id` (composite foreign to `attendees` on `tenant_id,id`); `credential_id` (composite foreign to `credentials` on `tenant_id,id`); `provider` string enum-checked to `apple`/`google`; `pass_serial_number` string; `pass_url` string nullable; `status` string enum-checked to `created`/`active`/`updated`/`revoked`/`expired`/`failed`, default `created`; `last_pushed_at` timestamp nullable; `last_push_reason_code` string nullable; `superseded_by_id` nullable self-referencing foreign key to `wallet_passes.id`; `created_at`/`updated_at`. Add unique index on `(tenant_id, provider, pass_serial_number)` and index on `(tenant_id, event_id, attendee_id, provider)` and `(tenant_id, credential_id)` (depends: T021; accept: T021's `wallet_passes` assertions pass).
- [X] T030 [US1] Create migration `database/migrations/2026_07_06_000002_create_wallet_pass_apple_device_registrations_table.php` creating table `wallet_pass_apple_device_registrations` with: ULID primary key `id`; `tenant_id` (foreign to `tenants.id`); `wallet_pass_id` (foreign to `wallet_passes.id`); `device_library_identifier` string; `push_token` string; `registered_at` timestamp; `unregistered_at` timestamp nullable. Add unique index on `(wallet_pass_id, device_library_identifier)` and index on `(tenant_id, wallet_pass_id, unregistered_at)` (depends: T021, T029; accept: T021's device-registration assertions pass).
- [X] T031 [P] [US1] Create `app/Modules/WalletPasses/Infrastructure/Persistence/Models/WalletPass.php` Eloquent model: fillable = all non-timestamp columns from T029; casts `status` and `provider` to string enums if enum classes exist in this codebase's convention (otherwise plain string), `last_pushed_at` to `datetime`; relations `attendee()` (belongsTo Attendee), `credential()` (belongsTo Credential), `supersededBy()` (belongsTo self on `superseded_by_id`); apply the same tenant-scoping trait used by Phase 1 models (open `app/Modules/Credentials/Infrastructure/Persistence/Models/Credential.php` first and copy its tenant-scope trait usage) (depends: T029; accept: a quick tinker/test query respects tenant scope automatically).
- [X] T032 [P] [US1] Create `app/Modules/WalletPasses/Infrastructure/Persistence/Models/WalletPassAppleDeviceRegistration.php` Eloquent model with fillable = all columns from T030, cast `registered_at`/`unregistered_at` to `datetime`, relation `walletPass()` (belongsTo WalletPass) (depends: T030; accept: T021 passes).
- [X] T033 [P] [US1] Create `database/factories/WalletPassFactory.php` and `database/factories/WalletPassAppleDeviceRegistrationFactory.php` producing valid rows linked to existing tenant/event/attendee/credential factories, with `status` defaulting to `active` and `provider` defaulting to `apple` (depends: T031, T032; accept: `WalletPass::factory()->create()` succeeds in a test).
- [X] T034 [US1] Create `app/Modules/WalletPasses/Infrastructure/Adapters/Apple/ApplePassBuilder.php` with a `build(array $passData): ApplePassBundle` method that produces: `pass.json` (fields: `passTypeIdentifier`, `teamIdentifier`, `serialNumber`, `webServiceURL` from `config('wallet.apple.web_service_base_url')`, `authenticationToken` (random 32+ char string), `organizationName`, `description`, event name/date/location, attendee name, ticket type, optional zone/tier label, a `barcodes` entry with `message` set to the raw credential token and `format` `PKBarcodeFormatQR`); `manifest.json` (SHA-1 or SHA-256 digest of every bundled file); and a PKCS#7 detached signature over `manifest.json` using OpenSSL, loading the certificate/key from the secret references named in `config('wallet.apple.certificate_secret_reference')` / `config('wallet.apple.private_key_secret_reference')` (depends: T023; accept: T023 passes).
- [X] T035 [US1] Create `app/Modules/WalletPasses/Infrastructure/Adapters/Apple/AppleWalletAdapter.php` implementing `WalletAdapter`: `generate()` calls `ApplePassBuilder::build()` and returns a `created` result with the bundle's `passUrl` (the fetch URL, not raw bytes); `update()` rebuilds the bundle with new data and sends an empty-payload APNs push (topic = `config('wallet.apple.pass_type_identifier')`, signed with the same certificate) to every row in `wallet_pass_apple_device_registrations` for that pass where `unregistered_at IS NULL`; `revoke()` marks content invalidated and performs the same push. Every method must return `WalletAdapterResult{status: 'failed', reasonCode: 'credential_not_active'}` immediately (no provider call) if the incoming request's credential status is not `active` (depends: T015, T034; accept: the Apple-adapter section of T025 passes).
- [X] T036 [US1] Create `app/Modules/WalletPasses/Infrastructure/Adapters/Google/GoogleWalletObjectBuilder.php`
- [X] T037 [US1] Create `app/Modules/WalletPasses/Infrastructure/Adapters/Google/GoogleWalletAdapter.php`
- [X] T038 [US1] Create `app/Modules/WalletPasses/Application/Actions/GenerateWalletPassAction.php`
- [X] T039 [US1] Create `app/Modules/WalletPasses/Http/Requests/GenerateWalletPassRequest.php`
- [X] T040 [US1] Create `app/Modules/WalletPasses/Http/Controllers/Public/ApplePassController.php`
- [X] T041 [US1] Create `app/Modules/WalletPasses/Http/Controllers/AppleWebService/RegisterDeviceController.php` and related Apple PassKit web service controllers
- [X] T042 [P] [US1] Create `resources/js/components/wallet/AddToWalletButtons.tsx`
- [X] T043 [US1] Create domain events `app/Modules/WalletPasses/Domain/Events/WalletPassGenerated.php`
- [X] T044 [US1] Run `php artisan test --group=wallet-passes`

**Checkpoint**: US1 is independently demonstrable — an attendee can add an
Apple and a Google wallet pass, and a non-active credential is rejected.

---

## Phase 4: User Story 2 - Validate Entry with a Staff QR Scan (Priority: P1)

**Goal**: Staff scan a QR code and get an authoritative accepted/duplicate/
revoked/expired/rejected result; a successful scan updates check-in state.

**Independent Test**: Scan a valid credential (accepted, once), scan it again
(duplicate), scan a revoked/expired credential (rejected with that reason).

### Tests for User Story 2

- [X] T045 [P] [US2]
- [X] T046 [P] [US2]
- [X] T047 [P] [US2]
- [X] T048 [P] [US2]
- [X] T049 [P] [US2]
- [X] T050 [P] [US2]
- [X] T051 [P] [US2]
- [X] T052 [P] [US2]
- [X] T053 [P] [US2]

### Implementation for User Story 2

- [X] T054 [US2] Create migration `database/migrations/2026_07_06_000005_create_event_check_in_settings_table.php`
- [X] T055 [US2] Create migration `database/migrations/2026_07_06_000006_create_scan_events_table.php`
- [X] T056 [US2] Create migration `database/migrations/2026_07_06_000007_create_event_check_in_summaries_table.php`
- [X] T057 [US2] Create migration `database/migrations/2026_07_06_000008_add_check_in_columns_to_attendees_table.php`
- [X] T058 [P] [US2] Create Eloquent models
- [X] T059 [P] [US2] Edit `app/Modules/Attendees/Infrastructure/Persistence/Models/Attendee.php`
- [X] T060 [P] [US2] Create factories
- [X] T061 [US2] Create `app/Modules/Scanning/Domain/SingleEntryEvaluator.php`
- [X] T062 [US2] Create `app/Modules/Scanning/Application/Actions/ScanDecisionEvaluatorImpl.php`
- [X] T063 [US2] Create `app/Modules/Scanning/Application/Actions/SubmitScanAction.php`
- [X] T064 [US2] Create `app/Modules/Scanning/Http/Requests/SubmitScanRequest.php`
- [X] T065 [US2] Create `app/Modules/Scanning/Http/Controllers/ScanController.php`
- [X] T066 [P] [US2] Create `resources/js/pages/tenant/checkin/Scanner.tsx`
- [X] T067 [US2] Create domain events and `ScanAuditListener`
- [X] T068 [US2] Run `php artisan test --group=check-in`

**Checkpoint**: US1 and US2 are both independently functional — this is the
MVP: attendees can add wallet passes and staff can authoritatively scan them.

---

## Phase 5: User Story 3 - Keep Wallet Passes Synchronized (Priority: P2)

**Goal**: Event-detail changes and credential revoke/reissue propagate to
issued wallet passes without attendee action.

**Independent Test**: Change an event's date/time/location and confirm an
update job targets affected passes; revoke a credential and confirm its
wallet pass is marked revoked and the adapter's revoke operation was called.

### Tests for User Story 3

- [X] T069 [P] [US3]
- [X] T070 [P] [US3]
- [X] T071 [P] [US3]
- [X] T072 [P] [US3]

### Implementation for User Story 3

- [X] T073 [US3]
- [X] T074 [US3]
- [X] T075 [US3]
- [X] T076 [US3]
- [X] T077 [US3]
- [X] T078 [US3]
- [X] T079 [US3]

**Checkpoint**: US1, US2, US3 all independently functional.

---

## Phase 6: User Story 4 - Monitor Real-Time Check-In Counts (Priority: P2)

**Goal**: An authorized organizer views live registration/check-in counts
for their event.

**Independent Test**: As scans are accepted, an authorized organizer's
dashboard count increases within the documented short polling delay; a
different tenant/event's data never appears.

### Tests for User Story 4

- [X] T080 [P] [US4]
- [X] T081 [P] [US4]
- [X] T082 [P] [US4]
- [X] T083 [P] [US4]

### Implementation for User Story 4

- [X] T084 [US4]
- [X] T085 [US4]
- [X] T086 [US4]
- [X] T087 [P] [US4]
- [X] T088 [US4]
- [X] T089 [US4]

**Checkpoint**: US1-US4 all independently functional.

---

## Phase 7: User Story 5 - Continue Scanning During a Connectivity Loss (Priority: P3)

**Goal**: A documented, testable offline-tolerant scanning design: signed
allowlist export, local dedupe, and conflict-aware reconciliation.

**Independent Test**: Fetch an allowlist, submit an offline batch, and
confirm two independently-offline-accepted scans of one credential
reconcile to exactly one `accepted` plus a flagged conflict.

### Tests for User Story 5

- [X] T090 [P] [US5]
- [X] T091 [P] [US5]
- [X] T092 [P] [US5]
- [X] T093 [P] [US5]
- [X] T094 [P] [US5]

### Implementation for User Story 5

- [X] T095 [US5]
- [X] T096 [P] [US5]
- [X] T097 [US5]
- [X] T098 [US5]
- [X] T099 [US5]
- [X] T100 [US5]
- [X] T101 [US5]
- [X] T102 [US5]
- [X] T103 [US5]

**Checkpoint**: All five user stories are independently functional.

---

## Final Phase: Polish & Cross-Cutting Concerns

- [X] T104 [P]
- [X] T105 [P]
- [X] T106 [P]
- [X] T107
- [X] T108
- [X] T109 [P]
- [X] T110
- [X] T111
- [X] T112
- [X] T113
- [X] T114

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories.
- **User Stories (Phase 3-7)**: All depend on Foundational completion.
  - US1 (Phase 3) has no dependency on any other story.
  - US2 (Phase 4) has no dependency on any other story.
  - US3 (Phase 5) depends on US1 (wallet passes must exist to synchronize).
  - US4 (Phase 6) depends on US2 (dashboard reads scan-produced data).
  - US5 (Phase 7) depends on US2 (reconciliation reuses the scan decision evaluator).
- **Polish (Final Phase)**: Depends on every user story you choose to ship.

### Product Delivery Dependencies

- **Foundation (Phase 0)**: Tenant isolation, RBAC, audit, versioned APIs,
  adapter interfaces — already accepted.
- **Phase 1 (accepted)**: Events, ticketing/orders, attendees, and the
  signed credential lifecycle — the sole source of truth this phase reuses.
- **This phase (Phase 2)**: Wallet passes and scanning. Kiosk, badge,
  manual desk, ACS zone/lane/anti-passback, identity verification, and
  marketplace (Phase 3+) MUST NOT be built here and MUST depend on this
  phase's accepted contracts once their own plans exist.

### User Story Dependencies

- **US1 (P1)**: Start after Foundational. No dependency on other stories.
- **US2 (P1)**: Start after Foundational. No dependency on other stories.
- **US3 (P2)**: Start after US1 is complete.
- **US4 (P2)**: Start after US2 is complete.
- **US5 (P3)**: Start after US2 is complete.

### Within Each User Story

- Tests are written first and must fail before their matching
  implementation task is started.
- Migrations before models before factories before domain/application
  services before HTTP layer before frontend.
- Story complete (its own test-group command exits 0) before moving to a
  dependent story.

### Parallel Opportunities

- All Setup tasks marked `[P]` can run in parallel.
- All Foundational tasks marked `[P]` can run in parallel.
- Once Foundational is done, US1 and US2 can be worked in parallel by
  different people/agents; US3 must wait for US1, US4 and US5 must wait
  for US2.
- All test tasks marked `[P]` within one story can run in parallel.
- All model/factory tasks marked `[P]` within one story can run in
  parallel.

---

## Parallel Example: User Story 1

```bash
# Launch all US1 tests together (after Foundational is done):
Task: "Create tests/Integration/MySql/WalletPassSchemaTest.php"
Task: "Create tests/Unit/WalletPasses/WalletPassLifecycleTest.php"
Task: "Create tests/Unit/WalletPasses/ApplePassBuilderTest.php"
Task: "Create tests/Unit/WalletPasses/GoogleWalletObjectBuilderTest.php"
Task: "Create tests/Contract/Phase2/PublicWalletApiTest.php"
Task: "Create tests/Integration/Security/WalletPassIsolationTest.php"
Task: "Create tests/Feature/WalletPasses/AppleWebServiceTest.php"

# Launch the two independent models together once T029/T030 land:
Task: "Create app/Modules/WalletPasses/Infrastructure/Persistence/Models/WalletPass.php"
Task: "Create app/Modules/WalletPasses/Infrastructure/Persistence/Models/WalletPassAppleDeviceRegistration.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 + User Story 2 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (blocks everything).
3. Complete Phase 3: User Story 1 (wallet pass generation).
4. Complete Phase 4: User Story 2 (staff scanning).
5. **STOP and VALIDATE**: run `php artisan test --group=wallet-passes` and
   `php artisan test --group=check-in` independently; both must exit 0.
6. This is the deployable MVP: attendees can add wallet passes and staff can
   authoritatively scan them, even before sync/dashboard/offline exist.

### Incremental Delivery

1. Setup + Foundational → foundation ready.
2. Add US1 → validate independently → deployable.
3. Add US2 → validate independently → deployable MVP.
4. Add US3 → validate independently → wallet sync live.
5. Add US4 → validate independently → dashboard live.
6. Add US5 → validate independently → offline design + reference
   implementation live.
7. Final Phase → polish, regression, and full quality gate.

---

## Notes

- `[P]` tasks touch different files and have no unfinished dependency.
- `[Story]` labels map every user-story-phase task back to `spec.md`.
- Every migration task lists exact columns; do not add or omit a column
  without updating `data-model.md` first.
- Never introduce a second credential trust path: every wallet and scan
  decision must call back into the Phase 1 `CredentialValidator` described
  in "Ground-truth integration points" above.
- Commit after each task or small logical group.
- Stop at any `**Checkpoint**` to validate a story independently before
  continuing.
