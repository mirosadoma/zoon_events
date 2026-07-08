# Deep Review Findings — 006 Frontend Control Dashboard

**Reviewer**: Opus 4.8 (deep review) · **Date**: 2026-07-08
**Scope reviewed**: everything marked `[X]` in [tasks.md](tasks.md) (T001–T109)
**Verdict**: ⚠️ **Not complete.** Structure, routing, read-only pages, and shared
components are in good shape, but **most state-changing (write) actions are not
wired to the backend**. Several sensitive actions *report success in the UI while
doing nothing on the server*, which violates the spec's auditability and
credential-security requirements. The existing tests pass against these stubs and
therefore give a false "done" signal.

This file is written so a **cheaper LLM can execute the fixes**. Each task lists the
exact file, the exact backend endpoint (all of which already exist), a copyable
`fetch` pattern, and acceptance criteria. Work top-down: **P0 → P1 → P2 → P3**.

> **Environment note for the implementer**: `node_modules/` and `vendor/` are not
> installed in this checkout, so `npm run test`, `npm run typecheck`, `npm run lint`,
> and `composer test` could not be run during this review. After each task run
> `npm run lint && npm run typecheck && npm run test` (and the relevant
> `composer test` feature test). Do **not** trust the `[X]` marks in tasks.md.

---

## The core problem

`routes/web.php` wires **only `GET` pages** (plus login/logout). Every write goes to
the **already-built** REST API under `/api/v1/...` (see `app/Modules/*/Routes/api.php`).
The Phase-3/4 pages (manual desk, scanner, kiosk, badges, ACS, admin users/roles)
correctly call those endpoints with `fetch`. But the **Phase-1 core pages
(events, ticketing, price tiers, registration, credentials, attendees) were left as
presentational shells** — their buttons open modals whose `onConfirm` either does
nothing or only mutates local React state.

**Canonical working pattern to copy** (from
[resources/js/pages/tenant/manual-desk/Desk.tsx](../../resources/js/pages/tenant/manual-desk/Desk.tsx#L60-L113)):

```ts
const apiHeaders = {
  'Content-Type': 'application/json',
  Accept: 'application/json',
  'X-Tenant-ID': tenantId,           // pass tenantId as a prop from the ViewModel
}

const response = await fetch(`/api/v1/tenant/events/${event.id}/.../action`, {
  method: 'POST',
  credentials: 'include',
  headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() }, // writes need this
  body: JSON.stringify({ /* payload */ }),
})
const body = await response.json()
if (!response.ok) { /* show ErrorState / toast, keep modal open */ }
else { /* success toast + router.reload() to refresh props */ }
```

Every write endpoint below is guarded by `permission:*` + `idempotency` middleware,
so the `Idempotency-Key` header is **mandatory** and the backend already writes the
audit record. The UI must surface real success/failure and must not report success
on a failed/absent call (spec CR-003, FR-040, FR-041).

---

## P0 — Sensitive actions that fake success (correctness + security)

These are the most dangerous: the operator sees "done" but the backend was never
called. They break **CR-003 (auditability)**, **CR-004 (credential security)**,
**FR-016**, **FR-025**, and **FR-041**.

### F-1 · Credential revoke & reissue do nothing (only local state)
- **Where**: [resources/js/pages/tenant/credentials/Detail.tsx:69-73](../../resources/js/pages/tenant/credentials/Detail.tsx#L69-L73)
  passes `onRevoked={() => setLocalStatus('revoked')}` /
  `onReissued={() => setLocalStatus('active')}`. No network call happens.
  [components/credentials/CredentialDialog.tsx](../../resources/js/components/credentials/CredentialDialog.tsx)
  just invokes those callbacks.
- **Endpoints (exist already)**:
  - `POST /api/v1/tenant/events/{event_id}/credentials/{credential_id}/revoke` (body `{ reason }`)
  - `POST /api/v1/tenant/events/{event_id}/credentials/{credential_id}/reissue`
- **Fix**: In `Detail.tsx`, make `onRevoked`/`onReissued` call the endpoints (with
  `Idempotency-Key`, `X-Tenant-ID`), await the response, on `!ok` show an error and
  keep the current status, on `ok` show a success toast and `router.reload()` (do
  not hand-set status). Pass `tenantId` and `credential.id` down. Same wiring must
  apply anywhere `CredentialDialog` is used.
- **Acceptance**: Revoke with a reason actually flips the credential to `revoked` in
  the DB and records the reason; a failed call leaves status unchanged and shows an
  error; reissue creates a new credential linked to the prior one (FR-025).

### F-2 · Event publish & cancel do nothing
- **Where**: [resources/js/pages/tenant/events/Detail.tsx:82,90](../../resources/js/pages/tenant/events/Detail.tsx#L77-L92)
  — both `ConfirmModal` `onConfirm` handlers only `setPublishOpen(false)` /
  `setCancelOpen(false)`.
- **Endpoints**: `POST /api/v1/tenant/events/{event_id}/publish`,
  `POST /api/v1/tenant/events/{event_id}/cancel`.
- **Fix**: call the endpoints on confirm; success → toast + `router.reload()`;
  failure → error toast, modal stays. Keep the `event.publish`/`event.cancel`
  `PermissionGate`s already present.
- **Acceptance**: Publishing a draft actually changes status to `published`
  (FR-016); cancel changes it to cancelled; both survive a page reload.

---

## P1 — Core write flows not implemented at all

These pages render but have **no submit handler**, so the primary US2/US3 journeys
cannot be completed. This is the bulk of the missing work.

### F-3 · Create / edit event has no submit
- **Where**: [resources/js/pages/tenant/events/EventSetup.tsx](../../resources/js/pages/tenant/events/EventSetup.tsx)
  has a "Save changes" button but **no** `onClick`/`fetch`/form submit anywhere.
- **Endpoints**: `POST /api/v1/tenant/events` (create),
  `PATCH /api/v1/tenant/events/{event_id}` (edit).
- **Fix**: wire the form to POST (create mode, `event.id === null`) or PATCH (edit).
  On success redirect to `/tenant/events/{id}`; surface field-level validation
  errors from the response envelope (FR-040).
- **Requirement**: FR-015, FR-016 (edit). Acceptance = the US2 independent test:
  create an event and see it as a draft in the list.

### F-4 · Registration form builder is a read-only stub
- **Where**: controller [EventDashboardController::registrationForm()](../../app/Modules/AdminConsole/Http/Controllers/Tenant/Events/EventDashboardController.php#L78-L86)
  hardcodes `'fields' => []`; the page
  [registration/Builder.tsx:36](../../resources/js/pages/tenant/registration/Builder.tsx#L33-L37)
  literally says *"Add registration fields from this page once save actions are
  wired."* No add/edit/reorder/delete/require exists.
- **Backend**: Registration module already exposes field actions (see
  `app/Modules/Registration/Routes/api.php`). The controller must load the event's
  existing fields into props and the page must implement CRUD + reorder + required
  toggle across all field types (text/email/phone/number/date/dropdown/multi-select/
  checkbox/hidden/consent).
- **Requirement**: FR-017. **Note**: `api-integration-map.md` wrongly lists this row
  as "EXISTS" — correct that row too.

### F-5 · Registration preview always shows empty fields
- **Where**: [EventDashboardController::registrationPreview()](../../app/Modules/AdminConsole/Http/Controllers/Tenant/Events/EventDashboardController.php#L88-L106)
  passes `'form' => ['fields' => []]`.
- **Fix**: load the real fields + branding so the preview reflects the builder
  output (depends on F-4). **Requirement**: FR-018.

### F-6 · Ticket types cannot be created/edited/disabled
- **Where**: [resources/js/pages/tenant/events/Ticketing.tsx](../../resources/js/pages/tenant/events/Ticketing.tsx)
  — no submit handler.
- **Endpoints**: `POST /api/v1/tenant/events/{event_id}/ticket-types`,
  `PATCH /api/v1/tenant/events/{event_id}/ticket-types/{ticket_type_id}`.
- **Fix**: add create/edit/disable forms wired to those endpoints. **Requirement**: FR-019.

### F-7 · Price tiers cannot be created/edited/disabled
- **Where**: [resources/js/pages/tenant/ticketing/PriceTiers.tsx](../../resources/js/pages/tenant/ticketing/PriceTiers.tsx)
  — display only.
- **Endpoint**: `POST /api/v1/tenant/events/{event_id}/ticket-types/{ticket_type_id}/price-tiers`.
- **Fix**: add create/edit/disable wired to that endpoint. **Requirement**: FR-020.

### F-8 · Attendee detail has no actions
- **Where**: [resources/js/pages/tenant/attendees/Detail.tsx](../../resources/js/pages/tenant/attendees/Detail.tsx)
  — no reissue / revoke / print badge / manual check-in controls at all.
- **Fix**: add the four permission-gated actions (reuse `CredentialDialog` fixed in
  F-1 for revoke/reissue; badge print → `POST .../badge-print-jobs`; manual check-in
  → the desk/scan endpoint). **Requirement**: FR-023.

---

## P2 — Security & correctness

### F-9 · Kiosk-mode route is unauthenticated / no device session
- **Where**: [routes/web.php:30](../../routes/web.php#L30) registers
  `/kiosk/{device_code}` **outside** the `auth` group with **no** `kiosk.session`
  middleware; [KioskModeController::show()](../../app/Modules/AdminConsole/Http/Controllers/Kiosk/KioskModeController.php#L16-L28)
  looks the kiosk up by `device_code` with no device-token check.
- **Impact**: anyone who knows/guesses a `device_code` can open the kiosk screen
  (event branding + attendee lookup) with no authentication.
- **Spec**: [frontend-routes.md:64](frontend-routes.md) + T073 require
  `kiosk.session` / `kiosk.session.clear` middleware.
- **Fix**: apply the `kiosk.session` device-session middleware to the route (and
  validate the device token in the controller). **Requirement**: FR-031, CR-004.

### F-10 · Overview `gates_active` is hardcoded to 0
- **Where**: [DashboardOverviewBuilder.php:71](../../app/Modules/AdminConsole/Application/DashboardOverviewBuilder.php#L71)
  returns `'gates_active' => 0` instead of counting active ACS gates/lanes.
- **Fix**: compute it from the AccessControl module's published query (do **not** add
  a new `where` on another module's model — see F-12). **Requirement**: FR-008.

### F-11 · `/platform/{section}` always returns empty items
- **Where**: [DashboardController::section()](../../app/Modules/AdminConsole/Http/Controllers/DashboardController.php#L66-L70)
  returns `'items' => []`. Pre-existing, but confirm this is an intended placeholder
  and give it a real empty state, or wire the section data. Low priority.

---

## P3 — Plan/architecture conformance & test integrity

### F-12 · AdminConsole reads other modules' Eloquent models directly
- **Where**: e.g.
  [EventOperationsController.php](../../app/Modules/AdminConsole/Http/Controllers/Tenant/Events/EventOperationsController.php)
  and [DashboardOverviewBuilder.php](../../app/Modules/AdminConsole/Application/DashboardOverviewBuilder.php)
  query `Order`, `Attendee`, `Credential`, `TicketType`, `PriceTier`, `AcsZone`,
  `ScanEvent`, `Kiosk`, `Notification` models directly.
- **Conflict**: plan.md ("presentation never queries another module's persistence
  directly", Constitution VI) and `api-integration-map.md` require data to flow
  through each module's **published application queries**. `tests/Architecture/ModuleBoundaryTest.php`
  allowlists `app/Modules/AdminConsole`, so this is **not caught by CI**.
- **Fix (larger, [M:S])**: route reads through the owning modules' query contracts.
  If accepted as a deliberate deviation, record it explicitly in plan.md /
  api-integration-map.md instead of leaving the docs claiming compliance.

### F-13 · "Browser/E2E" tests are not real E2E
- **Where**: `resources/js/__tests__/phase6-*-browser.test.tsx` are Vitest **jsdom**
  component renders using `axe-core` (not `@axe-core/playwright`); there is **no
  Playwright config/harness** despite T004/CR-009 requiring the 12 E2E journeys via
  `@axe-core/playwright`. `test:browser` just runs Vitest.
- **Example gap**: [phase6-overview-browser.test.tsx:7-9](../../resources/js/__tests__/phase6-overview-browser.test.tsx#L7-L9)
  **mocks away `DashboardLayout`**, so T025's promised "nav matches permissions" and
  "RTL" assertions never actually run.
- **Fix**: either stand up a real Playwright + `@axe-core/playwright` harness for the
  12 journeys, or update tasks.md/test-plan.md/spec CR-009 to state the E2E layer is
  jsdom-simulated (and stop mocking the shell in journeys that assert on nav/RTL).

### F-14 · Integration tests pass against the stubs (false confidence)
- **Where**: [credential-actions.test.tsx](../../resources/js/__tests__/credential-actions.test.tsx)
  only asserts the `onRevoked`/`onReissued` **callback** fires — it never checks a
  backend call, so it stays green even though F-1 does nothing.
  [events-manage.test.tsx](../../resources/js/__tests__/events-manage.test.tsx) only
  checks a button renders, not that create works (F-3).
- **Fix**: after F-1…F-8, strengthen these tests to assert the correct
  `fetch`/endpoint is called with the right method, body, and `Idempotency-Key`, and
  that failures do **not** show success (mirror the pattern already used in
  [acs-config.test.tsx](../../resources/js/__tests__/acs-config.test.tsx)).

### F-15 · Backend feature suite never confirmed green
- **Where**: tasks.md T108 itself notes `composer test` is "blocked by flaky
  `zonetec_testing` migration state (deadlocks during concurrent db:wipe /
  migrate:fresh)". So the AdminConsole RBAC/tenant feature tests
  (`tests/Feature/AdminConsole/*`) were written but **not verified to pass**.
- **Fix**: get the migration/test DB stable and run `composer test` to confirm the
  Feature/AdminConsole suite is actually green; fix any real failures surfaced.

---

## Remediation task list (for the cheaper model)

Do these in order; run `npm run lint && npm run typecheck && npm run test` after each.

- [ ] **R1** [M:S] F-1 — wire credential revoke/reissue to the API (real audit, real failure handling)
- [ ] **R2** [M:H] F-2 — wire event publish/cancel to the API
- [ ] **R3** [M:S] F-3 — wire create/edit event form (POST/PATCH) with validation errors
- [ ] **R4** [M:S] F-4 — implement registration form builder CRUD/reorder/require + load existing fields
- [ ] **R5** [M:H] F-5 — load real fields/branding into registration preview (after R4)
- [ ] **R6** [M:H] F-6 — wire ticket-type create/edit/disable
- [ ] **R7** [M:H] F-7 — wire price-tier create/edit/disable
- [ ] **R8** [M:H] F-8 — add attendee-detail actions (reissue/revoke/print/check-in)
- [ ] **R9** [M:S] F-9 — apply `kiosk.session` middleware + device-token check to `/kiosk/{device_code}`
- [ ] **R10** [M:H] F-10 — compute real `gates_active` in the overview
- [ ] **R11** [M:S] F-12 — route AdminConsole reads through module queries (or document the deviation)
- [ ] **R12** [M:S] F-13 — real Playwright E2E harness (or correct the docs to match reality)
- [ ] **R13** [M:H] F-14 — harden integration tests to assert real endpoint calls + failure paths
- [ ] **R14** [M:S] F-15 — stabilize test DB and confirm `composer test` (Feature/AdminConsole) is green
- [ ] **R15** [M:H] Fix `api-integration-map.md` rows that claim EXISTS/Confirmed for flows that were stubbed (registration builder, credential/event/ticket/price actions), and re-mirror into spec.md.

### Verified as actually working (no action needed)
Manual desk (lookup/scan/print/reprint), walk-up registration, browser scanner
(`/scans`), ACS zones/lanes/rules create, badge print jobs, admin users/roles,
gate-health polling, the shared shell/nav/RBAC `can`-map, status badges, and the
read-only list/detail pages all call their real endpoints or render correctly.
