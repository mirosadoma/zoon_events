# RBAC UI Map (brief §18 deliverable)

The brief lists illustrative permission names; the UI MUST use the **executable**
catalog (`docs/standards/permission-catalog.md`, compared to `PermissionSeeder` by CI).
This table maps brief names → real keys and the nav/action they govern. Visibility is
UX-only; server middleware is authoritative.

| Brief name | Real catalog key(s) | Governs (nav / action) |
|---|---|---|
| `events.create` / `events.update` | `event.manage` | Events create/edit |
| `events.publish` | `event.publish` | Publish event |
| `events.cancel` | `event.cancel` | Cancel event |
| (events view) | `event.view` | Events nav + detail |
| `registration.manage` | `registration.manage` | Registration builder actions |
| `ticketing.manage` | `ticketing.manage` | Ticket types + price tiers |
| `orders.manage` | `order.view` / `order.manage` | Orders nav / order actions |
| `attendees.view` / `attendees.manage` | `attendee.view` / `attendee.manage` | Attendees nav / actions |
| `credentials.issue` | (issued via lifecycle; no separate UI create) | — |
| `credentials.revoke` | `credential.revoke` | Revoke (reason modal) |
| `credentials.reissue` | `credential.reissue` | Reissue (confirm modal) |
| (credentials view) | `credential.view` | Credentials nav + detail |
| `wallet.manage` | `wallet.pass.view` / `wallet.pass.manage` | Wallet nav / manage |
| `scan.perform` | `checkin.scan.submit` | Scanner submit |
| `checkin.perform` | `checkin.desk.perform` | Manual desk check-in |
| (check-in dashboard) | `checkin.dashboard.view` | Check-in dashboard + scan events |
| `badge.print` | `badge.print` | Print badge |
| `badge.reprint` | `badge.reprint` | Reprint (reason modal) |
| (badge templates) | `badge.template.manage` | Badge templates |
| `kiosk.manage` | `kiosk.manage` / `kiosk.health.view` | Kiosks nav / health |
| `acs.manage` | `acs.configure` | ACS zones/lanes/rules |
| (acs events/health) | `acs.events.view` / `acs.health.view` | Access logs / gate health |
| (acs emergency) | `acs.emergency.manage` | Emergency controls (confirm + reason) |
| `reports.view` | per-metric domain view keys | Reports |
| `audit.view` | `audit.view` | Audit logs |
| `users.manage` | `membership.view` / `membership.manage` (tenant) · `platform.user.*` (platform) | Users nav / actions |
| `roles.manage` | `role.view` / `role.manage` · `role.assign` | Roles nav / actions |
| `tenant.manage` | `tenant.view` + `configuration.view` | Tenant settings |
| `attendee.walkup.register` | `attendee.walkup.register` | Walk-up registration |

Rules:
- No illustrative key ships in code; every `PermissionGate`/nav entry uses a real key.
- Kiosk mode authenticates via device session (`kiosk.session`), not workforce RBAC.
- Public/attendee surfaces use order/registration tokens, not workforce RBAC.
