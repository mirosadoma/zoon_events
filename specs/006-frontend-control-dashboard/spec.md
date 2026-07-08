# Feature Specification: Frontend Control Dashboard for Completed Core Phases

**Feature Branch**: `006-frontend-control-dashboard`

**Created**: 2026-07-07

**Status**: Draft

**Input**: User description: "use front_plan_step_1.md — Build a complete web operator/admin dashboard that exposes, tests, manages, and visually validates all completed backend functionality (Foundation + Phases 1–4) before starting any new product phase."

**Product Phase**: Frontend Consolidation Phase (exposes Foundation + Phase 1 Registration-Ticketing-Credentials + Phase 2 Wallet-Scanning + Phase 3 Kiosk-Badge-Manual-Desk + Phase 4 ACS)

**Deployment Modes**: both (SaaS and on-premise)

## User Scenarios & Testing *(mandatory)*

This feature delivers an operator/admin web dashboard. It does not add new backend
business capabilities; it makes the already-completed backend (Foundation through
Phase 4) visible and operable from the browser. Each user story below is an
independently shippable slice: implementing only one still yields a usable product
increment.

### User Story 1 - Sign in and operate a permission-aware dashboard (Priority: P1)

An operator opens the web app, signs in with email and password, and lands on a
dashboard shell (sidebar, top bar, tenant indicator, current-role indicator,
breadcrumbs, page title, content area). The navigation and every action shown are
filtered to the permissions the signed-in user actually holds. The main overview
presents at-a-glance operational metrics for the current tenant.

**Why this priority**: Nothing else is reachable without authenticated,
permission-aware navigation. This slice is the MVP: it proves auth, tenant/role
context, RBAC-driven visibility, and the shared layout that every later screen
depends on.

**Independent Test**: Sign in as users with differing permission sets and confirm
each sees only the menu items and actions their permissions allow, reaches the
overview, and that an unauthenticated visitor is redirected to sign-in.

**Acceptance Scenarios**:

1. **Given** a valid account, **When** the user submits correct credentials, **Then** a submit loader shows, the session is established, and the user is redirected to the overview.
2. **Given** invalid credentials, **When** the user submits, **Then** a clear error message appears and no session is created.
3. **Given** a signed-in user lacking `events.create`, **When** they view navigation, **Then** the "create event" action is not shown.
4. **Given** a signed-in user, **When** the overview loads, **Then** tenant-scoped summary metrics (events, attendees, orders, credentials, today's check-ins, active kiosks/gates, failed scans, recent audit events) render with a skeleton loader while fetching.
5. **Given** an unauthenticated visitor, **When** they open any protected route, **Then** they are redirected to sign-in.

---

### User Story 2 - Manage events, registration forms, ticketing, and pricing (Priority: P2)

An organizer/admin lists the current tenant's events, creates a new event, opens
an event's tabbed detail view, and configures its registration form fields, ticket
types, and price tiers.

**Why this priority**: Events are the root object every downstream operation hangs
off. Without event setup there are no orders, attendees, credentials, scans, or
access rules to observe.

**Independent Test**: As an organizer, create an event, add a registration field,
create a ticket type and a price tier, and confirm each appears in its list with
correct status; verify publish is gated behind `events.publish`.

**Acceptance Scenarios**:

1. **Given** the events list, **When** the organizer searches/filters by name, status, tier, or date, **Then** the table updates to matching tenant events only.
2. **Given** `events.create`, **When** the organizer completes the create-event form and saves as draft, **Then** the event is created and shown as a draft.
3. **Given** an event, **When** the organizer opens its detail view, **Then** tabbed sections (Overview, Registration Form, Ticket Types, Price Tiers, Orders, Attendees, Credentials, Wallet Passes, Scanning, Badges, ACS, Audit Logs) are available.
4. **Given** the registration form builder, **When** the organizer adds, edits, reorders, or removes a field and marks it required, **Then** the change is saved and reflected in the registration preview.
5. **Given** `events.publish`, **When** the organizer confirms the publish modal, **Then** the event status changes to published; without the permission the publish action is hidden.

---

### User Story 3 - View orders, attendees, and manage credentials (Priority: P3)

An organizer/admin reviews orders and their details, browses and filters
attendees, opens an attendee's full profile, and (when permitted) revokes or
reissues a credential with a required reason.

**Why this priority**: This is the core value of the platform's registration/
credential lifecycle becoming visible and actionable — the primary reason to expose
Phase 1 in the UI.

**Independent Test**: Open an event's orders and attendees lists, filter them, open
detail pages, and perform a credential revoke (with reason) and a reissue, each
gated by the matching permission and confirmed via modal.

**Acceptance Scenarios**:

1. **Given** the orders list, **When** filtering by payment status and searching by buyer, **Then** matching orders render with number, buyer, total, currency, payment and order status.
2. **Given** an order, **When** opening its details, **Then** buyer, payment, items, linked attendees, credential status, and audit history are shown.
3. **Given** an attendee's detail page and `credentials.revoke`, **When** the user confirms revoke and provides a required reason, **Then** the credential status becomes revoked and the reason is recorded.
4. **Given** `credentials.reissue`, **When** the user reissues a credential, **Then** a new active credential is shown linked to the prior one.
5. **Given** a user lacking `credentials.revoke`, **When** viewing a credential, **Then** the revoke action is not shown.

---

### User Story 4 - Wallet passes, QR scanning, and check-in (Priority: P4)

Staff view wallet passes and their status, scan QR credentials from the browser
(camera or manual code entry), see a large accept/reject result, and monitor a
check-in dashboard and scan-event log.

**Why this priority**: On-site entry validation is the operational heart of event
day; exposing Phase 2 lets staff verify the credential core works end-to-end.

**Independent Test**: On the scanner page, submit a valid code and see an accepted
result with attendee context, submit a revoked/invalid code and see a rejection
with reason, and confirm the check-in dashboard counts update.

**Acceptance Scenarios**:

1. **Given** the scanner page and `scan.perform`, **When** a valid code is submitted, **Then** a large accepted result shows attendee name, ticket type, status, and entry-allowed message.
2. **Given** a revoked or invalid code, **When** submitted, **Then** a large rejected result shows the reason and a suggested next action.
3. **Given** rapid repeated submissions, **When** a scan is in flight, **Then** duplicate submissions are prevented and the button shows a loader.
4. **Given** the check-in dashboard, **When** it loads, **Then** registered, checked-in, not-checked-in, accepted/rejected/duplicate/revoked counts and latest scan events render.
5. **Given** the scan-events page, **When** filtering by result, scanner type, or attendee, **Then** matching scan records render with detail access.

---

### User Story 5 - Kiosk, badge printing, and manual desk operations (Priority: P5)

Operators register and monitor kiosks, run a self-service kiosk mode screen,
manage badge templates and print jobs, and use a manual desk to search attendees
and handle exceptions (check-in, print, reprint, walk-up registration, override)
with required reasons where sensitive.

**Why this priority**: Exposes Phase 3 on-site operations; important but dependent
on the event/attendee/credential foundations above.

**Independent Test**: Register a kiosk and confirm its status/last-seen render; on
the manual desk, search an attendee and perform a permitted action requiring a
reason; confirm a badge print job appears with status.

**Acceptance Scenarios**:

1. **Given** `kiosk.manage`, **When** registering a kiosk, **Then** it appears in the list with device name, location, status, last seen, printer/scanner status, and app version.
2. **Given** kiosk mode for a device code, **When** an attendee is looked up and confirmed, **Then** a print action is offered and a clear success/reset screen follows.
3. **Given** `badge.reprint`, **When** a reprint is requested, **Then** a reason is required before the job is submitted.
4. **Given** the manual desk, **When** searching by name/email/phone, **Then** matching attendees render with status and permitted actions (check-in, print, override, walk-up).
5. **Given** a manual override, **When** confirmed, **Then** a reason is captured and the action is recorded via the backend audit trail.

---

### User Story 6 - ACS access control management and monitoring (Priority: P6)

An ACS operator views an access-control overview, manages zones, lanes, and
authorization rules, reviews access logs, and monitors gate health; emergency
egress controls appear only when the backend supports them and only for
authorized users.

**Why this priority**: Exposes Phase 4; valuable for access-controlled venues but
the most specialized and dependent slice.

**Independent Test**: As an `acs.manage` user, create a zone, create a lane
assigned to that zone, create an authorization rule, and confirm access logs and
gate-health pages render tenant-scoped data.

**Acceptance Scenarios**:

1. **Given** `acs.manage`, **When** creating a zone and a lane assigned to it, **Then** both appear in their lists with status.
2. **Given** the rules page, **When** creating an authorization rule, **Then** it appears with ticket/attendee type, zone, lane, direction, and validity window.
3. **Given** the access-logs page, **When** filtering by result, zone, lane, or direction, **Then** matching entry/exit records render with rejection reason where applicable.
4. **Given** the gate-health page, **When** it loads, **Then** each gate/lane shows status, last heartbeat, last event, and any error/emergency state.
5. **Given** emergency controls and an authorized user, **When** an emergency action is triggered, **Then** a confirmation modal is required before proceeding; users without the permission never see the control.

---

### User Story 7 - Foundation administration, reports, and audit visibility (Priority: P7)

A platform admin views and manages users, roles/permissions, and tenant settings,
and reviews audit logs; operators view per-event operational reports. All are
gated by the relevant permissions and only shown when the backing API exists.

**Why this priority**: Rounds out visibility of Foundation and monitoring; useful
for administration and validation but not required for the primary operational
flows above.

**Independent Test**: As an admin with `users.manage`, list and filter users and
toggle activation; view audit logs filtered by actor/action/date; open an event
report and confirm summary metrics render.

**Acceptance Scenarios**:

1. **Given** `users.manage`, **When** viewing the users page, **Then** users can be listed, searched, filtered by status/role, and activated/deactivated.
2. **Given** `roles.manage`, **When** editing a role, **Then** permissions can be assigned; protected system roles are not editable unless explicitly allowed.
3. **Given** `audit.view`, **When** filtering audit logs by actor, action, entity type, and date, **Then** matching records render with before/after detail.
4. **Given** `reports.view`, **When** opening an event report, **Then** registration, paid-order, credential, wallet, check-in, badge, and ACS summary figures render.
5. **Given** a Foundation feature whose backend API is absent, **When** the page loads, **Then** a documented placeholder/empty state is shown instead of an error.

---

### Edge Cases

- **Missing backend API**: When an endpoint required to surface a completed-phase capability does not exist, the UI MUST show a documented placeholder/empty state (never a raw error), and the gap MUST be recorded under "Missing Backend API Requirements".
- **Cross-tenant isolation**: Every list, detail, metric, filter, cached response, and export the dashboard shows MUST reflect only the signed-in user's tenant; the UI MUST never request or render another tenant's records, and a tenant-mismatched response MUST be treated as an error, not displayed.
- **Missing permission**: When an actor lacks a required permission, the corresponding menu item and action are hidden; if a forbidden response is nonetheless returned, the UI shows a clear "you do not have permission" message rather than partial data.
- **Audit-backed actions**: Sensitive actions (publish/cancel event, revoke/reissue credential, print/reprint badge, manual override, disable ACS rule, emergency action) rely on the backend to write audit records; if the backend action fails, the UI surfaces the failure and does not present the action as succeeded.
- **Credential validity**: Expired, revoked, replayed, or unknown-key credentials scanned at the scanner/kiosk MUST produce a clear rejection with reason; the UI never grants entry on a rejected result.
- **Localization/RTL**: Every screen renders correctly in Arabic (RTL) and English (LTR), including dates, numbers, currencies, validation messages, and layout direction.
- **Degraded/offline dependency**: When an external adapter, network, or on-premise dependency is unavailable, affected pages show a clear error/empty state and remain navigable; the dashboard does not crash a whole route.
- **Empty data**: Every list page shows a purpose-specific empty state when no records exist (no events, no attendees, no scans, no kiosks, no ACS zones, etc.).
- **Duplicate submission**: Forms and scans disable their submit control while a request is in flight to prevent duplicate writes.

## Requirements *(mandatory)*

### Functional Requirements

**Authentication & session**

- **FR-001**: The dashboard MUST let a user sign in with email and password using the existing Foundation authentication, showing a submit loader and a clear error on failure.
- **FR-002**: The dashboard MUST redirect unauthenticated users away from protected routes to the sign-in screen and redirect to the overview after a successful sign-in.
- **FR-003**: The dashboard MUST display the signed-in user's identity, current role, and current tenant persistently in the shell.

**Layout, navigation & RBAC visibility**

- **FR-004**: The dashboard MUST provide a consistent shell with sidebar navigation, top bar, user-profile menu, tenant indicator, role indicator, breadcrumbs, page title, content area, global route loader, toast notifications, confirmation modals, and route-level error boundaries.
- **FR-005**: Navigation items MUST appear only when the signed-in user holds the permission that governs the destination (e.g., ACS pages require `acs.manage`, audit logs require `audit.view`).
- **FR-006**: Every action control (buttons/links for create, edit, publish, revoke, reissue, print, override, etc.) MUST be shown only when the user holds the corresponding permission; permission checks in the UI are for experience only and MUST NOT be treated as a security boundary.
- **FR-007**: All dashboard routes MUST be protected such that only authenticated, authorized users can reach them.

**Overview & metrics**

- **FR-008**: The overview MUST present tenant-scoped summary metrics: total/published events, total attendees, total orders, total credentials issued, today's check-ins, active kiosks, active gates, failed scans, and recent audit events.

**Foundation administration**

- **FR-009**: The dashboard MUST let permitted admins view the user profile (name, email, phone, role, tenant, last login where available).
- **FR-010**: With `users.manage`, admins MUST be able to list, search, filter (by status/role), create, edit, and activate/deactivate users.
- **FR-011**: With `roles.manage`, admins MUST be able to list roles, view/assign permissions, and create/edit roles, while protected system roles remain non-editable unless explicitly allowed.
- **FR-012**: With `tenant.manage`, admins MUST be able to view tenant settings (name, slug, branding, default language, default timezone, and residency/retention where available).
- **FR-013**: With `audit.view`, users MUST be able to list and filter audit logs (by actor, action, entity type, date) and view before/after detail.

**Events, registration, ticketing, pricing**

- **FR-014**: Users MUST be able to list current-tenant events with search and filters (name, status, tier, date) and a table showing name, tier, status, start/end dates, capacity, registration count, and per-row actions.
- **FR-015**: With `events.create`, users MUST be able to create an event (name, slug, description, tier, start/end, timezone, location, capacity, branding where available) and save as draft or save-and-continue.
- **FR-016**: Users MUST be able to open an event detail view exposing the defined tabbed sections, and with `events.update` edit the event, and with `events.publish`/`events.cancel` publish or cancel it behind a confirmation modal.
- **FR-017**: With `registration.manage`, users MUST be able to view, add, edit, reorder, delete, and mark required the registration form fields across the supported field types, and preview the resulting registration form.
- **FR-018**: The dashboard MUST provide a public-style registration preview showing event branding, fields, and ticket selection, with an optional test submission when allowed.
- **FR-019**: With `ticketing.manage`, users MUST be able to list, create, edit, and disable ticket types (name, description, price, currency, quantity, sale window, attendee type, tier, status) and see remaining quantity.
- **FR-020**: With `ticketing.manage`, users MUST be able to list, create, edit, and disable price tiers (name, ticket type, price, currency, window, capacity threshold, priority, status) and see which tier is active by date.

**Orders, attendees, credentials**

- **FR-021**: With `orders.manage`, users MUST be able to list orders with search and payment/order-status filters and open an order detail (buyer, payment, items, linked attendees, credential status, audit history).
- **FR-022**: With `attendees.view`, users MUST be able to list attendees with search and filters (ticket type, registration/check-in/credential status) and open an attendee detail (personal info, custom fields, ticket, order, credential, wallet status, check-in and badge-print history, audit logs).
- **FR-023**: From an attendee detail, permitted users MUST be able to reissue/revoke a credential, print a badge, and manually check in, each gated by its permission.
- **FR-024**: Users MUST be able to list credentials with status filter and attendee search, view a credential detail (code, attendee, ticket, status, issued/expiry/revoked timestamps, revoke reason, reissued-from link, scan history, audit logs), and see credential status among pending/active/revoked/expired/reissued.
- **FR-025**: With `credentials.revoke`, users MUST revoke a credential only after providing a required reason and confirming a modal; with `credentials.reissue`, users MUST reissue a credential, with the new credential linked to its predecessor.

**Wallet, scanning, check-in**

- **FR-026**: Users MUST be able to list wallet passes with provider/status filters and attendee search and open a pass detail (provider, serial, attendee, credential, status, last pushed, pass URL where available, audit logs); trigger-update and revoke appear only when the backend and permission allow.
- **FR-027**: With `scan.perform`, the scanner page MUST accept a QR credential via camera (where available) or manual code entry, prevent duplicate in-flight submissions, and show a large accepted result (attendee, ticket type, status, entry-allowed) or rejected result (reason + suggested action).
- **FR-028**: The check-in dashboard MUST show registered, checked-in, not-checked-in, accepted, rejected, duplicate, and revoked-attempt counts, check-ins over time, and latest scan events for the event.
- **FR-029**: Users MUST be able to list scan events with filters (result, scanner type, gate/zone, offline mode) and attendee search, and view scan detail, with results covering accepted/rejected/duplicate/revoked/expired/unauthorized_zone/anti_passback_rejected/manual_override.

**Kiosk, badge, manual desk**

- **FR-030**: With `kiosk.manage`, users MUST be able to list and register kiosks and view kiosk detail/status (device info, location, status, last seen, printer/scanner health, recent check-ins/print jobs, audit logs) and activate/deactivate a kiosk.
- **FR-031**: The dashboard MUST provide a self-service kiosk-mode screen keyed by device code with event branding, QR scan, name/email/phone lookup fallback, attendee confirmation, print action, and clear success/error/reset states suitable for fullscreen.
- **FR-032**: With `badge.print`/`badge.reprint`, users MUST be able to manage badge templates (list, create, edit, preview, activate/deactivate) and view badge print jobs (list, filter by status, attendee search, reprint reason, printed-by/at) with statuses pending/printing/printed/failed/cancelled.
- **FR-033**: The manual desk MUST let permitted staff search an attendee (name/email/phone), view status, and perform check-in, print, reprint, walk-up registration, and manual override — requiring a reason for override/reprint and relying on the backend to record audit entries.

**ACS**

- **FR-034**: The ACS overview MUST show tenant-scoped totals (zones, lanes, active/offline gates, accepted/rejected entries, anti-passback rejections, emergency events) and latest gate events.
- **FR-035**: With `acs.manage`, users MUST be able to manage zones (list/create/edit/disable), lanes (list/create/edit/disable, assign to zone), and authorization rules (list/create/edit/disable with ticket/attendee type, zone, lane, direction, validity window).
- **FR-036**: Users MUST be able to view ACS access logs with filters (result, zone, lane, direction) and attendee/credential search including rejection reason, and view a gate-health page (status, last heartbeat, last event, error/emergency state).
- **FR-037**: Emergency egress controls MUST appear only when the backend supports them and only for authorized users, and any emergency-related action MUST require a confirmation modal (and a reason where applicable).

**Reports**

- **FR-038**: With `reports.view`, users MUST be able to open a per-event report showing registration count, paid orders, payment success rate, credentials issued/revoked, wallet adoption, check-in count, first-scan success rate, badge print count, and ACS accepted/rejected entries; where a chart capability exists it MAY visualize trends, otherwise cards/tables are used.

**Cross-cutting UX**

- **FR-039**: Every data-fetching page MUST show a loading state (skeletons for cards/tables/detail), every list page MUST show a purpose-specific empty state, and every page MUST show a clear error state on failure.
- **FR-040**: Every form submission MUST show a loader scoped to the form/button, disable the submit control during the request, prevent duplicate clicks, show a success toast, show field-level validation errors, and keep the user in place unless the flow requires redirect.
- **FR-041**: Sensitive actions MUST use a confirmation modal (publish/cancel event, revoke/reissue credential, print/reprint badge, manual override, disable ACS rule, emergency action), and revoke/reprint/override/emergency actions MUST also require a reason.
- **FR-042**: The dashboard MUST use consistent status badges across event, order, payment, credential, wallet, scan, kiosk, badge-print, and ACS-lane statuses.
- **FR-043**: The interface MUST be responsive for desktop and tablet and usable on mobile for the scanner, kiosk-mode, and manual-desk surfaces.
- **FR-044**: The interface MUST be prepared for Arabic and English with full RTL/LTR support and locale-aware dates, numbers, and currencies for all user-visible content.

**API integration & error handling**

- **FR-045**: The dashboard MUST integrate with existing backend APIs through a dedicated client layer that establishes tenant context and handles loading, validation errors, unauthorized (re-auth), and forbidden (permission message) responses consistently.
- **FR-046**: When a required backend API is missing, the dashboard MUST document the gap under "Missing Backend API Requirements" and render a safe placeholder/empty state (via an adapter/placeholder) rather than a broken page, without introducing new backend business modules.

### Constitutional Requirements *(mandatory)*

- **CR-001 Tenant Scope**: Every screen, list, metric, filter, detail, cached
  response, and export the dashboard renders MUST be limited to the signed-in
  user's tenant. The client MUST send trusted tenant context on every request,
  MUST NOT allow selecting or requesting another tenant's data, and MUST treat any
  response whose tenant does not match the active context as an error rather than
  display it. No cross-tenant aggregation is performed in the browser.
- **CR-002 RBAC**: Actors are Platform Admin, Organizer/Admin, Event Staff
  (scanning/check-in), Badge Staff, Manual Desk Operator, and ACS Operator.
  Navigation and actions are governed by the permission set in §17 of the source
  plan (e.g., `events.*`, `credentials.*`, `scan.perform`, `badge.*`, `kiosk.manage`,
  `acs.manage`, `reports.view`, `audit.view`, `users.manage`, `roles.manage`,
  `tenant.manage`). Least-privilege default: no permission ⇒ item hidden. UI checks
  are UX-only; the backend remains the authorization boundary.
- **CR-003 Auditability**: The dashboard itself is a client and relies on backend
  audit records for state-changing actions it triggers — publish/cancel event,
  credential revoke/reissue, badge print/reprint, manual check-in/override, walk-up
  registration, ACS rule changes, and emergency actions. The UI MUST surface
  success/failure faithfully and MUST NOT report an audited action as complete when
  the backend call failed. Where an audit-log API exists, the UI exposes actor,
  action, entity, tenant, timestamp, outcome, and before/after context read-only.
- **CR-004 Credential Security**: The dashboard never signs, mints, or stores
  credential secrets. It displays credential status/lifecycle and submits scan
  payloads for server-side validation. Expired, revoked, replayed, or
  unknown/rotated-key credentials MUST render as clear rejections with reason; the
  UI never grants entry on a rejected server result and never exposes signing keys
  or raw secret payloads.
- **CR-005 Data and PDPL**: The dashboard displays personal and operational data
  (names, emails, phones, order/payment references, credential and access data).
  It MUST show only data the backend returns for the user's tenant and permissions,
  MUST NOT persist personal data beyond session/cache needs, MUST honor consent and
  minimization already enforced by the backend, and MUST respect residency by not
  routing tenant data outside approved boundaries. Retention/deletion are backend
  responsibilities; the UI does not create shadow copies of sensitive data.
- **CR-006 API and Integrations**: The dashboard consumes the existing versioned
  REST contracts via a dedicated client/adapter layer with consistent handling of
  loading, validation, unauthorized, forbidden, and tenant-scope behavior, plus
  request cancellation where needed. Camera scanning, wallet providers, printers,
  and ACS/gate data are reached only through their backend APIs; missing endpoints
  are documented and stubbed behind mock-safe adapters, never bypassed with new
  backend business logic.
- **CR-007 White-Label and Localization**: The shell and public-style surfaces
  (registration preview, kiosk mode) MUST honor tenant branding, and all
  user-visible content MUST be available in Arabic and English with correct RTL/LTR
  layout and locale-aware dates, numbers, and currencies. No tenant-specific code
  forks; branding is configuration-driven.
- **CR-008 Deployment Parity**: The dashboard MUST behave equivalently in SaaS and
  on-premise deployments, targeting the backend via configuration. It MUST NOT
  require cloud-only services; where an external adapter or network dependency is
  unavailable it MUST degrade to a clear error/empty state and remain navigable. No
  Docker is introduced by this phase.
- **CR-009 Automated Verification**: Required tests — unit tests (permission-gated
  navigation/actions, status badges, form validation, submit loaders, empty/error
  states), integration tests (login, events list/create, ticket-type create,
  attendee detail load, credential revoke/reissue, QR scan, manual-desk search, ACS
  rule create), and end-to-end tests for the twelve critical journeys in §20.3 of
  the source plan (admin dashboard, event creation, registration-form config,
  ticket creation, orders/attendees view, credential revoke/reissue, valid and
  revoked scan, badge print, manual-desk search, ACS zone/lane/rule).
- **CR-010 Phase Alignment**: This is a frontend consolidation phase that exposes
  the accepted Foundation and Phases 1–4 backends before any new product phase. It
  depends on Foundation auth/RBAC/tenant/audit and the Phase 1–4 domain APIs, adds
  no new backend business modules, and MUST NOT weaken existing contracts or
  controls. Phase 5 MUST NOT begin until this phase is complete.

### Key Entities *(include if feature involves data)*

The dashboard consumes and displays these backend-owned entities (no new schema is
introduced by this phase):

- **User / Role / Permission**: The signed-in actor, assigned role, and the
  permission set that drives navigation and action visibility.
- **Tenant**: The organization context (name, slug, branding, default language/
  timezone, residency/retention) scoping all displayed data.
- **Event**: Root object with tier, status, schedule, location, capacity, and
  registration counts.
- **Registration Form / Field**: Ordered, typed fields (text/email/phone/number/
  date/dropdown/multi-select/checkbox/hidden/consent) with required/validation.
- **Ticket Type / Price Tier**: Sellable inventory and time/priority-based pricing.
- **Order**: Buyer, payment status/reference, items, and linked attendees.
- **Attendee**: Registrant profile with custom fields, ticket, order, credential,
  wallet, check-in, and badge-print history.
- **Credential**: Lifecycle object (pending/active/revoked/expired/reissued) with
  code, timestamps, revoke reason, reissued-from link, and scan history.
- **Wallet Pass**: Provider (Apple/Google), serial, status, last-pushed, URL.
- **Scan Event**: Result-typed record (accepted/rejected/duplicate/revoked/expired/
  unauthorized_zone/anti_passback_rejected/manual_override) with scanner/gate/zone.
- **Kiosk**: Device with location, status, last-seen, printer/scanner health,
  app version.
- **Badge Template / Badge Print Job**: Template config and print-job lifecycle
  (pending/printing/printed/failed/cancelled).
- **ACS Zone / Lane / Rule / Access Log / Gate Health**: Access-control topology,
  authorization rules, entry/exit logs, and gate heartbeat/error/emergency state.
- **Audit Log**: Read-only actor/action/entity/tenant/timestamp/outcome records
  with before/after detail.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A returning operator can sign in and reach the overview in under 30 seconds on a standard connection.
- **SC-002**: 100% of the completed backend phases (Foundation + Phases 1–4) are reachable and visibly represented from the dashboard, satisfying all 45 acceptance items in the source plan.
- **SC-003**: 100% of list pages display a purpose-specific empty state, a loading state, and a clear error state under the corresponding conditions.
- **SC-004**: Navigation and action visibility match the signed-in user's permissions with zero unauthorized actions shown across the defined permission set.
- **SC-005**: Staff can submit a QR/code scan and see an accept/reject result (with reason on reject) within 3 seconds under normal conditions.
- **SC-006**: 100% of user-visible labels and messages are available in both Arabic and English, with layout rendering correctly in RTL and LTR.
- **SC-007**: An organizer can complete an end-to-end event setup (create event, add one registration field, create one ticket type, create one price tier) in under 5 minutes.
- **SC-008**: Every form submission disables its control during the request and prevents duplicate writes, verified for 100% of forms listed in the source plan's submit-loader list.
- **SC-009**: The interface is usable without horizontal scrolling on desktop and tablet widths, and the scanner, kiosk-mode, and manual-desk surfaces are operable on mobile widths.
- **SC-010**: Manual QA confirms 100% of the twelve critical end-to-end journeys pass, and any missing backend endpoint is recorded under "Missing Backend API Requirements" rather than presented as a broken page.

## Missing Backend API Requirements

This section mirrors `api-integration-map.md` and records endpoint/read-projection
gaps discovered while wiring each screen to the existing backend. No new backend
business module is created to fill a gap; confirmed rows are satisfied through
AdminConsole ViewModels or existing owning-module APIs.

| ID | Screen | Required read projection | Final treatment |
|---|---|---|---|
| GAP-1 | Price tiers | Price tier rows including `is_active_now` | Confirmed through `EventDashboardViewModel`; empty state renders when no tiers exist. |
| GAP-2 | Wallet pass detail | Wallet pass detail including `last_pushed_at` and optional pass URL | Confirmed through AdminConsole wallet pass detail ViewModel. |
| GAP-3 | Scan events | Event scan rows with filters | Confirmed through AdminConsole scan-events ViewModel. |
| GAP-4 | Kiosk detail | Kiosk detail with recent check-ins/print jobs | Confirmed through AdminConsole ViewModel plus Kiosk API show operation. |
| GAP-5 | Badge print jobs | Event badge print job rows | Confirmed through AdminConsole ViewModel plus BadgePrinting API index operation. |
| GAP-6 | Event report | Per-metric summary reads, including wallet adoption and first-scan success rate | Confirmed through `EventReportViewModel`; wallet adoption is computed, first-scan success rate is labelled not available yet. |

## Assumptions

- **Existing stack reused**: The frontend is built on the project's existing web
  stack and consumes the existing REST backend; existing Foundation authentication,
  RBAC, and tenant isolation are reused rather than rebuilt. Docker is not
  introduced.
- **Backend is source of truth**: All authorization, credential signing/validation,
  audit writing, and data retention remain backend responsibilities; the UI is a
  client and never enforces security on its own.
- **Permissions are backend-provided**: The UI receives the user's effective
  permissions from the backend and uses them only for navigation/action visibility.
- **State management**: The project's existing frontend state/data-fetching approach
  is reused; if none exists, a simple API-client + per-module hooks + local form
  state structure is used, favoring an existing query/cache library when present and
  avoiding over-engineering.
- **Charts optional**: Reporting uses cards/tables by default and adds charts only
  if a charting capability already exists in the frontend.
- **Scope boundary**: This phase adds no new backend business features and does not
  begin Phase 5; it only exposes and operates the completed Foundation and Phase 1–4
  capabilities. Public-facing registration and kiosk-mode surfaces are included only
  as operator-validated previews of existing backend behavior.
- **Target devices**: Primary use is desktop and tablet for operations, with mobile
  support focused on scanner, kiosk-mode, and manual-desk surfaces.
