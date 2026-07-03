# Tasks: Registration, Ticketing, Orders, and Credentials

**Input**: Design documents from `specs/002-registration-ticketing-credentials/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/`, `quickstart.md`

**Tests**: Mandatory and test-first. Each named test task must fail for the
expected missing behavior before its implementation task is completed.

**Organization**: Tasks are grouped by user story. Equal-priority stories are
ordered by executable dependency: US1 → US4 → US2 → US3 → US5 → US6 → US7.

**Product Phase**: Phase 1 Registration-Ticketing-Credentials

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel after listed dependencies because it owns different files.
- **[Story]**: Maps the task to the corresponding specification user story.
- Every task names an exact file or directory and includes a verifiable outcome.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Register Phase 1 modules, configuration, test groups, localization,
and contract tooling without changing product behavior.

- [ ] T001 Register Events, Registration, Ticketing, Orders, Payments, Attendees, Credentials, and Notifications providers in `app/Providers/ModuleServiceProvider.php` (depends: Phase 0 complete; accept: `php artisan about` boots with all providers).
- [ ] T002 [P] Create provider skeletons in `app/Modules/{Events,Registration,Ticketing,Orders,Payments,Attendees,Credentials,Notifications}/Providers/` (depends: none; accept: providers contain no product routes or persistence behavior yet).
- [ ] T003 [P] Create configuration files `config/registration.php`, `config/payments.php`, `config/credentials.php`, and `config/notifications.php` with safe disabled/local defaults and no secret values (depends: none; accept: `php artisan config:cache` succeeds).
- [ ] T004 Document all Phase 1 environment keys and synthetic test values in `.env.example` and `.env.testing` (depends: T003; accept: config validation names missing keys without printing values).
- [ ] T005 [P] Add Phase 1 test groups and MySQL helpers in `phpunit.xml` and `tests/Support/Phase1MySqlTestCase.php` (depends: none; accept: `php artisan test --list-tests` discovers Phase 1 suites).
- [ ] T006 [P] Add Arabic and English Phase 1 message catalogs in `lang/en/phase1.php`, `lang/ar/phase1.php`, `resources/js/locales/en.ts`, and `resources/js/locales/ar.ts` (depends: none; accept: identical message-key sets are enforced).
- [ ] T007 [P] Create Phase 1 frontend route/type entry points in `resources/js/types/phase1.ts` and `resources/js/pages/{public/registration,tenant/events}/` (depends: none; accept: `npm run typecheck` succeeds with empty typed pages).
- [ ] T008 Add Phase 1 OpenAPI lint command and contract path to `package.json` and `composer.json` quality scripts (depends: none; accept: `npx redocly lint specs/002-registration-ticketing-credentials/contracts/openapi.yaml` passes with zero warning).

**Checkpoint**: The application boots and all Phase 1 scaffolding remains behavior-free.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Extend Phase 0 controls for encrypted personal data, public event
context, permissions, signing keys, errors, and module boundaries.

**CRITICAL**: Complete this phase before any user-story implementation.

- [ ] T009 [P] Write Phase 1 module-boundary and forbidden-scope tests in `tests/Architecture/Phase1ModuleBoundaryTest.php` (depends: T001; accept: cross-module Infrastructure imports and Phase 2+ namespaces fail).
- [ ] T010 [P] Write personal-data encryption, blind-index, and key-rotation tests in `tests/Unit/Shared/PersonalDataProtectionTest.php` (depends: T004; accept: tests fail before protection services exist).
- [ ] T011 Implement versioned `PersonalDataCipher` and `BlindIndex` in `app/Modules/Shared/Application/DataProtection/` (depends: T010; accept: authenticated encryption, dual-read rotation, normalization, and exact blind lookup tests pass).
- [ ] T012 [P] Write public host/event context spoofing and cleanup tests in `tests/Integration/Security/PublicEventContextTest.php` (depends: T005; accept: headers, unknown hosts, inactive tenants, and leaked context fail closed).
- [ ] T013 Implement `PublicEventContext`, store, resolver contract, and cleanup middleware in `app/Modules/Events/Domain/Context/`, `app/Modules/Events/Contracts/PublicEventContextResolver.php`, and `app/Modules/Events/Http/Middleware/` (depends: T012; accept: context binds only trusted host/slug resolution and always clears).
- [ ] T014 Configure public discovery, form, registration, checkout, callback, and organizer rate limiters in `app/Providers/AppServiceProvider.php` (depends: T003, T013; accept: limits use safe host/event/source or tenant/actor keys and return standard 429 responses).
- [ ] T015 [P] Write Phase 1 permission allow/deny and immediate-revocation tests in `tests/Feature/Authorization/Phase1PermissionMatrixTest.php` (depends: T005; accept: every planned permission initially lacks a catalog entry).
- [ ] T016 Extend `database/seeders/PermissionCatalogSeeder.php` and `database/seeders/SystemRoleSeeder.php` with Phase 1 permissions and least-privilege Event/Ticketing roles (depends: T015; accept: repeat seeding is idempotent and custom roles stay empty).
- [ ] T017 Implement Phase 1 policies in `app/Modules/Authorization/Policies/Phase1/` and register them in `app/Modules/Authorization/Providers/AuthorizationServiceProvider.php` (depends: T016; accept: T015 passes for tenant, event, and action scope).
- [ ] T018 [P] Write Phase 1 stable error and no-leak tests in `tests/Contract/Phase1ProblemDetailsTest.php` (depends: T008; accept: every new code has Arabic/English text and no provider/PII leakage).
- [ ] T019 Extend exception mapping in `app/Modules/Shared/Http/Problems/FoundationProblemRenderer.php` and locale catalogs for all Phase 1 error codes (depends: T018; accept: contract tests pass without controller-built errors).
- [ ] T020 [P] Write Ed25519 key-ring, canonical token, rotation, and readiness tests in `tests/Unit/Credentials/CredentialKeyRingTest.php` (depends: T004; accept: tests fail before credential primitives exist).
- [ ] T021 Implement credential value objects, canonical encoder, Ed25519 key ring, and secret-reference loader in `app/Modules/Credentials/Domain/` and `app/Modules/Credentials/Application/Signing/` (depends: T020; accept: active/verify-only/retired/compromised behavior and constant-time verification pass).
- [ ] T022 [P] Define payment and notification contracts/results in `app/Modules/Payments/Contracts/`, `app/Modules/Payments/Domain/`, and `app/Modules/Notifications/Contracts/` from `contracts/payment-adapter.md` and `contracts/notification-adapter.md` (depends: T002; accept: interfaces expose no provider SDK or credential type).
- [ ] T023 [P] Implement deterministic fake payment, email, and SMS adapters in `app/Modules/Payments/Testing/` and `app/Modules/Notifications/Testing/` (depends: T022; accept: all documented success/failure/timeout/unknown states require no network).
- [ ] T024 Extend configuration validation and health categories in `app/Modules/Operations/Application/Configuration/ConfigurationValidator.php` and `app/Modules/Operations/Application/Health/Checks/` for data, signing, payment, and notification keys (depends: T003, T011, T021-T023; accept: unsafe live config fails readiness without exposing values).
- [ ] T025 Merge Phase 1 schemas/operations into `specs/001-project-foundation/contracts/openapi.yaml`, update `docs/api/openapi.yaml`, and preserve `specs/002-registration-ticketing-credentials/contracts/openapi.yaml` as review input (depends: T008; accept: sync, lint, and backward-compatibility checks pass).
- [ ] T026 Register Phase 1 middleware aliases and empty versioned route files in `bootstrap/app.php`, `routes/api.php`, and each module `Routes/api.php` (depends: T013-T14, T17, T25; accept: route cache boots and no undocumented operation exists).

**Checkpoint**: Encryption, public context, permissions, signing, adapters,
errors, health, and contracts are ready; no story route performs product work.

---

## Phase 3: User Story 1 - Launch a Branded Event (Priority: P1) MVP

**Goal**: An authorized organizer configures and publishes a bilingual branded
event with an immutable form and available ticket types.

**Independent Test**: Create and publish one free event, open its approved-host
public page/form, and verify tenant isolation, branding, locale, and readiness.

### Tests for User Story 1

- [ ] T027 [P] [US1] Write event/form/ticket schema and composite-scope tests in `tests/Integration/MySql/EventSetupSchemaTest.php` (depends: T026; accept: tests fail before migrations).
- [ ] T028 [P] [US1] Write event lifecycle, tier-default, and publication-readiness unit tests in `tests/Unit/Events/EventLifecycleTest.php` (depends: T026; accept: invalid transitions/readiness currently fail).
- [ ] T029 [P] [US1] Write registration-form field, condition-cycle, immutability, and consent tests in `tests/Unit/Registration/RegistrationFormSchemaTest.php` (depends: T026; accept: tests fail before validators).
- [ ] T030 [P] [US1] Write organizer event/form/ticket OpenAPI tests in `tests/Contract/Phase1/EventSetupApiTest.php` (depends: T025; accept: documented operations are initially unimplemented).
- [ ] T031 [P] [US1] Write event setup isolation/RBAC/audit tests in `tests/Integration/Security/EventSetupIsolationTest.php` (depends: T015, T025; accept: cross-tenant IDs and unauthorized publication are denied identically).
- [ ] T032 [P] [US1] Write public host, branding, Arabic/English, accessibility, and enumeration tests in `tests/Feature/Events/PublicEventPageTest.php` and `resources/js/__tests__/public-event.test.tsx` (depends: T006-T007, T013; accept: pages/routes are missing).

### Implementation for User Story 1

- [ ] T033 [US1] Create events and event-branding migration in `database/migrations/2026_07_03_000009_create_events_tables.php` (depends: T027; accept: tenant-first keys, lifecycle checks, schedule checks, and approved-reference fields pass T027).
- [ ] T034 [US1] Create registration-form/version migration in `database/migrations/2026_07_03_000010_create_registration_form_tables.php` (depends: T027, T033; accept: version uniqueness and immutable published evidence pass).
- [ ] T035 [US1] Create ticket-type migration in `database/migrations/2026_07_03_000011_create_ticket_types_table.php` (depends: T027, T033; accept: same-event keys, currency/sale checks, and tenant-first indexes pass).
- [ ] T036 [P] [US1] Implement Event and EventBranding models in `app/Modules/Events/Infrastructure/Persistence/Models/` (depends: T033; accept: guarded fields, casts, and tenant scope tests pass).
- [ ] T037 [P] [US1] Implement RegistrationForm and RegistrationFormVersion models in `app/Modules/Registration/Infrastructure/Persistence/Models/` (depends: T034; accept: published update/delete attempts throw).
- [ ] T038 [P] [US1] Implement TicketType model in `app/Modules/Ticketing/Infrastructure/Persistence/Models/TicketType.php` (depends: T035; accept: cross-event relationships and invalid lifecycle fail).
- [ ] T039 [P] [US1] Implement event tier defaults and lifecycle/readiness services in `app/Modules/Events/Domain/` and `app/Modules/Events/Application/Publication/` (depends: T028, T036-T038; accept: T028 passes).
- [ ] T040 [P] [US1] Implement typed form fields, conditional validation, canonical schema hashing, and immutable version publishing in `app/Modules/Registration/Domain/Fields/` and `app/Modules/Registration/Application/Validation/` (depends: T029, T037; accept: T029 passes).
- [ ] T041 [US1] Implement audited event create/update/publish/cancel actions in `app/Modules/Events/Application/Actions/` (depends: T039-T040; accept: required state and audit commit/rollback together).
- [ ] T042 [US1] Implement audited form draft/publish and ticket create/update actions in `app/Modules/Registration/Application/Actions/` and `app/Modules/Ticketing/Application/Actions/` (depends: T038, T040-T041; accept: system validates same-event scope and protected published state).
- [ ] T043 [US1] Implement organizer event/form/ticket requests, resources, controllers, and routes in `app/Modules/{Events,Registration,Ticketing}/Http/` and their `Routes/api.php` files (depends: T030, T041-T042; accept: T030 passes with thin controllers).
- [ ] T044 [US1] Implement approved-host public event/form query and controllers in `app/Modules/Events/Application/Queries/`, `app/Modules/Registration/Application/Queries/`, and `app/Modules/Events/Http/Controllers/Public/` (depends: T013, T039-T043; accept: unknown/inactive/cross-tenant host-event combinations return uniform 404).
- [ ] T045 [P] [US1] Implement organizer event setup pages/view models in `resources/js/pages/tenant/events/`, `resources/js/components/events/`, and `app/Modules/AdminConsole/ViewModels/Events/` (depends: T043; accept: page props contain no persistence object or unauthorized field).
- [ ] T046 [P] [US1] Implement public branded event/form shell in `resources/js/pages/public/registration/Event.tsx`, `resources/js/components/registration/`, and `resources/css/app.css` (depends: T032, T044; accept: English/LTR and Arabic/RTL accessibility tests pass).
- [ ] T047 [US1] Register immutable Event/Form/Ticket domain events and sanitized audit mappings in `app/Modules/{Events,Registration,Ticketing}/Domain/Events/` and `app/Modules/Audit/Application/Listeners/Phase1/` (depends: T041-T046; accept: T031 and all US1 tests pass).

**Checkpoint**: US1 is independently demonstrable as a published branded event
with no attendee, order, payment, credential, or notification creation.

---

## Phase 4: User Story 4 - Control Ticket Inventory and Scheduled Pricing (Priority: P1)

**Goal**: Deterministic pricing and MySQL-authoritative holds prevent overselling.

**Independent Test**: Compete for the final inventory unit and exercise all
time/capacity boundaries; one sale/hold succeeds and one price is selected.

### Tests for User Story 4

- [ ] T048 [P] [US4] Write inventory/hold/price-tier schema tests in `tests/Integration/MySql/TicketInventorySchemaTest.php` (depends: T035; accept: tables/constraints are absent).
- [ ] T049 [P] [US4] Write deterministic price-tier boundary tests in `tests/Unit/Ticketing/PriceTierEvaluatorTest.php` (depends: T038; accept: evaluator is absent).
- [ ] T050 [P] [US4] Write final-unit concurrency, expiry, conversion, and double-release tests in `tests/Integration/Ticketing/InventoryConcurrencyTest.php` (depends: T005; accept: oversell protection is absent).
- [ ] T051 [P] [US4] Write price-tier API and RBAC/audit tests in `tests/Contract/Phase1/TicketPricingApiTest.php` and `tests/Integration/Security/TicketPricingIsolationTest.php` (depends: T025, T043; accept: tier operations are incomplete).

### Implementation for User Story 4

- [ ] T052 [US4] Create inventory, hold, and price-tier migration in `database/migrations/2026_07_03_000012_create_ticket_inventory_tables.php` (depends: T048; accept: capacity/counter/currency/scope constraints and indexes pass).
- [ ] T053 [P] [US4] Implement TicketInventory, InventoryHold, and PriceTier models in `app/Modules/Ticketing/Infrastructure/Persistence/Models/` (depends: T052; accept: invalid counters and cross-event references fail).
- [ ] T054 [P] [US4] Implement Money, PriceQuote, and PriceTierEvaluator in `app/Modules/Ticketing/Domain/ValueObjects/` and `Application/Pricing/` (depends: T049, T053; accept: all timezone and boundary cases pass).
- [ ] T055 [US4] Implement row-locked reserve/convert/release services in `app/Modules/Ticketing/Application/Inventory/` (depends: T050, T053-T054; accept: held+sold never exceeds capacity and terminal transitions are idempotent).
- [ ] T056 [US4] Implement `ExpireInventoryHoldsJob` and scheduled command in `app/Modules/Ticketing/Application/Jobs/`, `app/Console/Commands/ExpireInventoryHolds.php`, and `routes/console.php` (depends: T055; accept: bounded duplicate workers release each hold once).
- [ ] T057 [US4] Extend ticket and price-tier actions/controllers/resources/routes in `app/Modules/Ticketing/Application/Actions/`, `Http/`, and `Routes/api.php` (depends: T051, T054-T055; accept: T051 passes).
- [ ] T058 [P] [US4] Add ticket inventory/price controls and state components in `resources/js/components/ticketing/` and `resources/js/pages/tenant/events/Ticketing.tsx` (depends: T057; accept: conflict/sold-out/paused states are localized and accessible).
- [ ] T059 [US4] Add inventory/price domain events, audited changes, metrics, and slow-lock telemetry in `app/Modules/Ticketing/Domain/Events/`, `app/Modules/Audit/Application/Listeners/Phase1/`, and `app/Modules/Operations/Application/Telemetry/` (depends: T055-T058; accept: no quantity/personal/provider payload leaks).
- [ ] T060 [US4] Add 10,000-attempt no-oversell and query-plan fixture in `tests/Performance/Phase1TicketingPerformanceTest.php` (depends: T056-T059; accept: intended indexes are used and zero oversell occurs).
- [ ] T061 [US4] Run and fix all `ticket-inventory` and `price-tiers` groups across `tests/` (depends: T048-T060; accept: all US4 tests pass independently).

**Checkpoint**: US4 supplies a stable quote/hold contract for free and paid registration.

---

## Phase 5: User Story 2 - Complete Free Self-Registration (Priority: P1)

**Goal**: A valid free submission atomically creates one order, attendee,
credential, notification intent, inventory sale, and audit trail.

**Independent Test**: Submit and replay a free registration; exactly one complete
aggregate exists and its PII-free QR validates.

### Tests for User Story 2

- [ ] T062 [P] [US2] Write submission/order/attendee/credential/notification schema tests in `tests/Integration/MySql/FreeRegistrationSchemaTest.php` (depends: T061; accept: product tables are absent).
- [ ] T063 [P] [US2] Write encrypted submission, consent, and exact-form-version tests in `tests/Integration/Registration/SubmissionValidationTest.php` (depends: T040; accept: storage/validation path is absent).
- [ ] T064 [P] [US2] Write free registration atomicity, audit-failure, and replay tests in `tests/Integration/Registration/FreeRegistrationTest.php` (depends: T055; accept: journey is absent).
- [ ] T065 [P] [US2] Write credential issue/sign/validate/no-PII tests in `tests/Unit/Credentials/CredentialIssuanceTest.php` (depends: T021; accept: issuance service is absent).
- [ ] T066 [P] [US2] Write public registration/order OpenAPI and uniform-error tests in `tests/Contract/Phase1/PublicRegistrationApiTest.php` (depends: T025, T044; accept: operations are unimplemented).
- [ ] T067 [P] [US2] Write public registration XSS, mass-assignment, rate-limit, host/event isolation, and token-enumeration tests in `tests/Integration/Security/PublicRegistrationSecurityTest.php` (depends: T014, T044; accept: defenses are incomplete).
- [ ] T068 [P] [US2] Write bilingual conditional-form/checkout/confirmation component tests in `resources/js/__tests__/free-registration.test.tsx` (depends: T046; accept: interactive journey is absent).

### Implementation for User Story 2

- [ ] T069 [US2] Create submission, order, item, and hold-link migration in `database/migrations/2026_07_03_000013_create_registration_order_tables.php` (depends: T062; accept: immutable snapshots, access-token hash, same-event keys, and money checks pass).
- [ ] T070 [US2] Create attendee migration in `database/migrations/2026_07_03_000014_create_attendees_table.php` (depends: T062, T069; accept: encrypted fields/blind indexes and one-item relation pass).
- [ ] T071 [US2] Create credential key metadata/credentials migration in `database/migrations/2026_07_03_000015_create_credentials_table.php` (depends: T062, T070; accept: token/nonce digests, key states, supersession scope, and one-active strategy pass).
- [ ] T072 [US2] Create notification-intent migration in `database/migrations/2026_07_03_000016_create_notifications_table.php` (depends: T062, T069-T071; accept: one intent per order/channel/template/version and encrypted destination checks pass).
- [ ] T073 [P] [US2] Implement RegistrationSubmission model/repository and encrypted answer/consent mapper in `app/Modules/Registration/Infrastructure/Persistence/` and `Application/Submission/` (depends: T063, T069; accept: only exact published schema values persist).
- [ ] T074 [P] [US2] Implement Order and OrderItem models/value objects in `app/Modules/Orders/Infrastructure/Persistence/Models/` and `app/Modules/Orders/Domain/` (depends: T069; accept: immutable money snapshots and legal transitions pass).
- [ ] T075 [P] [US2] Implement Attendee model and creation contract in `app/Modules/Attendees/Infrastructure/Persistence/Models/` and `Contracts/` (depends: T070; accept: PII is encrypted and blind-index tests pass).
- [ ] T076 [P] [US2] Implement Credential model, issuer, compact token encoder, and validator in `app/Modules/Credentials/Infrastructure/Persistence/Models/` and `Application/` (depends: T065, T071; accept: signing, authoritative state, tamper, expiry, and PII-free tests pass).
- [ ] T077 [P] [US2] Implement Notification model and confirmation-intent factory in `app/Modules/Notifications/Infrastructure/Persistence/Models/` and `Application/` (depends: T072; accept: duplicate aggregate creation yields one intent).
- [ ] T078 [US2] Implement audited `CompleteFreeRegistration` orchestration in `app/Modules/Orders/Application/Actions/CompleteFreeRegistration.php` using Registration, Ticketing, Attendees, Credentials, and Notifications contracts (depends: T055, T073-T077; accept: T064 passes and no module queries another module's models).
- [ ] T079 [US2] Implement public registration/order requests, resources, controllers, and routes in `app/Modules/Orders/Http/` and `app/Modules/Orders/Routes/api.php` (depends: T066-T067, T078; accept: replay returns original safe response and token is displayed once).
- [ ] T080 [P] [US2] Implement public conditional form, free checkout, and order result pages in `resources/js/pages/public/registration/` and `resources/js/components/registration/` (depends: T068, T079; accept: Arabic/English validation and accessibility pass).
- [ ] T081 [US2] Add free-registration domain events and atomic sanitized audit mappings in `app/Modules/{Registration,Orders,Attendees,Credentials,Notifications}/Domain/Events/` and `app/Modules/Audit/Application/Listeners/Phase1/` (depends: T078-T080; accept: forced audit failure leaves zero partial aggregate).
- [ ] T082 [US2] Add synthetic event/registration factories and idempotent Phase 1 seeder in `database/factories/Phase1/` and `database/seeders/Phase1RegistrationSeeder.php` (depends: T069-T081; accept: repeat test seeding is deterministic and production refuses).
- [ ] T083 [US2] Run and fix `free-registration` and Phase 1 public security groups across `tests/` (depends: T062-T082; accept: all US2 tests pass independently with fake delivery).

**Checkpoint**: Free registration is the first end-to-end attendee MVP.

---

## Phase 6: User Story 3 - Purchase a Paid Ticket (Priority: P1)

**Goal**: Paid checkout completes only from authoritative matching payment state,
and every failure/unknown outcome is idempotent and recoverable.

**Independent Test**: Drive fake payment success, failure, duplicate, timeout,
unknown, mismatch, and reconciliation; only one valid capture issues one credential.

### Tests for User Story 3

- [ ] T084 [P] [US3] Write payment account/attempt/webhook/refund schema tests in `tests/Integration/MySql/PaymentSchemaTest.php` (depends: T083; accept: tables are absent).
- [ ] T085 [P] [US3] Write shared payment adapter contract suite in `tests/Contract/Payments/PaymentGatewayContractTestCase.php` and fake cases in `FakePaymentGatewayTest.php` (depends: T022-T023; accept: contract gaps are listed).
- [ ] T086 [P] [US3] Write Moyasar request/response/redaction contract tests in `tests/Contract/Payments/MoyasarPaymentGatewayTest.php` using HTTP fakes (depends: T022; accept: adapter is absent and no real network is used).
- [ ] T087 [P] [US3] Write paid checkout/callback/browser race/late-capture integration tests in `tests/Integration/Payments/PaidRegistrationTest.php` (depends: T078; accept: paid flow is absent).
- [ ] T088 [P] [US3] Write duplicate/forged webhook and amount/currency/account/live-mode mismatch tests in `tests/Integration/Security/PaymentWebhookSecurityTest.php` (depends: T013-T014; accept: callback route is absent).
- [ ] T089 [P] [US3] Write timeout-before-send, unknown-outcome, reconciliation, hold-expiry, and outage recovery tests in `tests/Integration/Payments/PaymentReconciliationTest.php` (depends: T056; accept: reconciliation is absent).
- [ ] T090 [P] [US3] Write public payment-intent/status OpenAPI tests in `tests/Contract/Phase1/PaidRegistrationApiTest.php` (depends: T025, T079; accept: payment operations are unimplemented).

### Implementation for User Story 3

- [ ] T091 [US3] Create payment tables migration in `database/migrations/2026_07_03_000017_create_payment_tables.php` (depends: T084; accept: account mapping, unique provider/event/idempotency keys, money bounds, and reconciliation indexes pass).
- [ ] T092 [P] [US3] Implement PaymentAccount, PaymentAttempt, WebhookReceipt, and Refund models in `app/Modules/Payments/Infrastructure/Persistence/Models/` (depends: T091; accept: provider payload/card fields cannot be mass assigned).
- [ ] T093 [P] [US3] Complete fake payment adapter and registry/readiness rules in `app/Modules/Payments/Testing/FakePaymentGateway.php` and `Application/PaymentGatewayRegistry.php` (depends: T085, T092; accept: fake passes every adapter contract state).
- [ ] T094 [P] [US3] Implement Moyasar adapter/authentication/mapping in `app/Modules/Payments/Infrastructure/Adapters/Moyasar/` using secret references and bounded HTTP (depends: T086, T092; accept: contract tests pass with zero provider payload leakage).
- [ ] T095 [US3] Implement payment intent creation and browser-return reconciliation in `app/Modules/Payments/Application/Actions/` (depends: T087, T093-T094; accept: provider calls occur outside inventory transactions and unknown stays pending).
- [ ] T096 [US3] Implement webhook receipt controller and authoritative fetch processor in `app/Modules/Payments/Http/Controllers/Webhooks/MoyasarWebhookController.php` and `Application/Webhooks/` (depends: T088, T094-T095; accept: quick dedupe acknowledgement precedes idempotent processing).
- [ ] T097 [US3] Implement `ReconcilePaymentAttemptJob` and bounded scheduler in `app/Modules/Payments/Application/Jobs/`, `app/Console/Commands/ReconcilePayments.php`, and `routes/console.php` (depends: T089, T095-T096; accept: reconcile-first recovery never duplicates capture effects).
- [ ] T098 [US3] Implement audited `CompletePaidRegistration` orchestration in `app/Modules/Orders/Application/Actions/CompletePaidRegistration.php` (depends: T078, T095-T097; accept: exact account/order/amount/currency/live match converts hold and creates one attendee/credential).
- [ ] T099 [US3] Implement public payment-intent/status requests, resources, controllers, and routes in `app/Modules/Payments/Http/` and `Routes/api.php` (depends: T090, T095-T098; accept: T090 passes with stable provider-neutral outcomes).
- [ ] T100 [P] [US3] Implement paid checkout action/pending/recovery UI in `resources/js/pages/public/registration/Payment.tsx` and `resources/js/components/orders/` (depends: T099; accept: immutable totals and action-required/pending/failed states are accessible/localized).
- [ ] T101 [US3] Add payment domain events, audit mappings, safe metrics, and readiness in `app/Modules/Payments/Domain/Events/`, `app/Modules/Audit/Application/Listeners/Phase1/`, and `app/Modules/Operations/Application/Health/Checks/PaymentCheck.php` (depends: T95-T100; accept: succeeded/denied/failed/unknown outcomes carry no secrets).
- [ ] T102 [US3] Add tenant payment-account configuration action/command in `app/Modules/Payments/Application/Actions/ConfigurePaymentAccount.php` and `app/Console/Commands/ConfigurePaymentAccount.php` (depends: T092-T094; accept: stores references only, requires privilege/reason, and never echoes secrets).
- [ ] T103 [US3] Add paid registration, callback burst, and recovery performance tests in `tests/Performance/Phase1PaymentPerformanceTest.php` (depends: T097-T101; accept: duplicate bursts converge and reconciliation backlog drains within plan target).
- [ ] T104 [US3] Document production Moyasar onboarding evidence template in `docs/operations/payments.md` (depends: T94-T102; accept: live readiness requires merchant approval, test/live keys, webhook, refund, outage, and reconciliation proof).
- [ ] T105 [US3] Run and fix shared/Moyasar payment adapter contract suites across `tests/Contract/Payments/` (depends: T085-T104; accept: fake and Moyasar pass the same matrix).
- [ ] T106 [US3] Run and fix `paid-registration`, `payments`, and `payment-reconciliation` groups across `tests/` (depends: T084-T105; accept: all US3 tests pass independently).

**Checkpoint**: Paid registration is safe under retries, callback races, and outages.

---

## Phase 7: User Story 5 - Manage Attendees and Orders (Priority: P2)

**Goal**: Authorized organizers safely search/correct attendees, inspect orders,
and request bounded full/partial refunds.

**Independent Test**: Manage one synthetic attendee/order/refund with full audit
evidence while unauthorized and cross-tenant actors see no data.

### Tests for User Story 5

- [ ] T107 [P] [US5] Write bounded order/attendee query, blind-index, cursor, and 100k-row plan tests in `tests/Integration/Orders/OrganizerQueriesTest.php` (depends: T083; accept: organizer queries are absent).
- [ ] T108 [P] [US5] Write attendee correction/history/privacy and cross-tenant tests in `tests/Feature/Attendees/ManageAttendeeTest.php` (depends: T075; accept: correction action is absent).
- [ ] T109 [P] [US5] Write full/partial/duplicate/excess/unknown refund tests in `tests/Integration/Payments/RefundTest.php` (depends: T106; accept: refund action is absent).
- [ ] T110 [P] [US5] Write organizer orders/attendees/refunds OpenAPI and RBAC tests in `tests/Contract/Phase1/OrganizerOperationsApiTest.php` (depends: T025; accept: operations are incomplete).

### Implementation for User Story 5

- [ ] T111 [P] [US5] Implement bounded order and attendee queries in `app/Modules/Orders/Application/Queries/` and `app/Modules/Attendees/Application/Queries/` (depends: T107; accept: cursors bind tenant/event/filter/blind-index version and plans use intended indexes).
- [ ] T112 [P] [US5] Implement audited attendee correction/history action in `app/Modules/Attendees/Application/Actions/CorrectAttendee.php` (depends: T108, T111; accept: PII changes use redacted markers and financial identity is immutable).
- [ ] T113 [US5] Implement refund request/reconciliation actions and job in `app/Modules/Payments/Application/Actions/`, `Application/Jobs/ReconcileRefundJob.php` (depends: T109, T093-T097; accept: cumulative refund never exceeds capture and unknown is reconcilable).
- [ ] T114 [US5] Implement organizer order/attendee/refund requests, resources, controllers, and routes in `app/Modules/{Orders,Attendees,Payments}/Http/` and their `Routes/api.php` files (depends: T110-T113; accept: T110 passes with uniform cross-tenant 404).
- [ ] T115 [P] [US5] Implement explicit organizer order/attendee view models and pages in `app/Modules/AdminConsole/ViewModels/Events/`, `resources/js/pages/tenant/events/{Orders,Attendees}.tsx`, and `resources/js/components/{orders,attendees}/` (depends: T114; accept: lists minimize PII and render pending/unknown/refund states).
- [ ] T116 [US5] Add attendee/order/refund events and atomic audit mappings in `app/Modules/{Attendees,Orders,Payments}/Domain/Events/` and `app/Modules/Audit/Application/Listeners/Phase1/` (depends: T112-T115; accept: audit failure rolls back required local transitions).
- [ ] T117 [US5] Add organizer operations accessibility, tenant-switch, and zero-unauthorized-props tests in `resources/js/__tests__/organizer-operations.test.tsx` and `tests/Feature/AdminConsole/Phase1OrganizerAuthorizationTest.php` (depends: T115-T116; accept: all states pass Arabic/English and mobile/desktop checks).
- [ ] T118 [US5] Run and fix `phase-1-organizer`, `attendees`, and `refunds` groups across `tests/` (depends: T107-T117; accept: all US5 tests pass independently).

**Checkpoint**: Organizer operations expose only authorized event-scoped data.

---

## Phase 8: User Story 6 - Revoke and Reissue Credentials (Priority: P2)

**Goal**: Revocation is immediate and reissue atomically supersedes the old QR.

**Independent Test**: Validate, revoke, reject, reissue, and validate only the
replacement under key rotation and concurrency.

### Tests for User Story 6

- [ ] T119 [P] [US6] Write credential validation result and token-format contract tests in `tests/Contract/Credentials/CredentialContractTest.php` (depends: T076; accept: every result in `contracts/credential-contract.md` is enforced).
- [ ] T120 [P] [US6] Write revoke/reissue concurrency, audit atomicity, and one-active tests in `tests/Integration/Credentials/CredentialLifecycleTest.php` (depends: T076; accept: lifecycle actions are absent).
- [ ] T121 [P] [US6] Write cross-tenant/event, random-ID, tamper, key-state, expiry, and replay security tests in `tests/Integration/Security/CredentialSecurityTest.php` (depends: T021, T076; accept: lifecycle endpoints are absent).
- [ ] T122 [P] [US6] Write revoke/reissue/validate OpenAPI and RBAC tests in `tests/Contract/Phase1/CredentialApiTest.php` (depends: T025; accept: operations are incomplete).

### Implementation for User Story 6

- [ ] T123 [US6] Implement audited revoke/reissue actions with credential-set locking in `app/Modules/Credentials/Application/Actions/` (depends: T120-T121; accept: old token becomes invalid and exactly one replacement is active).
- [ ] T124 [US6] Harden validator result mapping and authoritative tenant/event checks in `app/Modules/Credentials/Application/Validation/CredentialValidator.php` (depends: T119, T123; accept: malformed/invalid/cross-scope responses expose no attendee data).
- [ ] T125 [US6] Implement credential requests, resources, controllers, policies, and routes in `app/Modules/Credentials/Http/` and `Routes/api.php` (depends: T122-T124; accept: T122 passes and raw token appears only in issue/reissue response).
- [ ] T126 [P] [US6] Implement organizer credential view/lifecycle dialog in `resources/js/pages/tenant/events/Credentials.tsx` and `resources/js/components/credentials/` (depends: T125; accept: permissions, reason, conflict, and one-time QR states are accessible/localized).
- [ ] T127 [US6] Add credential lifecycle/key-failure events, audit mappings, metrics, and alerts in `app/Modules/Credentials/Domain/Events/`, `app/Modules/Audit/Application/Listeners/Phase1/`, and `app/Modules/Operations/Application/Telemetry/` (depends: T123-T126; accept: validation denial is evidenced without token/PII).
- [ ] T128 [US6] Implement credential key health/rotation/compromise commands in `app/Console/Commands/CredentialKeysCheck.php` and `RotateCredentialKey.php` (depends: T021, T124; accept: commands use secret references, require explicit confirmation, and print no key material).
- [ ] T129 [US6] Document token, key ceremony, rotation, compromise, revoke, and reissue runbook in `docs/operations/credential-keys.md` (depends: T128; accept: recovery distinguishes signing disablement from historical verification).
- [ ] T130 [US6] Add credential lifecycle accessibility and no-token-leak regression tests in `resources/js/__tests__/credential-lifecycle.test.tsx` and `tests/Integration/Security/CredentialLeakageTest.php` (depends: T126-T129; accept: HTML/log/audit/error fixtures contain no raw QR).
- [ ] T131 [US6] Run and fix `credentials` and Phase 1 credential security groups across `tests/` (depends: T119-T130; accept: all US6 tests pass independently).

**Checkpoint**: The credential core is ready for later wallet/scanning consumers.

---

## Phase 9: User Story 7 - Receive Reliable Localized Confirmations (Priority: P3)

**Goal**: Localized email/SMS delivery is durable, idempotent, observable, and
cannot invalidate a completed registration.

**Independent Test**: Deliver English/Arabic confirmations through fakes, retry
temporary failures, reconcile unknowns, and expose safe terminal failure once.

### Tests for User Story 7

- [ ] T132 [P] [US7] Write common email/SMS adapter contract suites in `tests/Contract/Notifications/NotificationAdapterContractTestCase.php` and fake adapter tests (depends: T022-T023; accept: all documented states are represented).
- [ ] T133 [P] [US7] Write SMTP and Unifonic request/callback/redaction tests in `tests/Contract/Notifications/{SmtpEmailAdapterTest,UnifonicSmsAdapterTest}.php` using fakes (depends: T022; accept: adapters are absent and no network runs).
- [ ] T134 [P] [US7] Write duplicate job, temporary/permanent/unknown failure, callback, and recovery tests in `tests/Integration/Notifications/NotificationDeliveryTest.php` (depends: T077; accept: delivery pipeline is absent).
- [ ] T135 [P] [US7] Write Arabic/English template, bidi, branding, and no-sensitive-content tests in `tests/Feature/Notifications/LocalizedConfirmationTest.php` (depends: T006; accept: templates are absent).

### Implementation for User Story 7

- [ ] T136 [P] [US7] Implement SMTP email and Unifonic SMS adapters in `app/Modules/Notifications/Infrastructure/Adapters/` (depends: T132-T133; accept: both pass the common contract and resolve secrets internally).
- [ ] T137 [P] [US7] Implement versioned Arabic/English confirmation templates in `app/Modules/Notifications/Application/Rendering/`, `resources/views/mail/phase1/`, and locale catalogs (depends: T135; accept: bidi-safe event/order/credential links contain no forbidden values).
- [ ] T138 [US7] Implement `DeliverNotificationJob`, callback processing, bounded retry, and adapter registry in `app/Modules/Notifications/Application/` and `Http/Controllers/Webhooks/` (depends: T134, T136-T137; accept: duplicate delivery intent/job/callback converges).
- [ ] T139 [US7] Register notification routes, schedules, telemetry, and safe readiness checks in `app/Modules/Notifications/Routes/api.php`, `Providers/NotificationServiceProvider.php`, `routes/console.php`, and `app/Modules/Operations/Application/Health/Checks/NotificationCheck.php` (depends: T138; accept: outage cannot fail order/credential and readiness exposes category only).
- [ ] T140 [US7] Add notification terminal events and sanitized audit mappings in `app/Modules/Notifications/Domain/Events/` and `app/Modules/Audit/Application/Listeners/Phase1/` (depends: T138-T139; accept: queued and terminal failure evidence contains no destination/body/provider payload).
- [ ] T141 [US7] Add organizer delivery-status view to `resources/js/pages/tenant/events/Orders.tsx` and `resources/js/components/orders/NotificationStatus.tsx` (depends: T139-T140; accept: authorized staff see safe queued/delivered/failure categories).
- [ ] T142 [US7] Document SMTP/Unifonic sender onboarding, templates, callback, retry, and outage recovery in `docs/operations/notifications.md` (depends: T136-T141; accept: live readiness evidence and secret handling are explicit).
- [ ] T143 [US7] Run and fix `notifications` and localized confirmation groups across `tests/` (depends: T132-T142; accept: all US7 tests pass independently).

**Checkpoint**: Every completed registration has durable localized confirmation handling.

---

## Phase 10: Polish and Cross-Cutting Release Readiness

**Purpose**: Validate Phase 1 as one secure, portable, documented release.

- [ ] T144 [P] Update permission and audit catalogs in `docs/standards/permission-catalog.md` and `docs/standards/audit-event-catalog.md` (depends: T047, T059, T081, T101, T116, T127, T140; accept: automated catalog checks match code exactly).
- [ ] T145 [P] Document event/form/ticket/inventory lifecycle and support procedures in `docs/standards/phase1-registration-ticketing.md` (depends: T061, T083; accept: safe/unsafe examples and ownership are explicit).
- [ ] T146 [P] Document Phase 1 PDPL classification, encryption, consent, retention, anonymization, legal hold, residency, and breach handling in `docs/operations/phase1-data-governance.md` (depends: T011, T073, T075; accept: no unapproved fixed production retention is implied).
- [ ] T147 Implement tenant-aware anonymization/legal-hold jobs and dry-run command in `app/Modules/Attendees/Application/Jobs/`, `app/Console/Commands/ApplyAttendeeRetention.php`, and `routes/console.php` (depends: T146; accept: eligible PII is removed while financial/audit tombstones remain).
- [ ] T148 [P] Extend architecture/phase-boundary tests in `tests/Architecture/PhaseBoundaryTest.php` for wallet, scan, check-in, kiosk, badge, ACS, identity, marketplace, and hardware exclusions (depends: all story phases; accept: controlled forbidden fixtures fail).
- [ ] T149 [P] Add full Phase 1 OpenAPI route coverage, response conformance, and compatibility tests in `tests/Contract/Phase1/Phase1OpenApiCoverageTest.php` (depends: T025, all routes; accept: 24 review operations have zero drift/undocumented route).
- [ ] T150 [P] Add full cross-channel tenant/event isolation matrix in `tests/Integration/Security/Phase1IsolationMatrixTest.php` (depends: all story phases; accept: 100% request/job/event/cache/file/log/telemetry/adapter attacks are denied).
- [ ] T151 [P] Add Phase 1 RBAC and audit catalog coverage matrices in `tests/Integration/Security/Phase1RbacAuditMatrixTest.php` (depends: T144; accept: every permission has allow/deny/revocation and every required action has outcome evidence).
- [ ] T152 [P] Add PII/card/secret/XSS/SQL-injection/mass-assignment regression suite in `tests/Integration/Security/Phase1SecurityRegressionTest.php` (depends: all story phases; accept: forbidden fixtures never persist or render).
- [ ] T153 [P] Add SaaS/on-premise and blocked-network recovery suite in `tests/Integration/Operations/Phase1DeploymentParityTest.php` (depends: T106, T143; accept: local core parity and explicit payment/SMS degradation pass).
- [ ] T154 Add Phase 1 migration fresh/upgrade/rollback/backup-restore evidence in `docs/release/phase1-migration-evidence.md` (depends: all migrations; accept: Phase 0 upgrade and repeat seed pass on MySQL 8.4).
- [ ] T155 Run dependency audits and remediate critical/high findings in `composer.lock`, `package-lock.json`, and `docs/release/phase1-dependency-audit.md` (depends: implementation complete; accept: zero unresolved critical/high advisory).
- [ ] T156 Execute every command in `specs/002-registration-ticketing-credentials/quickstart.md` and correct only inaccurate validation instructions (depends: T144-T155; accept: every command/result is reproduced natively without Docker).
- [ ] T157 Create Phase 1 release readiness report in `docs/release/phase1-readiness.md` linking API, migration, isolation, RBAC, audit, privacy, payment, credential, notification, accessibility, performance, parity, and exception evidence (depends: T156; accept: no expired exception or Phase 2+ artifact exists).
- [ ] T158 Run complete Composer/npm/backend/frontend/OpenAPI/docs/phase-boundary quality gates from `specs/002-registration-ticketing-credentials/quickstart.md` and mark Phase 1 ready only when all pass (depends: T157; accept: clean fresh run has zero failure, warning, drift, or skipped mandatory suite).

---

## Dependencies and Execution Order

### Phase Dependencies

| Phase | Tasks | Depends on | Completion gate |
|---|---|---|---|
| Setup | T001-T008 | Phase 0 | Providers/config/tests/contracts boot |
| Foundational | T009-T026 | Setup | Encryption, context, RBAC, signing, adapters, OpenAPI pass |
| US1 Event Setup | T027-T047 | Foundational | Published bilingual branded event |
| US4 Inventory/Pricing | T048-T061 | US1 ticket model | Zero oversell and deterministic price |
| US2 Free Registration | T062-T083 | US1 + US4 | One atomic free attendee/order/credential |
| US3 Paid Registration | T084-T106 | US2 aggregate + US4 holds | Authoritative recoverable paid flow |
| US5 Organizer Operations | T107-T118 | US2; refunds use US3 | Safe attendee/order/refund management |
| US6 Credential Lifecycle | T119-T131 | US2 credentials | Immediate revoke and atomic reissue |
| US7 Confirmations | T132-T143 | US2 notification intent | Reliable localized delivery |
| Polish | T144-T158 | Desired stories complete | Full Phase 1 release gates |

### User Story Dependency Graph

```text
Setup -> Foundational -> US1 -> US4 -> US2 -> US3
                                  |       ├-> US5
                                  |       ├-> US6
                                  |       └-> US7
                                  └----------> US5 query groundwork

US3 is required for US5 refund behavior.
US5, US6, and US7 may proceed in parallel after their listed prerequisites.
```

### Parallel Opportunities

- Setup: T002-T007 can run concurrently after T001 ownership is agreed.
- Foundational: T009, T010, T012, T015, T018, T020, and T022 are independent test/contract tasks.
- US1: T027-T032 tests run in parallel; T036-T040 split across owned modules; T045-T046 split organizer/public UI.
- US4: T048-T051 tests run in parallel; T053-T054 split persistence/domain work.
- US2: T062-T068 tests run in parallel; T073-T077 split by module; T080 can follow API contract independently.
- US3: T084-T090 tests run in parallel; T093-T094 split fake/Moyasar adapters; T100 and T104 are separate UI/docs work.
- US5: T107-T110 tests run in parallel; T111-T112 split Orders/Attendees.
- US6: T119-T122 tests run in parallel; T126, T128, and T129 own separate UI/operations/docs paths.
- US7: T132-T135 tests run in parallel; T136-T137 split adapter/template work.
- Polish: T144-T146 and T148-T153 own separate documentation/test files.

## Parallel Examples

### US1

```text
T027 schema tests | T028 lifecycle tests | T029 form tests | T030 API tests | T031 security tests | T032 UI tests
then
T036 event models | T037 form models | T038 ticket model | T039 lifecycle | T040 form validator
```

### US2

```text
T062 schema | T063 submission | T064 atomicity | T065 credential | T066 contract | T067 security | T068 frontend
then
T073 Registration | T074 Orders | T075 Attendees | T076 Credentials | T077 Notifications
```

### US3

```text
T084 schema | T085 fake contract | T086 Moyasar contract | T087 journey | T088 webhook security | T089 recovery | T090 API
then
T093 fake adapter | T094 Moyasar adapter
```

### US5-US7

```text
After US3: US5 organizer/refunds
After US2: US6 credential lifecycle | US7 confirmation delivery
```

## Implementation Strategy

### MVP

1. Complete Setup and Foundational.
2. Complete US1 event setup.
3. Complete US4 inventory/pricing.
4. Complete US2 free registration.
5. Stop and validate the free-registration MVP before enabling payments.

### Incremental Delivery

1. Event setup establishes organizer/public configuration.
2. Inventory/pricing makes availability authoritative.
3. Free registration proves the complete local aggregate.
4. Paid registration adds the external financial boundary.
5. Organizer operations, credential lifecycle, and confirmations harden daily operations.
6. Polish verifies the combined Phase 1 release.

### Execution Rules

- Execute tasks in numeric order unless `[P]` and all listed dependencies are complete.
- Write and observe each test fail before implementing its behavior.
- Files shared by multiple tasks are edited sequentially even when nearby tasks are `[P]`.
- Do not mark a task complete until its inline acceptance check passes.
- MySQL 8.4 is the acceptance database for constraints/concurrency.
- Use synthetic data only; never commit secrets, card data, real attendees, or provider payloads.
- Any public contract change updates both OpenAPI sources and compatibility tests.
- Do not scaffold wallet, scan/check-in, kiosk/badge, ACS, identity, marketplace, or hardware features.
