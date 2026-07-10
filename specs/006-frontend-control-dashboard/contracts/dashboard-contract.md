# Phase 6 Dashboard Consolidation Contract

Extends the Phase 0–4 dashboard contracts
(`specs/001..005/contracts/dashboard-contract.md`). Only the additions and
consolidation rules below apply; every unchanged prior rule remains authoritative.
This phase adds **no new domain capability** — it unifies the shell, completes
navigation/route wiring, and adds the remaining presentation surfaces for
already-accepted Phase 0–4 backends.

## Unified shell

- One `DashboardLayout` provides sidebar, top bar, user-profile menu, tenant
  indicator, current-role indicator, breadcrumbs, page title, content area, global
  route loader, toast notifications, confirmation/reason modals, and route-level
  error boundaries. All existing pages migrate onto it; `FoundationLayout` is folded
  in.
- Branding, locale (Arabic/English), RTL/LTR, theme (light/dark/system), visible
  focus, skip links, and reduced-motion follow `docs/standards/dashboard-design-system.md`.

## Navigation & page authorization (real permission catalog)

Navigation visibility is convenience only; server `dashboard.permission:<key>` /
`permission:<key>,tenant` middleware is authoritative, and an operator scoped to one
tenant/event never sees another's data (CR-002). Full route table:
[../frontend-routes.md](../frontend-routes.md).

| Area | Page(s) | Required permission |
|---|---|---|
| Profile | `/profile` | authenticated |
| Users | `/admin/users` | `platform.user.manage` or `membership.manage` |
| Roles | `/admin/roles` | `platform.role.manage` or `role.manage`/`role.assign` |
| Tenant settings | `/admin/tenant-settings` | `platform.tenant.manage` or `tenant.view`+`configuration.view` |
| Audit logs | `/admin/audit-logs` | `audit.view` (export: `audit.export`) |
| Events | list/detail/create/edit | `event.view` / `event.manage`; publish `event.publish`; cancel `event.cancel` |
| Registration form | builder/preview | `registration.manage` |
| Ticketing | ticket types / price tiers | `ticketing.manage` |
| Orders | list/detail | `order.view`; refund `payment.refund` |
| Attendees | list/detail | `attendee.view`; edit `attendee.manage` |
| Credentials | list/detail | `credential.view`; revoke `credential.revoke`; reissue `credential.reissue` |
| Wallet passes | list/detail | `wallet.pass.view`; manage `wallet.pass.manage` |
| Scanner | scan | `checkin.scan.submit`; override `checkin.scan.override` |
| Check-in dashboard / scan events | view | `checkin.dashboard.view` |
| Kiosks | list/detail | `kiosk.manage` / `kiosk.health.view` |
| Kiosk mode | `/kiosk/{device_code}` | device session (`kiosk.session`), not user RBAC |
| Badges | templates / print jobs | `badge.template.manage` / `badge.print`; reprint `badge.reprint` |
| Manual desk | desk / walk-up | `checkin.desk.perform`; walk-up `attendee.walkup.register` |
| ACS | overview/zones/lanes/rules/logs/health | `acs.events.view`/`acs.health.view` view; config `acs.configure`; emergency `acs.emergency.manage` |
| Reports | event report | per-metric domain view keys (research.md D7) |

## Required states (every page)

- **Lists**: loading (skeleton), empty (purpose-specific), error, forbidden.
- **Details**: card skeletons, error, forbidden.
- **Live views** (check-in, scan events, gate events, ACS health): bounded
  short-interval polling; active emergency prominently flagged.

## Action UX rules

- **Confirmation modal** for: publish/cancel event, revoke/reissue credential,
  print/reprint badge, manual override, disable ACS rule, emergency raise/clear.
- **Reason required** (ReasonModal) for: credential revoke, badge reprint, manual
  override, emergency action.
- **Forms**: disable submit during request, prevent duplicate submits, scoped
  loader, field-level validation from envelope, success toast, stay in place unless
  the flow redirects.

## Privacy & security in presentation

- No signing key, credential secret, wallet/ACS M2M secret, or raw QR payload ever
  appears in props, logs, or HTML. Secrets shown once (per prior contracts) are not
  re-displayed.
- Operational feeds (scan events, gate events, access logs) show a credential
  reference and access metadata, not full attendee PII (CR-005).
- Every screen renders only tenant-scoped, permission-scoped props resolved
  server-side.

## Parity

All dashboard actions invoke the same application actions, permissions, idempotency
handling, audited transactions, and domain events as the versioned API operations.
No presentation layer queries any domain module's persistence directly.
