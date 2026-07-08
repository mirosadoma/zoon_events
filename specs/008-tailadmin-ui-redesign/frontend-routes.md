# Frontend Routes (brief §18 deliverable)

Every route is a same-origin Inertia page already wired in `routes/web.php`. This
phase **restyles** each onto the TailAdmin system and wires its action controls to the
existing `/api/v1/**` endpoints; routes and permissions are unchanged. Real permission
keys per [rbac-ui-map.md](rbac-ui-map.md). Status legend: **RESTYLE** (page exists,
re-skin + wire actions), **NEW-UI** (additive UI surface only, e.g. notification/search
in shell). No new backend route is added by this phase.

## Main

| Route | Permission | Page | Status |
|---|---|---|---|
| `/login` | guest | `pages/Auth/Login.tsx` | RESTYLE |
| `/dashboard` | authenticated | `pages/FoundationDashboard.tsx` | RESTYLE |
| `/profile` | authenticated | `pages/Profile.tsx` | RESTYLE |
| `/tenant/events/{id}/reports` | reports view keys | `pages/tenant/reports/EventReport.tsx` | RESTYLE |

## Event Operations

| Route | Permission | Page | Status |
|---|---|---|---|
| `/tenant/events` | `event.view` | `pages/tenant/events/List.tsx` | RESTYLE |
| `/tenant/events/create` · `/{id}/edit` | `event.manage` | `pages/tenant/events/EventSetup.tsx` | RESTYLE + wire |
| `/tenant/events/{id}` | `event.view` | `pages/tenant/events/Detail.tsx` | RESTYLE + wire publish/cancel |
| `/{id}/registration-form` | `registration.manage` | `pages/tenant/registration/Builder.tsx` | RESTYLE + wire CRUD |
| `/{id}/registration-preview` | `registration.manage` | `pages/public/registration/Event.tsx` | RESTYLE |
| `/{id}/ticket-types` | `ticketing.manage` | `pages/tenant/events/Ticketing.tsx` | RESTYLE + wire |
| `/{id}/price-tiers` | `ticketing.manage` | `pages/tenant/ticketing/PriceTiers.tsx` | RESTYLE + wire |
| `/{id}/orders` · `/orders/{oid}` | `order.view` | `pages/tenant/events/Orders.tsx`, `pages/tenant/orders/Detail.tsx` | RESTYLE |
| `/{id}/attendees` · `/attendees/{aid}` | `attendee.view` | `pages/tenant/events/Attendees.tsx`, `pages/tenant/attendees/Detail.tsx` | RESTYLE + wire actions |
| `/{id}/credentials` · `/credentials/{cid}` | `credential.view` | `pages/tenant/events/Credentials.tsx`, `pages/tenant/credentials/Detail.tsx` | RESTYLE + wire revoke/reissue |
| `/{id}/wallet-passes` · `/{pid}` | `wallet.pass.view` | `pages/tenant/checkin/WalletPasses.tsx`, `pages/tenant/wallet/Detail.tsx` | RESTYLE |
| `/{id}/scanner` | `checkin.scan.submit` | `pages/tenant/checkin/Scanner.tsx` | RESTYLE |
| `/{id}/check-in-dashboard` | `checkin.dashboard.view` | `pages/tenant/checkin/Dashboard.tsx` | RESTYLE |
| `/{id}/scan-events` | `checkin.dashboard.view` | `pages/tenant/checkin/ScanEvents.tsx` | RESTYLE |

## On-site Operations

| Route | Permission | Page | Status |
|---|---|---|---|
| `/{id}/kiosks` · `/kiosks/{kid}` | `kiosk.manage`/`kiosk.health.view` | `pages/tenant/kiosk/{Index,Detail}.tsx` | RESTYLE |
| `/kiosk/{deviceCode}` | device session | `pages/kiosk/Mode.tsx` | RESTYLE (fullscreen) |
| `/{id}/badge-templates` · `/badge-print-jobs` | `badge.template.manage`/`badge.print` | `pages/tenant/badge-templates/Designer.tsx`, `pages/tenant/badges/PrintJobs.tsx` | RESTYLE + wire reprint |
| `/{id}/manual-desk` · `/manual-desk/walk-up` | `checkin.desk.perform`/`attendee.walkup.register` | `pages/tenant/manual-desk/{Desk,WalkUp}.tsx` | RESTYLE |

## Access Control

| Route | Permission | Page | Status |
|---|---|---|---|
| `/{id}/acs` | `acs.events.view`/`acs.health.view` | `pages/tenant/acs/Index.tsx` | RESTYLE |
| `/{id}/acs/zones` · `/lanes` · `/rules` | `acs.configure` | `pages/tenant/acs/{Zones,Lanes,Rules}.tsx` | RESTYLE + wire |
| `/{id}/acs/access-logs` | `acs.events.view` | `pages/tenant/acs/AccessLogs.tsx` | RESTYLE |
| `/{id}/acs/gate-health` | `acs.health.view` | `pages/tenant/acs/GateHealth.tsx` | RESTYLE |

## Administration

| Route | Permission | Page | Status |
|---|---|---|---|
| `/admin/users` | `membership.view` | `pages/admin/Users.tsx` | RESTYLE |
| `/admin/roles` | `role.view` | `pages/admin/Roles.tsx` | RESTYLE |
| `/admin/tenant-settings` | `tenant.view` | `pages/admin/TenantSettings.tsx` | RESTYLE |
| `/admin/audit-logs` | `audit.view` | `pages/admin/AuditLogs.tsx` | RESTYLE |
| System Settings | platform config keys | (new or `DashboardSection.tsx`) | RESTYLE / confirm |

Shell additions (all routes): grouped collapsible sidebar, `NotificationDropdown`,
global `SearchCommand`, `UserMenu` — NEW-UI in the shared shell, permission-aware.
