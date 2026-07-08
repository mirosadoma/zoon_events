# Component Map (brief §18 deliverable)

Status: **EXISTS** (keep as-is), **RESTYLE** (exists from feature 006; re-skin to
TailAdmin, keep API/props), **NEW** (additive component to build). Paths under
`resources/js/components/` unless noted.

## Layout / navigation

| Component | Path | Status |
|---|---|---|
| `DashboardLayout` | `layouts/DashboardLayout.tsx` | RESTYLE |
| `Sidebar` | `layout/Sidebar.tsx` | RESTYLE |
| `SidebarSection` (collapsible group) | `layout/SidebarSection.tsx` | NEW |
| `SidebarItem` (active state, icon) | `layout/Sidebar.tsx` (extract) | RESTYLE |
| `Topbar` | `layout/Topbar.tsx` | RESTYLE |
| `UserMenu` | `layout/UserMenu.tsx` | NEW |
| `NotificationDropdown` | `layout/NotificationDropdown.tsx` | NEW |
| `SearchCommand` (global search) | `layout/SearchCommand.tsx` | NEW |
| `Breadcrumbs` | `layout/Breadcrumbs.tsx` | RESTYLE |
| `PageHeader` / `PageContent` | `layout/*.tsx` | RESTYLE |
| `PermissionGate` / `ProtectedRoute` | `layout/*.tsx` | EXISTS |

## Cards

| Component | Path | Status |
|---|---|---|
| `StatCard` / `MetricCard` | `cards/*.tsx` | NEW |
| `InfoCard` / `DetailsCard` | `feedback/DetailsCard.tsx` | RESTYLE |

## Tables

| Component | Path | Status |
|---|---|---|
| `DataTable` | `tables/DataTable.tsx` | RESTYLE |
| `TableToolbar` / `FiltersBar` | `tables/FiltersBar.tsx` | RESTYLE |
| `TableSearch` | `tables/SearchInput.tsx` | RESTYLE |
| `TablePagination` | `tables/Pagination.tsx` | RESTYLE |
| `ActionDropdown` (row action menu) | `tables/ActionDropdown.tsx` | NEW |
| `TableEmptyState` / `TableSkeleton` | `feedback/States.tsx`, `loaders/TableSkeleton.tsx` | RESTYLE |

## Status / forms / modals / loaders / feedback

| Component | Path | Status |
|---|---|---|
| `StatusBadge` (all domains, AR/EN, sizes) | `status/StatusBadge.tsx` | RESTYLE |
| Form inputs (`Text/Email/Phone/Number/Select/MultiSelect/Checkbox/Radio/DateTime/File/Textarea`) | `forms/*.tsx` | RESTYLE (+ add missing: Email/Phone/Radio/File/MultiSelect) |
| `FormSection` / `FormActions` / `ValidationError` / `RequiredLabel` | `forms/*.tsx` | RESTYLE |
| `SubmitButtonWithLoader` | `forms/SubmitButtonWithLoader.tsx` | RESTYLE |
| `ConfirmModal` / `ReasonModal` | `modals/*.tsx` | RESTYLE |
| `DetailsModal` | `modals/DetailsModal.tsx` | NEW |
| Loaders (`GlobalRouteLoader/PageSkeleton/TableSkeleton/CardSkeleton/FormSubmitLoader/ButtonSpinner`) | `loaders/*.tsx` | RESTYLE |
| States (`Empty/Error/Forbidden/Conflict/Queued`) | `feedback/States.tsx` | RESTYLE |
| `Toaster` + `useToast` | `feedback/Toaster.tsx`, `hooks/useToast.tsx` | RESTYLE |

## Domain components (restyle where user-visible)

`components/{acs,acs-health,badge-templates,badges,checkin,credentials,gate-events,
kiosk,manual-desk,orders,registration,ticketing,wallet}/**` — RESTYLE to shared cards/
tables/badges; wire any cosmetic action to its endpoint (see api-integration-map.md).

## Tokens / styles

`resources/css/app.css` — RESTYLE (evolve tokens + utility classes to TailAdmin scale;
add missing status/card/table utilities). `docs/standards/dashboard-design-system.md` —
update.
