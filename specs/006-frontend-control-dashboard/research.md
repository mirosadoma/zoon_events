# Phase 0 Research: Frontend Control Dashboard

All Technical Context unknowns are resolved; there are no open `NEEDS
CLARIFICATION` items. The decisions below record the choices that shape the design.

## Decision 1 — Inertia server-driven props, not a separate REST SPA

- **Decision**: Build every dashboard page as an Inertia page rendered by an
  `AdminConsole` controller that assembles props via a read-only ViewModel, exactly
  as the existing Phase 0–4 pages do. Do **not** introduce the source plan's
  `/src/api/*.ts` REST client, `axios`-per-module wrappers, or `useEvents`/
  `useOrders` fetch hooks as the data layer.
- **Rationale**: ADR `007-react-inertia` and the shipped code
  (`HandleInertiaRequests`, `@inertiajs/react`, existing `pages/**` + `ViewModels/**`)
  establish Inertia as the single data path. A parallel REST client would duplicate
  auth, tenant context, CSRF, and error handling and create a second security
  surface — a Constitution II/VI risk. Form writes use Inertia visits / `router`
  and the same module actions the API uses.
- **Alternatives considered**: (a) Separate React SPA consuming REST — rejected by
  ADR 007 for parity/licensing and by the constitution for a second auth/tenant
  path. (b) Hybrid REST hooks only for live views — rejected; bounded polling via
  Inertia partial reloads (`checkin-polling.ts`) already covers live data.

## Decision 2 — Real permission catalog governs; map the source plan's names

- **Decision**: Gate navigation and actions on the executable keys in
  `docs/standards/permission-catalog.md` (verified by the `PermissionSeeder`), not
  the illustrative names in source plan §17.
- **Rationale**: The source plan invented convenient names (`events.create`,
  `scan.perform`, `acs.manage`); the codebase enforces different keys through
  `dashboard.permission:<key>`. Using the wrong names would silently hide or expose
  controls. `spec.md` CR-002 already flags §17 as illustrative.
- **Mapping (source-plan → real key)**:

  | Source-plan name | Real permission key(s) |
  |---|---|
  | `events.create` / `events.update` | `event.manage` |
  | `events.publish` / `events.cancel` | `event.publish` / `event.cancel` (also `event.reopen`, `event.archive`) |
  | `registration.manage` | `registration.manage` |
  | `ticketing.manage` | `ticketing.manage` |
  | `orders.manage` | `order.view` / `order.manage` (refunds: `payment.refund`) |
  | `attendees.view` / `attendees.manage` | `attendee.view` / `attendee.manage` |
  | `credentials.issue/revoke/reissue` | `credential.view` / `credential.revoke` / `credential.reissue` (validate: `credential.validate`) |
  | `wallet.manage` | `wallet.pass.view` / `wallet.pass.manage` |
  | `scan.perform` / `checkin.perform` | `checkin.scan.submit` / `checkin.scan.override` / `checkin.dashboard.view` |
  | `badge.print` / `badge.reprint` | `badge.print` / `badge.reprint` / `badge.template.manage` |
  | `kiosk.manage` | `kiosk.manage` / `kiosk.health.view`; walk-up: `checkin.desk.perform` / `attendee.walkup.register` |
  | `acs.manage` | `acs.configure` / `acs.events.view` / `acs.health.view` / `acs.emergency.manage` |
  | `reports.view` | (no dedicated key) reuse per-domain view keys (`event.view`, `order.view`, `checkin.dashboard.view`, `acs.events.view`) — see Decision 7 |
  | `audit.view` | `audit.view` (export: `audit.export`; verify: `audit.verify`) |
  | `users.manage` / `roles.manage` / `tenant.manage` | tenant: `membership.manage` / `role.manage` / `role.assign` / `tenant.view` / `configuration.view`; platform: `platform.user.manage` / `platform.role.manage` / `platform.tenant.manage` / `platform.configuration.view` |

## Decision 3 — Consolidation + gap-fill, not greenfield

- **Decision**: Treat existing `pages/**` from Phases 0–4 as EXISTS/EXTEND and add
  only the missing pages/states; unify them under one shell.
- **Rationale**: Login, foundation dashboard, event setup/attendees/credentials/
  orders/ticketing, check-in dashboard/scanner/wallet-passes, badge designer, kiosk
  index, manual-desk, ACS index, gate events, and ACS health already exist. The
  value of Phase 6 is a **single cohesive shell + complete navigation + wiring +
  gap pages**, not rebuilding shipped work. `component-map.md` classifies each item.
- **Alternatives considered**: Rebuild all pages under a new structure — rejected as
  wasteful and regression-prone.

## Decision 4 — One shared shell and component system

- **Decision**: Introduce a single `DashboardLayout` (sidebar, topbar, tenant/role
  indicators, breadcrumbs, global route loader, toasts, confirm/reason modals, route
  error boundary) and shared `components/{layout,tables,forms,feedback,status,
  modals,loaders}`, reusing the project-owned design system.
- **Rationale**: `docs/standards/dashboard-design-system.md` already mandates
  responsive nav, logical RTL, theme modes, focus, and loading/empty/error/forbidden
  states. A shared shell removes per-phase drift and satisfies CR-007.
- **Alternatives considered**: Per-phase layouts (current partial state) — rejected
  for inconsistency; a third-party admin template — rejected by ADR 007 (licensing/
  parity) and the "no CDN/licensed assets" design-system rule.

## Decision 5 — RBAC navigation is data-driven and UX-only

- **Decision**: A single navigation manifest maps each destination to its
  permission key; the sidebar/breadcrumbs render from it filtered by the shared
  `can` map. `PermissionGate` wraps action controls.
- **Rationale**: Matches the existing `tenant-navigation.ts` shape and keeps the
  server `dashboard.permission` middleware authoritative (Constitution II). One
  manifest also lets tests assert visibility per permission set cheaply.

## Decision 6 — Bounded polling for live views

- **Decision**: Check-in dashboard, scan events, gate events, and ACS/lane health
  reuse the Phase 2/3 fixed short-interval polling (`lib/checkin-polling.ts`) via
  Inertia partial reloads; no WebSocket/SSE.
- **Rationale**: Prior dashboard contracts (003/004/005) fixed this pattern; it is
  on-premise-friendly (no streaming infra) and already tested.

## Decision 7 — Reports reuse existing view permissions and read models

- **Decision**: Per-event report pages aggregate figures already exposed by the
  Foundation dashboard and per-module read models; gate each metric on the relevant
  domain view permission rather than a new `reports.view` key. Charts render only if
  an existing capability is present; otherwise cards/tables.
- **Rationale**: No `reports.view` permission exists in the catalog, and the
  constitution forbids inventing capability without need. Any figure not already
  available becomes a Missing-API row (Decision 8), not a new module.

## Decision 8 — Missing-API discipline

- **Decision**: When a page needs data no existing module query exposes, add a
  thin, versioned read projection (query method + OpenAPI operation) to the owning
  accepted module and log it in `api-integration-map.md`; until then, render a
  mock-safe placeholder/empty state.
- **Rationale**: Satisfies source plan §23 and Constitution I/VI (API-first, no
  persistence bypass) without creating new business modules. The register is the
  single source of truth for `spec.md`'s Missing-Backend-API section.

## Decision 9 — Localization, RTL, and accessibility are acceptance gates

- **Decision**: Every new/extended surface ships `en`/`ar` strings, logical
  Tailwind properties, locale-aware date/number/currency formatting, visible focus,
  skip links, and axe-clean markup; browser tests assert Arabic/RTL parity.
- **Rationale**: CR-007 and the design-system standard require it; the existing
  `foundation-accessibility.test.tsx` + `@axe-core/playwright` provide the harness.

## Decision 10 — Testing stack reuse

- **Decision**: Vitest + RTL for unit/integration, Playwright + axe for
  browser/E2E, PHPUnit for AdminConsole controller/ViewModel + `dashboard.permission`
  authorization; wire into existing `composer quality` and npm `test`/`typecheck`/
  `lint` gates.
- **Rationale**: The stack is already present (`package.json`, `tests/**`,
  `resources/js/__tests__/**`); no new tooling is needed.
