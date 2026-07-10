# Frontend Routes (source-plan §23 deliverable)

Every route below is a same-origin Inertia page under `routes/web.php`, protected
server-side by `auth` and — for tenant pages — `tenant.context` (with
`tenant.context.clear`) plus `dashboard.permission:<key>`. The client
`ProtectedRoute`/`PermissionGate` gates are convenience only; the middleware is
authoritative. Real permission keys come from `docs/standards/permission-catalog.md`
(see `research.md` Decision 2). Status legend: **EXISTS** (page shipped in Phase
0–4), **WIRE** (page component exists; route/nav wiring to complete),
**NEW** (page to build this phase).

## Platform / foundation

| Route | Permission | Page component | Status |
|---|---|---|---|
| `/login` | guest | `pages/Auth/Login.tsx` | EXISTS |
| `/` (dashboard) | authenticated | `pages/FoundationDashboard.tsx` | EXISTS → shell merge |
| `/platform/{section}` | per-section platform key | `pages/DashboardSection.tsx` | EXISTS |
| `/profile` | authenticated | `pages/Profile.tsx` | NEW |
| `/admin/users` | `platform.user.manage` / `membership.manage` | `pages/admin/Users.tsx` | NEW |
| `/admin/roles` | `platform.role.manage` / `role.manage` | `pages/admin/Roles.tsx` | NEW |
| `/admin/tenant-settings` | `platform.tenant.manage` / `tenant.view`+`configuration.view` | `pages/admin/TenantSettings.tsx` | NEW |
| `/admin/audit-logs` | `audit.view` | `pages/admin/AuditLogs.tsx` | NEW |

## Events, registration, ticketing (Phase 1)

| Route | Permission | Page component | Status |
|---|---|---|---|
| `/tenant/events` | `event.view` | `pages/tenant/events/index` | WIRE |
| `/tenant/events/create` | `event.manage` | `pages/tenant/events/EventSetup.tsx` | EXISTS (create mode) |
| `/tenant/events/{event_id}` | `event.view` | `pages/tenant/events/Detail.tsx` (tabs) | NEW (tab shell) |
| `/tenant/events/{event_id}/edit` | `event.manage` | `EventSetup.tsx` (edit mode) | EXISTS |
| `/tenant/events/{event_id}/registration-form` | `registration.manage` | `pages/tenant/registration/Builder.tsx` | NEW |
| `/tenant/events/{event_id}/registration-preview` | `registration.manage` | `pages/public/registration/Event.tsx` (preview) | EXISTS (reuse) |
| `/tenant/events/{event_id}/ticket-types` | `ticketing.manage` | `pages/tenant/events/Ticketing.tsx` | EXISTS |
| `/tenant/events/{event_id}/price-tiers` | `ticketing.manage` | `pages/tenant/ticketing/PriceTiers.tsx` | NEW |
| `/tenant/events/{event_id}/orders` | `order.view` | `pages/tenant/events/Orders.tsx` | EXISTS |
| `/tenant/events/{event_id}/orders/{order_id}` | `order.view` | `pages/tenant/orders/Detail.tsx` | NEW |
| `/tenant/events/{event_id}/attendees` | `attendee.view` | `pages/tenant/events/Attendees.tsx` | EXISTS |
| `/tenant/events/{event_id}/attendees/{attendee_id}` | `attendee.view` | `pages/tenant/attendees/Detail.tsx` | NEW |
| `/tenant/events/{event_id}/credentials` | `credential.view` | `pages/tenant/events/Credentials.tsx` | EXISTS |
| `/tenant/events/{event_id}/credentials/{credential_id}` | `credential.view` | `pages/tenant/credentials/Detail.tsx` | NEW |

Credential actions on detail/attendee pages: revoke (`credential.revoke`, reason
required), reissue (`credential.reissue`), print badge (`badge.print`), manual
check-in (`checkin.desk.perform`).

## Wallet, scanning, check-in (Phase 2)

| Route | Permission | Page component | Status |
|---|---|---|---|
| `/tenant/events/{event_id}/wallet-passes` | `wallet.pass.view` | `pages/tenant/checkin/WalletPasses.tsx` | EXISTS |
| `/tenant/events/{event_id}/wallet-passes/{pass_id}` | `wallet.pass.view` | `pages/tenant/wallet/Detail.tsx` | NEW |
| `/tenant/events/{event_id}/scanner` | `checkin.scan.submit` | `pages/tenant/checkin/Scanner.tsx` | EXISTS |
| `/tenant/events/{event_id}/check-in-dashboard` | `checkin.dashboard.view` | `pages/tenant/checkin/Dashboard.tsx` | EXISTS |
| `/tenant/events/{event_id}/scan-events` | `checkin.dashboard.view` | `pages/tenant/checkin/ScanEvents.tsx` | NEW |

## Kiosk, badge, manual desk (Phase 3)

| Route | Permission | Page component | Status |
|---|---|---|---|
| `/tenant/events/{event_id}/kiosks` | `kiosk.manage` / `kiosk.health.view` | `pages/tenant/kiosk/Index.tsx` | EXISTS |
| `/tenant/events/{event_id}/kiosks/{kiosk_id}` | `kiosk.health.view` | `pages/tenant/kiosk/Detail.tsx` | NEW |
| `/kiosk/{device_code}` | `kiosk.session` (device token) | `pages/kiosk/Mode.tsx` | NEW |
| `/tenant/events/{event_id}/badge-templates` | `badge.template.manage` | `pages/tenant/badge-templates/Designer.tsx` | EXISTS |
| `/tenant/events/{event_id}/badge-print-jobs` | `badge.print` | `pages/tenant/badges/PrintJobs.tsx` | NEW |
| `/tenant/events/{event_id}/manual-desk` | `checkin.desk.perform` | `pages/tenant/manual-desk/Desk.tsx` | EXISTS |
| `/tenant/events/{event_id}/manual-desk/walk-up` | `attendee.walkup.register` | `manual-desk/WalkUpFormPanel.tsx` (route or in-page) | EXISTS (component) |

## ACS access control (Phase 4)

| Route | Permission | Page component | Status |
|---|---|---|---|
| `/tenant/events/{event_id}/acs` | `acs.events.view` / `acs.health.view` | `pages/tenant/acs/Index.tsx` (overview) | EXISTS |
| `/tenant/events/{event_id}/acs/zones` | `acs.configure` | `pages/tenant/acs/Zones.tsx` | NEW |
| `/tenant/events/{event_id}/acs/lanes` | `acs.configure` | `pages/tenant/acs/Lanes.tsx` | NEW |
| `/tenant/events/{event_id}/acs/rules` | `acs.configure` | `pages/tenant/acs/Rules.tsx` | NEW |
| `/tenant/events/{event_id}/acs/access-logs` | `acs.events.view` | `pages/tenant/gate-events/Index.tsx` (reuse/extend) | EXISTS |
| `/tenant/events/{event_id}/acs/gate-health` | `acs.health.view` | `pages/tenant/acs-health/Index.tsx` | EXISTS |
| emergency egress (in ACS overview/health) | `acs.emergency.manage` | `components/acs/*` + ReasonModal | EXISTS (component) |

Note: `acs/zones`, `acs/lanes`, `acs/rules` may already be edited via
`components/acs/ZoneLaneEditor.tsx` and `RuleEditor.tsx` inside `acs/Index.tsx`;
this phase either promotes them to dedicated routes (as the source plan requests)
or keeps them as tabs and updates this map accordingly during implementation.

## Reports (Phase 1–4 rollup)

| Route | Permission | Page component | Status |
|---|---|---|---|
| `/tenant/events/{event_id}/reports` | per-metric domain view keys (research.md D7) | `pages/tenant/reports/EventReport.tsx` | NEW |

## Wiring notes

- Currently `routes/web.php` wires only `login`, `/`, `/platform/{section}`,
  `/docs/api/openapi.yaml`, and tenant check-in/scanner/wallet-passes. All other
  tenant pages above need route entries added behind the same middleware stack; this
  is the bulk of the "WIRE" work.
- Tenant event routes use the existing `prefix('tenant/events/{event_id}')` group
  with `tenant.context.clear` + `tenant.context`.
- Kiosk mode uses `kiosk.session`/`kiosk.session.clear` middleware, not user RBAC.
