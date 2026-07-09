---
description: "Task list for TailAdmin Dashboard UI Redesign"
---

# Tasks: TailAdmin Dashboard UI Redesign

**Input**: Design documents from `/specs/008-tailadmin-ui-redesign/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/ui-contract.md,
frontend-routes.md, component-map.md, design-system.md, api-integration-map.md,
rbac-ui-map.md, test-plan.md, missing-api-requirements.md

**Tests**: MANDATORY (Constitution VII / CR-009). Component, page-render, and flow
tests (incl. real-endpoint assertions) are included per user story.

**Organization**: Grouped by user story (US1–US7 from spec.md) for independent
implementation and testing.

**Product Phase**: Frontend UI Redesign Phase (restyle over completed Foundation +
Phases 1–4; no backend business change; no Phase 5).

## Format: `[ID] [P?] [Story?] [Model] Description with file path`

- **[P]**: Can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: US1–US7 (user-story phases only)
- **[Model]** (per request "do tasks for cheap llm model"): recommended LLM tier —
  - **[M:H]** = cheaper model (e.g., Haiku): token/CSS restyle, presentational page/
    component re-skin, i18n, status variants, skeletons/empty/error states, tests from
    an explicit spec, and straightforward `fetch` action wiring copied from the
    documented pattern.
  - **[M:S]** = capable model (e.g., Sonnet): shell/nav architecture, notification/
    search gap handling, sensitive-action wiring (credential revoke/reissue, publish/
    cancel — CR-003 correctness), registration-builder CRUD, the shared `apiFetch`
    helper + error mapping, and any thin AdminConsole prop addition.
- Sources: component-map.md (EXISTS/RESTYLE/NEW), rbac-ui-map.md (real permission keys),
  api-integration-map.md (screen→endpoint), design-system.md (tokens/anatomy),
  contracts/ui-contract.md (route→permission→states→endpoint). **Restyle in place —
  keep props/routes/permissions; do not fork components; do not change backend logic.**

## Path Conventions

Frontend: `resources/js/**`, tokens in `resources/css/app.css`. Tests:
`resources/js/__tests__/**`. Thin presentation props (only if needed):
`app/Modules/AdminConsole/**`. Docs: `docs/standards/dashboard-design-system.md`.
**Action pattern** (copy from `resources/js/pages/tenant/manual-desk/Desk.tsx`):
`fetch('/api/v1/...', { credentials:'include', headers:{...apiHeaders,'Idempotency-Key':crypto.randomUUID()}, body })`,
then on `!ok` show error/toast (no status change), on `ok` toast + `router.reload()`.

---

## Phase 1: Setup (Shared Foundation)

**Purpose**: Design tokens, shared helpers, and a demo-content baseline.

- [X] T001 [M:H] Evolve design tokens + utility classes in `resources/css/app.css` to the TailAdmin scale per design-system.md (surfaces, ink, brand, radius, shadow, typography, spacing; keep `.dark`, `:focus-visible`, `.skip-link`, reduced-motion, logical-RTL)
- [X] T002 [P] [M:H] Add a centralized status-color palette (success/warning/danger/info/neutral/emphasis, light+dark) to `resources/css/app.css` for `StatusBadge`/alerts
- [X] T003 [M:S] Add a shared `apiFetch` helper in `resources/js/lib/apiFetch.ts` (tenant header, `Idempotency-Key` for writes, uniform error/validation/unauthorized/forbidden mapping per FR-017)
- [X] T004 [P] [M:H] Audit and list TailAdmin/demo leftovers to remove in `specs/008-tailadmin-ui-redesign/missing-api-requirements.md` notes (no demo data ships)

**Checkpoint**: Tokens + shared fetch helper ready.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The shared component library every page and the shell reuse.

**⚠️ CRITICAL**: No user-story phase may begin until this phase is complete.

- [X] T005 [P] [M:H] Restyle `StatusBadge` in `resources/js/components/status/StatusBadge.tsx` to cover all domains + statuses in data-model.md §4 with AR/EN labels and small/medium sizes
- [X] T006 [P] [M:H] Restyle `resources/js/components/tables/{DataTable,FiltersBar,SearchInput,Pagination}.tsx` to the TailAdmin table (toolbar, soft borders, status cells, responsive scroll)
- [X] T007 [P] [M:H] Build table row `ActionDropdown` in `resources/js/components/tables/ActionDropdown.tsx` (keyboard + RTL aware)
- [X] T008 [P] [M:H] Build `StatCard`/`MetricCard`/`InfoCard` in `resources/js/components/cards/` (icon + label + value + delta/description + optional status)
- [X] T009 [P] [M:H] Restyle form inputs in `resources/js/components/forms/*` and add missing ones (`EmailInput,PhoneInput,RadioGroup,MultiSelect,FileInput`) with label/required/inline `ValidationError`
- [X] T010 [P] [M:H] Restyle `SubmitButtonWithLoader` (spinner, disabled, duplicate-guard) in `resources/js/components/forms/SubmitButtonWithLoader.tsx`
- [X] T011 [P] [M:H] Restyle `resources/js/components/modals/{ConfirmModal,ReasonModal}.tsx` and add `DetailsModal.tsx` (focus-trap, ESC/overlay, RTL)
- [X] T012 [P] [M:H] Restyle loaders in `resources/js/components/loaders/*` (GlobalRouteLoader with brand icon, Page/Table/Card/Form skeletons, ButtonSpinner)
- [X] T013 [P] [M:H] Restyle feedback states in `resources/js/components/feedback/States.tsx` (Empty/Error/Forbidden/Conflict/Queued) + `Toaster.tsx`
- [X] T014 [P] [M:H] Add shared design-system i18n keys (states, common actions, table labels) to `resources/js/locales/{en,ar}.ts`
- [X] T015 [P] [M:H] [Test] Component tests for `StatusBadge` variants + `DataTable` states + `ActionDropdown` in `resources/js/__tests__/ds-table-badge.test.tsx`
- [X] T016 [P] [M:H] [Test] Component tests for `SubmitButtonWithLoader` (disable/duplicate-guard), `ConfirmModal`, `ReasonModal` (reason required) in `resources/js/__tests__/ds-forms-modals.test.tsx`

**Checkpoint**: Shared component library ready; shell and pages can begin.

---

## Phase 3: User Story 1 - Unified shell and shared design system (Priority: P1) 🎯 MVP

**Goal**: One TailAdmin-style shell: grouped collapsible sidebar, topbar (search,
notifications, tenant/role indicators, user menu), breadcrumbs, page header, permission-
aware nav, responsive drawer, RTL.

**Independent Test**: Load the shell as users with different permissions (nav filters);
resize to tablet/mobile (drawer, no horizontal scroll); switch to Arabic (RTL); render
each shared state.

### Tests for User Story 1 (MANDATORY)

- [X] T017 [P] [US1] [M:H] [Test] Sidebar renders grouped items per `can` map + hidden without permission in `resources/js/__tests__/shell-nav.test.tsx`
- [X] T018 [P] [US1] [M:H] [Test] `PermissionGate` hide + `NotificationDropdown`/`SearchCommand` empty-state render in `resources/js/__tests__/shell-topbar.test.tsx`
- [X] T019 [P] [US1] [M:H] [Test] Login page renders (fields, submit loader, error) + AR/RTL axe in `resources/js/__tests__/login-redesign.test.tsx`

### Implementation for User Story 1

- [X] T020 [US1] [M:S] Restyle `resources/js/layouts/DashboardLayout.tsx` to the TailAdmin shell (sidebar + topbar + breadcrumbs + content + route loader + toast host + error boundary)
- [X] T021 [P] [US1] [M:S] Build grouped collapsible `SidebarSection` and restyle `resources/js/components/layout/Sidebar.tsx` (Main/Event Operations/On-site/Access Control/Administration groups, icons, active highlight, permission visibility, mobile drawer)
- [X] T022 [US1] [M:S] Update `resources/js/lib/navigation.ts` + `lib/tenant-navigation.ts` to the grouped manifest with real permission keys per rbac-ui-map.md
- [X] T023 [P] [US1] [M:S] Restyle `resources/js/components/layout/Topbar.tsx` + build `UserMenu.tsx` (profile/account/support/sign out), tenant + role indicators, optional language/theme toggle
- [X] T024 [P] [US1] [M:S] Build `resources/js/components/layout/NotificationDropdown.tsx` and `SearchCommand.tsx` with graceful empty/placeholder states (GAP-A/GAP-B in missing-api-requirements.md)
- [X] T025 [P] [US1] [M:H] Restyle `resources/js/components/layout/{Breadcrumbs,PageHeader,PageContent}.tsx`
- [X] T026 [US1] [M:H] Restyle login page `resources/js/pages/Auth/Login.tsx` (auth card, logo, fields, submit loader, error, responsive)
- [X] T027 [US1] [M:H] Add US1 shell i18n strings (nav groups, user menu, notifications, search) to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: Shell is the MVP — demoable independently.

---

## Phase 4: User Story 2 - Overview and event lifecycle pages (Priority: P2)

**Goal**: Restyle overview + events/registration/ticketing/price-tiers and wire
create/edit/publish/cancel and field/ticket/tier actions to real endpoints.

**Independent Test**: Overview cards+tables render; events list uses shared table;
create event calls `POST /api/v1/tenant/events`; publish calls `.../publish`; add
field/ticket/tier calls its real endpoint.

### Tests for User Story 2 (MANDATORY)

- [X] T028 [P] [US2] [M:H] [Test] Overview cards + events table render (skeleton/empty/error) in `resources/js/__tests__/overview-events-redesign.test.tsx`
- [X] T029 [P] [US2] [M:S] [Test] Create-event calls `POST /api/v1/tenant/events`; publish calls `.../publish` (assert fetch, not just callback) in `resources/js/__tests__/events-actions.test.tsx`

### Implementation for User Story 2

- [X] T030 [US2] [M:H] Restyle overview `resources/js/pages/FoundationDashboard.tsx` (metric cards via StatCard + recent events/orders/scans/audit tables)
- [X] T031 [P] [US2] [M:H] Restyle events list `resources/js/pages/tenant/events/List.tsx` onto shared DataTable + ActionDropdown + StatusBadge
- [X] T032 [US2] [M:S] Restyle + WIRE create/edit `resources/js/pages/tenant/events/EventSetup.tsx` → `POST /api/v1/tenant/events` (create) / `PATCH .../{id}` (edit) with `apiFetch`, validation, redirect
- [X] T033 [US2] [M:S] Restyle event detail tabs `resources/js/pages/tenant/events/Detail.tsx` + WIRE publish/cancel `ConfirmModal` → `POST .../publish` / `.../cancel` (gated `event.publish`/`event.cancel`)
- [X] T034 [P] [US2] [M:S] Restyle + WIRE registration builder `resources/js/pages/tenant/registration/Builder.tsx` (field list, add/edit/reorder/require via Registration endpoints; load existing fields)
- [X] T035 [P] [US2] [M:H] Restyle + WIRE ticket types `resources/js/pages/tenant/events/Ticketing.tsx` → `POST/PATCH .../ticket-types`
- [X] T036 [P] [US2] [M:H] Restyle + WIRE price tiers `resources/js/pages/tenant/ticketing/PriceTiers.tsx` → `POST .../ticket-types/{ttid}/price-tiers`
- [X] T037 [US2] [M:H] Add US2 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1 + US2 functional.

---

## Phase 5: User Story 3 - Orders, attendees, and credentials (Priority: P3)

**Goal**: Restyle orders/attendees/credentials lists + details; wire credential
revoke (reason) / reissue to real endpoints.

**Independent Test**: Filter lists; open details; revoke calls `.../credentials/{id}/revoke`
with reason (failure does NOT flip status); reissue calls `.../reissue`.

### Tests for User Story 3 (MANDATORY)

- [X] T038 [P] [US3] [M:H] [Test] Orders/attendees/credentials tables + detail render in `resources/js/__tests__/oac-redesign.test.tsx`
- [X] T039 [P] [US3] [M:S] [Test] Credential revoke calls `.../revoke` with reason; failure keeps status; reissue calls `.../reissue` in `resources/js/__tests__/credential-actions-real.test.tsx`

### Implementation for User Story 3

- [X] T040 [P] [US3] [M:H] Restyle orders `resources/js/pages/tenant/events/Orders.tsx` + detail `resources/js/pages/tenant/orders/Detail.tsx` (cards + audit timeline)
- [X] T041 [P] [US3] [M:H] Restyle attendees `resources/js/pages/tenant/events/Attendees.tsx` + detail `resources/js/pages/tenant/attendees/Detail.tsx` (identity-status badge if backend returns it)
- [X] T042 [P] [US3] [M:H] Restyle credentials `resources/js/pages/tenant/events/Credentials.tsx` + detail `resources/js/pages/tenant/credentials/Detail.tsx`
- [X] T043 [US3] [M:S] WIRE `resources/js/components/credentials/CredentialDialog.tsx` + credential detail: revoke → `POST .../credentials/{cid}/revoke` (reason), reissue → `.../reissue`; reflect real result via `router.reload()` (no local-only status)
- [X] T044 [P] [US3] [M:S] WIRE attendee-detail actions (reissue/revoke/print/manual check-in) to their real endpoints in `resources/js/pages/tenant/attendees/Detail.tsx`
- [X] T045 [US3] [M:H] Add US3 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US3 functional.

---

## Phase 6: User Story 4 - Wallet, scanning, and check-in (Priority: P4)

**Goal**: Restyle wallet passes, scanner, check-in dashboard, scan events.

**Independent Test**: Scanner shows big accepted/rejected panels from `POST .../scans`;
check-in cards + latest events render; scan-events table filters; wallet table renders.

### Tests for User Story 4 (MANDATORY)

- [X] T046 [P] [US4] [M:H] [Test] Scanner result panel + check-in cards + scan-events table render in `resources/js/__tests__/scanning-redesign.test.tsx`
- [X] T047 [P] [US4] [M:H] [Test] Mobile-width scanner has no horizontal page scroll + AR/RTL axe in `resources/js/__tests__/scanner-responsive.test.tsx`

### Implementation for User Story 4

- [X] T048 [P] [US4] [M:H] Restyle scanner `resources/js/pages/tenant/checkin/Scanner.tsx` (large centered result card, button loader, duplicate-guard) — keep existing `POST .../scans` call
- [X] T049 [P] [US4] [M:H] Restyle check-in dashboard `resources/js/pages/tenant/checkin/Dashboard.tsx` (metric cards + latest scan events)
- [X] T050 [P] [US4] [M:H] Restyle scan events `resources/js/pages/tenant/checkin/ScanEvents.tsx` onto shared table + StatusBadge + reason column
- [X] T051 [P] [US4] [M:H] Restyle wallet passes `resources/js/pages/tenant/checkin/WalletPasses.tsx` + detail `resources/js/pages/tenant/wallet/Detail.tsx`
- [X] T052 [US4] [M:H] Add US4 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US4 functional.

---

## Phase 7: User Story 5 - Kiosk, badge, and manual desk (Priority: P5)

**Goal**: Restyle kiosk list/detail + kiosk mode, badge templates/print jobs, manual
desk; wire reprint (reason) and override.

**Independent Test**: Kiosk list/detail + fullscreen kiosk mode render; reprint calls
`.../badge-print-jobs/{id}/reprint` with reason; manual desk search calls `.../desk/lookups`.

### Tests for User Story 5 (MANDATORY)

- [X] T053 [P] [US5] [M:H] [Test] Kiosk table + badge print-jobs table render in `resources/js/__tests__/kiosk-badge-redesign.test.tsx`
- [X] T054 [P] [US5] [M:H] [Test] Manual-desk reprint uses `ReasonModal` → calls reprint endpoint in `resources/js/__tests__/manual-desk-redesign.test.tsx`

### Implementation for User Story 5

- [X] T055 [P] [US5] [M:H] Restyle kiosk `resources/js/pages/tenant/kiosk/{Index,Detail}.tsx` onto shared table/cards
- [X] T056 [P] [US5] [M:H] Restyle kiosk mode `resources/js/pages/kiosk/Mode.tsx` (fullscreen-friendly, same visual language)
- [X] T057 [P] [US5] [M:H] Restyle badge templates `resources/js/pages/tenant/badge-templates/Designer.tsx` + print jobs `resources/js/pages/tenant/badges/PrintJobs.tsx`; keep reprint wiring via `ReasonModal`
- [X] T058 [US5] [M:H] Restyle manual desk `resources/js/pages/tenant/manual-desk/{Desk,WalkUp}.tsx` (workstation cards); keep existing lookup/scan/print/reprint/walk-up wiring
- [X] T059 [US5] [M:H] Add US5 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US5 functional.

---

## Phase 8: User Story 6 - ACS access-control pages (Priority: P6)

**Goal**: Restyle ACS overview/zones/lanes/rules/access-logs/gate-health; keep
zone/lane/rule create wired to real endpoints.

**Independent Test**: ACS overview cards render; create zone/lane/rule calls
`POST .../acs/{zones|lanes|rules}`; access logs + gate health render.

### Tests for User Story 6 (MANDATORY)

- [X] T060 [P] [US6] [M:H] [Test] ACS overview cards + zones/lanes/rules tables render in `resources/js/__tests__/acs-redesign.test.tsx`
- [X] T061 [P] [US6] [M:S] [Test] Create zone/lane/rule calls `POST .../acs/...` (assert fetch) in `resources/js/__tests__/acs-actions-real.test.tsx`

### Implementation for User Story 6

- [X] T062 [P] [US6] [M:H] Restyle ACS overview `resources/js/pages/tenant/acs/Index.tsx` (metric cards)
- [X] T063 [P] [US6] [M:H] Restyle ACS zones/lanes/rules `resources/js/pages/tenant/acs/{Zones,Lanes,Rules}.tsx` onto shared tables/forms; keep create wiring
- [X] T064 [P] [US6] [M:H] Restyle access logs `resources/js/pages/tenant/acs/AccessLogs.tsx` + gate health `resources/js/pages/tenant/acs/GateHealth.tsx`
- [X] T065 [US6] [M:S] Restyle emergency controls (`resources/js/components/acs/EmergencyControls.tsx`) with `ConfirmModal`+`ReasonModal`, gated `acs.emergency.manage`; keep endpoint wiring
- [X] T066 [US6] [M:H] Add US6 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: US1–US6 functional.

---

## Phase 9: User Story 7 - Administration, reports, and settings (Priority: P7)

**Goal**: Restyle users/roles/tenant-settings/audit/reports/profile/system-settings.

**Independent Test**: Each admin page uses shared table/card/form + permission-aware
controls; a missing-API surface shows a documented placeholder.

### Tests for User Story 7 (MANDATORY)

- [X] T067 [P] [US7] [M:H] [Test] Users/roles/audit tables + reports cards render (with placeholder) in `resources/js/__tests__/admin-redesign.test.tsx`

### Implementation for User Story 7

- [X] T068 [P] [US7] [M:H] Restyle users `resources/js/pages/admin/Users.tsx` + roles `resources/js/pages/admin/Roles.tsx` onto shared table/forms; keep action wiring
- [X] T069 [P] [US7] [M:H] Restyle tenant settings `resources/js/pages/admin/TenantSettings.tsx` + audit logs `resources/js/pages/admin/AuditLogs.tsx`
- [X] T070 [P] [US7] [M:H] Restyle reports `resources/js/pages/tenant/reports/EventReport.tsx` (metric cards/tables + documented placeholders per GAP-D)
- [X] T071 [P] [US7] [M:H] Restyle profile `resources/js/pages/Profile.tsx` and System Settings (`resources/js/pages/DashboardSection.tsx` or new) with placeholder per GAP-C
- [X] T072 [US7] [M:H] Add US7 i18n strings to `resources/js/locales/{en,ar}.ts`

**Checkpoint**: All user stories functional.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Demo removal, docs, accessibility/RTL, responsive, gaps, and validation.

- [X] T073 [P] [M:H] Remove all TailAdmin/demo content, sample pages, and demo branding across `resources/js/**` (per T004 audit); confirm Zonetec branding everywhere
- [X] T074 [P] [M:H] Update `docs/standards/dashboard-design-system.md` with the new tokens/components and the permission→page matrix
- [X] T075 [P] [M:S] [Test] Accessibility + Arabic/RTL sweep (axe) across all restyled pages in `resources/js/__tests__/redesign-accessibility.test.tsx`
- [X] T076 [P] [M:H] [Test] Responsive sweep (sidebar drawer, table scroll/card conversion, no horizontal scroll) in `resources/js/__tests__/redesign-responsive.test.tsx`
- [X] T077 [M:H] Finalize `specs/008-tailadmin-ui-redesign/missing-api-requirements.md` and mirror the summary into `spec.md` Missing Backend API Requirements
- [X] T078 [M:H] Run quality gates: `npm run lint`, `npm run typecheck`, `npm run test`, `vite build`, `composer quality` (docs check + phase-boundary check) — fix failures
- [X] T079 [M:H] Run `quickstart.md` five scenarios end-to-end and record results

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies.
- **Foundational (Phase 2)**: depends on Setup; BLOCKS all user stories.
- **User Stories (Phases 3–9)**: depend on Foundational; US1 (shell) is the practical
  base every page renders inside, so complete US1 before demoing US2–US7 (they remain
  independently testable via their own routes). Priority order P1→P7.
- **Polish (Phase 10)**: depends on all targeted user stories.

### Within Each User Story

- Tests first (fail) → restyle presentational parts → wire actions to real endpoints →
  i18n. Keep props/routes/permissions unchanged.

### Parallel Opportunities

- All `[P]` Foundational component tasks (T005–T016) run in parallel.
- After US1, most page restyles across US2–US7 are `[P]` (different files).
- Cheaper-model batch: dispatch all `[M:H]` restyle/i18n/test tasks to a cheaper model
  where `[P]`; reserve `[M:S]` for the shell/nav, notification/search, and sensitive-
  action wiring (T003, T020–T024, T032–T034, T043–T044, T061, T065, T075).

---

## Parallel Example: User Story 3

```text
# Restyle pages (cheaper model, parallel):
Task T040 [M:H] orders list + detail
Task T041 [M:H] attendees list + detail
Task T042 [M:H] credentials list + detail
# Sensitive action wiring (capable model):
Task T043 [M:S] credential revoke/reissue → real endpoints
Task T044 [M:S] attendee-detail actions → real endpoints
```

---

## Model-Tier Summary (per "cheap llm model")

- **[M:H] cheaper-model-eligible** (token/CSS restyle, presentational pages, i18n,
  status/skeleton/state components, tests from explicit specs, simple fetch wiring):
  T001–T002, T004–T019, T025–T031, T035–T042, T045–T060, T062–T064, T066–T074, T076–T079.
- **[M:S] capable-model-recommended** (shell/nav architecture, notification/search gap,
  sensitive-action wiring, registration CRUD, `apiFetch` helper, a11y sweep):
  T003, T020–T024, T032–T034, T043–T044, T061, T065, T075.
- Every task, regardless of tier, must pass lint/typecheck/test/build + `composer quality`.

---

## Implementation Strategy

### MVP First (User Story 1)

Phase 1 Setup → Phase 2 Foundational (shared components) → Phase 3 US1 (shell) →
validate → demo. The shell + component library is the smallest useful increment.

### Incremental Delivery

Add US2…US7 one at a time; each restyles and wires its own routes and is independently
testable. Because actions must reflect real backend results, each story's flow tests
assert the real endpoint is called — this closes the 006 cosmetic-only gaps.

## Notes

- [P] = different files, no incomplete-task dependency.
- Restyle in place: keep props/routes/permissions; do not fork components; do not change
  backend business logic; do not add Phase 5.
- Use real permission keys (rbac-ui-map.md), never the brief's illustrative names.
- No cosmetic success: an action's displayed outcome must reflect a real backend
  response (FR-011/CR-003).
- Every restyled surface stays Arabic/English + RTL, responsive, and axe-clean.
- Remove all TailAdmin demo content before done; update the design-system doc.
