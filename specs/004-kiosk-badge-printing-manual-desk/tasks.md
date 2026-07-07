# Tasks: Kiosk Check-In, Badge Printing, and Manual Desk

**Input**: Design documents from `specs/004-kiosk-badge-printing-manual-desk/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/`, `quickstart.md`

**Tests**: Mandatory and test-first. Each named test task must fail for the
expected missing behavior before its implementation task is completed.

**Organization**: Tasks are grouped by user story, in executable dependency
order: US1 → US2 → US3 → US4 → US5 → US6 (priority order P1, P1, P2, P2, P2, P3).

**Product Phase**: Phase 3 Kiosk-Badge-Printing-Manual-Desk

## How to execute this file (read this first)

These tasks are written so a small/cheap LLM can execute each one with
**zero additional lookups** in the common case:

- Every task names the exact file(s) to create or edit.
- Every migration/model task lists the exact column names, types, and enum
  values inline — copy them directly from the task text, do not re-derive
  them from `data-model.md`.
- Every task has `(depends: ...; accept: ...)`. `depends` lists task IDs
  that must be done first. `accept` is the exact, mechanically checkable
  condition that means the task is finished.
- Do exactly one task at a time, in ID order within its phase, respecting
  `depends`. Do not skip ahead or combine tasks.
- Every task that reuses an existing Phase 0/1/2 class names its exact path
  in the "Ground-truth integration points" section below or inline in the
  task; open that exact file before writing new code that must match its
  conventions (naming, base classes, response envelopes, audit pattern).
- Never invent a Phase 4+ concept (ACS zone/lane, anti-passback, gate
  authorization, identity verification, venue marketplace). If a value is
  marked "reserved" in a Phase 2 file, declare/read it but do not add new
  meaning to it in this phase.
- Never add a second way to validate a credential's signature. The only
  new validation entry point this phase adds is `CredentialValidator::
  validateById()` (T024), which performs the exact same status/expiry/
  scope checks as `validate()` but is keyed by an already-resolved
  `credential_id` instead of a raw signed token — it never re-implements
  or bypasses signature verification.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel after listed dependencies because it owns
  different files.
- **[Story]**: Maps the task to the corresponding specification user story.
- Every task names an exact file or directory and includes a verifiable
  outcome.

## Ground-truth integration points (read before Foundational/US1+ tasks)

- **Scan decision core** (`app/Modules/Scanning/Application/Actions/
  SubmitScanAction.php`, unchanged in signature): `execute(ScanContext
  $context, ?ScanDecision $forcedDecision = null): ScanSubmission`. Every
  kiosk/desk check-in calls this exact class with a new `scannerType`
  (`kiosk` or `manual_desk`) and `scannerId` (kiosk id or staff user id).
  Do not fork or duplicate this class.
- **`ScanContext`** (`app/Modules/Scanning/Domain/ValueObjects/
  ScanContext.php`) gains an optional `credentialId` field (T024) so a
  lookup-resolved check-in (no raw QR) can be submitted. Exactly one of
  `qrPayload`/`credentialId` must be non-empty per request.
- **`ScanDecisionEvaluatorImpl`** (`app/Modules/Scanning/Application/
  Actions/ScanDecisionEvaluatorImpl.php`) gains a branch (T025): empty
  `qrPayload` + non-null `credentialId` → calls the new
  `CredentialValidator::validateById()` instead of `validate()`. The
  duplicate/override/audit logic below that point is completely unchanged.
- **`CredentialValidator`** (`app/Modules/Credentials/Application/
  Validation/CredentialValidator.php`) gains `validateById(string
  $credentialId, string $tenantId, string $eventId): array` (T024),
  returning the exact same shape as `validate()`
  (`['credential_id'=>...,'status'=>'active','event_id'=>...]`) and
  throwing the exact same `Phase1Problem` codes (`credential_invalid`,
  `credential_expired`, `credential_{status}`) by reading the `Credential`
  row directly instead of verifying a signed token. Do **not** use the
  deprecated `App\Modules\Credentials\Application\CredentialValidator`
  wrapper class.
- **Credential revoke/reissue for reprint** (`app/Modules/Credentials/
  Application/Actions/ReissueCredential.php`):
  `execute(TenantContext $context, string $eventId, string $credentialId,
  string $reason): IssuedCredential`. `ReprintBadgeAction` (T098) calls
  this unmodified when `reprint_revokes_old_qr` is enabled; never write a
  second revoke/reissue path.
- **Walk-up registration reuses Phase 1 registration exactly**:
  - Free ticket: `App\Modules\Orders\Application\Actions\
    CompleteFreeRegistration::execute(FreeRegistrationInput $input):
    CompletedRegistration` (open `app/Modules/Orders/Domain/
    FreeRegistrationInput.php` for its exact constructor fields:
    `tenantId, eventId, formVersionId, ticketTypeId, idempotencyKey,
    answers, consent, buyer, attendee, locale, credentialExpiresAt`).
  - Paid ticket with on-site payment enabled: `App\Modules\Orders\
    Application\Actions\StartPaidRegistration::execute(FreeRegistrationInput
    $input): CompletedRegistration` (creates a `pending_payment` order),
    then `App\Modules\Orders\Contracts\OrderPaymentPort::completeCaptured(
    string $orderId, string $paymentAccountId, int $capturedMinor, string
    $currency, bool $live): PaidOrderResult` with `$paymentAccountId =
    'on_site'` and `$live = false`. `App\Modules\Orders\Application\
    Actions\CompletePaidRegistration` is the bound implementation of
    `OrderPaymentPort` — inject the interface, not the class.
  - Never call `App\Modules\Attendees\Contracts\AttendeeCreator` or
    `App\Modules\Credentials\Contracts\CredentialIssuer` directly; always
    go through one of the two Orders actions above so ticket inventory,
    submission records, and notifications stay consistent with standard
    registration.
- **Audit pattern** (copy exactly, do not invent a new one):
  1. A mutation happens inside `App\Modules\Audit\Application\
     AuditedTransaction::run(callable $mutation, callable $afterCommit)`
     (see `SubmitScanAction`) or a plain `DB::transaction()` (see
     `ReissueCredential`, `CompleteFreeRegistration`).
  2. The mutation dispatches a domain event (e.g. `event(new
     BadgePrintJobPrinted(...))`).
  3. A dedicated listener class in `app/Modules/Audit/Application/
     Listeners/Phase3/` (mirror `app/Modules/Audit/Application/Listeners/
     Phase2/WalletPassAuditListener.php` and `ScanAuditListener.php`
     exactly) injects `App\Modules\Audit\Contracts\AuditWriter` and calls
     `$this->audit->write('tenant', $tenantId, '<action>', '<outcome>',
     actor: ..., reasonCode: ..., targetType: ..., targetId: ..., metadata:
     [...])`.
  4. The listener is registered in the owning module's
     `ServiceProvider::boot()` via `Event::listen(SomeEvent::class,
     [ListenerClass::class, 'handleSomething']);` (see
     `WalletPassesServiceProvider::boot()`).
  5. For an action that can be *blocked before any row is created* (e.g. a
     reprint denied for missing permission/reason), there is no domain
     event to hang a listener off; call `AuditWriter::write(...)` directly
     inside the action for that blocked branch, exactly once per attempt.
- **Permission/authorization pattern** (copy exactly): one policy class
  per phase at `app/Modules/Authorization/Policies/Phase{N}/
  Phase{N}Policy.php` with a `public const ABILITIES = ['ability' =>
  'permission.key']` map and one `allows(User $user, string $ability):
  bool` method reading `TenantContextStore::currentOrNull()` (see
  `app/Modules/Authorization/Policies/Phase2/Phase2Policy.php`). Register
  it in `app/Modules/Authorization/Providers/
  AuthorizationServiceProvider.php` the same way Phase 2's policy is
  registered there.
- **Stable error pattern** (copy exactly): one class per phase at
  `app/Modules/Shared/Http/Problems/Phase{N}Problem.php` with a `public
  const STATUS = ['code' => httpStatus]` array and a `make(string $code):
  FoundationException` static method resolving the detail message from
  `__("phase{N}.{$code}")` (see `app/Modules/Shared/Http/Problems/
  Phase2Problem.php`).
- **Route/middleware pattern** (copy exactly): tenant staff routes use
  `->middleware(['auth:sanctum', 'throttle:phase1-organizer',
  'tenant.context.clear', 'tenant.context'])` at the group level, then
  `->middleware(['permission:<key>,tenant', 'idempotency'])` per route
  (idempotency only on state-changing POSTs) — see
  `app/Modules/Scanning/Routes/api.php`. New kiosk device routes use a new
  `['kiosk.session.clear', 'kiosk.session']` pair instead of the tenant
  pair (T021/T081), never `auth:sanctum` (the caller is a device, not a
  logged-in user).
- **Controller response pattern**: use the `RespondsWithApi` trait
  (`app/Modules/Shared/Http/Responses/RespondsWithApi.php`)'s `$this->
  success($data, $status)` method for every success response, exactly as
  `ScanController` does.
- **Context-store pattern** for the new kiosk session (copy exactly from
  `app/Modules/Tenancy/Domain/Context/TenantContextStore.php`): a
  request-scoped singleton with `bind()` (throws on rebind/mismatch),
  `current()` (throws if unbound), `currentOrNull()`, `clear()`.
- **Existing Phase 1/2 files you will open but not modify unless a task
  says so**: `app/Modules/Scanning/Http/Controllers/ScanController.php`,
  `app/Modules/Scanning/Http/Requests/SubmitScanRequest.php`,
  `app/Modules/Attendees/Application/Queries/OrganizerAttendeeQuery.php`
  (copy its decrypt-and-filter pattern for the new lookup query),
  `app/Modules/WalletPasses/Testing/FakeWalletAdapter.php` (copy its
  `calls()`-introspection pattern for `FakePrinterAdapter`).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Register Phase 3 modules, configuration, test groups,
localization, and contract tooling without changing product behavior.

- [X] T001 Register `App\Modules\Kiosk\Providers\KioskServiceProvider::class` and `App\Modules\BadgePrinting\Providers\BadgePrintingServiceProvider::class` in the `providers` array of `app/Providers/ModuleServiceProvider.php` (depends: Phase 0/1/2 complete; accept: `php artisan about` boots and lists both providers with zero errors).
- [X] T002 [P] Create empty provider classes `app/Modules/Kiosk/Providers/KioskServiceProvider.php` and `app/Modules/BadgePrinting/Providers/BadgePrintingServiceProvider.php`, each extending `Illuminate\Support\ServiceProvider` with empty `register()` and `boot()` methods (depends: none; accept: `composer dump-autoload` succeeds and both classes are autoloadable).
- [X] T003 [P] Create `config/printing.php` returning an array with keys: `default_printer_adapter` (env `PRINTER_ADAPTER`, default `fake`), `kiosk.session_secret_length` (default `40`), `kiosk.session_ttl_hours` (env `KIOSK_SESSION_TTL_HOURS`, default `168`), `kiosk.default_offline_threshold_seconds` (default `120`), `lookup.confirmation_code_ttl_seconds` (default `300`), `lookup.max_matches` (default `8`), `notifications.lookup_confirmation_channel` (default `email`) (depends: none; accept: `php artisan config:cache` succeeds).
- [X] T004 Add the seven new environment keys from T003 with synthetic test-only values to `.env.example` and `.env.testing`, setting `PRINTER_ADAPTER=fake` in `.env.testing` (depends: T003; accept: `php artisan zonetec:config:validate --env=testing` names every declared key without printing any value).
- [X] T005 [P] Add eight new test groups (`kiosk`, `manual-desk`, `badge-printing`, `badge-reprint`, `walk-up-registration`, `kiosk-health`, `phase-3-isolation`, `phase-3`) to `phpunit.xml` and create `tests/Support/Phase3MySqlTestCase.php` that extends the existing `tests/Support/MySqlTestCase.php` with no additional logic (depends: none; accept: `php artisan test --list-tests` runs without error).
- [X] T006 [P] Create `lang/en/phase3.php` and `lang/ar/phase3.php` with a matching key for each of: `kiosk_session_invalid`, `kiosk_session_unconfirmed`, `kiosk_retired`, `lookup_too_many_matches`, `lookup_confirmation_required`, `lookup_confirmation_invalid`, `badge_template_not_active`, `badge_template_invalid_field`, `badge_reprint_reason_required`, `badge_reprint_not_permitted`, `badge_no_prior_print_job`, `badge_print_not_permitted`, `printer_unavailable`, `printer_error`, `payload_rejected`, `checkin_desk_not_permitted`, `walk_up_registration_disabled`, `walk_up_payment_not_collectible`, `kiosk.paired`, `badge_print.printed`, `walk_up_attendee.registered`. Add the same key set to `resources/js/locales/en.ts` and `resources/js/locales/ar.ts` (depends: none; accept: a PHP script/test comparing `array_keys()` of the two `lang/*/phase3.php` files shows zero difference).
- [X] T007 [P] Create `resources/js/types/phase3.ts` exporting TypeScript interfaces `Kiosk` (fields: id, device_name, device_code, status, printer_status, last_heartbeat_at, confirmation_required), `BadgeTemplate` (fields: id, name, layout, paper_size, printer_type, status), `BadgePrintJob` (fields: id, status, failure_reason, is_reprint, reprint_reason, original_print_job_id, printed_at), and a `BADGE_TEMPLATE_ALLOWED_FIELDS` string-literal-union/array constant listing exactly: `attendee_name`, `company`, `job_title`, `qr`, `ticket_type`, `tier`, `zone`, `sponsor_logo_ref`, `organizer_logo_ref`, `color_code`. Create empty page shells `resources/js/pages/tenant/kiosk/Index.tsx`, `resources/js/pages/tenant/badge-templates/Designer.tsx`, and `resources/js/pages/tenant/manual-desk/Desk.tsx`, each exporting a default React component that renders only a heading (depends: none; accept: `npm run typecheck` succeeds).
- [X] T008 Add `"openapi:phase3": "redocly lint specs/004-kiosk-badge-printing-manual-desk/contracts/openapi.yaml"` to the `scripts` object in `package.json` (depends: none; accept: `npm run openapi:phase3` exits 0 with zero warnings).
- [X] T009 [P] Register empty route files `app/Modules/Kiosk/Routes/api.php` and `app/Modules/BadgePrinting/Routes/api.php` (each containing only `<?php` and a `use Illuminate\Support\Facades\Route;` line, no routes yet), and require them from `routes/api.php` (depends: T001; accept: `php artisan route:list` runs with zero errors and shows no new routes yet).
- [X] T010 [P] Create `tests/Architecture/Phase3ModuleBoundaryTest.php` asserting, using the existing architecture-test conventions in `tests/Architecture/`: (a) no class outside `app/Modules/Kiosk/**` references `App\Modules\Kiosk\Infrastructure\*`, and no class outside `app/Modules/BadgePrinting/**` references `App\Modules\BadgePrinting\Infrastructure\*`; (b) no file anywhere under `app/`, `resources/js/`, `routes/`, or `database/` contains any of the literal strings `AcsLane`, `AntiPassback`, `IdentityVerification`, `Marketplace`, `GateAuthorization` (case-insensitive) (depends: T001; accept: the test passes immediately since no such code exists yet, and it must keep passing after every later task in this feature).

**Checkpoint**: The application boots with Phase 3 scaffolding; no product behavior yet.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Permissions, policies, errors, the printer adapter contract,
the kiosk session context/middleware skeleton, and the shared attendee
lookup query that every user story needs. No user-story work may start
before this phase is complete.

**CRITICAL**: Complete this phase before any user-story implementation.

- [X] T011 [P] Create `tests/Feature/Authorization/Phase3PermissionMatrixTest.php` asserting that each of these seven permission strings does not yet exist in the permission catalog: `kiosk.manage`, `kiosk.health.view`, `checkin.desk.perform`, `badge.print`, `badge.reprint`, `badge.template.manage`, `attendee.walkup.register` (depends: T005; accept: the test currently passes because the catalog is empty of these strings, and will be rewritten in T012 to allow/deny assertions).
- [X] T012 Add the seven permissions from T011 to `database/seeders/PermissionSeeder.php`'s `definitions()` array with: `kiosk.manage` (module `kiosk`, risk `sensitive`), `kiosk.health.view` (module `kiosk`, risk `standard`), `checkin.desk.perform` (module `scanning`, risk `sensitive`), `badge.print` (module `badge-printing`, risk `standard`), `badge.reprint` (module `badge-printing`, risk `privileged`), `badge.template.manage` (module `badge-printing`, risk `sensitive`), `attendee.walkup.register` (module `attendees`, risk `sensitive`) — all `scope` `tenant`. Extend `database/seeders/SystemRoleSeeder.php`'s `'On-Site Staff'` permission-key array (inside the per-tenant `$roles` map) to also include `checkin.desk.perform` (depends: T011; accept: rerunning `php artisan db:seed --class=PermissionSeeder --env=testing` twice produces no duplicate rows; rewrite T011 to assert all seven permissions now exist, the `'Tenant Administrator'` tenant role has all seven (it auto-inherits every `scope=tenant` permission id per `SystemRoleSeeder`'s existing logic), `'On-Site Staff'` has `checkin.desk.perform`, and custom roles remain empty of them).
- [X] T013 Create `app/Modules/Authorization/Policies/Phase3/Phase3Policy.php` following the exact structure of `app/Modules/Authorization/Policies/Phase2/Phase2Policy.php` (open that file first), with `public const ABILITIES = ['manageKiosk' => 'kiosk.manage', 'viewKioskHealth' => 'kiosk.health.view', 'performDeskCheckIn' => 'checkin.desk.perform', 'printBadge' => 'badge.print', 'reprintBadge' => 'badge.reprint', 'manageBadgeTemplate' => 'badge.template.manage', 'registerWalkUpAttendee' => 'attendee.walkup.register']`. Register it in `app/Modules/Authorization/Providers/AuthorizationServiceProvider.php` the same way `Phase2Policy` is registered there (depends: T012; accept: T011's rewritten allow/deny assertions pass for tenant, event, and action scope).
- [X] T014 [P] Create `tests/Contract/Phase3ProblemDetailsTest.php` asserting that each of the 15 error codes listed in T006 (excluding the three catalog-key-only entries `kiosk.paired`, `badge_print.printed`, `walk_up_attendee.registered`) plus `checkin_desk_not_permitted`, `walk_up_registration_disabled`, and `walk_up_payment_not_collectible` has an Arabic and an English message and appears in no test fixture alongside a printer connection secret, kiosk session secret, or PII field (depends: T006; accept: the test currently fails because these codes are not yet mapped to HTTP statuses).
- [X] T015 Create `app/Modules/Shared/Http/Problems/Phase3Problem.php` following the exact structure of `app/Modules/Shared/Http/Problems/Phase2Problem.php` (open that file first) with `public const STATUS` mapping: `kiosk_session_invalid` => 401, `kiosk_session_unconfirmed` => 401, `kiosk_retired` => 401, `lookup_too_many_matches` => 422, `lookup_confirmation_required` => 422, `lookup_confirmation_invalid` => 422, `badge_template_not_active` => 409, `badge_template_invalid_field` => 422, `badge_reprint_reason_required` => 422, `badge_reprint_not_permitted` => 403, `badge_no_prior_print_job` => 409, `badge_print_not_permitted` => 403, `printer_unavailable` => 503, `printer_error` => 409, `payload_rejected` => 422, `checkin_desk_not_permitted` => 403, `walk_up_registration_disabled` => 403, `walk_up_payment_not_collectible` => 422; `make()` returns a `FoundationException` built the same way `Phase2Problem::make()` does, with detail from `__("phase3.{$code}")` (depends: T014; accept: T014 passes).
- [X] T016 [P] Create the printer adapter contract: `app/Modules/BadgePrinting/Contracts/PrinterAdapter.php` (interface with `print(PrintPayload $payload): PrintResult` and `health(): PrinterHealthResult`); `app/Modules/BadgePrinting/Domain/ValueObjects/PrintPayload.php` (readonly, fields: `array $fields` keyed by allowed field name to `string|null`, `string $paperSize`, `string $printerType`, `string $idempotencyKey`); `app/Modules/BadgePrinting/Domain/Results/PrintResult.php` (readonly, fields: `string $status` one of `printed|failed`, `?string $reasonCode`, `?string $confirmationReference`); `app/Modules/BadgePrinting/Domain/Results/PrinterHealthResult.php` (readonly, fields: `string $status` one of `ready|error|disconnected|unknown`, `?string $reasonCode`) (depends: T002; accept: all four types autoload with no fatal error).
- [X] T017 [P] Implement `app/Modules/BadgePrinting/Testing/FakePrinterAdapter.php` implementing `PrinterAdapter`: `print()` returns `PrintResult{status:'printed', reasonCode:null, confirmationReference:<deterministic fake string>}` unless `$payload->fields['__force_failure'] ?? null` is one of `unavailable`|`error`|`payload_rejected`, in which case return `PrintResult{status:'failed', reasonCode:'printer_'.$forced}` (map `payload_rejected` to reasonCode `payload_rejected` literally); `health()` returns `PrinterHealthResult{status:'ready'}` unless a public `forceHealth(string $status, ?string $reasonCode = null): void` method was previously called on the same instance, in which case return that forced value; every call is recorded in an in-memory array inspectable via a public `calls(): array` method — copy this introspection pattern from `app/Modules/WalletPasses/Testing/FakeWalletAdapter.php` (open it first) (depends: T016; accept: a throwaway unit test can call `print()` and `health()` against the fake with no network access and read back recorded calls).
- [X] T018 Create `app/Modules/BadgePrinting/Providers/BadgePrintingServiceProvider.php`'s `register()`: bind `PrinterAdapter::class` via `$this->app->bind(PrinterAdapter::class, fn ($app) => match (config('printing.default_printer_adapter', 'fake')) { default => $app->make(FakePrinterAdapter::class) });` (only the `fake` branch exists this phase; do not add an `apple`/`google`-style branch for a printer vendor that has no approved adapter yet) (depends: T017; accept: `app(PrinterAdapter::class)` resolves to `FakePrinterAdapter` in the `testing` environment).
- [X] T019 [P] Create `app/Modules/Kiosk/Domain/ValueObjects/KioskSessionContext.php` (readonly, fields: `string $tenantId, string $eventId, string $kioskId, bool $confirmed`) and `app/Modules/Kiosk/Domain/Context/KioskSessionContextStore.php` mirroring the exact `bind()`/`current()`/`currentOrNull()`/`clear()` structure of `app/Modules/Tenancy/Domain/Context/TenantContextStore.php` (open that file first): `bind(KioskSessionContext $context)` throws `FoundationException::forbidden('kiosk_context_rebind', ...)` if already bound in this request; `current()` throws `FoundationException::forbidden('kiosk_context_required', ...)` if unbound (depends: T002; accept: types autoload and a throwaway unit test can bind/read/clear the store).
- [X] T020 Register `app/Modules/Kiosk/Domain/Context/KioskSessionContextStore.php` as a singleton in `app/Modules/Kiosk/Providers/KioskServiceProvider.php`'s `register()` via `$this->app->singleton(KioskSessionContextStore::class);` (depends: T019; accept: `app(KioskSessionContextStore::class)` returns the same instance across two resolutions within one request).
- [X] T021 Create middleware skeletons `app/Modules/Kiosk/Http/Middleware/ResolveKioskSession.php` and `app/Modules/Kiosk/Http/Middleware/ClearKioskSession.php` (the latter mirroring `App\Modules\Tenancy\Http\Middleware\ClearTenantContext` exactly — just calls `KioskSessionContextStore::clear()` then `$next($request)`). For `ResolveKioskSession`, this task only creates the class shape (constructor injecting `KioskSessionContextStore`, a `handle(Request $request, Closure $next): Response` method that currently always throws `Phase3Problem::make('kiosk_session_invalid')` — full lookup logic against the not-yet-existing `KioskSession` model is added in T081). Register both as aliases `'kiosk.session' => ResolveKioskSession::class` and `'kiosk.session.clear' => ClearKioskSession::class` in `bootstrap/app.php`'s `$middleware->alias([...])` array, next to the existing `'tenant.context'` entries (depends: T020, T015; accept: `php artisan route:list` runs with zero errors and the two middleware classes autoload).
- [X] T022 Add two new health-check categories, `badge_printer` and `kiosk_fleet`, to `app/Modules/Operations/Application/Health/Checks/` (open an existing Phase 1/2 check file in that directory first and copy its structure) reporting `configured`/`degraded`/`unreachable` for the printer adapter and a placeholder zero-count `kiosk_fleet` summary (real counts are wired in T135), without ever reading a connection secret (depends: T003; accept: `php artisan zonetec:config:validate --env=testing` output includes both new categories).
- [X] T023 Merge every operation from `specs/004-kiosk-badge-printing-manual-desk/contracts/openapi.yaml` into `specs/001-project-foundation/contracts/openapi.yaml` and `docs/api/openapi.yaml`, and extend the existing `submitScan` operation's `scanner_type` request-schema enum to add `manual_desk` (kiosk check-in stays on the separate `/kiosk/v1/scans` path documented in the merged contract and is **not** added to this enum), keeping `specs/004-kiosk-badge-printing-manual-desk/contracts/openapi.yaml` unchanged as review input (depends: T008; accept: `php scripts/sync-openapi.php --check` and `npx redocly lint specs/001-project-foundation/contracts/openapi.yaml` both pass).
- [X] T024 Add credential-id-based (no-QR) validation support: edit `app/Modules/Scanning/Domain/ValueObjects/ScanContext.php` changing the constructor's `public string $qrPayload` parameter to `public string $qrPayload = ''` and adding a new parameter `public ?string $credentialId = null` immediately after it. Add a new public method to `app/Modules/Credentials/Application/Validation/CredentialValidator.php`: `validateById(string $credentialId, string $tenantId, string $eventId): array` that runs `Credential::query()->where('tenant_id', $tenantId)->where('event_id', $eventId)->find($credentialId)`, throws `Phase1Problem::make('credential_invalid')` if null, throws `Phase1Problem::make('credential_expired')` if `$credential->expires_at->isPast()`, throws `Phase1Problem::make('credential_'.$credential->status)` if `status !== 'active'`, and otherwise returns `['credential_id' => $credential->id, 'status' => 'active', 'event_id' => $credential->event_id]` — the exact same return shape as `validate()` (depends: none; accept: a new `tests/Unit/Credentials/CredentialValidatorValidateByIdTest.php` passes for active/expired/revoked/not-found fixtures).
- [X] T025 Edit `app/Modules/Scanning/Application/Actions/ScanDecisionEvaluatorImpl.php`'s `evaluate()` method: if `$context->qrPayload !== ''`, call `$this->credentials->validate(...)` exactly as today; otherwise (when `$context->qrPayload === ''` and `$context->credentialId !== null`) call `$this->credentials->validateById($context->credentialId, $context->tenantId, $context->eventId)` and apply the exact same `try/catch (FoundationException)` result mapping already in the method. Every line below the credential-resolution `try/catch` block (single-entry evaluation, decision construction) stays unmodified (depends: T024; accept: a new `tests/Unit/Scanning/ScanDecisionEvaluatorCredentialIdPathTest.php` passes: an active credential referenced by id alone yields `accepted`, a revoked one yields `revoked`, an expired one yields `expired`, an unknown id yields `rejected`).
- [X] T026 [P] Create `tests/Unit/Scanning/LookupAttendeesQueryTest.php` asserting a not-yet-existing `App\Modules\Scanning\Application\Queries\LookupAttendeesQuery::search(string $tenantId, string $eventId, string $fragment, int $maxMatches): array` returns matches scoped to tenant/event only; for an email- or phone-shaped `$fragment` it resolves via the existing `BlindIndex::email()`/`BlindIndex::phone()` exact-match lookup first; otherwise it performs a bounded name search fetching at most `$maxMatches + 1` candidate rows (to detect "too many" without an unbounded `COUNT` query) and decrypts only that bounded page — copy this decrypt-and-filter pattern from `app/Modules/Attendees/Application/Queries/OrganizerAttendeeQuery.php` (open it first); each returned match has `attendee_id`, the attendee's current non-revoked non-superseded `credential_id` (or `null`), decrypted display name, ticket type label, and `checkin_status`; when more than `$maxMatches` rows are found, return an array with a `too_many` boolean `true` and an empty match list instead of a partial list (depends: T005; accept: the test fails today because the class does not exist).
- [X] T027 Create `app/Modules/Scanning/Application/Queries/LookupAttendeesQuery.php` implementing exactly the contract asserted in T026 (depends: T026; accept: T026 passes).
- [X] T028 [P] Create `tests/Integration/Security/Phase3ModuleBoundaryQueryTest.php` asserting no class outside `app/Modules/Scanning/**` references the `App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee`, `App\Modules\Credentials\Infrastructure\Persistence\Models\Credential`, or `App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent` classes directly for lookup purposes — every later `Kiosk` and manual-desk controller must call `LookupAttendeesQuery`, never those models directly (depends: T027; accept: the test passes now since no kiosk/desk controller exists yet; it must keep passing after every later task in this feature).

**Checkpoint**: Permissions, policies, errors, the printer adapter, the
kiosk session context/middleware skeleton, and the shared lookup query
exist; no story route performs product work yet.

---

## Phase 3: User Story 1 - Staff Operate the Manual Desk for Check-In and Badge Printing (Priority: P1) 🎯 MVP

**Goal**: Authorized staff search for an attendee by name/email/phone or
QR, check them in, and print their badge, without a kiosk device.

**Independent Test**: Search for an attendee, see their credential and
check-in status, check them in (duplicate/revoked/expired rejected exactly
like Phase 2 staff scanning), and print a badge from the event's active
template (created via factory for this story's tests; the no-code designer
UI/API ships in US5).

### Tests for User Story 1

- [X] T029 [P] [US1] Create `tests/Integration/MySql/BadgeTemplateSchemaTest.php` asserting the `badge_templates` table has columns `id, tenant_id, event_id, name, layout, paper_size, printer_type, status, created_at, updated_at`, a `status` check constraint allowing only `draft|active|inactive`, and an index on `(tenant_id, event_id, status)` (depends: T005; accept: the test fails today because the table does not exist).
- [X] T030 [P] [US1] Create `tests/Integration/MySql/BadgePrintJobSchemaTest.php` asserting the `badge_print_jobs` table has columns `id, tenant_id, event_id, attendee_id, credential_id, badge_template_id, kiosk_id, printed_by_user_id, status, failure_reason, is_reprint, reprint_reason, original_print_job_id, printed_at, created_at, updated_at`, a `status` check constraint allowing only `queued|printed|failed`, composite foreign keys tying `attendee_id`/`credential_id`/`badge_template_id` to their tables on `(tenant_id, event_id, id)`, and indexes on `(tenant_id, event_id, attendee_id, created_at)` and `(tenant_id, event_id, status)` (depends: T005; accept: the test fails today because the table does not exist).
- [X] T031 [P] [US1] Create `tests/Unit/BadgePrinting/BadgeLayoutValidatorTest.php` asserting a not-yet-existing `App\Modules\BadgePrinting\Application\Support\BadgeLayoutValidator::validate(array $layout): void` throws `Phase3Problem::make('badge_template_invalid_field')` when any field key in the layout is outside the fixed allowlist (`attendee_name, company, job_title, qr, ticket_type, tier, zone, sponsor_logo_ref, organizer_logo_ref, color_code`), and does not throw for a layout using only allowlisted keys (depends: T015; accept: the test fails today because the class does not exist).
- [X] T032 [P] [US1] Create `tests/Unit/BadgePrinting/CreateBadgePrintJobActionTest.php` asserting a not-yet-existing `App\Modules\BadgePrinting\Application\Actions\CreateBadgePrintJobAction::execute(...)`: (a) throws `Phase3Problem::make('badge_template_not_active')` and creates no `BadgePrintJob` row when the target event has zero `active` templates; (b) with an active template, creates a `BadgePrintJob` whose rendered payload contains only the fields present in that template's `layout` and none of the fields it omits; (c) calls the bound `PrinterAdapter` (the `FakePrinterAdapter` from T017) and sets `status = 'printed'` on success or `status = 'failed'` with the adapter's `reasonCode` on forced failure (depends: T018; accept: the test fails today because the class does not exist).
- [X] T033 [P] [US1] Create `tests/Feature/Scanning/ManualDeskLookupTest.php` covering `POST /api/v1/tenant/events/{event_id}/desk/lookups`: returns matches scoped to the caller's tenant/event only; returns `too_many_matches: true` semantics for an overly broad fragment (per T026's bound); is denied with `403` for a user lacking `checkin.desk.perform`; matches the `AttendeeLookupEnvelope` schema in `contracts/openapi.yaml` (depends: T023; accept: the test fails today with 404 route-not-found).
- [X] T034 [P] [US1] Create `tests/Feature/Scanning/ManualDeskCheckInTest.php` covering `POST /api/v1/tenant/events/{event_id}/scans` with `scanner_type: 'manual_desk'` and `credential_id` (no `qr_payload`): succeeds and returns `accepted` for a user with `checkin.desk.perform`; returns `403 checkin_desk_not_permitted` for a user who only has `checkin.scan.submit`; a second submission for the same credential returns `duplicate`; the created `ScanEvent` row has `scanner_type = 'manual_desk'` and `scanner_id` equal to the acting user's id (depends: T023; accept: the test fails today because `manual_desk` is not yet an accepted `scanner_type` and `credential_id` is not yet an accepted field).
- [X] T035 [P] [US1] Create `tests/Feature/BadgePrinting/CreateBadgePrintJobApiTest.php` covering `POST /api/v1/tenant/events/{event_id}/badge-print-jobs`: `201` with a `BadgePrintJobEnvelope` body for an authorized `badge.print` user against an event with an active template; `403` without the permission; `422 badge_template_not_active` when no template is active; unknown request fields rejected (depends: T023; accept: the test fails today with 404 route-not-found).
- [X] T036 [P] [US1] Create `tests/Integration/Security/BadgePrintingIsolationTest.php` asserting that a desk lookup, desk check-in, or badge-print-job request naming an attendee/credential/template belonging to a different tenant or event than the caller's authenticated context returns the exact same response as an unknown target (depends: T005; accept: the test fails today because the routes do not exist).
- [X] T037 [P] [US1] Create `tests/Integration/Security/Phase3AuditAtomicityTest.php` with (for now) two cases: a forced audit-write failure during a manual-desk check-in leaves zero `ScanEvent`/`Attendee.checkin_status` change, and a forced audit-write failure during badge-print-job creation leaves zero `BadgePrintJob` row (use the same forced-failure fixture technique as the existing Phase 2 audit-atomicity test — open it first and copy the pattern) (depends: T005; accept: the test fails today because the actions do not exist).
- [X] T038 [P] [US1] Create `tests/Contract/Phase3/ManualDeskApiTest.php` running every success and principal `401/403/404/422` case documented in `contracts/openapi.yaml` for the `lookupAttendeeAtDesk` and `createBadgePrintJob` operations (depends: T023; accept: the test fails today with 404 route-not-found).

### Implementation for User Story 1

- [X] T039 [US1] Create migration `database/migrations/2026_07_06_000011_create_badge_templates_table.php` creating table `badge_templates` with: ULID primary key `id`; `tenant_id` (foreign to `tenants.id`); `event_id` (composite foreign to `events` on `tenant_id,id`); `name` string(120); `layout` json; `paper_size` string(40); `printer_type` string(40); `status` string(20) default `draft`; `created_at`/`updated_at`(6). Add a check constraint `badge_templates_status_chk CHECK (status IN ('draft','active','inactive'))` and an index on `(tenant_id, event_id, status)`. Add a code comment noting "at most one active template per event is enforced in `ActivateBadgeTemplateAction`, not a DB constraint" (depends: T029; accept: T029's `badge_templates` assertions pass).
- [X] T040 [US1] Create migration `database/migrations/2026_07_06_000012_create_badge_print_jobs_table.php` creating table `badge_print_jobs` with: ULID primary key `id`; `tenant_id` (foreign to `tenants.id`); `event_id` (composite foreign to `events` on `tenant_id,id`); `attendee_id` (composite foreign to `attendees` on `tenant_id,event_id,id`); `credential_id` (composite foreign to `credentials` on `tenant_id,event_id,id`); `badge_template_id` (composite foreign to `badge_templates` on `tenant_id,event_id,id`); `kiosk_id` char(26) nullable, **no foreign key yet** (the `kiosks` table does not exist until T069; the FK is added in T071); `printed_by_user_id` char(26) nullable, foreign to `users.id`, nullable-on-delete not required (leave default restrict); `status` string(20) default `queued`; `failure_reason` string(60) nullable; `is_reprint` boolean default `false`; `reprint_reason` string(500) nullable; `original_print_job_id` char(26) nullable, self-referencing foreign key to `badge_print_jobs.id`; `printed_at` timestamp(6) nullable; `created_at`/`updated_at`(6). Add a check constraint `badge_print_jobs_status_chk CHECK (status IN ('queued','printed','failed'))` and indexes on `(tenant_id, event_id, attendee_id, created_at)` and `(tenant_id, event_id, status)` (depends: T030, T039; accept: T030's assertions pass for every listed column and index except the `kiosk_id` FK, which T030 must not assert yet).
- [X] T041 [P] [US1] Create `app/Modules/BadgePrinting/Infrastructure/Persistence/Models/BadgeTemplate.php` and `app/Modules/BadgePrinting/Infrastructure/Persistence/Models/BadgePrintJob.php` Eloquent models: fillable = all non-timestamp columns from T039/T040 respectively; casts `layout` to `array` on `BadgeTemplate`; casts `printed_at` to `datetime` and `is_reprint` to `boolean` on `BadgePrintJob`; relations `badgeTemplate()` (belongsTo BadgeTemplate) and `originalPrintJob()` (belongsTo self on `original_print_job_id`) on `BadgePrintJob` (depends: T039, T040; accept: a quick tinker/test query against each model succeeds).
- [X] T042 [P] [US1] Create `database/factories/BadgeTemplateFactory.php` (defaults: `status = 'active'`, `layout = ['attendee_name' => [], 'qr' => [], 'ticket_type' => []]`, `paper_size = 'a6'`, `printer_type = 'fake'`) and `database/factories/BadgePrintJobFactory.php` (defaults: `status = 'queued'`, `is_reprint = false`), both linked to existing tenant/event/attendee/credential/badge-template factories (depends: T041; accept: `BadgeTemplate::factory()->create()` and `BadgePrintJob::factory()->create()` both succeed in a test).
- [X] T043 [US1] Create `app/Modules/BadgePrinting/Application/Support/BadgeLayoutValidator.php` per T031's contract: `validate(array $layout): void` iterates `array_keys($layout)` and throws `Phase3Problem::make('badge_template_invalid_field')` on the first key not in the fixed allowlist listed in T031 (depends: T031; accept: T031 passes).
- [X] T044 [US1] Create `app/Modules/BadgePrinting/Application/Actions/RenderBadgePrintPayloadAction.php`: `execute(string $tenantId, string $eventId, string $attendeeId, string $credentialId, BadgeTemplate $template): PrintPayload` looks up only the attendee/credential/ticket-type fields the template's `layout` actually references (reuse the exact `PersonalDataCipher`-based decrypt pattern from `SubmitScanAction::resolveDisplayFields()` — open that method first — for `attendee_name`; the raw QR field is the credential's existing signed token, never re-derived); returns a `PrintPayload` with `paperSize = $template->paper_size`, `printerType = $template->printer_type` (depends: T016, T041; accept: a unit test confirms a template with only `attendee_name`+`qr` produces a payload with exactly those two field keys).
- [X] T045 [US1] Create `app/Modules/BadgePrinting/Application/Actions/CreateBadgePrintJobAction.php` per T032's contract: resolves the event's single `active` `BadgeTemplate` (throws `badge_template_not_active` via `Phase3Problem::make()` if none, before creating any row); calls `RenderBadgePrintPayloadAction`; inside `App\Modules\Audit\Application\AuditedTransaction::run()` (copy the exact pattern from `SubmitScanAction::execute()`) creates the `BadgePrintJob` row (`status = 'queued'`, `kiosk_id`/`printed_by_user_id` from constructor-passed context, `is_reprint = false`), calls `PrinterAdapter::print()`, and force-fills `status`/`failure_reason`/`printed_at` from the result; the after-commit callback dispatches one of the domain events created in T046 (depends: T032, T043, T044, T018; accept: T032 passes).
- [X] T046 [US1] Create domain events `app/Modules/BadgePrinting/Domain/Events/BadgePrintJobCreated.php`, `BadgePrintJobPrinted.php`, `BadgePrintJobFailed.php` (each readonly, fields: `string $tenantId, string $eventId, string $badgePrintJobId, string $attendeeId, ?string $reasonCode`) (depends: T002; accept: types autoload with no fatal error).
- [X] T047 [US1] Create `app/Modules/Audit/Application/Listeners/Phase3/BadgePrintAuditListener.php` mirroring `app/Modules/Audit/Application/Listeners/Phase2/WalletPassAuditListener.php`'s exact structure (open it first), with `handleCreated`/`handlePrinted`/`handleFailed` methods writing actions `badge_print.created`/`badge_print.printed`/`badge_print.failed`, `targetType = 'badge_print_job'`, `targetId = $event->badgePrintJobId`, `metadata = ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId]`. Register the three listeners in `app/Modules/BadgePrinting/Providers/BadgePrintingServiceProvider.php`'s `boot()` (depends: T046; accept: creating a `BadgePrintJob` via T045 produces exactly one matching audit row).
- [X] T048 [US1] Create `app/Modules/BadgePrinting/Http/Requests/CreateBadgePrintJobRequest.php` (rules: `attendee_id` required string, `credential_id` required string; add a `withValidator` unknown-fields check copying the exact pattern from `app/Modules/Scanning/Http/Requests/SubmitScanRequest.php`) and `app/Modules/BadgePrinting/Http/Resources/BadgePrintJobResource.php` (exposes exactly the fields in the `BadgePrintJob` schema in `contracts/openapi.yaml`) and `app/Modules/BadgePrinting/Http/Controllers/BadgePrintJobController.php::store` which checks `Phase3Policy::allows($user, 'printBadge')` (`abort(403)` per `ScanController`'s pattern if not) and calls `CreateBadgePrintJobAction` with `printedByUserId = $user->id`, `kioskId = null` (depends: T045, T048 request/resource; accept: T035 passes).
- [X] T049 [US1] Register the route in `app/Modules/BadgePrinting/Routes/api.php`: `Route::prefix('tenant/events/{event_id}')->middleware(['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context'])->group(...)` containing `Route::post('/badge-print-jobs', [BadgePrintJobController::class, 'store'])->middleware(['permission:badge.print,tenant', 'idempotency']);` (copy the exact group/middleware shape from `app/Modules/Scanning/Routes/api.php`, open it first) (depends: T048; accept: T035 and T038 pass).
- [X] T050 [P] [US1] Create `app/Modules/Scanning/Http/Requests/AttendeeLookupRequest.php` (rules: `qr_payload` sometimes string max:512, `query` sometimes string min:2 max:120, `confirmation_code` sometimes string max:12; a `withValidator` rule requiring exactly one of `qr_payload`/`query` to be present) and `app/Modules/Scanning/Http/Resources/AttendeeLookupResource.php` matching the `AttendeeLookupEnvelope` schema in `contracts/openapi.yaml` (depends: T002; accept: `npm run typecheck`-equivalent PHP static check / a quick unit test instantiates both with no error).
- [X] T051 [US1] Create `app/Modules/Scanning/Http/Controllers/ManualDesk/LookupController.php::store`: checks `Phase3Policy::allows($user, 'performDeskCheckIn')` (`throw Phase3Problem::make('checkin_desk_not_permitted')` if not); when `qr_payload` is present, calls `App\Modules\Credentials\Application\Validation\CredentialValidator::validate()` and maps its result/exception to a lookup-style single-match response without recording any `ScanEvent`; when `query` is present, calls `LookupAttendeesQuery::search($tenantId, $eventId, $query, config('printing.lookup.max_matches', 8))` (note: read the max-matches value from `config('printing.lookup.max_matches')` — add this exact nested key if T003 wrote it as `lookup.max_matches`, keep the config file structure consistent) and returns `too_many_matches: true` when the query reports it (depends: T027, T050, T013; accept: T033 passes).
- [X] T052 [US1] Register the route in `app/Modules/Scanning/Routes/api.php` (append to the existing file, do not replace its current Phase 2 routes): `Route::post('/desk/lookups', [\App\Modules\Scanning\Http\Controllers\ManualDesk\LookupController::class, 'store'])->middleware(['permission:checkin.desk.perform,tenant']);` inside the same `tenant/events/{event_id}` group already defined in that file (depends: T051; accept: T033 and T038 pass).
- [X] T053 [US1] Edit `app/Modules/Scanning/Http/Requests/SubmitScanRequest.php`: change the `scanner_type` rule to `Rule::in(['staff_phone', 'handheld_scanner', 'manual_desk'])`; change `qr_payload` from `['required', 'string', 'max:512']` to `['sometimes', 'string', 'max:512']`; add `'credential_id' => ['sometimes', 'string']`; add to the `unknown` fields allowlist in `withValidator`; add a new `withValidator` rule requiring exactly one of `qr_payload`/`credential_id` to be non-empty when present (depends: none; accept: a unit test posting `scanner_type: 'manual_desk', credential_id: '...'` with no `qr_payload` passes validation, and posting neither fails validation).
- [X] T054 Add `'checkin_desk_not_permitted' => 403` to `app/Modules/Shared/Http/Problems/Phase3Problem.php`'s `STATUS` map if T015 did not already include it (it does — this task only verifies and is a no-op if already present; skip silently if T015's map already has the key) (depends: T015; accept: `Phase3Problem::make('checkin_desk_not_permitted')` returns a 403 exception).
- [X] T055 [US1] Edit `app/Modules/Scanning/Http/Controllers/ScanController.php::store`: when `$request->string('scanner_type')->toString() === 'manual_desk'`, check `Phase3Policy::allows($user, 'performDeskCheckIn')` instead of `Phase2Policy::allows($user, 'submitScan')`, throwing `Phase3Problem::make('checkin_desk_not_permitted')` on failure (inject `Phase3Policy` via the constructor alongside the existing `Phase2Policy`); build the `ScanContext` passing `qrPayload: $request->string('qr_payload')->toString()` and the new `credentialId: $request->input('credential_id')` parameter (both may be present per T053's request rules, only one will be non-empty in practice) (depends: T053, T054, T024, T013; accept: T034 passes).
- [X] T056 [P] [US1] Create `resources/js/components/manual-desk/AttendeeLookupPanel.tsx` and `resources/js/components/manual-desk/CheckInResultPanel.tsx` — presentational components only, rendering the shapes returned by `AttendeeLookupResource`/`ScanResultResource`; no data-fetching logic inside these components (depends: T007; accept: `npm run typecheck` succeeds).
- [X] T057 [US1] Wire `resources/js/pages/tenant/manual-desk/Desk.tsx` (from T007) to call `/desk/lookups` and `/scans` (`scanner_type: 'manual_desk'`) using the existing fetch/axios wrapper used elsewhere in `resources/js/pages/tenant` — open `resources/js/pages/tenant/checkin/Scanner.tsx` first and copy its data-fetching pattern; render `AttendeeLookupPanel`/`CheckInResultPanel` from T056 (depends: T056, T052, T055; accept: `npm run typecheck` and `npm run build` both succeed).
- [X] T058 [US1] Run `php artisan test --group=manual-desk` and `php artisan test --group=badge-printing`; fix any failing assertion from T029-T038 (depends: T029-T057; accept: both commands exit 0).

**Checkpoint**: US1 is independently demonstrable — staff can look up an
attendee, check them in at the manual desk, and print their badge from a
factory-seeded active template.

---

## Phase 4: User Story 2 - Attendee Self-Service Check-In and Badge Print at a Kiosk (Priority: P1)

**Goal**: A paired, healthy kiosk can scan or look up an attendee, check
them in under the same rules as US1, and print a badge, unattended.

**Independent Test**: Pair a kiosk, present a valid QR (or look up by
name), confirm accepted/duplicate/revoked/expired behave exactly like US1,
and confirm a printed badge results from an accepted/manual_override check-in.

### Tests for User Story 2

- [X] T059 [P] [US2] Create `tests/Integration/MySql/KioskSchemaTest.php` asserting the `kiosks` table has columns `id, tenant_id, event_id, device_name, device_code, location_label, status, printer_status, last_heartbeat_at, confirmation_required, confirmation_code_hash, retired_at, created_at, updated_at` with a unique index on `(tenant_id, event_id, device_code)`, a `status` check constraint allowing only `registered|online|offline|degraded|retired`, and a `printer_status` check constraint allowing only `unknown|ready|error|disconnected`; and that `kiosk_sessions` has columns `id, tenant_id, kiosk_id, secret_hash, confirmed_at, expires_at, revoked_at, created_at` with an index on `(tenant_id, kiosk_id, revoked_at)`; and that `badge_print_jobs.kiosk_id` now has a foreign key to `kiosks.id` (depends: T005; accept: the test fails today because neither table exists and the FK is absent).
- [X] T060 [P] [US2] Create `tests/Unit/Kiosk/KioskSessionLifecycleTest.php` asserting: pairing a kiosk returns a raw secret whose hash matches the stored `secret_hash`, and the raw value cannot be recovered from the stored row; pairing a second time revokes the first session (`revoked_at` set, non-null) while the new session remains valid (depends: T005; accept: fails today, `PairKioskAction` does not exist).
- [X] T061 [P] [US2] Create `tests/Unit/Kiosk/KioskSessionConfirmationTest.php` asserting: when `Kiosk.confirmation_required = true`, a freshly paired session is rejected for lookup/scan/print with `kiosk_session_unconfirmed` until `ConfirmKioskSessionAction` succeeds with the correct code (hashed match against `confirmation_code_hash`), and rejected with `lookup_confirmation_invalid`-equivalent (`kiosk_session_unconfirmed` persists) for a wrong code; once confirmed, the session remains confirmed for its remaining lifetime (depends: T005; accept: fails today).
- [X] T062 [P] [US2] Create `tests/Contract/Kiosk/KioskSessionAuthMiddlewareTest.php` asserting `ResolveKioskSession` (from T081) returns `401 kiosk_session_invalid` for an unknown/malformed/revoked/expired session secret, `401 kiosk_retired` when the owning `Kiosk.status = 'retired'` even if the session row itself is not yet expired, and `401 kiosk_session_unconfirmed` for an unconfirmed session on any route except the confirmation route (depends: T021; accept: fails today because `ResolveKioskSession` only ever throws `kiosk_session_invalid`).
- [X] T063 [P] [US2] Create `tests/Feature/Kiosk/KioskHeartbeatTest.php` covering `POST /api/v1/kiosk/v1/heartbeat`: updates `last_heartbeat_at` and `printer_status`; a kiosk whose `last_heartbeat_at` is older than `EventCheckInSetting.kiosk_offline_threshold_seconds` is derived `offline` by `KioskStatusDeriver`; a `printer_status = 'error'` heartbeat derives `degraded` even with a fresh heartbeat (depends: T005; accept: fails today, route does not exist).
- [X] T064 [P] [US2] Create `tests/Feature/Kiosk/KioskLookupAndScanTest.php` covering `POST /api/v1/kiosk/v1/lookups` and `POST /api/v1/kiosk/v1/scans`: QR-based and name/email/phone-based lookup both resolve to the same accepted/duplicate/revoked/expired/rejected outcome via `POST /kiosk/v1/scans`; an overly broad name fragment returns `lookup_too_many_matches`; when `EventCheckInSetting.lookup_confirmation_required = true`, a name/email/phone match requires a verified one-time code before the scan is accepted, and the code is delivered through the existing notification adapter fake (assert on the fake's recorded sends, not a real provider call) (depends: T005; accept: fails today).
- [X] T065 [P] [US2] Create `tests/Feature/Kiosk/KioskBadgePrintTest.php` covering `POST /api/v1/kiosk/v1/badge-print-jobs`: succeeds only after an `accepted`/`manual_override` check-in for the same attendee/credential in the current kiosk session; the created `BadgePrintJob.kiosk_id` equals the authenticated kiosk's id and `printed_by_user_id` is null (depends: T005; accept: fails today).
- [X] T066 [P] [US2] Create `tests/Integration/Security/KioskIsolationTest.php` asserting a kiosk session's lookup/scan/print requests naming (directly or via a resolved credential) a different tenant or event than the kiosk's own registration are rejected identically to an unknown target, and that a retired kiosk's still-unexpired session is rejected for every operation (depends: T005; accept: fails today).
- [X] T067 [P] [US2] Create `tests/Contract/Phase3/KioskManagementApiTest.php` running every success and principal `401/403/404/422` case documented in `contracts/openapi.yaml` for `registerKiosk`, `listKiosks`, `pairKiosk`, `retireKiosk` (depends: T023; accept: fails today with 404 route-not-found).
- [X] T068 [P] [US2] Extend `tests/Integration/Security/Phase3AuditAtomicityTest.php` (from T037) with three more cases: a forced audit-write failure during kiosk pairing leaves zero `KioskSession` row, during a kiosk heartbeat-derived status change leaves the prior status unchanged, and during a kiosk check-in leaves zero `ScanEvent`/checkin-state change (depends: T037; accept: fails today because the actions do not exist).

### Implementation for User Story 2

- [X] T069 [US2] Create migration `database/migrations/2026_07_06_000013_create_kiosks_table.php` creating table `kiosks` with: ULID primary key `id`; `tenant_id` (foreign to `tenants.id`); `event_id` (composite foreign to `events` on `tenant_id,id`); `device_name` string(120); `device_code` string(40); `location_label` string(160) nullable; `status` string(20) default `registered`; `printer_status` string(20) default `unknown`; `last_heartbeat_at` timestamp(6) nullable; `confirmation_required` boolean default `false`; `confirmation_code_hash` string(255) nullable; `retired_at` timestamp(6) nullable; `created_at`/`updated_at`(6). Add check constraints `kiosks_status_chk CHECK (status IN ('registered','online','offline','degraded','retired'))` and `kiosks_printer_status_chk CHECK (printer_status IN ('unknown','ready','error','disconnected'))`; add a unique index on `(tenant_id, event_id, device_code)` and an index on `(tenant_id, event_id, status)` (depends: T059; accept: T059's `kiosks` assertions pass).
- [X] T070 [US2] Create migration `database/migrations/2026_07_06_000014_create_kiosk_sessions_table.php` creating table `kiosk_sessions` with: ULID primary key `id`; `tenant_id` (foreign to `tenants.id`); `kiosk_id` (composite foreign to `kiosks` on `tenant_id,id`); `secret_hash` string(255); `confirmed_at` timestamp(6) nullable; `expires_at` timestamp(6); `revoked_at` timestamp(6) nullable; `created_at`(6). Add an index on `(tenant_id, kiosk_id, revoked_at)` (depends: T059, T069; accept: T059's `kiosk_sessions` assertions pass).
- [X] T071 [US2] Create migration `database/migrations/2026_07_06_000015_add_kiosk_id_foreign_key_to_badge_print_jobs_table.php` running `Schema::table('badge_print_jobs', fn (Blueprint $table) => $table->foreign('kiosk_id')->references('id')->on('kiosks')->restrictOnDelete());` (depends: T069; accept: T059's FK assertion on `badge_print_jobs.kiosk_id` passes).
- [X] T072 [US2] Create migration `database/migrations/2026_07_06_000016_add_kiosk_settings_to_event_check_in_settings_table.php` adding `kiosk_offline_threshold_seconds` integer default `120` and `lookup_confirmation_required` boolean default `false` to `event_check_in_settings` (depends: none; accept: `php artisan migrate --env=testing` succeeds and both columns exist with the stated defaults).
- [X] T073 [P] [US2] Create `app/Modules/Kiosk/Infrastructure/Persistence/Models/Kiosk.php` and `app/Modules/Kiosk/Infrastructure/Persistence/Models/KioskSession.php` Eloquent models: fillable = all non-timestamp columns from T069/T070 respectively; casts `last_heartbeat_at`/`retired_at` to `datetime` on `Kiosk`, `confirmed_at`/`expires_at`/`revoked_at` to `datetime` on `KioskSession`; relation `sessions()` (hasMany KioskSession) on `Kiosk` (depends: T069, T070; accept: a quick tinker/test query against each model succeeds).
- [X] T074 [P] [US2] Create `database/factories/KioskFactory.php` (defaults: `status = 'registered'`, `printer_status = 'unknown'`, `confirmation_required = false`) and `database/factories/KioskSessionFactory.php` (defaults: `expires_at = now()->addDays(7)`), both linked to existing tenant/event factories (depends: T073; accept: `Kiosk::factory()->create()` and `KioskSession::factory()->create()` both succeed).
- [X] T075 [US2] Create `app/Modules/Kiosk/Application/Actions/RegisterKioskAction.php`: `execute(string $tenantId, string $eventId, string $deviceName, ?string $locationLabel, bool $confirmationRequired, ?string $plainConfirmationCode): Kiosk` generates a unique `device_code` (e.g. `Str::upper(Str::random(8))`, retried on collision), creates the `Kiosk` row, and if `$confirmationRequired` is true sets `confirmation_code_hash = hash('sha256', $plainConfirmationCode)` (throw a validation error if `$confirmationRequired` is true and `$plainConfirmationCode` is empty) (depends: T073; accept: T067's register-kiosk assertions pass).
- [X] T076 [US2] Create `app/Modules/Kiosk/Application/Actions/PairKioskAction.php`: `execute(Kiosk $kiosk): array{secret: string, expiresAt: \DateTimeInterface}` generates a raw secret via `sodium_bin2base64(random_bytes(config('printing.kiosk.session_secret_length', 40)), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)`, revokes every existing non-revoked `KioskSession` for the kiosk (`forceFill(['revoked_at' => now()])`), creates a new `KioskSession` with `secret_hash = hash('sha256', $secret)` and `expires_at = now()->addHours(config('printing.kiosk.session_ttl_hours', 168))`, and returns the raw secret (never persisted) plus the expiry (depends: T060, T073; accept: T060 passes).
- [X] T077 [US2] Create `app/Modules/Kiosk/Application/Actions/RetireKioskAction.php`: `execute(Kiosk $kiosk): void` sets `status = 'retired'`, `retired_at = now()`, and revokes every non-revoked session for the kiosk (depends: T073; accept: T067's retire-kiosk assertions pass).
- [X] T078 [US2] Create `app/Modules/Kiosk/Application/Actions/ConfirmKioskSessionAction.php`: `execute(KioskSession $session, Kiosk $kiosk, string $submittedCode): void` throws `Phase3Problem::make('kiosk_session_unconfirmed')` if `! hash_equals($kiosk->confirmation_code_hash ?? '', hash('sha256', $submittedCode))`; otherwise sets `confirmed_at = now()` on the session (depends: T061, T073; accept: T061 passes).
- [X] T079 [US2] Create `app/Modules/Kiosk/Application/Actions/RecordKioskHeartbeatAction.php`: `execute(Kiosk $kiosk, string $printerStatus, ?string $printerReasonCode, ?string $appVersion): void` force-fills and saves `last_heartbeat_at = now()`, `printer_status = $printerStatus` on the kiosk (does not itself write a derived `status` column — derivation happens at read time via T080) (depends: T073; accept: T063 passes once wired to the controller in T083).
- [X] T080 [US2] Create `app/Modules/Kiosk/Domain/KioskStatusDeriver.php`: `derive(Kiosk $kiosk, int $thresholdSeconds, \DateTimeInterface $now): string` returns `'retired'` if `$kiosk->status === 'retired'`; else `'degraded'` if `$kiosk->printer_status === 'error'`; else `'offline'` if `$kiosk->last_heartbeat_at === null` or `$kiosk->last_heartbeat_at` is older than `$thresholdSeconds` seconds before `$now`; else `'online'` (depends: T073; accept: T063 and T133 pass).
- [X] T081 [US2] Complete `app/Modules/Kiosk/Http/Middleware/ResolveKioskSession.php` (skeleton from T021) with full logic: extract the secret from an `Authorization: KioskSession {secret}` header (`401 kiosk_session_invalid` via `Phase3Problem::make()` if the header is missing/malformed); hash it and find a `KioskSession` where `secret_hash` matches, `revoked_at IS NULL`, and `expires_at > now()` (`kiosk_session_invalid` if none found); load the related `Kiosk` (`kiosk_retired` if `status === 'retired'`); if `Kiosk.confirmation_required` and `KioskSession.confirmed_at IS NULL` and the current route is not the session-confirmation route, throw `kiosk_session_unconfirmed`; otherwise bind a `KioskSessionContext` into `KioskSessionContextStore` (depends: T062, T073, T015; accept: T062 passes).
- [X] T082 [US2] Create `app/Modules/Kiosk/Http/Requests/RegisterKioskRequest.php` (rules: `device_name` required string max:120, `location_label` sometimes nullable string max:160, `confirmation_required` sometimes boolean, `confirmation_code` sometimes required_if:confirmation_required,true string max:12) and `app/Modules/Kiosk/Http/Controllers/Management/KioskController.php` with `store` (checks `Phase3Policy::allows($user, 'manageKiosk')`, calls `RegisterKioskAction`), `index` (checks `manageKiosk` or `viewKioskHealth`, lists kiosks for the event with `KioskStatusDeriver`-derived `status` per row), and `retire` (checks `manageKiosk`, calls `RetireKioskAction`) actions, each responding via `RespondsWithApi` (depends: T075, T077, T013; accept: T067's register/list/retire assertions pass).
- [X] T083 [US2] Create `app/Modules/Kiosk/Http/Controllers/Management/KioskPairingController.php::store` (checks `manageKiosk`, calls `PairKioskAction`, returns the raw secret exactly once per `KioskPairingEnvelope`) (depends: T076, T013; accept: T067's pair-kiosk assertions pass).
- [X] T084 [US2] Create `app/Modules/Kiosk/Http/Controllers/Device/KioskHeartbeatController.php::store` (reads `KioskSessionContext` from `KioskSessionContextStore::current()`, loads the `Kiosk`, calls `RecordKioskHeartbeatAction`) and `app/Modules/Kiosk/Http/Controllers/Device/KioskSessionConfirmationController.php::store` (calls `ConfirmKioskSessionAction`) (depends: T079, T078, T020; accept: T063 and part of T061 pass).
- [X] T085 [US2] Create `app/Modules/Kiosk/Http/Controllers/Device/KioskLookupController.php::store`: reads tenant/event from `KioskSessionContextStore::current()` instead of `TenantContextStore`; delegates to `LookupAttendeesQuery`/`CredentialValidator::validate()` exactly like `ManualDesk\LookupController` (T051) but reading context from the kiosk store; when `EventCheckInSetting.lookup_confirmation_required` is true and the match came from a name/email/phone `query` (not `qr_payload`), generates a 6-digit numeric code, stores its hash in `Cache::put("kiosk-lookup-otp:{$tenantId}:{$attendeeId}", hash('sha256', $code), now()->addSeconds(config('printing.lookup.confirmation_code_ttl_seconds', 300)))`, and dispatches it through the existing `App\Modules\Notifications\Contracts\NotificationAdapter` binding (open an existing usage such as `App\Modules\Notifications\Application\Actions\ProcessNotificationCallback.php` or `ConfirmationIntentCreator`'s implementation first and copy its dispatch call shape), returning `confirmation_required: true` in the response instead of the match; a follow-up call with `confirmation_code` set compares its hash against the cached value (`lookup_confirmation_invalid` on mismatch/expired) before returning the match (depends: T027, T024, T020, T015; accept: T064 passes).
- [X] T086 [US2] Create `app/Modules/Kiosk/Http/Controllers/Device/KioskScanController.php::store`: reads `KioskSessionContext` from the store, builds `ScanContext(tenantId: $context->tenantId, eventId: $context->eventId, scannerId: $context->kioskId, scannerType: 'kiosk', qrPayload: '', credentialId: $request->string('credential_id')->toString(), override: false, overrideReason: null, actorCanOverride: false)` (a kiosk session never carries override permission), calls `SubmitScanAction` (depends: T025, T020; accept: T064 passes).
- [X] T087 [US2] Create `app/Modules/Kiosk/Http/Controllers/Device/KioskBadgePrintController.php::store`: reads `KioskSessionContext`, calls the existing `CreateBadgePrintJobAction` (from T045) with `kioskId = $context->kioskId`, `printedByUserId = null` (depends: T045, T020; accept: T065 passes).
- [X] T088 [US2] Register every kiosk route in `app/Modules/Kiosk/Routes/api.php` per `contracts/openapi.yaml`'s Kiosk Management and Kiosk Device paths: management routes (`registerKiosk`, `listKiosks`, `pairKiosk`, `retireKiosk`) use `['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context']` at the group level plus `permission:kiosk.manage,tenant` (or `kiosk.health.view,tenant` for the list route) per route; device routes (`sendKioskHeartbeat`, `confirmKioskSession`, `lookupAttendeeAtKiosk`, `submitKioskScan`, `createKioskBadgePrintJob`) use `['kiosk.session.clear', 'kiosk.session']` at the group level and no `permission:` middleware (depends: T082, T083, T084, T085, T086, T087; accept: T067, T063, T064, T065 pass).
- [X] T089 [US2] Create domain events `app/Modules/Kiosk/Domain/Events/KioskPaired.php`, `KioskRetired.php`, `KioskStatusChanged.php` (readonly, fields as needed per event) and `app/Modules/Audit/Application/Listeners/Phase3/KioskAuditListener.php` mirroring `WalletPassAuditListener`'s structure, writing actions `kiosk.registered`, `kiosk.paired`, `kiosk.retired`, `kiosk.status_changed`. Register the listeners in `KioskServiceProvider::boot()`; dispatch `KioskPaired` from `PairKioskAction` and `KioskRetired` from `RetireKioskAction` (depends: T076, T077; accept: T068's kiosk-pairing audit-atomicity assertion passes).
- [X] T090 [P] [US2] Create `resources/js/components/kiosk/PairingDialog.tsx` and `resources/js/components/kiosk/HeartbeatIndicator.tsx` — presentational components only (depends: T007; accept: `npm run typecheck` succeeds).
- [X] T091 [US2] Wire `resources/js/pages/tenant/kiosk/Index.tsx` (from T007) to `registerKiosk`/`listKiosks`/`pairKiosk`/`retireKiosk`, rendering `PairingDialog` from T090 (depends: T090, T088; accept: `npm run typecheck` and `npm run build` succeed).
- [X] T092 [US2] Run `php artisan test --group=kiosk`; fix any failing assertion from T059-T068 (depends: T059-T091; accept: exits 0).

**Checkpoint**: US1 and US2 are both independently functional — this is
the MVP: staff and self-service kiosks can both check attendees in and
print badges.

---

## Phase 5: User Story 3 - Reprint a Badge with Permission and Reason (Priority: P2)

**Goal**: Authorized staff reprint a badge with a required reason, linked
to the original print job, optionally revoking the prior QR.

**Independent Test**: Reprint a previously badged attendee with permission
and a reason (succeeds, linked); without permission (blocked); without a
reason (blocked); with `reprint_revokes_old_qr` enabled, the prior
credential/QR is rejected by a subsequent scan.

**Depends on**: US1 (an initial `BadgePrintJob` must already be creatable).

### Tests for User Story 3

- [X] T093 [P] [US3] Create `tests/Unit/BadgePrinting/ReprintBadgeActionTest.php` asserting a not-yet-existing `App\Modules\BadgePrinting\Application\Actions\ReprintBadgeAction::execute(...)`: throws `badge_reprint_not_permitted` and creates no job/audit-success record for a user lacking `badge.reprint` (still writes one audit record for the blocked attempt); throws `badge_reprint_reason_required` for an empty reason; throws `badge_no_prior_print_job` when the attendee has no existing `BadgePrintJob`; on success, creates a new `BadgePrintJob` with `is_reprint = true`, `original_print_job_id` equal to the prior job's id, and the given `reprint_reason` (depends: T045; accept: fails today, class does not exist).
- [X] T094 [P] [US3] Create `tests/Feature/BadgePrinting/ReprintBadgeApiTest.php` covering `POST /api/v1/tenant/events/{event_id}/badge-print-jobs/{badge_print_job_id}/reprint`: `201` for an authorized user with a reason; `403` without `badge.reprint`; `422` without a reason; `409 badge_no_prior_print_job` semantics are represented as documented in `contracts/openapi.yaml`'s `reprintBadge` operation (depends: T023; accept: fails today with 404 route-not-found).
- [X] T095 [P] [US3] Create `tests/Integration/Security/ReprintOldQrRevocationTest.php` asserting: with `EventCheckInSetting.reprint_revokes_old_qr = true`, completing a reprint calls `ReissueCredential` (assert via the resulting `Credential.status = 'superseded'` and a new active credential, exactly as Phase 1's reissue behaves) and a subsequent scan of the prior credential's raw token returns `revoked`/`rejected` per the existing scan contract; with the setting `false`, the prior credential/QR remains fully valid for entry after a reprint (depends: T005; accept: fails today).
- [X] T096 [P] [US3] Extend `tests/Integration/Security/Phase3AuditAtomicityTest.php` (from T068) asserting every reprint attempt — successful or blocked by missing permission, reason, or prior job — produces exactly one audit record with actor, attendee, reason (if provided), and outcome (depends: T068; accept: fails today).

### Implementation for User Story 3

- [X] T097 [US3] Create migration `database/migrations/2026_07_06_000017_add_reprint_revokes_old_qr_to_event_check_in_settings_table.php` adding `reprint_revokes_old_qr` boolean default `false` to `event_check_in_settings` (depends: none; accept: `php artisan migrate --env=testing` succeeds and the column exists with the stated default).
- [X] T098 [US3] Create `app/Modules/BadgePrinting/Application/Actions/ReprintBadgeAction.php` per T093's contract: constructor-injects `Phase3Policy`, `App\Modules\Credentials\Application\Actions\ReissueCredential`, `App\Modules\Audit\Contracts\AuditWriter`, `RenderBadgePrintPayloadAction`, `PrinterAdapter`. `execute(User $actor, TenantContext $tenantContext, string $eventId, string $attendeeId, string $reason): BadgePrintJob`: if `! $this->policy->allows($actor, 'reprintBadge')`, call `$this->audit->write('tenant', $tenantContext->tenant->id, 'badge_print.reprint_blocked', 'blocked', actor: $actor, reasonCode: 'badge_reprint_not_permitted', targetType: 'attendee', targetId: $attendeeId)` then `throw Phase3Problem::make('badge_reprint_not_permitted')`; if `trim($reason) === ''`, write the same blocked-audit shape with `reasonCode: 'badge_reprint_reason_required'` then throw; resolve the most recent `BadgePrintJob` for `(tenantId, eventId, attendeeId)` ordered by `created_at desc` (write blocked-audit with `reasonCode: 'badge_no_prior_print_job'` then throw if none); if `EventCheckInSetting.reprint_revokes_old_qr` is true, call `ReissueCredential::execute($tenantContext, $eventId, $priorJob->credential_id, $reason)` and use the returned credential's id for rendering; otherwise reuse `$priorJob->credential_id`; render via `RenderBadgePrintPayloadAction` against the (possibly reissued) credential and the prior job's `badge_template_id`'s current template row; create the new `BadgePrintJob` (`is_reprint = true`, `reprint_reason = $reason`, `original_print_job_id = $priorJob->id`) inside `AuditedTransaction::run()`, call `PrinterAdapter::print()`, dispatch `BadgePrintJobReprinted` (T099) after commit (depends: T093, T044, T045; accept: T093 passes).
- [X] T099 [US3] Create domain event `app/Modules/BadgePrinting/Domain/Events/BadgePrintJobReprinted.php` (readonly, fields: `tenantId, eventId, badgePrintJobId, attendeeId, originalPrintJobId, reason`) and add `handleReprinted` to `app/Modules/Audit/Application/Listeners/Phase3/BadgePrintAuditListener.php` (from T047) writing action `badge_print.reprinted`, `metadata: ['event_id' => ..., 'original_print_job_id' => ..., 'reason' => ...]`; register the new listener binding in `BadgePrintingServiceProvider::boot()` (depends: T047, T098; accept: T096 passes for the success path).
- [X] T100 [US3] Create `app/Modules/BadgePrinting/Http/Requests/ReprintBadgeRequest.php` (rules: `reprint_reason` required string min:1 max:500) and add a `reprint(ReprintBadgeRequest $request, string $eventId, string $badgePrintJobId, ReprintBadgeAction $action)` method to `app/Modules/BadgePrinting/Http/Controllers/BadgePrintJobController.php` (from T048) that resolves the target job's `attendee_id` for the given `badge_print_job_id`, calls `ReprintBadgeAction::execute(...)`, and responds with `BadgePrintJobResource` at `201` (depends: T098, T048; accept: T094 passes).
- [X] T101 [US3] Register the route in `app/Modules/BadgePrinting/Routes/api.php`: `Route::post('/badge-print-jobs/{badge_print_job_id}/reprint', [BadgePrintJobController::class, 'reprint'])->middleware(['permission:badge.reprint,tenant', 'idempotency']);` inside the same group as T049 (depends: T100; accept: T094 passes).
- [X] T102 [P] [US3] Create `resources/js/components/manual-desk/ReprintDialog.tsx` — presentational, a reason-input form only (depends: T007; accept: `npm run typecheck` succeeds).
- [X] T103 [US3] Run `php artisan test --group=badge-reprint`; fix any failing assertion from T093-T096 (depends: T093-T102; accept: exits 0).

**Checkpoint**: US1, US2, US3 all independently functional.

---

## Phase 6: User Story 4 - Register a Walk-Up Attendee at the Manual Desk (Priority: P2)

**Goal**: With the event toggle enabled, staff create a walk-up attendee
using Phase 1's exact issuance rules, then immediately check them in and
print their badge.

**Independent Test**: Toggle enabled → register succeeds, tagged
`origin = 'walk_up'`, immediately checkable-in via US1's desk flow; toggle
disabled → endpoint unavailable; paid ticket with no on-site payment method
enabled → clear rejection, nothing created.

**Depends on**: US1 (check-in/print already functional for the newly
created attendee).

### Tests for User Story 4

- [X] T104 [P] [US4] Create `tests/Integration/MySql/AttendeeOriginColumnTest.php` asserting the `attendees` table has an `origin` column defaulting to `'standard'` with a check constraint allowing only `standard|walk_up` (depends: T005; accept: fails today, column does not exist).
- [X] T105 [P] [US4] Create `tests/Unit/Attendees/RegisterWalkUpAttendeeActionTest.php` asserting a not-yet-existing `App\Modules\Attendees\Application\Actions\RegisterWalkUpAttendeeAction::execute(...)`: throws `walk_up_registration_disabled` and creates nothing when `EventCheckInSetting.walk_up_registration_enabled = false`; for a free ticket type with the toggle enabled, creates an `Attendee` with `origin = 'walk_up'` and an active `Credential`, calling `CompleteFreeRegistration` under the hood (assert via the resulting `Order.status = 'paid'` and `Order.total_minor = 0`); for a paid ticket type with `walk_up_payment_method_enabled = false`, throws `walk_up_payment_not_collectible` and creates no `Order`/`Attendee` row; for a paid ticket type with `walk_up_payment_method_enabled = true`, creates a `paid` `Order`/`Attendee`/`Credential` via `StartPaidRegistration` + `OrderPaymentPort::completeCaptured(..., 'on_site', ..., live: false)` (depends: T005; accept: fails today, class does not exist).
- [X] T106 [P] [US4] Create `tests/Feature/Attendees/WalkUpRegistrationApiTest.php` covering `POST /api/v1/tenant/events/{event_id}/desk/walk-up-attendees`: `201` for an authorized `attendee.walkup.register` user with the toggle enabled and a free ticket type; `403` without the permission; `403 walk_up_registration_disabled` when the toggle is off; `422 walk_up_payment_not_collectible` for a paid ticket type with on-site payment disabled (depends: T023; accept: fails today with 404 route-not-found).
- [X] T107 [P] [US4] Extend `tests/Integration/Security/Phase3AuditAtomicityTest.php` (from T096) asserting a forced audit-write failure during walk-up registration leaves zero `Attendee`/`Order`/`Credential` row created (depends: T096; accept: fails today).
- [X] T108 [P] [US4] Create `tests/Feature/Attendees/WalkUpThenCheckInTest.php` asserting a freshly walk-up-registered attendee can immediately be found via `POST /desk/lookups` and checked in via `POST /scans` with `scanner_type: 'manual_desk'` (reuses US1's endpoints end-to-end) (depends: T057; accept: fails today because walk-up registration does not exist).

### Implementation for User Story 4

- [X] T109 [US4] Create migration `database/migrations/2026_07_06_000018_add_origin_to_attendees_table.php` adding `origin` string(20) default `'standard'` to `attendees`, plus a check constraint `attendees_origin_chk CHECK (origin IN ('standard','walk_up'))` (depends: T104; accept: T104 passes).
- [X] T110 [US4] Create migration `database/migrations/2026_07_06_000019_add_walk_up_settings_to_event_check_in_settings_table.php` adding `walk_up_registration_enabled` boolean default `false` and `walk_up_payment_method_enabled` boolean default `false` to `event_check_in_settings` (depends: none; accept: `php artisan migrate --env=testing` succeeds and both columns exist with the stated defaults).
- [X] T111 [US4] Edit `app/Modules/Attendees/Infrastructure/Persistence/Models/Attendee.php`'s `$fillable` array to add `'origin'` (depends: T109; accept: `Attendee::query()->create(['origin' => 'walk_up', ...])` persists the value).
- [X] T112 Add `'walk_up_registration_disabled' => 403` and `'walk_up_payment_not_collectible' => 422` to `app/Modules/Shared/Http/Problems/Phase3Problem.php`'s `STATUS` map if T015 did not already include them (it does — this task only verifies and is a no-op if already present) (depends: T015; accept: both codes resolve to the stated HTTP status).
- [X] T113 [US4] Create `app/Modules/Attendees/Application/Actions/RegisterWalkUpAttendeeAction.php` per T105's contract, constructor-injecting `App\Modules\Orders\Application\Actions\CompleteFreeRegistration`, `App\Modules\Orders\Application\Actions\StartPaidRegistration`, `App\Modules\Orders\Contracts\OrderPaymentPort`, `App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType` (for price lookup), and `App\Modules\Audit\Contracts\AuditWriter`: reads `EventCheckInSetting` for the two toggles (throw `walk_up_registration_disabled` if the first is false, before touching any other table); looks up the selected `TicketType`'s price; if `0`, builds a `FreeRegistrationInput` (fields per the Ground-truth section above) with a synthetic `idempotencyKey` (e.g. `'walkup_'.Str::ulid()`) and calls `CompleteFreeRegistration::execute()`, then force-fills `origin = 'walk_up'` on the resulting attendee row in the same request (a second small update after the registration action returns, wrapped in its own `DB::transaction`); if price `> 0` and `walk_up_payment_method_enabled` is false, throws `walk_up_payment_not_collectible` and creates nothing; if price `> 0` and the toggle is true, calls `StartPaidRegistration::execute()` then `OrderPaymentPort::completeCaptured($orderId, 'on_site', $totalMinor, $currency, live: false)`, then force-fills `origin = 'walk_up'` the same way (depends: T105, T111, T112; accept: T105 passes).
- [X] T114 [US4] Create `app/Modules/Attendees/Http/Requests/RegisterWalkUpAttendeeRequest.php` (rules per `RegisterWalkUpAttendeeRequest` schema in `contracts/openapi.yaml`: `name` required string max:160, `email` required email, `phone` sometimes string max:32, `ticket_type_id` required string) and `app/Modules/Attendees/Http/Controllers/ManualDesk/WalkUpAttendeeController.php::store` checking `Phase3Policy::allows($user, 'registerWalkUpAttendee')` and calling `RegisterWalkUpAttendeeAction` (depends: T113, T013; accept: T106 passes).
- [X] T115 [US4] Create `app/Modules/Attendees/Routes/api.php` if it does not already exist (check first with a file listing; if it exists, open it and append instead of overwriting) registering `POST /tenant/events/{event_id}/desk/walk-up-attendees` with `['auth:sanctum', 'throttle:phase1-organizer', 'tenant.context.clear', 'tenant.context']` at the group level and `['permission:attendee.walkup.register,tenant', 'idempotency']` on the route; require the file from `routes/api.php` if it was newly created (depends: T114; accept: T106 and T108 pass).
- [X] T116 [P] [US4] Create `resources/js/components/manual-desk/WalkUpForm.tsx` — presentational form only (depends: T007; accept: `npm run typecheck` succeeds).
- [X] T117 [US4] Run `php artisan test --group=walk-up-registration`; fix any failing assertion from T104-T108 (depends: T104-T116; accept: exits 0).

**Checkpoint**: US1, US2, US3, US4 all independently functional.

---

## Phase 7: User Story 5 - Organizer Designs a Badge Template Without Code (Priority: P2)

**Goal**: An organizer creates, edits, activates, and deactivates a badge
template through the API/UI, immediately affecting subsequent print jobs.

**Independent Test**: Create a draft, activate it, confirm it is the one
`CreateBadgePrintJobAction` (from US1) uses next; confirm activating a new
template deactivates the previous one; confirm an unknown-field layout is
rejected.

**Depends on**: US1 (the `badge_templates` table and
`BadgeLayoutValidator` already exist; this story only adds the
organizer-facing management surface).

### Tests for User Story 5

- [X] T118 [P] [US5] Create `tests/Unit/BadgePrinting/ActivateBadgeTemplateActionTest.php` asserting a not-yet-existing `App\Modules\BadgePrinting\Application\Actions\ActivateBadgeTemplateAction::execute(BadgeTemplate $target): void` sets the target's `status` to `active` and, within the same transaction, sets any other currently `active` template for the same event to `inactive`; at most one `active` row ever exists per event, verified with a concurrent-activation test using `lockForUpdate` (depends: T041; accept: fails today, class does not exist).
- [X] T119 [P] [US5] Create `tests/Feature/BadgePrinting/BadgeTemplateApiTest.php` covering `POST/GET /tenant/events/{event_id}/badge-templates`, `PATCH /.../{badge_template_id}`, `POST /.../{badge_template_id}/activate`, `POST /.../{badge_template_id}/deactivate`: full `200/201/403/404/422` matrix per `contracts/openapi.yaml`, including `422 badge_template_invalid_field` for an unknown layout key (depends: T023; accept: fails today with 404 route-not-found).
- [X] T120 [P] [US5] Create `tests/Integration/Security/BadgeTemplateIsolationTest.php` asserting cross-tenant/cross-event badge template read/write is rejected identically to an unknown target (depends: T005; accept: fails today).
- [X] T121 [P] [US5] Extend `tests/Integration/Security/Phase3AuditAtomicityTest.php` (from T107) asserting create/update/activate/deactivate on a badge template each produce exactly one audit record (depends: T107; accept: fails today).

### Implementation for User Story 5

- [X] T122 [US5] Create `app/Modules/BadgePrinting/Application/Actions/CreateOrUpdateBadgeTemplateAction.php`: `execute(string $tenantId, string $eventId, ?BadgeTemplate $existing, string $name, array $layout, string $paperSize, string $printerType): BadgeTemplate` calls `BadgeLayoutValidator::validate($layout)` (throws `badge_template_invalid_field` on failure) before creating or force-filling+saving the row; new templates default to `status = 'draft'` (depends: T043, T041; accept: T119's create/update assertions pass).
- [X] T123 [US5] Create `app/Modules/BadgePrinting/Application/Actions/ActivateBadgeTemplateAction.php` per T118's contract: inside `DB::transaction()`, `lockForUpdate()` every `BadgeTemplate` row for `(tenantId, eventId)`, force-fill any row with `status = 'active'` other than the target to `status = 'inactive'`, then force-fill the target to `status = 'active'` (depends: T118; accept: T118 passes).
- [X] T124 [US5] Create `app/Modules/BadgePrinting/Application/Actions/DeactivateBadgeTemplateAction.php`: `execute(BadgeTemplate $target): void` force-fills `status = 'inactive'` and saves, only when the target's current status is `active` (no-op otherwise) (depends: T041; accept: T119's deactivate assertions pass).
- [X] T125 [US5] Create domain events `app/Modules/BadgePrinting/Domain/Events/BadgeTemplateCreated.php`, `BadgeTemplateUpdated.php`, `BadgeTemplateActivated.php`, `BadgeTemplateDeactivated.php` and `app/Modules/Audit/Application/Listeners/Phase3/BadgeTemplateAuditListener.php` (mirror `WalletPassAuditListener`'s structure) writing actions `badge_template.created`/`updated`/`activated`/`deactivated`, `targetType = 'badge_template'`. Register the listeners in `BadgePrintingServiceProvider::boot()`; dispatch the events from T122/T123/T124 (depends: T122, T123, T124; accept: T121 passes).
- [X] T126 [US5] Create `app/Modules/BadgePrinting/Http/Requests/BadgeTemplateRequest.php` (rules per `BadgeTemplateRequest` schema in `contracts/openapi.yaml`: `name` required string max:120, `layout` required array, `paper_size` required string, `printer_type` required string) and `app/Modules/BadgePrinting/Http/Resources/BadgeTemplateResource.php`, plus `app/Modules/BadgePrinting/Http/Controllers/BadgeTemplateController.php` with `store`, `index`, `update`, `activate`, `deactivate` actions, each checking `Phase3Policy::allows($user, 'manageBadgeTemplate')` (depends: T122, T123, T124, T013; accept: T119 passes).
- [X] T127 [US5] Register the five badge-template routes in `app/Modules/BadgePrinting/Routes/api.php` per `contracts/openapi.yaml`, each with `permission:badge.template.manage,tenant` (write routes also with `idempotency`) inside the same group as T049 (depends: T126; accept: T119 and T120 pass).
- [X] T128 [P] [US5] Create `resources/js/components/badge-templates/FieldPicker.tsx` and `resources/js/components/badge-templates/LayoutPreview.tsx` — presentational only, both importing the `BADGE_TEMPLATE_ALLOWED_FIELDS` constant from `resources/js/types/phase3.ts` (T007) so the UI can never offer a field outside the allowlist; `LayoutPreview` renders both an `ltr`/English and an `rtl`/Arabic preview pane (depends: T007; accept: `npm run typecheck` succeeds).
- [X] T129 [US5] Wire `resources/js/pages/tenant/badge-templates/Designer.tsx` (from T007) to the five endpoints from T127 and the two components from T128 (depends: T128, T127; accept: `npm run typecheck` and `npm run build` succeed).
- [X] T130 [US5] Run `php artisan test --group=badge-printing` again to confirm no regression from this story, plus `npm run test -- badge-templates` (depends: T118-T129; accept: both exit 0).

**Checkpoint**: US1-US5 all independently functional.

---

## Phase 8: User Story 6 - Operations Monitor Kiosk and Printer Health (Priority: P3)

**Goal**: Authorized viewers see live kiosk/printer status per event within
a short, bounded polling delay.

**Independent Test**: Stop a kiosk's heartbeats past the configured
threshold → it shows `offline`; relay a printer error → it shows
`degraded` alongside the printer's own error state; cross-tenant/event
kiosk health is never visible.

**Depends on**: US2 (kiosks and heartbeats already exist).

### Tests for User Story 6

- [X] T131 [P] [US6] Create `tests/Feature/Kiosk/KioskHealthListTest.php` asserting `GET /api/v1/tenant/events/{event_id}/kiosks` returns each kiosk's `KioskStatusDeriver`-derived `status` and its `printer_status`, correctly reflecting a stale heartbeat as `offline` and a printer error as `degraded`, scoped to the caller's authorized event only (depends: T005; accept: fails today if `KioskController::index` (T082) does not yet include derived status — if T082 already wired it, this task only adds the offline/degraded-specific assertions).
- [X] T132 [P] [US6] Create `tests/Integration/Security/KioskHealthIsolationTest.php` asserting an operations viewer authorized for one event never sees another tenant's or event's kiosk health (depends: T005; accept: fails today if not already covered by T066).
- [X] T133 [P] [US6] Create `tests/Unit/Kiosk/KioskStatusDeriverEdgeCasesTest.php` asserting a retired kiosk always derives `'retired'` regardless of how recent its `last_heartbeat_at` is, and a fresh heartbeat with `printer_status = 'error'` still derives `'degraded'`, never `'online'` (depends: T080; accept: fails today if these two edge cases are not yet covered by T063/T080's original tests).

### Implementation for User Story 6

- [X] T134 [US6] Verify `app/Modules/Kiosk/Http/Controllers/Management/KioskController.php::index` (T082) already calls `KioskStatusDeriver::derive()` per row using `EventCheckInSetting.kiosk_offline_threshold_seconds`; if it does not yet read that per-event threshold (T082 may have used a hardcoded default), edit it to do so now (depends: T082, T080; accept: T131 passes).
- [X] T135 [US6] Replace the placeholder `kiosk_fleet` health-check category from T022 in `app/Modules/Operations/Application/Health/Checks/` with real aggregate online/offline/degraded counts computed via `KioskStatusDeriver` across all of a tenant's kiosks (depends: T022, T080; accept: `php artisan zonetec:config:validate --env=testing` shows non-placeholder counts when kiosks exist in the seeded test database).
- [X] T136 [P] [US6] Create `resources/js/components/kiosk/HealthTable.tsx` — presentational, polls the kiosks list endpoint on the same short fixed interval pattern as an existing Phase 2 dashboard component (open `resources/js/pages/tenant/checkin/Dashboard.tsx` first and copy its polling hook) (depends: T007; accept: `npm run typecheck` succeeds).
- [X] T137 [US6] Wire `resources/js/pages/tenant/kiosk/Index.tsx` (already wired to management actions in T091) to also render `HealthTable` from T136 (depends: T136, T091; accept: `npm run typecheck` and `npm run build` succeed).
- [X] T138 [US6] Run `php artisan test --group=kiosk-health` and `npm run test -- kiosk-health`; fix any failing assertion from T131-T133 (depends: T131-T137; accept: both exit 0).

**Checkpoint**: All six user stories are independently functional.

---

## Final Phase: Polish & Cross-Cutting Concerns

- [X] T139 [P] Write `docs/operations/kiosk-badge-runbook.md` (kiosk pairing/confirmation/retirement, badge print/reprint operational runbook) and `docs/operations/printer-adapter-runbook.md` (onboarding, rotation, outage guide), following the structure of the existing `docs/operations/offline-scanning-design.md` and `docs/operations/wallet-adapter-runbook.md`.
- [X] T140 [P] Update `docs/standards/permission-catalog.md` and `docs/standards/audit-event-catalog.md` (open both first) with the seven Phase 3 permissions and the `kiosk.*`, `desk_scan.*`, `badge_template.*`, `badge_print.*`, `walk_up_attendee.*` audit action families from `plan.md`'s RBAC and Audit Catalog section.
- [X] T141 Code cleanup and refactoring pass across `app/Modules/Kiosk` and `app/Modules/BadgePrinting` for consistent naming with the Phase 1/2 conventions used throughout this file.
- [X] T142 Create `tests/Performance/KioskCheckInLatencyTest.php` asserting kiosk/desk check-in-to-print p95 latency stays under the bound in `plan.md`'s Performance Goals at a representative fixture scale, and a bounded-query-plan assertion for the kiosk-health list endpoint at fleet scale (reuse the query-plan assertion technique from an existing Phase 2 performance test — open one first).
- [X] T143 [P] Add regression tests for edge cases not yet covered: `original_print_job_id` cycle prevention (a reprint of a reprint always points to the immediately preceding job, never forms a cycle), and a badge template layout with zero configured fields still renders a payload with an empty `fields` array rather than erroring.
- [X] T144 Re-run `tests/Architecture/Phase3ModuleBoundaryTest.php` (T010) and `tests/Integration/Security/Phase3ModuleBoundaryQueryTest.php` (T028) to confirm they still pass after every implementation task in this file; fix any violation found.
- [X] T145 Open `tests/Integration/Security/AttendeeAnonymizationWalletCascadeTest.php` and add a new assertion that anonymizing an attendee preserves that attendee's `BadgePrintJob` rows (not deleted) while redacting identity-bearing rendered content, consistent with the existing wallet/credential cascade pattern in that file.
- [X] T146 Verify Arabic/English, RTL/LTR, and white-label behavior for the kiosk attendee-facing screens, manual desk UI, and badge template designer (including the bilingual layout preview from T128) via `npm run test -- --grep=rtl` or the project's existing localization test convention (open an existing Phase 2 localization test first to match its pattern).
- [X] T147 Verify SaaS/on-premise parity: run the kiosk/desk check-in test groups with `FakePrinterAdapter::forceHealth('disconnected')`/forced `print()` failure active and confirm check-in still completes with the `BadgePrintJob` left in a retryable `failed` state rather than blocking the check-in itself.
- [X] T148 Run `specs/004-kiosk-badge-printing-manual-desk/quickstart.md` end-to-end exactly as written and fix any discrepancy found between the guide and the implemented behavior.
- [X] T149 Run `composer run quality`, `npm run lint`, `npm run typecheck`, `npm run test`, and `npm run build`; fix any failure.
- [X] T150 Run `php artisan test` (the full suite, all phases) once more to confirm every Phase 0/1/2 gate remains green alongside every new Phase 3 suite.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories.
- **User Stories (Phase 3-8)**: All depend on Foundational completion.
  - US1 (Phase 3) has no dependency on any other story.
  - US2 (Phase 4) has no dependency on any other story.
  - US3 (Phase 5) depends on US1 (a `BadgePrintJob` must already exist to reprint).
  - US4 (Phase 6) depends on US1 (check-in/print must already work for the new attendee).
  - US5 (Phase 7) depends on US1 (the `badge_templates` table/validator already exist).
  - US6 (Phase 8) depends on US2 (kiosks and heartbeats must already exist).
- **Polish (Final Phase)**: Depends on every user story you choose to ship.

### Product Delivery Dependencies

- **Foundation (Phase 0)**: Tenant isolation, RBAC, audit, versioned APIs, adapter interfaces — already accepted.
- **Phase 1 (accepted)**: Events, ticketing/orders, attendees, and the signed credential lifecycle.
- **Phase 2 (accepted)**: Wallet passes and scanning — the scan decision order and credential contract this phase reuses unmodified.
- **This phase (Phase 3)**: Kiosk, badge printing, and manual desk. ACS zone/lane/anti-passback, identity verification, and venue marketplace (Phase 4+) MUST NOT be built here and MUST depend on this phase's accepted contracts once their own plans exist.

### User Story Dependencies

- **US1 (P1)**: Start after Foundational. No dependency on other stories.
- **US2 (P1)**: Start after Foundational. No dependency on other stories.
- **US3 (P2)**: Start after US1 is complete.
- **US4 (P2)**: Start after US1 is complete.
- **US5 (P2)**: Start after US1 is complete.
- **US6 (P3)**: Start after US2 is complete.

### Within Each User Story

- Tests are written first and must fail before their matching implementation task is started.
- Migrations before models before factories before domain/application services before HTTP layer before frontend.
- Story complete (its own test-group command exits 0) before moving to a dependent story.

### Parallel Opportunities

- All Setup tasks marked `[P]` can run in parallel.
- All Foundational tasks marked `[P]` can run in parallel (respecting the T024→T025 and T019→T020→T021 chains, which are not parallel).
- Once Foundational is done, US1 and US2 can be worked in parallel by different people/agents; US3, US4, and US5 must wait for US1; US6 must wait for US2.
- All test tasks marked `[P]` within one story can run in parallel.
- All model/factory tasks marked `[P]` within one story can run in parallel.

---

## Parallel Example: User Story 1

```bash
# Launch all US1 tests together (after Foundational is done):
Task: "Create tests/Integration/MySql/BadgeTemplateSchemaTest.php"
Task: "Create tests/Integration/MySql/BadgePrintJobSchemaTest.php"
Task: "Create tests/Unit/BadgePrinting/BadgeLayoutValidatorTest.php"
Task: "Create tests/Unit/BadgePrinting/CreateBadgePrintJobActionTest.php"
Task: "Create tests/Feature/Scanning/ManualDeskLookupTest.php"
Task: "Create tests/Feature/Scanning/ManualDeskCheckInTest.php"
Task: "Create tests/Feature/BadgePrinting/CreateBadgePrintJobApiTest.php"
Task: "Create tests/Integration/Security/BadgePrintingIsolationTest.php"
Task: "Create tests/Integration/Security/Phase3AuditAtomicityTest.php"
Task: "Create tests/Contract/Phase3/ManualDeskApiTest.php"

# Launch the two independent models together once T039/T040 land:
Task: "Create app/Modules/BadgePrinting/Infrastructure/Persistence/Models/BadgeTemplate.php"
Task: "Create app/Modules/BadgePrinting/Infrastructure/Persistence/Models/BadgePrintJob.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 + User Story 2 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (blocks everything).
3. Complete Phase 3: User Story 1 (manual desk check-in + print).
4. Complete Phase 4: User Story 2 (kiosk self-service check-in + print).
5. **STOP and VALIDATE**: run `php artisan test --group=manual-desk`, `php artisan test --group=badge-printing`, and `php artisan test --group=kiosk` independently; all three must exit 0.
6. This is the deployable MVP: staff and self-service kiosks can both check attendees in and print badges, even before reprint/walk-up/designer/health exist.

### Incremental Delivery

1. Setup + Foundational → foundation ready.
2. Add US1 → validate independently → deployable.
3. Add US2 → validate independently → deployable MVP.
4. Add US3 → validate independently → reprint live.
5. Add US4 → validate independently → walk-up live.
6. Add US5 → validate independently → no-code badge designer live.
7. Add US6 → validate independently → kiosk/printer health live.
8. Final Phase → polish, regression, and full quality gate.

---

## Notes

- `[P]` tasks touch different files and have no unfinished dependency.
- `[Story]` labels map every user-story-phase task back to `spec.md`.
- Every migration task lists exact columns; do not add or omit a column without updating `data-model.md` first.
- Never introduce a second credential trust path: every kiosk/desk check-in must call back into the unchanged `SubmitScanAction`/`ScanDecisionEvaluatorImpl`/`CredentialValidator` chain described in "Ground-truth integration points" above — the only new entry point is `validateById()`, which performs the same status/expiry/scope checks by id instead of by signed token.
- Never let `BadgePrinting` or `Kiosk` query `Attendees`, `Credentials`, or `Scanning` persistence directly outside the specific reuse points named above (`LookupAttendeesQuery`, `SubmitScanAction`, `ReissueCredential`, `CompleteFreeRegistration`/`StartPaidRegistration`/`OrderPaymentPort`).
- Commit after each task or small logical group.
- Stop at any `**Checkpoint**` to validate a story independently before continuing.
