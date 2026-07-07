# Component Map (source-plan §23 deliverable)

Classifies the React components/pages this phase touches. Status: **EXISTS** (in
`resources/js`), **EXTEND** (present but needs shell/state/RBAC work), **NEW**.
Existing inventory verified against `resources/js/**` on 2026-07-07.

## Shared layout (`components/layout/`, `layouts/`)

| Component | Purpose | Status |
|---|---|---|
| `DashboardLayout` | Unified shell: sidebar + topbar + content + loader + toasts + error boundary | NEW (merges `layouts/FoundationLayout.tsx`) |
| `Sidebar` | Permission-filtered navigation from manifest | NEW |
| `Topbar` | User menu, tenant indicator, role indicator, locale/theme toggle | NEW |
| `Breadcrumbs` | Route-derived trail | NEW |
| `PageHeader` / `PageContent` | Title + actions + body scaffold | NEW |
| `ProtectedRoute` | Auth/redirect wrapper (mirrors server guard) | NEW |
| `PermissionGate` | Hides action controls by `can` key | NEW |
| `FoundationLayout` | Existing per-phase layout | EXISTS → fold into `DashboardLayout` |

## Data components (`components/tables/`, `feedback/`, `status/`)

| Component | Purpose | Status |
|---|---|---|
| `DataTable` | Sortable/paginated table shell | NEW |
| `FiltersBar` / `SearchInput` | List filtering/search | NEW |
| `Pagination` | Page controls | NEW |
| `StatusBadge` | Unified badges (event/order/payment/credential/wallet/scan/kiosk/badge/ACS) | NEW (consolidates ad-hoc badges) |
| `EmptyState` / `ErrorState` / `ForbiddenState` | List/page states | EXTEND `components/foundation/States.tsx` |
| `DetailsCard` | Detail-page card | NEW |
| `Timeline` / `AuditTimeline` | Check-in/audit history | NEW |

## Form components (`components/forms/`, `modals/`)

| Component | Purpose | Status |
|---|---|---|
| `TextInput`/`SelectInput`/`DateTimeInput`/`CheckboxInput`/`TextareaInput` | Inputs | NEW (some exist inline) |
| `SubmitButtonWithLoader` | Disable + spinner + duplicate-submit guard | NEW |
| `FormSection` / `FormActions` | Layout | NEW |
| `ConfirmModal` | Sensitive-action confirmation | NEW |
| `ReasonModal` | Reason-required actions (revoke/reprint/override/emergency) | NEW |
| `credentials/CredentialDialog` | Credential revoke/reissue dialog | EXISTS → align to `ReasonModal` |

## Loading components (`components/loaders/`)

| Component | Status |
|---|---|
| `GlobalRouteLoader` | NEW |
| `PageSkeleton` / `TableSkeleton` / `CardSkeleton` / `FormSubmitLoader` / `ButtonSpinner` | NEW |

## Feature pages & components (by phase)

### Foundation / admin
- `pages/Auth/Login.tsx` — EXISTS
- `pages/FoundationDashboard.tsx`, `pages/DashboardSection.tsx` — EXISTS (shell merge)
- `pages/Profile.tsx`, `pages/admin/Users.tsx`, `Roles.tsx`, `TenantSettings.tsx`,
  `AuditLogs.tsx` — NEW

### Events / registration / ticketing
- `pages/tenant/events/{index,EventSetup,Attendees,Credentials,Orders,Ticketing}.tsx` — EXISTS
- `components/registration/{RegistrationField,LocalizedEventContent,FreeCheckout}.tsx`,
  `components/ticketing/InventoryStatus.tsx`, `components/orders/{PaymentState,NotificationStatus}.tsx` — EXISTS
- `pages/tenant/events/Detail.tsx` (tabbed), `registration/Builder.tsx`,
  `ticketing/PriceTiers.tsx`, `orders/Detail.tsx`, `attendees/Detail.tsx`,
  `credentials/Detail.tsx` — NEW

### Wallet / scanning
- `pages/tenant/checkin/{Dashboard,Scanner,WalletPasses}.tsx`,
  `components/checkin/{CheckInCounters,ScanResultCard}.tsx`,
  `components/wallet/AddToWalletButtons.tsx` — EXISTS
- `pages/tenant/wallet/Detail.tsx`, `pages/tenant/checkin/ScanEvents.tsx` — NEW

### Kiosk / badge / manual desk
- `pages/tenant/kiosk/Index.tsx`, `components/kiosk/{HeartbeatIndicator,PairingDialog,HealthTable}.tsx` — EXISTS
- `pages/tenant/badge-templates/Designer.tsx`, `components/badge-templates/{BadgePreviewCanvas,FieldPalettePanel,TemplateListPanel}.tsx` — EXISTS
- `pages/tenant/manual-desk/Desk.tsx`, `components/manual-desk/{AttendeeLookupPanel,CheckInResultPanel,WalkUpFormPanel,ReprintDialog}.tsx` — EXISTS
- `pages/tenant/kiosk/Detail.tsx`, `pages/kiosk/Mode.tsx`, `pages/tenant/badges/PrintJobs.tsx` — NEW

### ACS
- `pages/tenant/acs/Index.tsx`, `pages/tenant/gate-events/Index.tsx`,
  `pages/tenant/acs-health/Index.tsx`, `components/acs/{ZoneLaneEditor,RuleEditor}.tsx`,
  `components/acs-health/LaneHealthCard.tsx`, `components/gate-events/GateEventRow.tsx` — EXISTS
- `pages/tenant/acs/{Zones,Lanes,Rules}.tsx` (if promoted to dedicated routes) — NEW

### Reports
- `pages/tenant/reports/EventReport.tsx` — NEW

## Hooks, libs, locales, types
- `hooks/{useLocale,useTheme}.ts`, `lib/{navigation,tenant-navigation,formatters,formatMoney,checkin-polling}.ts`,
  `locales/{en,ar}.ts`, `types/{phase1,phase2,phase3,phase4}.ts` — EXISTS (EXTEND with new strings/keys/types)
- `types/shell.ts` (SessionContext, NavigationManifest) — NEW

## Consolidation actions
1. Introduce `DashboardLayout` and migrate all existing pages onto it.
2. Replace per-page ad-hoc badges/states with `StatusBadge` + shared state components.
3. Centralize navigation in one permission-keyed manifest.
4. Add NEW gap pages and wire all routes (see `frontend-routes.md`).
5. Keep every extension Arabic/RTL + axe compliant.
