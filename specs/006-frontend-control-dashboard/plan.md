# Implementation Plan: Frontend Control Dashboard for Completed Core Phases

**Branch**: `006-frontend-control-dashboard` | **Date**: 2026-07-07 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-frontend-control-dashboard/spec.md`

**Product Phase**: Frontend Consolidation Phase (exposes Foundation + Phase 1
Registration-Ticketing-Credentials + Phase 2 Wallet-Scanning + Phase 3
Kiosk-Badge-Manual-Desk + Phase 4 ACS)

**Deployment Modes**: SaaS and on-premise

## Summary

Consolidate the operator/admin dashboard so every already-completed backend
capability (Foundation through Phase 4) is reachable, visible, and operable from
one cohesive React/Inertia surface, and fill the presentation gaps that were not
built during the incremental Phase 0–4 delivery. This phase adds **no new backend
business module**: it extends the existing `AdminConsole` module (thin
Inertia controllers + read-only ViewModels) plus the shared React component
library, and consumes each domain module's existing application query/action
contracts. Where a completed phase lacks a read projection or a page needs a prop
the backend does not yet expose, the gap is recorded under **Missing Backend API
Requirements** and the UI ships a mock-safe placeholder/empty state — never a new
business module and never a direct read of another module's persistence.

The work is a unified dashboard shell (sidebar, top bar, tenant/role indicators,
breadcrumbs, global route loader, toasts, confirmation/reason modals, route error
boundaries) with RBAC-driven navigation and action visibility keyed to the real
permission catalog (`docs/standards/permission-catalog.md`), plus the remaining
list/detail/form pages for Foundation administration (users, roles,
tenant-settings, profile, audit logs), events (registration-form builder, price
tiers, order/attendee/credential detail), Phase 2 (wallet-pass detail, scan
events), Phase 3 (kiosk detail, kiosk mode, badge print jobs), Phase 4 (ACS zones,
lanes, rules, access logs pages), and per-event reports. It reuses the existing
Inertia data path, `react-i18next` Arabic/English localization with logical-RTL
Tailwind, the project-owned design system, and the Vitest/Testing-Library/Playwright
+ axe test stack. It introduces no separate SPA, no new REST `/src/api` client
layer, no Docker, and no cloud-only dependency, preserving SaaS/on-premise parity.

## Technical Context

**Language/Version**: TypeScript 5.9 (React 19 front end) on top of PHP 8.3
(Laravel 13 Inertia controllers/ViewModels); no language change

**Primary Dependencies**: Inertia 2 server adapter + `@inertiajs/react` 3.6,
React 19, Tailwind CSS 4 with logical properties, shadcn-style project-owned
components, `lucide-react` icons, `react-i18next` 17, `axios` (already present for
Inertia partial reloads/actions); Laravel Fortify/Sanctum session auth and the
existing `dashboard.permission` middleware and module query contracts. No new
runtime dependency is required; charts use existing capability or fall back to
cards/tables

**Storage**: None added. The dashboard is a presentation consumer; all data is
read through existing module application queries and rendered as Inertia props.
No new table, migration, or persisted frontend state is introduced

**Testing**: Vitest + React Testing Library + `@testing-library/jest-dom` for
component/unit, `@axe-core/playwright` + Playwright browser tests for
accessibility/RTL/responsive and end-to-end journeys, plus PHPUnit feature tests
for the thin AdminConsole controllers/ViewModels and `dashboard.permission`
route authorization; ESLint, TypeScript `tsc --noEmit`, Pint, and the existing
`composer quality` gates (OpenAPI sync/lint, docs check, phase-boundary check)

**Target Platform**: Same-origin Laravel web deployment (native Windows or Linux
web process; no Docker) serving the Inertia React dashboard for multi-tenant SaaS
and supported on-premise installs; primary operator use on desktop/tablet, with
mobile-usable scanner, kiosk-mode, and manual-desk surfaces

**Project Type**: Web application — existing modular Laravel monolith with an
Inertia/React front end; this feature is a frontend consolidation layer over the
accepted Phase 0–4 backends, extending only `AdminConsole` and `resources/js`

**Performance Goals**: Returning operator reaches the overview in under 30 s
(SC-001); scan submit shows accept/reject within 3 s (SC-005); list/detail pages
render skeletons immediately and hydrate on Inertia prop load; dashboards reuse the
Phase 2/3 bounded short-interval polling pattern (no new streaming/WebSocket
infrastructure)

**Constraints**: No new backend business module; presentation never queries
another module's persistence directly (Constitution VI) — it goes through existing
application query contracts or documented new read projections; RBAC visibility is
UX-only and the server `dashboard.permission`/`permission` middleware remains
authoritative; all tenant-scoped data comes from the authenticated tenant context,
never a client-supplied tenant id; Arabic/English + RTL/LTR parity for every
user-visible surface; no cloud-only dependency; missing read endpoints are
documented and stubbed, never faked as production-ready

**Scale/Scope**: Existing Phase 0–4 target (1,000 tenants, up to 100,000
attendees/event). Scope is roughly 40+ routes across 7 user-story slices, one
unified shell, ~10 shared component families (layout/tables/forms/feedback/status/
modals/loaders), and the gap pages enumerated in [frontend-routes.md](frontend-routes.md)
and [component-map.md](component-map.md); no new domain tables

## Constitution Check

*GATE: PASS before research; PASS after design.*

| Gate | Design evidence | Status |
|---|---|---|
| API-first | The dashboard consumes existing versioned contracts; it adds no bypass path. Any missing read projection is registered in [api-integration-map.md](api-integration-map.md) as a documented, versioned addition to the owning module's query/OpenAPI surface, not an ad-hoc UI query. Actions (publish/revoke/reissue/print/override/ACS writes) invoke the same application actions as the API. | PASS |
| Tenant isolation | Every page renders only Inertia props resolved from the authenticated tenant context via `tenant.context` middleware; no screen accepts a client-supplied tenant id, and a tenant-mismatched prop is treated as an error. Browser tests assert zero cross-tenant props ([test-plan.md](test-plan.md)). | PASS |
| RBAC and auditability | Navigation and every action gate on the real permission catalog through `PermissionGate`/`can` props; server `dashboard.permission`/`permission` middleware stays authoritative. The UI triggers audited actions through existing module actions and surfaces success/failure faithfully; it never reports an audited action complete when the backend call failed. | PASS |
| Credential security | The dashboard never signs, mints, stores, or displays credential secrets or signing keys. It shows lifecycle/status and submits scan payloads for server-side validation; expired/revoked/replayed/unknown-key results render as clear rejections. Wallet/ACS secrets shown once (per existing dashboard-contract rules) are not re-displayed. | PASS |
| Deployment parity | Same-origin Inertia surface runs identically in SaaS and on-premise with no cloud-only service; unavailable adapters/dependencies degrade to clear error/empty states per route error boundary. No Docker introduced. | PASS |
| GCC/KSA and PDPL | The UI displays only backend-returned, tenant/permission-scoped data, persists no personal data beyond session/cache, and shows minimal fields on operational feeds (e.g., gate events show credential reference, not full PII). Retention/residency/deletion remain backend responsibilities. | PASS |
| White-label/localization | Shell and public-style surfaces honor tenant branding; all user-visible content is Arabic/English via `react-i18next` with logical-RTL Tailwind and locale-aware date/number/currency formatting (existing `useLocale`, `formatMoney`, `formatters`). | PASS |
| Modularity/adapters | Only `AdminConsole` (presentation) and `resources/js` change. No domain module's internals are read directly; cross-module data flows through published query contracts. No hardware/vendor adapter is added by this phase. | PASS |
| Automated tests | Unit (permission-gated nav/actions, status badges, form validation, submit loaders, empty/error/forbidden states), integration (login, events list/create, ticket-type create, attendee detail load, credential revoke/reissue, QR scan, manual-desk search, ACS rule create), and 12 E2E journeys per CR-009/[test-plan.md](test-plan.md). | PASS |
| Phased delivery | Frontend consolidation over the accepted Phase 0–4 core; adds no new product capability and MUST complete before Phase 5. Phase-boundary check keeps Phase 5+ surfaces absent. | PASS |

No constitution exception is required. Every genuine backend gap is a documented
read-projection addition to an already-accepted module (recorded in
[api-integration-map.md](api-integration-map.md)), not a new business module and
not a weakening of any existing contract or control.

## Research Decisions

Detailed decisions and alternatives are in [research.md](research.md):

1. **Inertia, not a separate REST SPA.** The dashboard extends the existing
   Inertia/React surface (ADR 007) with server-resolved props via `AdminConsole`
   controllers + ViewModels. The source plan's `/src/api/*.ts` REST-client layer
   and `useXxx` fetch hooks are **not** adopted; data flows as Inertia props and
   form submissions use Inertia visits/`router`. This preserves parity and avoids a
   second auth/tenant path.
2. **Real permission catalog governs.** Navigation and action gating use the
   executable permission keys (`docs/standards/permission-catalog.md`), not the
   illustrative names in the source plan §17. [research.md](research.md) contains
   the full mapping (e.g., `events.create`→`event.manage`, `credentials.revoke`→
   `credential.revoke`, `scan.perform`→`checkin.scan.submit`, `badge.print`→
   `badge.print`, `acs.manage`→`acs.configure`, `audit.view`→`audit.view`).
3. **Consolidation + gap-fill, not greenfield.** Existing pages
   (`resources/js/pages/**`) from Phases 0–4 are unified under one shell; missing
   pages/states are added. [component-map.md](component-map.md) marks each page/
   component EXISTS / EXTEND / NEW.
4. **One shared shell and component system.** A single `DashboardLayout`
   (sidebar/topbar/breadcrumbs/indicators/global loader/toasts/error boundary) and
   the shared component families replace per-phase ad-hoc layouts, reusing the
   project-owned design system (`docs/standards/dashboard-design-system.md`).
5. **RBAC nav is data-driven and UX-only.** A single navigation manifest keyed by
   permission drives sidebar/breadcrumbs; `ProtectedRoute`/`PermissionGate` hide
   items, but the server middleware remains the security boundary.
6. **Bounded polling for live views.** Check-in, scan-events, gate-events, and
   ACS/lane-health reuse the Phase 2/3 short-interval polling pattern
   (`checkin-polling.ts`); no new realtime infrastructure.
7. **Missing-API discipline.** Any absent read projection is added narrowly to the
   owning module's query contract + OpenAPI and logged in
   [api-integration-map.md](api-integration-map.md); until available, the page uses
   a mock-safe adapter/placeholder empty state.
8. **Localization/RTL/a11y as acceptance gates.** Every new surface ships Arabic/
   English strings in `resources/js/locales`, logical-property layout, visible
   focus, and axe-clean markup, matching the existing accessibility bar.

## Architecture and Module Ownership

### AdminConsole (extended — presentation only)

Owns the dashboard shell, Inertia page controllers, and read-only ViewModels that
assemble props by calling other modules' **published application queries**. This
phase extends it with:

- shell/navigation ViewModels (permission-filtered navigation manifest, tenant/
  role indicators, breadcrumbs);
- gap-page controllers + ViewModels for Foundation admin (users, roles,
  tenant-settings, profile, audit logs), event sub-pages (registration-form
  builder, price tiers, order/attendee/credential detail), Phase 2 (wallet-pass
  detail, scan events), Phase 3 (kiosk detail, kiosk mode, badge print jobs),
  Phase 4 (ACS zones/lanes/rules/access-logs pages), and per-event reports;
- complete `routes/web.php` wiring for all pages behind `auth`, `tenant.context`,
  and `dashboard.permission:<key>`.

It creates no domain state and holds no business rules; every state-changing
action is delegated to the existing owning module's action.

### Existing modules (consumed unchanged)

`Tenancy`, `Authorization`, `Audit`, `Events`, `Registration`, `Ticketing`,
`Orders`, `Attendees`, `Credentials`, `WalletPasses`, `Scanning`, `Kiosk`,
`BadgePrinting`, `ManualDesk`, `AccessControl`, `Operations`, and `Shared` provide
tenant context, RBAC, audited actions, query contracts, errors/envelopes,
telemetry, and localization exactly as accepted in Phases 0–4. Where a page needs a
read projection a module does not yet expose, the module gains a **thin, versioned
query method** (documented in [api-integration-map.md](api-integration-map.md)) —
the only backend change this phase may introduce, and only to make an
already-completed capability visible.

### Frontend (`resources/js`, extended)

One `DashboardLayout` + shared component families (`components/{layout,tables,
forms,feedback,status,modals,loaders}`), a permission-aware navigation manifest,
and the gap pages under `pages/**`, reusing existing hooks (`useLocale`,
`useTheme`), libs (`navigation`, `tenant-navigation`, `formatters`, `formatMoney`,
`checkin-polling`), locales, and per-phase types (`types/phase1..4.ts`).

## Navigation, RBAC, and Page Authorization

- A single navigation manifest (extending `lib/navigation.ts` /
  `lib/tenant-navigation.ts`) lists every destination with its governing
  permission key; the sidebar, breadcrumbs, and in-page action controls read this
  manifest and the user's `can` map (already shared via `HandleInertiaRequests`).
- Each route is protected server-side by `auth` + (for tenant pages)
  `tenant.context` + `dashboard.permission:<key>`; the client gate is convenience
  only. Full route→permission mapping is in
  [contracts/dashboard-contract.md](contracts/dashboard-contract.md) and
  [frontend-routes.md](frontend-routes.md), using the real catalog keys, e.g.:
  events (`event.view`/`event.manage`/`event.publish`/`event.cancel`),
  registration (`registration.manage`), ticketing (`ticketing.manage`), orders
  (`order.view`/`order.manage`), attendees (`attendee.view`/`attendee.manage`),
  credentials (`credential.view`/`credential.revoke`/`credential.reissue`), wallet
  (`wallet.pass.view`/`wallet.pass.manage`), scanning (`checkin.scan.submit`/
  `checkin.dashboard.view`), kiosk (`kiosk.manage`/`kiosk.health.view`), desk
  (`checkin.desk.perform`/`attendee.walkup.register`), badges (`badge.template.manage`/
  `badge.print`/`badge.reprint`), ACS (`acs.configure`/`acs.events.view`/
  `acs.health.view`/`acs.emergency.manage`), audit (`audit.view`/`audit.export`),
  and platform admin (`platform.user.manage`, `platform.role.manage`,
  `platform.tenant.manage`, `platform.configuration.view`).

## Missing Backend API Requirements (process)

The running register lives in [api-integration-map.md](api-integration-map.md).
For each screen the plan maps the existing module query/action that supplies it; a
row with no existing source is a **gap**, recorded with: affected screen, owning
completed phase/module, the expected read projection (query method + prop shape or
OpenAPI operation), and the temporary UI treatment (mock-safe adapter or
placeholder empty state). No gap is filled with a new business module; a gap is
closed only by a thin, documented read method on the already-accepted owning
module. `spec.md`'s "Missing Backend API Requirements" section references this map.

## Cross-Cutting UX Strategy

- **States**: every list page has loading (skeleton), empty (purpose-specific),
  error, and forbidden states; every detail page has card skeletons and error
  states — reusing/extending `components/foundation/States.tsx`.
- **Forms**: submit disables the control, prevents duplicate submits, shows a
  scoped button/form loader, surfaces field-level validation from the backend
  envelope, and toasts success; sensitive actions route through `ConfirmModal`,
  and revoke/reprint/override/emergency through `ReasonModal`.
- **Status**: one `StatusBadge` family covers event/order/payment/credential/
  wallet/scan/kiosk/badge-print/ACS-lane statuses with consistent tokens.
- **Localization/RTL**: `react-i18next` strings in `locales/{en,ar}.ts`, logical
  Tailwind properties, `useLocale`/`useTheme`, locale-aware formatting.
- **Live views**: bounded polling via `checkin-polling.ts`.

## Project Structure

### Documentation (this feature)

```text
specs/006-frontend-control-dashboard/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── frontend-routes.md        # source-plan §23 deliverable
├── component-map.md          # source-plan §23 deliverable
├── api-integration-map.md    # source-plan §23 deliverable + Missing-API register
├── test-plan.md              # source-plan §23 deliverable
├── contracts/
│   └── dashboard-contract.md # repo-convention UI contract (route→permission→states)
├── checklists/
│   └── requirements.md
└── tasks.md                  # Created later by speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Modules/
│   └── AdminConsole/
│       ├── Http/Controllers/{Auth, Dashboard, Admin/{Users,Roles,TenantSettings,AuditLogs},
│       │     Tenant/{Events,Registration,Ticketing,Orders,Attendees,Credentials,
│       │       CheckIn,WalletPasses,ScanEvents,Kiosk,Badges,ManualDesk,Acs,Reports}}
│       ├── Http/Middleware/AuthorizeDashboardPage.php   # existing
│       ├── ViewModels/{Shell, Admin, Events, Orders, Attendees, Credentials,
│       │     Wallet, Scanning, Kiosk, Badges, ManualDesk, Acs, Reports}/
│       └── Providers/AdminConsoleServiceProvider.php
└── Modules/<domain>/Application/Queries/            # thin read projections only if a gap exists
resources/js/
├── app.tsx, ssr.tsx
├── layouts/DashboardLayout.tsx                       # new unified shell (+ existing FoundationLayout merged)
├── components/{layout,tables,forms,feedback,status,modals,loaders,
│     events,registration,ticketing,orders,credentials,wallet,checkin,
│     kiosk,badge-templates,manual-desk,acs,acs-health,gate-events}/
├── pages/{Auth, DashboardSection, admin/**, tenant/**, kiosk/**, public/**}/
├── hooks/{useLocale,useTheme, use<Module> as needed}
├── lib/{navigation,tenant-navigation,formatters,formatMoney,checkin-polling}
├── locales/{en,ar}.ts
└── types/{phase1,phase2,phase3,phase4, shell}.ts
routes/
└── web.php                                           # complete dashboard route wiring
tests/
├── Feature/AdminConsole/                             # controller/ViewModel + dashboard.permission authz
└── (frontend) resources/js/__tests__/**              # Vitest unit/integration
   + Playwright browser/E2E for the 12 journeys
```

**Structure Decision**: Keep the single Laravel + Inertia/React deployment. All
changes land in `AdminConsole` (thin controllers + read ViewModels), `resources/js`
(one shell + shared components + gap pages), and `routes/web.php`. Backend domain
modules are touched only to add narrow, documented read projections when a
completed capability has no existing query to surface it. No separate SPA, no REST
`/src/api` client, no new domain module, no generic repository, and no direct
cross-module persistence reads.

## Testing and Documentation Gates

Required tests (see [test-plan.md](test-plan.md)):

- **Unit (Vitest/RTL)**: sidebar/action visibility per permission set; `StatusBadge`
  renders each status; form validation + submit-loader/disable + duplicate-submit
  prevention; empty/error/forbidden state rendering; `PermissionGate` hides
  unauthorized actions; locale/RTL formatting.
- **Integration (Vitest/RTL + Inertia mocks)**: login, events list load, create-event
  flow, ticket-type create, attendee-detail load, credential revoke (with reason),
  credential reissue, QR scan accept + reject, manual-desk search, ACS rule create.
- **Backend feature (PHPUnit)**: each new AdminConsole controller/ViewModel returns
  correctly scoped props; `dashboard.permission:<key>` denies without the permission
  (403) and allows with it; zero cross-tenant props.
- **Browser/E2E (Playwright + axe)**: the 12 journeys in spec §User Story acceptance
  and source-plan §20.3 (admin dashboard, event creation, registration-form config,
  ticket creation, orders/attendees view, credential revoke, credential reissue,
  valid scan, revoked scan rejection, badge print, manual-desk search, ACS zone/
  lane/rule), plus Arabic/RTL, keyboard, and responsive/tablet checks.
- **Quality gates**: ESLint (`--max-warnings=0`), `tsc --noEmit`, Vite build, Pint,
  and existing `composer quality` (OpenAPI sync/lint for any added read projection,
  docs check, phase-boundary check) all remain mandatory.

Documentation deliverables: this plan's four §23 maps; updates to
`docs/standards/dashboard-design-system.md` (new shared components/states), the
permission→page matrix in `contracts/dashboard-contract.md`, and the
Missing-Backend-API register kept current through implementation. No new runbook is
required unless a read-projection addition warrants one.

## Post-Design Constitution Re-check

The design preserves every pre-design PASS: presentation-only changes with no new
domain module; all data via published module queries with tenant scope resolved
server-side; RBAC gating mirrored from the authoritative middleware; no credential
secret/key exposure; Arabic/English + RTL parity and accessibility as acceptance
gates; SaaS/on-premise parity with no cloud-only dependency; and Phase 5+ surfaces
kept absent by the phase-boundary check. Genuine gaps are narrow, versioned read
projections on accepted modules, tracked in the Missing-API register — not new
business logic.

**Result**: PASS. No complexity exception or governance waiver is required.

## Complexity Tracking

No constitution violations or justified complexity exceptions.
