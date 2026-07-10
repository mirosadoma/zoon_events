# UI Contract: TailAdmin Dashboard UI Redesign

The dashboard's "interface contract" is the set of Inertia routes, the **real**
permission that governs each, the required visual states, and — for action surfaces —
the existing backend endpoint the redesigned control must call. This consolidates
[frontend-routes.md](../frontend-routes.md), [rbac-ui-map.md](../rbac-ui-map.md), and
[api-integration-map.md](../api-integration-map.md).

## Contract rules

- **Reads**: Inertia props from the existing `AdminConsole` controllers/ViewModels;
  tenant scope from server context, never a client id.
- **Actions**: `fetch('/api/v1/...')` with `credentials:'include'`, `X-Tenant-ID`, and
  (for writes) `Idempotency-Key: crypto.randomUUID()`; on `!response.ok` show error/
  toast and keep the surface; on success toast + `router.reload()`. Never change
  displayed status without a successful response.
- **States**: every data page renders skeleton → {empty | error | forbidden |
  populated}; forbidden shows a permission message, not partial data.
- **Visibility**: nav item + action render only when the shared `can` map holds the
  route's real permission key; server middleware remains authoritative.

## Route → permission → states → action endpoint (representative)

| Route | Real permission (view) | States | Key action → existing endpoint |
|---|---|---|---|
| `/login` | guest | loader, error | `POST /login` |
| `/dashboard` | authenticated | card+table skeletons, error | — |
| `/tenant/events` | `event.view` | table states | — |
| `/tenant/events/create` | `event.manage` | form loader, validation | `POST /api/v1/tenant/events` |
| `/tenant/events/{id}` | `event.view` | tab/card skeletons | publish `POST /api/v1/tenant/events/{id}/publish`; cancel `.../cancel` (gated `event.publish`/`event.cancel`) |
| `/tenant/events/{id}/edit` | `event.manage` | form loader | `PATCH /api/v1/tenant/events/{id}` |
| `/tenant/events/{id}/registration-form` | `registration.manage` | list/empty, save loader | Registration field actions (`app/Modules/Registration/Routes/api.php`) |
| `/tenant/events/{id}/ticket-types` | `ticketing.manage` | table, form loader | `POST/PATCH /api/v1/tenant/events/{id}/ticket-types` |
| `/tenant/events/{id}/price-tiers` | `ticketing.manage` | table, form loader | `POST .../ticket-types/{ttid}/price-tiers` |
| `/tenant/events/{id}/orders` `/orders/{oid}` | `order.view` | table/detail states | — (view) |
| `/tenant/events/{id}/attendees` `/attendees/{aid}` | `attendee.view` | table/detail states | credential/print/check-in actions (see below) |
| `/tenant/events/{id}/credentials` `/credentials/{cid}` | `credential.view` | table/detail states | revoke `POST .../credentials/{cid}/revoke` (reason, `credential.revoke`); reissue `.../reissue` (`credential.reissue`) |
| `/tenant/events/{id}/wallet-passes` `/{pid}` | `wallet.pass.view` | table/detail states | wallet manage (where permitted) |
| `/tenant/events/{id}/scanner` | `checkin.scan.submit` | big result panel, button loader | `POST /api/v1/tenant/events/{id}/scans` |
| `/tenant/events/{id}/check-in-dashboard` | `checkin.dashboard.view` | card+table skeletons | — (bounded polling) |
| `/tenant/events/{id}/scan-events` | `checkin.dashboard.view` | table states | — |
| `/tenant/events/{id}/kiosks` `/kiosks/{kid}` | `kiosk.manage` / `kiosk.health.view` | table/detail states | register/activate kiosk actions |
| `/kiosk/{deviceCode}` | device session | fullscreen states | lookup/print actions |
| `/tenant/events/{id}/badge-templates` `/badge-print-jobs` | `badge.template.manage` / `badge.print` | table/detail states | print `POST .../badge-print-jobs`; reprint `.../{jid}/reprint` (reason, `badge.reprint`) |
| `/tenant/events/{id}/manual-desk` | `checkin.desk.perform` | workstation states | lookup `.../desk/lookups`; scan `.../scans`; walk-up `.../walk-up-registrations` (`attendee.walkup.register`) |
| `/tenant/events/{id}/acs` `/zones` `/lanes` `/rules` | `acs.events.view`/`acs.health.view` / `acs.configure` | overview cards, table, form loader | zone/lane/rule create `POST /api/v1/tenant/events/{id}/acs/{zones|lanes|rules}` |
| `/tenant/events/{id}/acs/access-logs` `/gate-health` | `acs.events.view` / `acs.health.view` | table/health states | emergency actions (`acs.emergency.manage`, confirm+reason) |
| `/admin/users` | `membership.view` (actions `membership.manage`) | table states | user activate/edit actions |
| `/admin/roles` | `role.view` (updates `role.manage`) | table/form states | role assign/update actions |
| `/admin/tenant-settings` | `tenant.view` (+`configuration.view`) | form states | configuration actions |
| `/admin/audit-logs` | `audit.view` | table states | — |
| `/tenant/events/{id}/reports` | per-metric view keys | card/table states, placeholders | — |
| `/profile` | authenticated | form states | profile actions where supported |

Notes: the exact endpoints above already exist (Phases 1–4); this phase wires the
redesigned controls to them and reflects the real result. Any control whose endpoint is
absent is logged in [missing-api-requirements.md](../missing-api-requirements.md) and
ships a placeholder.
