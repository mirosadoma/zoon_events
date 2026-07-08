# Data Model: TailAdmin Dashboard UI Redesign

This phase introduces **no backend schema**. The structured artifacts it owns are
UI/design constructs. They are the "entities" the redesign standardizes.

## 1. Design Tokens (`resources/css/app.css`)

Evolve the existing CSS custom properties + utility classes to the TailAdmin scale,
preserving `.dark` and logical-RTL behavior.

| Token group | Existing | Redesign target |
|---|---|---|
| Color — surface/ink/muted/border | `--surface`, `--ink`, `--muted`, `--border` | keep names; retune to TailAdmin light/enterprise palette |
| Color — brand/accent/focus | `--brand`, `--accent`, `--focus-ring` | keep; align to Zonetec brand |
| Status palette | ad hoc in `StatusBadge` | centralized status→color map (see §4) |
| Radius | `rounded-lg`/`rounded-xl` | consistent card/table/control radius scale |
| Shadow | subtle | soft card/table elevation scale |
| Typography | Instrument Sans | keep; define size/weight scale |
| Spacing | Tailwind default | consistent page/card/table spacing rhythm |

Rules: no external runtime fonts/CDN; light/dark via `.dark`; RTL via logical
properties; `:focus-visible` ring preserved; reduced-motion preserved.

## 2. Shared Component Inventory (status = EXISTS / RESTYLE / NEW)

Full table in [component-map.md](component-map.md). Summary:

- **RESTYLE (exists from 006)**: `DashboardLayout`, `Sidebar`, `Topbar`,
  `Breadcrumbs`, `PageHeader`, `PageContent`, `StatusBadge`, `DataTable`,
  `FiltersBar`/toolbar, `SearchInput`, `Pagination`, all `forms/*`, `ConfirmModal`,
  `ReasonModal`, all `loaders/*`, all `feedback/*` states, `PermissionGate`.
- **NEW (additive)**: `SidebarSection` (collapsible group), `UserMenu`,
  `NotificationDropdown`, `SearchCommand` (global search), `StatCard`/`MetricCard`,
  table `ActionDropdown` (row menu), `DetailsModal`.

Each component must support: loading/disabled states where applicable, Arabic/English
labels, RTL layout, keyboard focus, and small/medium sizes for badges.

## 3. Navigation Manifest (`lib/navigation.ts` / `lib/tenant-navigation.ts`)

Permission-keyed, grouped sidebar. Groups (brief §6.2): **Main** (Dashboard, Events,
Reports), **Event Operations** (Registration, Ticketing, Orders, Attendees,
Credentials, Wallet Passes, Scanning, Check-in), **On-site Operations** (Kiosks, Badge
Printing, Manual Desk), **Access Control** (ACS Overview, Zones, Lanes, Rules, Access
Logs, Gate Health), **Administration** (Users, Roles, Tenant Settings, Audit Logs,
System Settings). Each item carries its **real** governing permission key (see
[rbac-ui-map.md](rbac-ui-map.md)) and an icon; visibility filters on the shared `can`
map; server middleware stays authoritative.

## 4. Status Badge Map (`components/status/StatusBadge.tsx`)

One component, one status→variant table, AR/EN labels. Status sets (brief §7.3):

| Domain | Statuses |
|---|---|
| Event | draft, configured, published, registration_open, registration_closed, live, completed, cancelled, archived |
| Order | draft, pending_payment, paid, failed, cancelled, refunded, partially_refunded |
| Payment | pending, authorized, captured, failed, refunded, partially_refunded |
| Credential | pending, active, revoked, expired, reissued |
| Wallet Pass | created, active, updated, revoked, expired, failed |
| Scan Result | accepted, rejected, duplicate, revoked, expired, unauthorized_zone, anti_passback_rejected, manual_override |
| Kiosk | online, offline, inactive, error |
| Badge Print | pending, printing, printed, failed, cancelled |
| ACS | active, inactive, offline, error, emergency |

Variant tokens: success / warning / danger / info / neutral / emphasis, consistent in
light and dark. No hand-rolled color pills in pages.

## 5. Page Template

Every page composes: `Breadcrumbs` → `PageHeader` (title + actions gated by
`PermissionGate`) → `PageContent` with the standard state machine (skeleton → {empty |
error | forbidden | populated}). List pages use `DataTable`; detail pages use cards/
tabs/timelines. Action controls follow the form/action rules (scoped loader,
duplicate-guard, real endpoint call, toast, field validation).

## Invariants

1. **Single system**: no page hand-rolls a badge, table, modal, or state that a shared
   component already provides.
2. **Permission-keyed nav/actions**: every nav item and action carries a real catalog
   permission; no illustrative keys ship.
3. **No cosmetic success**: an action's displayed outcome always reflects a real backend
   response (FR-011).
4. **Tenant/RTL/a11y**: every surface is tenant-scoped, Arabic/English + RTL, and
   axe-clean.
5. **No new backend schema or business rule**; thin presentation props only.
