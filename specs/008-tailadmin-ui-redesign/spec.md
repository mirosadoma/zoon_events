# Feature Specification: TailAdmin Dashboard UI Redesign

**Feature Branch**: `008-tailadmin-ui-redesign`

**Created**: 2026-07-08

**Status**: Draft

**Input**: User description: "front_plan_design.md — Redesign and rebuild the
Zonetec React dashboard UI using TailAdmin as the visual/structural reference,
applying a clean, professional, enterprise-grade design across the existing
frontend without changing completed backend business logic."

**Product Phase**: Frontend UI Redesign Phase (design-system standardization over
the completed Foundation + Phase 1 Registration-Ticketing-Credentials + Phase 2
Wallet-Scanning + Phase 3 Kiosk-Badge-Manual-Desk + Phase 4 ACS). No new backend
phase; Phase 5 features are explicitly out of scope.

**Deployment Modes**: both (SaaS and on-premise)

## User Scenarios & Testing *(mandatory)*

This feature restyles and standardizes the existing operator/admin dashboard onto a
single, professional, enterprise-grade design system (TailAdmin as the visual
reference). It adds no new backend business capability; it makes every already-exposed
module look and behave consistently — one shell, one component library, consistent
states, responsive layouts, RTL/Arabic readiness, and permission-aware navigation —
and ensures redesigned action controls invoke the existing backend and reflect real
success/failure rather than a cosmetic result. Each user story is an independently
shippable slice.

### User Story 1 - Unified dashboard shell and shared design system (Priority: P1)

An operator signs in and works inside one consistent shell — a grouped sidebar, a
topbar (search, notifications, tenant indicator, role indicator, user menu, optional
language and theme toggles), breadcrumbs, page header, and content area — built from a
single reusable component library (cards, tables, status badges, forms, modals,
dropdowns, loaders, empty states, error states). Navigation and controls appear only
for permissions the user holds.

**Why this priority**: Every page depends on the shell and shared components. This
slice is the MVP: it establishes the design language, the reusable component set, RBAC
visibility, responsiveness, and RTL readiness that all later page redesigns reuse.

**Independent Test**: Load the shell as users with different permission sets and
confirm each sees only permitted sidebar groups/items; resize to tablet/mobile and
confirm the sidebar becomes a drawer and nothing overflows horizontally; switch to
Arabic and confirm the layout mirrors to RTL; render each shared component in its
loading, empty, error, and populated states.

**Acceptance Scenarios**:

1. **Given** a signed-in user, **When** the dashboard loads, **Then** the shell shows a grouped sidebar (Main, Event Operations, On-site Operations, Access Control, Administration), a topbar with tenant + role indicators and a user menu, breadcrumbs, and a page header, all in the new design language.
2. **Given** a user lacking a permission, **When** they view navigation, **Then** the governed sidebar items and in-page actions are hidden.
3. **Given** any list, **When** it is loading, empty, or errored, **Then** the shared skeleton loader, purpose-specific empty state, or clear error state renders respectively.
4. **Given** a tablet or mobile width, **When** the user navigates, **Then** the sidebar collapses to a drawer, tables scroll or become cards, forms stack, and no horizontal page scroll occurs.
5. **Given** Arabic locale, **When** any shell surface renders, **Then** direction is RTL with locale-aware dates/numbers and mirrored spacing/icons.

---

### User Story 2 - Redesigned overview and event lifecycle pages (Priority: P2)

An organizer uses redesigned overview, events list/detail/create/edit, registration
form builder, ticket types, and price tiers pages built from the shared components,
with metric cards, consistent tables, tabbed detail views, and working create/edit/
publish/cancel actions.

**Why this priority**: Events are the root object; the overview and event lifecycle are
the most-used surfaces and the clearest demonstration of the redesign end to end.

**Independent Test**: Open the overview and confirm metric cards and recent-activity
tables render; open the events list with the shared table (search/filter/pagination/
status badges/action menu); create an event and confirm it appears; open an event's
tabbed detail; add a registration field; create a ticket type and a price tier — each
action shows a scoped submit loader and reflects the real backend result.

**Acceptance Scenarios**:

1. **Given** the overview, **When** it loads, **Then** metric cards (total/published/live events, attendees, orders, credentials, today's check-ins, active kiosks, active gates, failed scans) and recent events/orders/scans/audit tables render with skeletons while fetching.
2. **Given** the events list, **When** searching/filtering by name/status/tier/date, **Then** the shared table updates with consistent columns, status badges, and a per-row action menu.
3. **Given** `events.create`, **When** the organizer submits the redesigned create-event form, **Then** the submit button disables with a spinner, duplicate clicks are prevented, and on success the event is created and shown (real backend call, not a cosmetic result).
4. **Given** an event detail, **When** it opens, **Then** the defined tabs render as cards/tabs and publish/cancel actions use a confirmation modal gated by `events.publish`/`events.cancel`.
5. **Given** the registration builder, ticket-types, and price-tiers pages, **When** a field/ticket/tier is added or edited, **Then** the change is submitted to the existing backend and reflected, with validation errors shown below fields.

---

### User Story 3 - Redesigned orders, attendees, and credentials (Priority: P3)

An organizer browses redesigned orders/attendees/credentials lists and detail pages,
and performs credential revoke (with reason) and reissue through the redesigned reason/
confirmation modals wired to the existing backend.

**Why this priority**: This is the core registration/credential lifecycle made visible
and actionable in the new design; credential actions must reflect real backend outcomes.

**Independent Test**: Filter orders and attendees; open an order, attendee, and
credential detail; revoke a credential with a required reason and reissue one — each via
a modal that calls the existing backend and reflects the real result.

**Acceptance Scenarios**:

1. **Given** the orders list, **When** filtering by payment/order status and searching by buyer, **Then** the shared table renders order number, buyer, total, currency, and status badges with an action menu.
2. **Given** an order/attendee/credential detail, **When** it opens, **Then** it renders as consistent cards/tables (buyer, payment, items, linked attendees, credential status, audit timeline / personal info, custom fields, ticket, order, credential, wallet, scan and badge history).
3. **Given** `credentials.revoke`, **When** the user confirms revoke and provides a required reason, **Then** the reason modal submits to the existing backend, the UI shows the real success/failure, and status reflects the persisted result — never a cosmetic-only change.
4. **Given** `credentials.reissue`, **When** the user reissues, **Then** the confirmation modal submits to the backend and the new credential linked to the prior one is shown.
5. **Given** a user lacking a credential action permission, **When** viewing a credential, **Then** that action is hidden.

---

### User Story 4 - Redesigned wallet, scanning, and check-in (Priority: P4)

Staff use redesigned wallet-pass pages, a large-format scanner page, a check-in
dashboard with metric cards, and a scan-events table.

**Why this priority**: On-site validation is operationally critical; the redesign must
keep the scanner fast and legible while standardizing the surrounding pages.

**Independent Test**: Open the scanner and submit a valid code (large accepted result)
and a revoked/invalid code (large rejected result with reason); confirm the check-in
dashboard metric cards and latest scan events render; filter the scan-events table.

**Acceptance Scenarios**:

1. **Given** the scanner page, **When** a code is submitted, **Then** a large centered result panel shows accepted (attendee/ticket/status) or rejected (reason) with a scoped button loader and duplicate-submit prevention.
2. **Given** the check-in dashboard, **When** it loads, **Then** metric cards (registered, checked-in, accepted, rejected, duplicate, revoked) and a latest-scan-events table render.
3. **Given** the scan-events page, **When** filtering by result/scanner/gate/zone/direction, **Then** the shared table renders with status badges and reason column.
4. **Given** the wallet-passes page, **When** it loads, **Then** the shared table shows attendee, provider (Apple/Google), status badge, last-pushed, and an action menu.
5. **Given** a mobile width, **When** using the scanner or manual entry, **Then** the surface remains operable without horizontal scroll.

---

### User Story 5 - Redesigned on-site operations (kiosk, badge, manual desk) (Priority: P5)

Operators use redesigned kiosk list/detail and fullscreen kiosk mode, badge templates
and print jobs, and a manual-desk workstation with search, quick actions, and reason-
gated overrides.

**Why this priority**: Exposes on-site operations in the new design; important but
dependent on the core surfaces above.

**Independent Test**: Open the kiosk list and detail; open kiosk mode in a fullscreen-
friendly layout; view badge templates and print jobs; on the manual desk, search an
attendee and perform a reason-gated reprint/override wired to the existing backend.

**Acceptance Scenarios**:

1. **Given** the kiosks page, **When** it loads, **Then** the shared table shows device name, location, status badge, last seen, printer/scanner status, and app version.
2. **Given** kiosk mode, **When** it opens for a device code, **Then** a simpler fullscreen-friendly layout uses the same visual language.
3. **Given** the badge print-jobs page, **When** a reprint is requested, **Then** a reason modal submits to the existing backend and the job status updates.
4. **Given** the manual desk, **When** searching by name/email/phone, **Then** matching attendees render with status summary and permitted quick actions.
5. **Given** a manual override, **When** confirmed, **Then** a reason is captured and submitted to the existing backend.

---

### User Story 6 - Redesigned ACS access-control pages (Priority: P6)

An ACS operator uses redesigned overview, zones, lanes, rules, access-logs, and gate-
health pages built from the shared tables/cards, with create actions wired to the
existing backend and emergency controls gated by permission.

**Why this priority**: Exposes Phase 4 in the new design; the most specialized slice.

**Independent Test**: Open the ACS overview metric cards; create a zone, a lane assigned
to it, and a rule via redesigned forms; confirm access-logs and gate-health render
tenant-scoped data.

**Acceptance Scenarios**:

1. **Given** the ACS overview, **When** it loads, **Then** metric cards (zones, lanes, active/offline gates, accepted/rejected entries, anti-passback rejections, emergency events) render.
2. **Given** `acs.manage`, **When** creating a zone/lane/rule via the redesigned forms, **Then** the submit calls the existing backend and the new record appears in the shared table.
3. **Given** the access-logs page, **When** filtering by result/zone/lane/direction, **Then** the shared table renders with reason column.
4. **Given** the gate-health page, **When** it loads, **Then** each gate/lane shows status badge, last heartbeat, last event, and error/emergency state.
5. **Given** emergency controls, **When** shown, **Then** they appear only for authorized users and require a confirmation modal.

---

### User Story 7 - Redesigned administration, reports, and settings (Priority: P7)

A platform admin uses redesigned users, roles, tenant-settings, audit-logs, reports,
profile, and system-settings pages built from the shared components, each gated by the
relevant permission.

**Why this priority**: Rounds out redesign coverage of Foundation administration and
reporting; useful but not required for the primary operational flows.

**Independent Test**: Open users/roles/tenant-settings/audit-logs/reports/profile/
settings; confirm each uses the shared table/card/form components, permission-aware
controls, and consistent states.

**Acceptance Scenarios**:

1. **Given** `users.manage`, **When** viewing users, **Then** the shared table supports search, status/role filters, and a permission-aware action menu.
2. **Given** `roles.manage`, **When** editing a role, **Then** the redesigned form assigns permissions and protected system roles remain non-editable unless allowed.
3. **Given** `audit.view`, **When** filtering audit logs, **Then** the shared table renders actor/action/entity/date with before/after detail.
4. **Given** `reports.view`, **When** opening an event report, **Then** metric cards/tables render available figures (with documented placeholders where a metric is not yet available).
5. **Given** any admin page whose backing API is absent, **When** it loads, **Then** a documented placeholder/empty state renders instead of an error.

---

### Edge Cases

- **Backend unchanged**: The redesign changes only presentation; if a redesigned action needs an endpoint that does not exist, it is recorded under "Missing Backend API Requirements" and the surface shows a documented placeholder — no backend business logic is added or changed.
- **No fake success**: A redesigned action control must reflect the real backend outcome; it MUST NOT report success (or change displayed status) without a successful backend call.
- **Missing permission**: Governed nav items and actions are hidden; a forbidden response shows a clear "you do not have permission" state, not partial data.
- **Cross-tenant isolation**: Every list, detail, metric, and filter shows only the signed-in user's tenant; a tenant-mismatched response is treated as an error, not displayed.
- **Localization/RTL**: Every redesigned screen renders correctly in Arabic (RTL) and English (LTR), including dates, numbers, currencies, validation messages, and layout direction.
- **Responsive breakpoints**: Desktop, tablet, and mobile (scanner/kiosk/manual-desk) surfaces remain usable with no horizontal page scroll; tables scroll or convert to cards.
- **Empty/error/loading**: Every list has a purpose-specific empty state, every data page a skeleton loader and a clear error state.
- **Duplicate submission**: Forms and scans disable their submit control while a request is in flight.
- **Demo content removed**: No TailAdmin demo data, labels, or sample pages remain; all content is Zonetec domain data with Zonetec branding.

## Requirements *(mandatory)*

### Functional Requirements

**Design system & shell**

- **FR-001**: The dashboard MUST provide one reusable shell (grouped sidebar, topbar with search + notifications + tenant indicator + role indicator + user menu + optional language/theme toggles, breadcrumbs, page header, content area, route transition loader) applied consistently across all pages.
- **FR-002**: The dashboard MUST provide a single shared component library — cards (metric/stat/info), data table (with toolbar, search, filters, pagination, action menu, skeleton, empty, error), status badges, form inputs, confirmation/reason/detail modals, dropdowns, loaders, and empty/error states — reused by every page.
- **FR-003**: The dashboard MUST use one shared status-badge component covering event, order, payment, credential, wallet, scan-result, kiosk, badge-print, and ACS statuses with a consistent color/label system in Arabic and English.
- **FR-004**: All TailAdmin demo content, sample pages, and demo branding MUST be removed; Zonetec branding, naming, routes, and domain data MUST be used throughout.

**Navigation & RBAC visibility**

- **FR-005**: The sidebar MUST group navigation (Main, Event Operations, On-site Operations, Access Control, Administration) with active-route highlighting, icons, collapsible groups, and mobile drawer support.
- **FR-006**: Every navigation item and action control MUST appear only when the signed-in user holds the governing permission; UI permission checks are for experience only and MUST NOT be treated as a security boundary.
- **FR-007**: All redesigned routes MUST remain protected so only authenticated, authorized users can reach them.

**Page redesign coverage**

- **FR-008**: The following surfaces MUST be redesigned onto the shared system: authentication (login), overview dashboard, events (list/create/detail/edit), registration form builder, ticketing (ticket types, price tiers), orders (list/detail), attendees (list/detail), credentials (list/detail), wallet passes (list/detail), scanner, check-in dashboard, scan events, kiosks (list/detail) and kiosk mode, badge templates and print jobs, manual desk, ACS (overview/zones/lanes/rules/access-logs/gate-health), users, roles, tenant settings, audit logs, reports, profile, and system settings.
- **FR-009**: Every list page MUST use the shared data table with consistent columns, status badges, search, filters, pagination, and a per-row action menu.
- **FR-010**: Every detail page MUST use consistent cards/tabs/timelines for its sections (e.g., event tabs, order/attendee/credential detail cards and audit timelines).

**Action wiring (no cosmetic-only results)**

- **FR-011**: Every redesigned action control (create/edit event, save registration form, create ticket type/price tier, revoke/reissue credential, scan, register/activate kiosk, create/print/reprint badge, manual check-in/override, create ACS zone/lane/rule, user/role changes) MUST invoke the existing backend API and reflect the real success/failure; it MUST NOT display success or change persisted-status display without a successful backend call.
- **FR-012**: Every form submission MUST show a loader scoped to the form/button, disable the submit control during the request, prevent duplicate submissions, show a success toast on success, and show field-level validation errors on failure.
- **FR-013**: Sensitive actions (publish/cancel event, revoke/reissue credential, print/reprint badge, manual override, disable ACS rule, delete/disable) MUST use a confirmation modal; revoke/reprint/override MUST also require a reason via the reason modal.

**States, responsiveness, localization**

- **FR-014**: Every data-fetching page MUST show a skeleton loading state, every list page a purpose-specific empty state, and every page a clear error state (failed to load, not found, forbidden, server error, network error, validation error).
- **FR-015**: The interface MUST be responsive: desktop-first for admin, tablet-friendly for event operations, and mobile-friendly for scanner/manual-desk/kiosk surfaces, with the sidebar collapsing to a drawer, tables scrolling or converting to cards, and forms stacking — with no horizontal page scroll.
- **FR-016**: Every user-visible surface MUST be available in Arabic and English with full RTL/LTR support and locale-aware dates, numbers, and currencies.

**API integration & gaps**

- **FR-017**: The redesign MUST integrate with existing backend APIs through a consistent client approach that uniformly handles auth, tenant context, loading, validation errors, unauthorized (re-auth), and forbidden (permission message) responses.
- **FR-018**: When a redesigned page needs a backend endpoint that does not exist, the gap MUST be recorded under "Missing Backend API Requirements" (page, required endpoint/method/request/response, priority, temporary behavior) and the page MUST render a safe placeholder/empty state — no new backend business module or business-rule change is introduced.

**Non-regression**

- **FR-019**: The redesign MUST NOT change backend business logic, rebuild completed backend phases, introduce Phase 5 features, or introduce Docker.

### Constitutional Requirements *(mandatory)*

- **CR-001 Tenant Scope**: Every screen, list, metric, filter, and detail the redesigned UI renders MUST be limited to the signed-in user's tenant; the client MUST send trusted tenant context, MUST NOT request another tenant's data, and MUST treat a tenant-mismatched response as an error rather than display it.
- **CR-002 RBAC**: Navigation and action visibility are governed by the existing permission catalog; least-privilege default (no permission ⇒ hidden). UI checks are UX-only; the backend remains authoritative.
- **CR-003 Auditability**: The redesign is a client and relies on backend audit records for state-changing actions it triggers; it MUST surface success/failure faithfully and MUST NOT report an audited action complete when the backend call failed or was never made.
- **CR-004 Credential Security**: The UI never signs, mints, or stores credential secrets. It displays credential status/lifecycle and submits scan payloads for server-side validation; rejected results render clearly and never grant entry.
- **CR-005 Data and PDPL**: The UI shows only backend-returned, tenant/permission-scoped data, persists no personal data beyond session/cache needs, and honors minimization and residency already enforced by the backend.
- **CR-006 API and Integrations**: The redesign consumes existing versioned REST contracts through a consistent client with uniform loading/validation/unauthorized/forbidden/tenant-scope handling; missing endpoints are documented and stubbed behind placeholders, never bypassed with new backend business logic.
- **CR-007 White-Label and Localization**: The shell and public-style surfaces honor tenant branding; all user-visible content is Arabic/English with correct RTL/LTR and locale-aware formatting. Branding is configuration-driven with no tenant-specific forks.
- **CR-008 Deployment Parity**: The redesigned UI behaves equivalently in SaaS and on-premise, targeting the backend via configuration, with no cloud-only dependency; unavailable dependencies degrade to clear error/empty states and remain navigable. No Docker.
- **CR-009 Automated Verification**: Required tests — component tests (sidebar/nav visibility, `PermissionGate`, `StatusBadge`, `DataTable` states, submit-loader disable, confirm/reason modals), page render tests for each redesigned page, and flow tests for the critical journeys (login, navigate, create event, create ticket type, view attendee, revoke/reissue credential, scan, manual desk, create ACS zone/lane/rule), plus Arabic/RTL and responsive checks.
- **CR-010 Phase Alignment**: This is a frontend redesign phase over the accepted Foundation and Phases 1–4; it adds no new backend business module, MUST NOT weaken existing contracts or controls, and MUST NOT begin any new backend product phase (including Phase 5).

### Key Entities *(include if feature involves data)*

The feature introduces no new backend schema; it consumes existing backend-owned
entities (events, orders, attendees, credentials, wallet passes, scan events, kiosks,
badge jobs, ACS zones/lanes/rules/logs, users, roles, tenants, audit logs). The
structured artifacts this phase does own are UI/design constructs:

- **Design Tokens**: The Zonetec color, radius, shadow, typography, and spacing scale
  applied consistently (TailAdmin-referenced, Zonetec-branded).
- **Shared Component Library**: The reusable set (shell, cards, data table, status
  badge, form inputs, modals, dropdowns, loaders, empty/error states).
- **Navigation Manifest**: The permission-keyed, grouped sidebar/route map.
- **Status Badge Map**: The status→color/label mapping across all module statuses.
- **Page Template**: The standard page composition (breadcrumbs + header + content +
  states) every page follows.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of the pages listed in FR-008 are redesigned onto the shared shell and component library, with zero TailAdmin demo content or demo branding remaining.
- **SC-002**: 100% of list pages use the shared data table and display a loading, purpose-specific empty, and clear error state under the corresponding conditions.
- **SC-003**: 100% of module statuses render through the single shared status-badge component with consistent colors and Arabic/English labels.
- **SC-004**: Navigation and action visibility match the signed-in user's permissions with zero unauthorized actions shown across the defined permission set.
- **SC-005**: 100% of the action controls in FR-011 invoke the existing backend and reflect the real result; zero controls report success or change persisted-status display without a successful backend call.
- **SC-006**: 100% of forms disable their submit control during the request, prevent duplicate submissions, and show a scoped loader.
- **SC-007**: The interface renders without horizontal page scrolling on desktop and tablet widths, and the scanner, kiosk-mode, and manual-desk surfaces are operable on mobile widths.
- **SC-008**: 100% of user-visible labels and messages render in both Arabic and English with correct RTL and LTR layout.
- **SC-009**: A returning operator can sign in and reach the redesigned overview in under 30 seconds on a standard connection.
- **SC-010**: 100% of the critical flow tests (login, navigate, create event, create ticket type, view attendee, revoke/reissue credential, scan, manual desk, create ACS zone/lane/rule) pass, and any missing backend endpoint is recorded under "Missing Backend API Requirements" rather than presented as a broken page.

## Missing Backend API Requirements

This section is populated during implementation (mirrored from
`missing-api-requirements.md`, a plan-phase deliverable). For each gap, record: page
name, required endpoint, method, request body, response shape, priority, and temporary
frontend behavior. No new backend business module or business-rule change is created to
fill a gap; the page ships a documented placeholder/empty state until the owning module
exposes the read/write projection.

## Assumptions

- **Redesign in place, single system**: The existing dashboard's shared components are
  upgraded/standardized in place onto the new design language (no parallel or forked
  component set); the current project stack is reused and no Docker is introduced.
- **Existing APIs reused; gaps documented**: Redesigned action surfaces call the
  existing REST/backend endpoints; where an endpoint is missing the gap is documented
  and the surface shows a placeholder rather than blocking the redesign.
- **Working actions, not just restyling**: Because the design brief requires submit
  loaders and flow tests for create/revoke/scan/etc., redesigned action controls must
  perform the real backend call and reflect the real outcome (closing any prior
  cosmetic-only gaps), without changing backend business rules.
- **Light-first, theme-ready**: The design is light/enterprise-first; a dark/light
  toggle is included only where theme support already exists.
- **Identity status is display-only**: Where the backend already returns an attendee
  identity status, the redesigned attendee views may show it as a badge, but no Phase 5
  identity-verification feature is built in this phase.
- **Notifications/search scope**: The topbar notification dropdown and global search
  surface existing backend-provided data where available; where not available they show
  an empty/placeholder state and the gap is documented.
- **Extra plan artifacts**: The design brief's requested `frontend-routes.md`,
  `component-map.md`, `design-system.md`, `api-integration-map.md`, `rbac-ui-map.md`,
  `test-plan.md`, and `missing-api-requirements.md` are produced in the `/speckit.plan`
  phase, not this specification.
- **Directory numbering**: The brief suggested `010-tailadmin-ui-redesign`; this
  feature uses the repository's next sequential number (`008`) for consistency.
