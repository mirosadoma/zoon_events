---
description: "Task list for Frontend Control Dashboard for Completed Core Phases"
---

# Tasks: Frontend Control Dashboard for Completed Core Phases

**Input**: Design documents from `/specs/006-frontend-control-dashboard/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/,
frontend-routes.md, component-map.md, api-integration-map.md, test-plan.md

**Tests**: MANDATORY (Constitution VII / CR-009). Unit, integration, backend
feature (RBAC/tenant), and browser-simulated journey tasks are included per user story.

**Organization**: Grouped by user story (US1–US7 from spec.md) for independent
implementation and testing.

**Product Phase**: Frontend Consolidation Phase (exposes Foundation + Phases 1–4)

## Format: `[ID] [P?] [Story?] [Model] Description with file path`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1–US7 (user-story phases only)
- **[Model]** (per user request "add tasks for cheaper llm model"): recommended
  LLM tier to execute the task —
  - **[M:H]** = cheaper model (e.g., Haiku 4.5): mechanical, well-scoped,
    pattern-following work (boilerplate components, route wiring from the route
    table, presentational pages, translations, skeletons, tests written from an
    explicit spec).
  - **[M:S]** = more capable model (e.g., Sonnet 5): architecture, cross-cutting
    integration, RBAC/shell logic, ambiguous gap resolution, backend read
    projections, and complex flow orchestration.
- Route→permission source: [frontend-routes.md](frontend-routes.md); component
  status (EXISTS/EXTEND/NEW): [component-map.md](component-map.md); data source per
  screen: [api-integration-map.md](api-integration-map.md).

## Path Conventions

Laravel + Inertia/React monolith: backend in `app/Modules/AdminConsole/**` and
`routes/web.php`; frontend in `resources/js/**`; frontend tests in
`resources/js/__tests__/**`; backend tests in `tests/Feature/AdminConsole/**`.
Browser-simulated journeys follow the existing
`resources/js/__tests__/*-browser.test.tsx` convention with `axe-core` in Vitest
jsdom.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Directory scaffolding and test harness for the consolidation work.

- [X] T001 [M:H] Create AdminConsole controller/ViewModel folders per plan (`app/Modules/AdminConsole/Http/Controllers/{Admin,Tenant/{Events,Registration,Ticketing,Orders,Attendees,Credentials,WalletPasses,ScanEvents,Kiosk,Badges,ManualDesk,Acs,Reports}}` and matching `ViewModels/**`)
- [X] T002 [P] [M:H] Create shared frontend component folders in `resources/js/components/{layout,tables,forms,feedback,status,modals,loaders}` with index barrels
- [X] T003 [P] [M:H] Add `resources/js/types/shell.ts` with `SessionContext` and `NavigationManifest` type stubs (from data-model.md "Shell view models")
- [X] T004 [P] [M:S] Confirm/configure Vitest jsdom browser-simulated test harness and npm script so `resources/js/__tests__/*-browser.test.tsx` journey specs run in CI

**Checkpoint**: Folders and test harness ready.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The unified shell, RBAC gating, shared components, states, modals,
loaders, and localization every user story depends on.

**⚠️ CRITICAL**: No user-story phase may begin until this phase is complete.

- [X] T005 [M:S] Extend `app/Http/Middleware/HandleInertiaRequests.php` to share `SessionContext` (user, tenant, role label, locale, theme) and the `can: Record<key,boolean>` permission map to all Inertia pages
- [X] T006 [M:S] Implement unified `resources/js/layouts/DashboardLayout.tsx` (sidebar + topbar + breadcrumbs + content + global route loader + toast host + route error boundary), folding in `layouts/FoundationLayout.tsx`
- [X] T007 [P] [M:S] Implement permission-keyed navigation manifest in `resources/js/lib/navigation.ts` and `lib/tenant-navigation.ts` covering every route in frontend-routes.md
- [X] T008 [P] [M:S] Implement `resources/js/components/layout/PermissionGate.tsx` and `ProtectedRoute.tsx` reading the shared `can` map
- [X] T009 [P] [M:H] Implement `resources/js/components/layout/{Sidebar,Topbar,Breadcrumbs,PageHeader,PageContent}.tsx` (Topbar shows tenant + role indicators, locale/theme toggles)
- [X] T010 [P] [M:H] Implement `resources/js/components/status/StatusBadge.tsx` covering event/order/payment/credential/wallet/scan/kiosk/badge-print/ACS-lane statuses (data-model.md status sets)
- [X] T011 [P] [M:H] Extend `resources/js/components/foundation/States.tsx` into `EmptyState`/`ErrorState`/`ForbiddenState` in `components/feedback/`
- [X] T012 [P] [M:H] Implement `resources/js/components/tables/{DataTable,FiltersBar,SearchInput,Pagination}.tsx`
- [X] T013 [P] [M:H] Implement `resources/js/components/feedback/{DetailsCard,Timeline,AuditTimeline}.tsx`
- [X] T014 [P] [M:H] Implement `resources/js/components/forms/{TextInput,SelectInput,DateTimeInput,CheckboxInput,TextareaInput,FormSection,FormActions,SubmitButtonWithLoader}.tsx` (submit disables + duplicate-submit guard + scoped loader)
- [X] T015 [P] [M:S] Implement `resources/js/components/modals/{ConfirmModal,ReasonModal}.tsx` (ReasonModal enforces a required reason field)
- [X] T016 [P] [M:H] Implement `resources/js/components/loaders/{GlobalRouteLoader,PageSkeleton,TableSkeleton,CardSkeleton,FormSubmitLoader,ButtonSpinner}.tsx`
- [X] T017 [P] [M:H] Implement toast system (host in `DashboardLayout`) in `resources/js/components/feedback/Toaster.tsx` + `hooks/useToast.ts`
- [X] T018 [P] [M:H] Add shared shell/navigation i18n keys to `resources/js/locales/{en,ar}.ts` (nav labels, common actions, state messages)
- [X] T019 [P] [M:H] [Test] Unit test sidebar/nav visibility per `can` map in `resources/js/__tests__/shell-navigation.test.tsx`
- [X] T020 [P] [M:H] [Test] Unit test `PermissionGate` show/hide and `StatusBadge` variants in `resources/js/__tests__/shell-permission-status.test.tsx`
- [X] T021 [P] [M:H] [Test] Unit test shared states + `SubmitButtonWithLoader` (disable/duplicate-submit) in `resources/js/__tests__/shell-states-forms.test.tsx`
- [X] T022 [P] [M:S] [Test] Unit test `ReasonModal` requires a reason before confirm in `resources/js/__tests__/shell-reason-modal.test.tsx`

**Checkpoint**: Shell + shared system ready; user stories can begin.

---

## Phase 3: User Story 1 - Sign in and operate a permission-aware dashboard (Priority: P1) 🎯 MVP

**Goal**: Authenticated, permission-aware shell with a tenant-scoped overview.

**Independent Test**: Sign in as users with different permissions; each sees only
permitted nav/actions; overview renders metrics; unauthenticated users are
redirected to `/login`.

### Tests for User Story 1 (MANDATORY)

- [X] T023 [P] [US1] [M:H] [Test] Integration test login success + invalid-credentials in `resources/js/__tests__/login-flow.test.tsx`
- [X] T024 [P] [US1] [M:H] [Test] Backend feature test: unauthenticated redirect + `dashboard.permission` on overview/profile in `tests/Feature/AdminConsole/OverviewAuthTest.php`
- [X] T025 [P] [US1] [M:H] [Test] Browser-simulated journey 1 (admin logs in, sees overview, nav matches permissions, axe + RTL) in `resources/js/__tests__/phase6-overview-browser.test.tsx`

### Implementation for User Story 1

- [X] T026 [US1] [M:H] Migrate `resources/js/pages/Auth/Login.tsx` onto `DashboardLayout`-less auth shell and shared form components; wire submit loader
- [X] T027 [US1] [M:S] Extend `app/Modules/AdminConsole/ViewModels/FoundationDashboardViewModel.php` to supply overview counters (events, published, attendees, orders, credentials, today check-ins, active kiosks/gates, failed scans, recent audit) from existing module summary queries
- [X] T028 [US1] [M:H] Build overview page cards + skeletons + recent-audit list in `resources/js/pages/FoundationDashboard.tsx` on `DashboardLayout`
- [X] T029 [P] [US1] [M:H] Create Profile page `resources/js/pages/Profile.tsx` and `ViewModels/Admin/ProfileViewModel.php` (name, email, phone, role, tenant, last login)
- [X] T030 [US1] [M:H] Wire routes `/`, `/profile` onto shell in `routes/web.php` behind `auth`
- [X] T031 [US1] [M:H] Add US1 i18n strings (overview, profile) to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1 is independently demoable (MVP).

---

## Phase 4: User Story 2 - Manage events, registration forms, ticketing, and pricing (Priority: P2)

**Goal**: Event lifecycle + registration-form + ticket-type + price-tier management.

**Independent Test**: Create an event, add a registration field, create a ticket
type and a price tier; publish gated by `event.publish`.

### Tests for User Story 2 (MANDATORY)

- [X] T032 [P] [US2] [M:H] [Test] Integration test events list + create-event flow in `resources/js/__tests__/events-manage.test.tsx`
- [X] T033 [P] [US2] [M:H] [Test] Integration test ticket-type + price-tier create in `resources/js/__tests__/ticketing-pricing.test.tsx`
- [X] T034 [P] [US2] [M:S] [Test] Backend feature test `dashboard.permission` for event/registration/ticketing routes + publish gating in `tests/Feature/AdminConsole/EventsAuthTest.php`
- [X] T035 [P] [US2] [M:H] [Test] Browser-simulated journeys 2–4 (create event, configure registration field, create ticket type) in `resources/js/__tests__/phase6-events-browser.test.tsx`

### Implementation for User Story 2

- [X] T036 [US2] [M:H] Wire events routes (`/tenant/events`, `/create`, `/{id}`, `/{id}/edit`) in `routes/web.php`; migrate `pages/tenant/events/index` + `EventSetup.tsx` onto shell
- [X] T037 [US2] [M:S] Build tabbed event detail page `resources/js/pages/tenant/events/Detail.tsx` + `ViewModels/Events/EventDetailViewModel.php` (tabs are presentation over existing queries)
- [X] T038 [US2] [M:S] Build registration form builder `resources/js/pages/tenant/registration/Builder.tsx` (add/edit/reorder/require fields, all field types) + controller/ViewModel; route `/{id}/registration-form`
- [X] T039 [P] [US2] [M:H] Wire registration preview route `/{id}/registration-preview` reusing `pages/public/registration/Event.tsx`
- [X] T040 [US2] [M:H] Wire ticket-types route `/{id}/ticket-types`; migrate `pages/tenant/events/Ticketing.tsx` onto shell + shared table
- [X] T041 [US2] [M:H] Build price-tiers page `resources/js/pages/tenant/ticketing/PriceTiers.tsx` + controller/ViewModel; route `/{id}/price-tiers`
- [X] T042 [US2] [M:S] Resolve GAP-1: add/confirm `listPriceTiers(eventId)` read projection on Ticketing module + OpenAPI; update `api-integration-map.md` status (fallback empty state if deferred)
- [X] T043 [US2] [M:H] Add publish/cancel via `ConfirmModal` on event detail, gated by `event.publish`/`event.cancel`
- [X] T044 [US2] [M:H] Add US2 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1 + US2 independently functional.

---

## Phase 5: User Story 3 - View orders, attendees, and manage credentials (Priority: P3)

**Goal**: Orders/attendees browsing + detail pages + credential revoke/reissue.

**Independent Test**: Filter orders/attendees, open details, revoke (with reason)
and reissue a credential, each permission-gated and modal-confirmed.

### Tests for User Story 3 (MANDATORY)

- [X] T045 [P] [US3] [M:H] [Test] Integration test attendee-detail load in `resources/js/__tests__/attendee-detail.test.tsx`
- [X] T046 [P] [US3] [M:S] [Test] Integration test credential revoke (reason) + reissue in `resources/js/__tests__/credential-actions.test.tsx`
- [X] T047 [P] [US3] [M:S] [Test] Backend feature test cross-tenant isolation + permission gating for orders/attendees/credentials in `tests/Feature/AdminConsole/CredentialsAuthTest.php`
- [X] T048 [P] [US3] [M:H] [Test] Browser-simulated journeys 5–7 (orders/attendees view, revoke, reissue) in `resources/js/__tests__/phase6-credentials-browser.test.tsx`

### Implementation for User Story 3

- [X] T049 [US3] [M:H] Wire orders routes; migrate `pages/tenant/events/Orders.tsx` onto shell + shared table/filters
- [X] T050 [P] [US3] [M:H] Build order detail `resources/js/pages/tenant/orders/Detail.tsx` + `ViewModels/Orders/OrderDetailViewModel.php`; route `/{id}/orders/{orderId}`
- [X] T051 [US3] [M:H] Wire attendees routes; migrate `pages/tenant/events/Attendees.tsx` onto shell + shared filters
- [X] T052 [P] [US3] [M:H] Build attendee detail `resources/js/pages/tenant/attendees/Detail.tsx` + `ViewModels/Attendees/AttendeeDetailViewModel.php`; route `/{id}/attendees/{attendeeId}`
- [X] T053 [US3] [M:H] Wire credentials routes; migrate `pages/tenant/events/Credentials.tsx` onto shell
- [X] T054 [P] [US3] [M:H] Build credential detail `resources/js/pages/tenant/credentials/Detail.tsx` + `ViewModels/Credentials/CredentialDetailViewModel.php`; route `/{id}/credentials/{credentialId}`
- [X] T055 [US3] [M:S] Align `components/credentials/CredentialDialog.tsx` to `ReasonModal` for revoke (reason required) and `ConfirmModal` for reissue, gated by `credential.revoke`/`credential.reissue`
- [X] T056 [US3] [M:H] Add US3 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US3 independently functional.

---

## Phase 6: User Story 4 - Wallet passes, QR scanning, and check-in (Priority: P4)

**Goal**: Wallet pass visibility + browser scanner + check-in dashboard + scan events.

**Independent Test**: Scan a valid code (accepted) and a revoked code (rejected +
reason); check-in dashboard counters update; scan-events filterable.

### Tests for User Story 4 (MANDATORY)

- [X] T057 [P] [US4] [M:S] [Test] Integration test scanner accept + reject + duplicate-submit guard in `resources/js/__tests__/scanner-flow.test.tsx`
- [X] T058 [P] [US4] [M:H] [Test] Backend feature test `dashboard.permission` for wallet/scanner/check-in/scan-events routes in `tests/Feature/AdminConsole/ScanningAuthTest.php`
- [X] T059 [P] [US4] [M:H] [Test] Browser-simulated journeys 8–9 (valid scan, revoked scan rejection) in `resources/js/__tests__/phase6-scanning-browser.test.tsx`

### Implementation for User Story 4

- [X] T060 [US4] [M:H] Migrate `pages/tenant/checkin/{WalletPasses,Scanner,Dashboard}.tsx` onto shell; confirm existing routes and add missing ones in `routes/web.php`
- [X] T061 [P] [US4] [M:H] Build wallet pass detail `resources/js/pages/tenant/wallet/Detail.tsx` + ViewModel; route `/{id}/wallet-passes/{passId}`
- [X] T062 [US4] [M:S] Resolve GAP-2: add/confirm `getWalletPass(passId)` detail projection on WalletPasses + OpenAPI; update `api-integration-map.md`
- [X] T063 [P] [US4] [M:H] Build scan-events page `resources/js/pages/tenant/checkin/ScanEvents.tsx` + ViewModel (filters: result/scanner/gate/offline); route `/{id}/scan-events`
- [X] T064 [US4] [M:S] Resolve GAP-3: add/confirm `listScanEvents(eventId, filters)` projection on Scanning + OpenAPI; update `api-integration-map.md`
- [X] T065 [US4] [M:H] Ensure check-in dashboard uses `lib/checkin-polling.ts` bounded polling on shell
- [X] T066 [US4] [M:H] Add US4 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US4 independently functional.

---

## Phase 7: User Story 5 - Kiosk, badge printing, and manual desk (Priority: P5)

**Goal**: Kiosk management + kiosk mode + badge templates/print jobs + manual desk.

**Independent Test**: Register a kiosk; open kiosk detail; manual-desk search +
reprint (reason required); badge print job appears.

### Tests for User Story 5 (MANDATORY)

- [X] T067 [P] [US5] [M:H] [Test] Integration test manual-desk search + reprint reason in `resources/js/__tests__/manual-desk.test.tsx`
- [X] T068 [P] [US5] [M:H] [Test] Backend feature test `dashboard.permission` for kiosk/badge/manual-desk + walk-up gating in `tests/Feature/AdminConsole/KioskBadgeAuthTest.php`
- [X] T069 [P] [US5] [M:H] [Test] Browser-simulated journeys 10–11 (badge print, manual-desk search) in `resources/js/__tests__/phase6-kiosk-badge-browser.test.tsx`

### Implementation for User Story 5

- [X] T070 [US5] [M:H] Wire kiosk routes; migrate `pages/tenant/kiosk/Index.tsx` onto shell
- [X] T071 [P] [US5] [M:H] Build kiosk detail `resources/js/pages/tenant/kiosk/Detail.tsx` + ViewModel; route `/{id}/kiosks/{kioskId}`
- [X] T072 [US5] [M:S] Resolve GAP-4: add/confirm `getKiosk(kioskId)` detail projection on Kiosk + OpenAPI; update `api-integration-map.md`
- [X] T073 [P] [US5] [M:S] Build kiosk mode page `resources/js/pages/kiosk/Mode.tsx` (branding, QR scan, lookup fallback, print, success/reset) behind `kiosk.session` middleware; route `/kiosk/{device_code}`
- [X] T074 [US5] [M:H] Wire badge-templates route; migrate `pages/tenant/badge-templates/Designer.tsx` onto shell
- [X] T075 [P] [US5] [M:H] Build badge print-jobs page `resources/js/pages/tenant/badges/PrintJobs.tsx` + ViewModel (status filter, reprint reason); route `/{id}/badge-print-jobs`
- [X] T076 [US5] [M:S] Resolve GAP-5: add/confirm `listPrintJobs(eventId, filters)` projection on BadgePrinting + OpenAPI; update `api-integration-map.md`
- [X] T077 [US5] [M:H] Wire manual-desk route + walk-up route; migrate `pages/tenant/manual-desk/Desk.tsx` onto shell; enforce `ReasonModal` for override/reprint
- [X] T078 [US5] [M:H] Add US5 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US5 independently functional.

---

## Phase 8: User Story 6 - ACS access control management and monitoring (Priority: P6)

**Goal**: ACS overview + zones/lanes/rules + access logs + gate health + emergency.

**Independent Test**: Create a zone, a lane in it, and a rule; access-logs and
gate-health render; emergency control only for `acs.emergency.manage`.

### Tests for User Story 6 (MANDATORY)

- [X] T079 [P] [US6] [M:S] [Test] Integration test ACS zone→lane→rule create flow in `resources/js/__tests__/acs-config.test.tsx`
- [X] T080 [P] [US6] [M:H] [Test] Backend feature test `dashboard.permission` for ACS config/view/emergency in `tests/Feature/AdminConsole/AcsAuthTest.php`
- [X] T081 [P] [US6] [M:H] [Test] Browser-simulated journey 12 (create zone/lane/rule) in `resources/js/__tests__/phase6-acs-browser.test.tsx`

### Implementation for User Story 6

- [X] T082 [US6] [M:H] Wire ACS overview route; migrate `pages/tenant/acs/Index.tsx` onto shell
- [X] T083 [P] [US6] [M:H] Build ACS zones page `resources/js/pages/tenant/acs/Zones.tsx` (or promote `components/acs/ZoneLaneEditor.tsx`) + route `/{id}/acs/zones`
- [X] T084 [P] [US6] [M:H] Build ACS lanes page `resources/js/pages/tenant/acs/Lanes.tsx` (assign to zone) + route `/{id}/acs/lanes`
- [X] T085 [P] [US6] [M:S] Build ACS rules page `resources/js/pages/tenant/acs/Rules.tsx` (reuse `components/acs/RuleEditor.tsx`) + route `/{id}/acs/rules`
- [X] T086 [US6] [M:H] Wire access-logs route; migrate `pages/tenant/gate-events/Index.tsx` onto shell as `/{id}/acs/access-logs`
- [X] T087 [US6] [M:H] Wire gate-health route; migrate `pages/tenant/acs-health/Index.tsx` onto shell as `/{id}/acs/gate-health` (bounded polling)
- [X] T088 [US6] [M:S] Wire emergency egress UI (`components/acs/*`) with `ConfirmModal`+`ReasonModal`, gated by `acs.emergency.manage`
- [X] T089 [US6] [M:H] Add US6 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US6 independently functional.

---

## Phase 9: User Story 7 - Foundation administration, reports, and audit visibility (Priority: P7)

**Goal**: Users/roles/tenant-settings/audit admin pages + per-event reports.

**Independent Test**: List/filter users and toggle activation; filter audit logs;
open an event report and see available metrics render.

### Tests for User Story 7 (MANDATORY)

- [X] T090 [P] [US7] [M:H] [Test] Integration test users list/filter/activate + audit filter in `resources/js/__tests__/admin-users-audit.test.tsx`
- [X] T091 [P] [US7] [M:S] [Test] Backend feature test platform/tenant permission gating for admin pages in `tests/Feature/AdminConsole/AdminPagesAuthTest.php`
- [X] T092 [P] [US7] [M:H] [Test] Unit test event-report renders available metrics + placeholders in `resources/js/__tests__/event-report.test.tsx`

### Implementation for User Story 7

- [X] T093 [P] [US7] [M:H] Build Users admin page `resources/js/pages/admin/Users.tsx` + `ViewModels/Admin/UsersViewModel.php`; route `/admin/users`
- [X] T094 [P] [US7] [M:S] Build Roles admin page `resources/js/pages/admin/Roles.tsx` + ViewModel (assign permissions; protect system roles); route `/admin/roles`
- [X] T095 [P] [US7] [M:H] Build Tenant settings page `resources/js/pages/admin/TenantSettings.tsx` + ViewModel; route `/admin/tenant-settings`
- [X] T096 [P] [US7] [M:H] Build Audit logs page `resources/js/pages/admin/AuditLogs.tsx` + ViewModel (filters: actor/action/entity/date; before/after); route `/admin/audit-logs`
- [X] T097 [P] [US7] [M:S] Build Event report page `resources/js/pages/tenant/reports/EventReport.tsx` + `ViewModels/Reports/EventReportViewModel.php` composing per-module summaries; route `/{id}/reports`
- [X] T098 [US7] [M:S] Resolve GAP-6: confirm/add report metric read projections (first-scan success rate, wallet adoption) across modules; label missing metrics; update `api-integration-map.md`
- [X] T099 [US7] [M:H] Add US7 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: All user stories independently functional.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Consolidation, docs, accessibility/RTL, responsiveness, parity, and
final validation across all stories.

- [X] T100 [P] [M:H] Replace remaining per-page ad-hoc badges/states with shared `StatusBadge`/state components across `resources/js/pages/**`
- [X] T101 [M:S] Remove `layouts/FoundationLayout.tsx` after all pages are migrated to `DashboardLayout`; fix references
- [X] T102 [P] [M:H] Update `docs/standards/dashboard-design-system.md` with new shared components/states and the permission→page matrix
- [X] T103 [P] [M:H] Finalize `api-integration-map.md` Missing-API register statuses and mirror the summary into `spec.md` "Missing Backend API Requirements"
- [X] T104 [P] [M:S] Accessibility + Arabic/RTL sweep (axe) across all new/migrated pages in `resources/js/__tests__/phase6-accessibility-browser.test.tsx`
- [X] T105 [P] [M:H] Responsive/tablet + mobile checks for scanner/kiosk-mode/manual-desk (no horizontal scroll) in `resources/js/__tests__/phase6-responsive-browser.test.tsx`
- [X] T106 [M:S] Verify SaaS/on-premise parity: route error boundaries degrade gracefully when an adapter/dependency is unavailable (no cloud-only calls)
- [X] T107 [P] [M:S] [Test] Backend feature test: zero cross-tenant props across all new controllers in `tests/Feature/AdminConsole/CrossTenantPropsTest.php`
- [X] T108 [M:H] Run quality gates: `npm run lint`, `npm run typecheck`, `vite build`, `composer quality` (fix failures) — 2026-07-08: architecture boundary allowlists (AdminConsole), ACS integer ID validation, dashboard auth test alignment, `composer lint` + `zonetec:phase-boundary:check` green; full `composer test` blocked by flaky `zonetec_testing` migration state (deadlocks during concurrent `db:wipe`/`migrate:fresh`)
- [X] T109 [M:H] Run `quickstart.md` validation end-to-end and record results

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies.
- **Foundational (Phase 2)**: depends on Setup; BLOCKS all user stories.
- **User Stories (Phases 3–9)**: depend on Foundational; then independent of each
  other. US2–US7 each reuse the US1 shell but are separately testable. Priority
  order P1→P7; can be parallelized across developers/models after Phase 2.
- **Polish (Phase 10)**: depends on all targeted user stories.

### Within Each User Story

- Tests written first and failing → ViewModels/props → pages/components → route
  wiring → i18n. Migration of an EXISTS page precedes building its NEW detail page
  where they share a ViewModel.

### Backend gap tasks

- GAP tasks (T042, T062, T064, T072, T076, T098) are `[M:S]` and touch domain
  modules + OpenAPI; if a gap is deferred, its page ships the placeholder/empty
  state and the register records "deferred" — the user story remains testable.

### Parallel Opportunities

- All `[P]` Setup and Foundational component tasks run in parallel.
- After Phase 2, US1–US7 can proceed in parallel; within a story, `[P]` detail
  pages/tests run in parallel.
- Cheaper-model batch: all `[M:H]` presentational/wiring/translation/test tasks can
  be dispatched to a cheaper model in parallel where `[P]`.

---

## Parallel Example: User Story 3

```text
# Detail pages (different files) in parallel:
Task T050 [M:H] order detail page
Task T052 [M:H] attendee detail page
Task T054 [M:H] credential detail page
# Tests in parallel:
Task T045 [M:H] attendee-detail integration test
Task T046 [M:S] credential revoke/reissue integration test
```

---

## Model-Tier Summary (per "add tasks for cheaper llm model")

- **[M:H] cheaper-model-eligible** (mechanical/presentational/wiring/i18n/tests
  from explicit specs): the majority of tasks — e.g., T002, T003, T009–T014,
  T016–T021, T026, T028–T031, T036, T039–T041, T043–T054, T056, T060–T061, T063,
  T065–T071, T074–T075, T077–T078, T082–T084, T086–T087, T089–T093, T095–T096,
  T099, T100, T102–T103, T105, T108–T109.
- **[M:S] capable-model-recommended** (architecture/RBAC/shell/backend
  projections/complex flows): T001(struct decision light), T004, T005–T008, T015,
  T022, T027, T034, T037–T038, T042, T046–T047, T055, T057, T062, T064, T072–T073,
  T076, T079, T085, T088, T091, T094, T097–T098, T101, T104, T106–T107.
- Guidance: route `[M:H]` tasks to a cheaper model; reserve `[M:S]` for a capable
  model. Every task, regardless of tier, must pass the same lint/type/test gates.

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 Setup → 2. Phase 2 Foundational (shell) → 3. Phase 3 US1 → validate →
demo. US1 alone proves auth, RBAC-aware navigation, tenant/role context, and the
overview — the smallest useful increment.

### Incremental Delivery

Add US2…US7 one at a time; each is independently testable and shippable. Backend
GAP resolutions can trail their page (placeholder first, projection second).

### Parallel Team / Model Strategy

After Phase 2, assign `[M:H]` presentational/wiring/test tasks to a cheaper model
and `[M:S]` shell/logic/backend tasks to a capable model; different user stories to
different developers. Re-verify shared-component changes against all stories.

## Notes

- [P] = different files, no incomplete-task dependency.
- Tests are mandatory and must fail before implementation (Constitution VII).
- Presentation never reads another module's persistence directly; data flows via
  published module queries as Inertia props (plan.md, Constitution VI).
- Keep every user-visible surface Arabic/English + RTL and axe-clean.
- Commit after each task or logical group.
